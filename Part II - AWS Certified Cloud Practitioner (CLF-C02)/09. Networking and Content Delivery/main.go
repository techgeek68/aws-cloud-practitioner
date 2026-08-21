package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"html/template"
	"io"
	"log"
	"math"
	"net"
	"net/http"
	"os"
	"os/exec"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

const (
	listenAddress = "127.0.0.1:8080"
	dbConfigPath  = "/etc/dashboard-db.json"

	requestTimeout = 5 * time.Second
)

type databaseConfig struct {
	Host string `json:"host"`
	Port int    `json:"port"`
	User string `json:"user"`
	Pass string `json:"pass"`
	Name string `json:"name"`
}

type identity struct {
	InstanceID   string `json:"instanceId"`
	InstanceType string `json:"instanceType"`
	AMI          string `json:"amiId"`
	Region       string `json:"region"`
	AZ           string `json:"az"`
	VPC          string `json:"vpcId"`
	Subnet       string `json:"subnetId"`
	PrivateIP    string `json:"privateIp"`
	PublicIP     string `json:"publicIp"`
	PublicDNS    string `json:"publicDns"`
}

type loadAverage struct {
	OneMinute     float64 `json:"oneMinute"`
	FiveMinute    float64 `json:"fiveMinute"`
	FifteenMinute float64 `json:"fifteenMinute"`
	Cores         int     `json:"cores"`
	Percent       float64 `json:"percent"`
}

type vitals struct {
	Apache         string      `json:"apache"`
	MariaDB        string      `json:"mariadb"`
	Firewalld      string      `json:"firewalld"`
	GoVersion      string      `json:"goVersion"`
	Uptime         string      `json:"uptime"`
	DiskUsage      string      `json:"diskUsage"`
	MemoryUsage    string      `json:"memUsage"`
	CPUUtilization *float64    `json:"cpuUtilization"`
	Load           loadAverage `json:"load"`
}

type networkChecks struct {
	IMDS     string `json:"imds"`
	DNS      string `json:"dns"`
	Internet string `json:"internet"`
}

type databaseStatus struct {
	Status    string `json:"status"`
	PageViews any    `json:"pageViews"`
}

type dashboardData struct {
	GeneratedAt   string         `json:"generatedAt"`
	Hostname      string         `json:"hostname"`
	OverallOK     bool           `json:"overallOk"`
	FailedSummary string         `json:"failedSummary"`
	Identity      identity       `json:"identity"`
	Vitals        vitals         `json:"vitals"`
	Network       networkChecks  `json:"network"`
	Database      databaseStatus `json:"database"`
	RecentErrors  []string       `json:"recentErrors"`
}

type app struct {
	db        *sql.DB
	templates *template.Template
	http      *http.Client
}

func main() {
	db, err := openDatabase(dbConfigPath)
	if err != nil {
		log.Fatalf("database setup failed: %v", err)
	}
	defer db.Close()

	funcMap := template.FuncMap{
		"statusClass": statusClass,
		"statusLabel": statusLabel,
		"cpuText":     cpuText,
		"number":      number,
		"meterClass":  meterClass,
		"pageViews":   pageViewsText,
	}

	templates, err := template.New("dashboard").
		Funcs(funcMap).
		Parse(pageTemplate)
	if err != nil {
		log.Fatalf("template setup failed: %v", err)
	}

	application := &app{
		db:        db,
		templates: templates,
		http: &http.Client{
			Timeout: 3 * time.Second,
		},
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/", application.dashboardHandler)

	server := &http.Server{
		Addr:              listenAddress,
		Handler:           securityHeaders(mux),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       30 * time.Second,
	}

	log.Printf("EC2 dashboard listening on http://%s", listenAddress)

	if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Fatal(err)
	}
}

func openDatabase(path string) (*sql.DB, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read %s: %w", path, err)
	}

	var config databaseConfig

	if err := json.Unmarshal(raw, &config); err != nil {
		return nil, fmt.Errorf("parse %s: %w", path, err)
	}

	if config.Host == "" || config.User == "" || config.Name == "" {
		return nil, errors.New("database configuration requires host, user, and name")
	}

	if config.Port == 0 {
		config.Port = 3306
	}

	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=true&timeout=3s&readTimeout=3s&writeTimeout=3s",
		config.User,
		config.Pass,
		config.Host,
		config.Port,
		config.Name,
	)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, fmt.Errorf("open MariaDB connection: %w", err)
	}

	db.SetConnMaxLifetime(5 * time.Minute)
	db.SetConnMaxIdleTime(2 * time.Minute)
	db.SetMaxIdleConns(2)
	db.SetMaxOpenConns(5)

	ctx, cancel := context.WithTimeout(context.Background(), requestTimeout)
	defer cancel()

	if err := db.PingContext(ctx); err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("connect to MariaDB: %w", err)
	}

	return db, nil
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("Referrer-Policy", "no-referrer")
		w.Header().Set(
			"Content-Security-Policy",
			"default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'",
		)

		next.ServeHTTP(w, r)
	})
}

func (a *app) dashboardHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.Header().Set("Allow", http.MethodGet)
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), requestTimeout)
	defer cancel()

	isJSON := r.URL.Query().Get("format") == "json"
	data := a.collectDashboardData(ctx, !isJSON)

	w.Header().Set("Cache-Control", "no-store, max-age=0, must-revalidate")

	if isJSON {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")

		if err := json.NewEncoder(w).Encode(data); err != nil {
			log.Printf("encode JSON response: %v", err)
		}

		return
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	if err := a.templates.ExecuteTemplate(w, "dashboard", data); err != nil {
		log.Printf("render dashboard: %v", err)
	}
}

func (a *app) collectDashboardData(ctx context.Context, countPageView bool) dashboardData {
	token := a.getIMDSToken(ctx)
	imdsOK := token != ""

	instance := identity{
		InstanceID:   a.getMetadata(ctx, token, "instance-id"),
		InstanceType: a.getMetadata(ctx, token, "instance-type"),
		AMI:          a.getMetadata(ctx, token, "ami-id"),
		Region:       a.getMetadata(ctx, token, "placement/region"),
		AZ:           a.getMetadata(ctx, token, "placement/availability-zone"),
		PrivateIP:    a.getMetadata(ctx, token, "local-ipv4"),
		PublicIP:     a.getMetadata(ctx, token, "public-ipv4"),
		PublicDNS:    a.getMetadata(ctx, token, "public-hostname"),
	}

	macList := a.getMetadata(ctx, token, "network/interfaces/macs/")
	primaryMAC := strings.TrimSuffix(strings.Split(macList, "\n")[0], "/")

	if primaryMAC != "" && primaryMAC != "N/A" {
		instance.VPC = a.getMetadata(
			ctx,
			token,
			"network/interfaces/macs/"+primaryMAC+"/vpc-id",
		)

		instance.Subnet = a.getMetadata(
			ctx,
			token,
			"network/interfaces/macs/"+primaryMAC+"/subnet-id",
		)
	} else {
		instance.VPC = "N/A"
		instance.Subnet = "N/A"
	}

	var (
		cpuUsage     *float64
		load         loadAverage
		recentErrors []string
	)

	var work sync.WaitGroup
	work.Add(3)

	go func() {
		defer work.Done()
		cpuUsage = cpuUtilization()
	}()

	go func() {
		defer work.Done()
		load = readLoadAverage()
	}()

	go func() {
		defer work.Done()
		recentErrors = recentSystemErrors(ctx, 6)
	}()

	apache := serviceStatus(ctx, "httpd")
	mariadb := serviceStatus(ctx, "mariadb")
	firewalld := serviceStatus(ctx, "firewalld")
	dns := dnsCheck(ctx, "amazonaws.com")
	internet := a.internetCheck(ctx)

	work.Wait()

	database := a.databaseStatus(ctx, countPageView)

	checks := []struct {
		name string
		ok   bool
	}{
		{"Apache", apache == "OK"},
		{"MariaDB service", mariadb == "OK"},
		{"firewalld", firewalld == "OK"},
		{"IMDSv2", imdsOK},
		{"DNS resolution", dns == "OK"},
		{"Outbound internet", internet == "OK"},
		{"Database connection", database.Status == "OK"},
	}

	failedChecks := make([]string, 0, len(checks))

	for _, check := range checks {
		if !check.ok {
			failedChecks = append(failedChecks, check.name)
		}
	}

	overallOK := len(failedChecks) == 0
	failedSummary := ""

	if !overallOK {
		failedSummary = strings.Join(failedChecks, ", ") + " needs attention."
	}

	return dashboardData{
		GeneratedAt:   time.Now().Format("2006-01-02 15:04:05 MST"),
		Hostname:      commandOutput(ctx, "hostname", "N/A"),
		OverallOK:     overallOK,
		FailedSummary: failedSummary,
		Identity:      instance,
		Vitals: vitals{
			Apache:         apache,
			MariaDB:        mariadb,
			Firewalld:      firewalld,
			GoVersion:      runtime.Version(),
			Uptime:         commandOutput(ctx, "uptime -p", "N/A"),
			DiskUsage: commandOutput(
				ctx,
				`df -h / | awk 'NR==2 {print $3 " used / " $2 " (" $5 ")"}'`,
				"N/A",
			),
			MemoryUsage: commandOutput(
				ctx,
				`free -h | awk '/Mem:/ {print $3 " used / " $2}'`,
				"N/A",
			),
			CPUUtilization: cpuUsage,
			Load:           load,
		},
		Network: networkChecks{
			IMDS:     boolStatus(imdsOK),
			DNS:      dns,
			Internet: internet,
		},
		Database:     database,
		RecentErrors: recentErrors,
	}
}

func (a *app) databaseStatus(ctx context.Context, countPageView bool) databaseStatus {
	if err := a.db.PingContext(ctx); err != nil {
		log.Printf("MariaDB ping failed: %v", err)

		return databaseStatus{
			Status:    "FAILED",
			PageViews: "N/A",
		}
	}

	/*
		Only the initial browser request increments the view counter.
		The browser polling request uses ?format=json and does not increment it.
	*/
	if countPageView {
		_, err := a.db.ExecContext(
			ctx,
			`UPDATE visitor_counter
			 SET views = views + 1
			 WHERE page_name = 'dashboard'`,
		)

		if err != nil {
			log.Printf("increment page views: %v", err)
		}
	}

	var views int64

	err := a.db.QueryRowContext(
		ctx,
		`SELECT views
		 FROM visitor_counter
		 WHERE page_name = 'dashboard'`,
	).Scan(&views)

	if err != nil {
		log.Printf("read page views: %v", err)

		return databaseStatus{
			Status:    "OK",
			PageViews: "N/A",
		}
	}

	return databaseStatus{
		Status:    "OK",
		PageViews: views,
	}
}

func (a *app) getIMDSToken(ctx context.Context) string {
	request, err := http.NewRequestWithContext(
		ctx,
		http.MethodPut,
		"http://169.254.169.254/latest/api/token",
		nil,
	)
	if err != nil {
		return ""
	}

	request.Header.Set("X-aws-ec2-metadata-token-ttl-seconds", "60")

	response, err := a.http.Do(request)
	if err != nil {
		return ""
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return ""
	}

	token, err := readResponseBody(response.Body, 4096)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(token)
}

func (a *app) getMetadata(ctx context.Context, token, path string) string {
	if token == "" {
		return "N/A"
	}

	request, err := http.NewRequestWithContext(
		ctx,
		http.MethodGet,
		"http://169.254.169.254/latest/meta-data/"+path,
		nil,
	)
	if err != nil {
		return "N/A"
	}

	request.Header.Set("X-aws-ec2-metadata-token", token)

	response, err := a.http.Do(request)
	if err != nil {
		return "N/A"
	}
	defer response.Body.Close()

	if response.StatusCode != http.StatusOK {
		return "N/A"
	}

	value, err := readResponseBody(response.Body, 8192)
	if err != nil {
		return "N/A"
	}

	value = strings.TrimSpace(value)

	if value == "" {
		return "N/A"
	}

	return value
}

func readResponseBody(body io.Reader, maxBytes int64) (string, error) {
	data, err := io.ReadAll(io.LimitReader(body, maxBytes))
	if err != nil {
		return "", err
	}

	return string(data), nil
}

func serviceStatus(ctx context.Context, unit string) string {
	status := commandOutput(
		ctx,
		"systemctl is-active "+shellQuote(unit),
		"unknown",
	)

	if status == "active" {
		return "OK"
	}

	return "FAILED"
}

func dnsCheck(ctx context.Context, host string) string {
	resolver := net.DefaultResolver

	records, err := resolver.LookupHost(ctx, host)
	if err == nil && len(records) > 0 {
		return "OK"
	}

	return "FAILED"
}

func (a *app) internetCheck(ctx context.Context) string {
	request, err := http.NewRequestWithContext(
		ctx,
		http.MethodGet,
		"https://checkip.amazonaws.com",
		nil,
	)
	if err != nil {
		return "FAILED"
	}

	response, err := a.http.Do(request)
	if err != nil {
		return "FAILED"
	}
	defer response.Body.Close()

	if response.StatusCode < 200 || response.StatusCode > 299 {
		return "FAILED"
	}

	value, err := readResponseBody(response.Body, 256)

	if err != nil || strings.TrimSpace(value) == "" {
		return "FAILED"
	}

	return "OK"
}

func commandOutput(ctx context.Context, command, fallback string) string {
	commandContext, cancel := context.WithTimeout(ctx, 3*time.Second)
	defer cancel()

	output, err := exec.CommandContext(
		commandContext,
		"sh",
		"-c",
		command,
	).Output()

	if err != nil {
		return fallback
	}

	value := strings.TrimSpace(string(output))

	if value == "" {
		return fallback
	}

	return value
}

func shellQuote(value string) string {
	return "'" + strings.ReplaceAll(value, "'", `'\''`) + "'"
}

type cpuSnapshot struct {
	total uint64
	idle  uint64
}

func readCPUSnapshot() (*cpuSnapshot, error) {
	raw, err := os.ReadFile("/proc/stat")
	if err != nil {
		return nil, err
	}

	firstLine := strings.SplitN(string(raw), "\n", 2)[0]
	fields := strings.Fields(firstLine)

	if len(fields) < 5 || fields[0] != "cpu" {
		return nil, errors.New("invalid CPU row in /proc/stat")
	}

	var total uint64
	values := make([]uint64, 0, len(fields)-1)

	for _, field := range fields[1:] {
		value, err := strconv.ParseUint(field, 10, 64)
		if err != nil {
			return nil, err
		}

		values = append(values, value)
		total += value
	}

	idle := values[3]

	if len(values) > 4 {
		idle += values[4]
	}

	return &cpuSnapshot{
		total: total,
		idle:  idle,
	}, nil
}

func cpuUtilization() *float64 {
	before, err := readCPUSnapshot()
	if err != nil {
		return nil
	}

	time.Sleep(120 * time.Millisecond)

	after, err := readCPUSnapshot()
	if err != nil {
		return nil
	}

	totalDelta := after.total - before.total
	idleDelta := after.idle - before.idle

	if totalDelta == 0 {
		return nil
	}

	usage := 100 * (1 - float64(idleDelta)/float64(totalDelta))
	usage = math.Max(0, math.Min(100, usage))
	usage = math.Round(usage*10) / 10

	return &usage
}

func readLoadAverage() loadAverage {
	raw, err := os.ReadFile("/proc/loadavg")
	cores := max(runtime.NumCPU(), 1)

	if err != nil {
		return loadAverage{Cores: cores}
	}

	fields := strings.Fields(string(raw))

	if len(fields) < 3 {
		return loadAverage{Cores: cores}
	}

	oneMinute, _ := strconv.ParseFloat(fields[0], 64)
	fiveMinute, _ := strconv.ParseFloat(fields[1], 64)
	fifteenMinute, _ := strconv.ParseFloat(fields[2], 64)

	percent := math.Min(100, (oneMinute/float64(cores))*100)

	return loadAverage{
		OneMinute:     math.Round(oneMinute*100) / 100,
		FiveMinute:    math.Round(fiveMinute*100) / 100,
		FifteenMinute: math.Round(fifteenMinute*100) / 100,
		Cores:         cores,
		Percent:       math.Round(percent*10) / 10,
	}
}

func recentSystemErrors(ctx context.Context, limit int) []string {
	limit = min(max(limit, 1), 20)

	commandContext, cancel := context.WithTimeout(ctx, 3*time.Second)
	defer cancel()

	output, err := exec.CommandContext(
		commandContext,
		"journalctl",
		"--no-pager",
		"--output=short-iso",
		"-p",
		"err..alert",
		"-n",
		strconv.Itoa(limit),
	).Output()

	if err != nil {
		return []string{}
	}

	rows := strings.Split(strings.TrimSpace(string(output)), "\n")
	errors := make([]string, 0, len(rows))

	for _, row := range rows {
		row = strings.TrimSpace(row)

		if row != "" {
			errors = append(errors, row)
		}
	}

	return errors
}

func boolStatus(ok bool) string {
	if ok {
		return "OK"
	}

	return "FAILED"
}

func statusClass(status string) string {
	if status == "OK" {
		return "ok"
	}

	return "fail"
}

func statusLabel(status, okLabel, failLabel string) string {
	if status == "OK" {
		return okLabel
	}

	return failLabel
}

func cpuText(value *float64) string {
	if value == nil {
		return "N/A"
	}

	return fmt.Sprintf("%.1f%%", *value)
}

func number(value float64) string {
	return fmt.Sprintf("%.2f", value)
}

func meterClass(value float64) string {
	switch {
	case value >= 90:
		return "is-high"
	case value >= 70:
		return "is-warning"
	default:
		return ""
	}
}

func pageViewsText(value any) string {
	switch typed := value.(type) {
	case int64:
		return fmt.Sprintf("%d times", typed)
	case int:
		return fmt.Sprintf("%d times", typed)
	default:
		return fmt.Sprint(typed)
	}
}

const pageTemplate = `{{ define "dashboard" }}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ .Identity.InstanceID }} | EC2 field report</title>
<style>
:root {
  --paper: #f5f1e6;
  --paper-shadow: #e7e0d1;
  --ink: #20201c;
  --soft-ink: #5d5a51;
  --line: #b9b0a0;
  --heavy-line: #746e62;
  --blue-ink: #31566b;
  --green-ink: #2f634b;
  --red-ink: #963f35;
  --amber-ink: #966f25;
  --typewriter: "Courier Prime", "Courier New", Courier, "Nimbus Mono PS", "Liberation Mono", "DejaVu Sans Mono", monospace;
}

* {
  box-sizing: border-box;
}

body {
  min-width: 320px;
  margin: 0;
  color: var(--ink);
  background:
    linear-gradient(90deg, rgba(32, 32, 28, 0.018) 1px, transparent 1px),
    linear-gradient(rgba(32, 32, 28, 0.014) 1px, transparent 1px),
    var(--paper);
  background-size: 31px 31px;
  font-family: var(--typewriter);
  font-size: 15px;
  letter-spacing: 0.01em;
  line-height: 1.48;
}

.report {
  position: relative;
  width: min(1120px, calc(100% - 40px));
  margin: 0 auto;
  padding: 42px 0 64px;
}

.masthead {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 13px;
  border-bottom: 2px solid var(--ink);
}

.machine-name {
  color: var(--soft-ink);
  font-size: 12px;
}

.report-code {
  color: var(--blue-ink);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  white-space: nowrap;
}

.intro {
  padding-top: 27px;
}

.kicker {
  margin: 0 0 8px;
  color: var(--blue-ink);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
}

h1 {
  max-width: 100%;
  margin: 0;
  font-size: clamp(25px, 4vw, 43px);
  font-weight: 700;
  letter-spacing: -0.055em;
  line-height: 1.1;
  overflow-wrap: anywhere;
}

.subhead {
  margin: 10px 0 26px;
  color: var(--soft-ink);
  font-size: 13px;
}

.status-strip {
  position: relative;
  display: grid;
  grid-template-columns: 12px minmax(0, 1fr) auto;
  align-items: center;
  gap: 13px;
  margin-bottom: 35px;
  padding: 14px 0;
  overflow: hidden;
  border-top: 1px solid var(--heavy-line);
  border-bottom: 1px solid var(--heavy-line);
}

.status-strip.ok {
  color: var(--green-ink);
}

.status-strip.warn {
  color: var(--red-ink);
}

.signal {
  width: 10px;
  height: 10px;
  border: 1px solid currentColor;
  border-radius: 50%;
}

.status-strip.ok .signal {
  background: var(--green-ink);
}

.status-strip.warn .signal {
  background: var(--red-ink);
}

.status-label {
  color: var(--ink);
  font-size: 14px;
  font-weight: 700;
}

.status-detail {
  margin-top: 2px;
  color: var(--soft-ink);
  font-size: 12px;
}

.report-time {
  color: var(--soft-ink);
  font-size: 11px;
  text-align: right;
  white-space: nowrap;
}

.module {
  margin: 0 0 34px;
}

.module-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 0 0 8px;
  border-bottom: 1px solid var(--heavy-line);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.module-heading::before {
  color: var(--blue-ink);
  font-weight: 400;
  content: attr(data-section);
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-bottom: 1px solid var(--line);
}

.cell {
  min-height: 88px;
  padding: 15px 16px 15px 0;
  border-right: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.cell:nth-child(3n + 2) {
  padding-left: 16px;
}

.cell:nth-child(3n) {
  padding-right: 0;
  padding-left: 16px;
  border-right: 0;
}

.field-name {
  margin-bottom: 8px;
  color: var(--soft-ink);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.field-value {
  font-size: 14px;
  overflow-wrap: anywhere;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 700;
}

.badge::before {
  width: 8px;
  height: 8px;
  border: 1px solid currentColor;
  border-radius: 50%;
  content: "";
}

.badge.ok {
  color: var(--green-ink);
}

.badge.ok::before {
  background: var(--green-ink);
}

.badge.fail {
  color: var(--red-ink);
}

.badge.fail::before {
  background: var(--red-ink);
}

.metric-line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.metric-value {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.metric-note {
  color: var(--soft-ink);
  font-size: 10px;
  text-align: right;
}

.meter {
  height: 5px;
  margin-top: 13px;
  overflow: hidden;
  border: 1px solid rgba(116, 110, 98, 0.38);
  background: var(--paper-shadow);
}

.meter-fill {
  display: block;
  width: var(--level, 0%);
  height: 100%;
  background: var(--blue-ink);
}

.meter-fill.is-warning {
  background: var(--amber-ink);
}

.meter-fill.is-high {
  background: var(--red-ink);
}

.error-list {
  margin: 0;
  padding: 0;
  list-style: none;
  border-bottom: 1px solid var(--line);
}

.error-list li {
  position: relative;
  padding: 13px 0 13px 20px;
  border-bottom: 1px solid var(--line);
  color: var(--soft-ink);
  font-size: 12px;
  line-height: 1.58;
  overflow-wrap: anywhere;
}

.error-list li::before {
  position: absolute;
  top: 20px;
  left: 1px;
  width: 7px;
  height: 7px;
  border: 1px solid var(--red-ink);
  border-radius: 50%;
  background: var(--red-ink);
  content: "";
}

.error-list li.empty {
  color: var(--green-ink);
}

.error-list li.empty::before {
  border-color: var(--green-ink);
  background: var(--green-ink);
}

.note {
  max-width: 80ch;
  margin: 11px 0 0;
  color: var(--soft-ink);
  font-size: 12px;
  line-height: 1.68;
}

footer {
  margin-top: 44px;
  padding-top: 13px;
  border-top: 2px solid var(--ink);
  color: var(--soft-ink);
  font-size: 11px;
}

footer code {
  color: var(--blue-ink);
  font-family: inherit;
}

@media (max-width: 720px) {
  .report {
    width: min(100% - 28px, 1120px);
    padding-top: 25px;
  }

  .report-code {
    display: none;
  }

  .status-strip {
    grid-template-columns: 12px minmax(0, 1fr);
  }

  .report-time {
    grid-column: 2;
    text-align: left;
  }

  .grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .cell,
  .cell:nth-child(3n),
  .cell:nth-child(3n + 2) {
    padding: 13px 12px 13px 0;
    border-right: 1px solid var(--line);
  }

  .cell:nth-child(even) {
    padding-right: 0;
    padding-left: 12px;
    border-right: 0;
  }
}

@media (max-width: 460px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .cell,
  .cell:nth-child(even) {
    min-height: auto;
    padding: 13px 0;
    border-right: 0;
  }
}
</style>
</head>
<body>
<div class="report">
  <header class="masthead">
    <span class="machine-name">{{ .Hostname }} / operational report</span>
    <span class="report-code">EC2 STATUS</span>
  </header>

  <main class="intro">
    <p class="kicker">Live instance report</p>

    <h1 id="instance-id">{{ .Identity.InstanceID }}</h1>

    <p class="subhead">
      <span id="instance-type">{{ .Identity.InstanceType }}</span>
      .
      <span id="availability-zone">{{ .Identity.AZ }}</span>
      . refresh interval: five seconds
    </p>

    <section class="status-strip {{ if .OverallOK }}ok{{ else }}warn{{ end }}">
      <span class="signal" aria-hidden="true"></span>

      <div>
        <div class="status-label" id="overall-label">
          {{ if .OverallOK }}All systems nominal{{ else }}Attention needed{{ end }}
        </div>

        <div class="status-detail" id="overall-detail">
          {{ if .OverallOK }}
            Every service and check below is reporting healthy.
          {{ else }}
            {{ .FailedSummary }}
          {{ end }}
        </div>
      </div>

      <time class="report-time" id="clock">{{ .GeneratedAt }}</time>
    </section>

    <section class="module">
      <h2 class="module-heading" data-section="01">Identity</h2>

      <div class="grid">
        <div class="cell"><div class="field-name">Instance ID</div><div class="field-value" id="identity-instance-id">{{ .Identity.InstanceID }}</div></div>
        <div class="cell"><div class="field-name">AMI</div><div class="field-value" id="ami-id">{{ .Identity.AMI }}</div></div>
        <div class="cell"><div class="field-name">Region</div><div class="field-value" id="region">{{ .Identity.Region }}</div></div>
        <div class="cell"><div class="field-name">Availability Zone</div><div class="field-value" id="identity-az">{{ .Identity.AZ }}</div></div>
        <div class="cell"><div class="field-name">VPC</div><div class="field-value" id="vpc-id">{{ .Identity.VPC }}</div></div>
        <div class="cell"><div class="field-name">Subnet</div><div class="field-value" id="subnet-id">{{ .Identity.Subnet }}</div></div>
        <div class="cell"><div class="field-name">Private IPv4</div><div class="field-value" id="private-ip">{{ .Identity.PrivateIP }}</div></div>
        <div class="cell"><div class="field-name">Public IPv4</div><div class="field-value" id="public-ip">{{ .Identity.PublicIP }}</div></div>
        <div class="cell"><div class="field-name">Public DNS</div><div class="field-value" id="public-dns">{{ .Identity.PublicDNS }}</div></div>
      </div>

      <p class="note">
        Whether a subnet is public or private depends on its route table. Confirm the route table associated with subnet <span id="note-subnet">{{ .Identity.Subnet }}</span> in the VPC console.
      </p>
    </section>

    <section class="module">
      <h2 class="module-heading" data-section="02">Vitals</h2>

      <div class="grid">
        <div class="cell"><div class="field-name">Apache</div><div class="field-value"><span class="badge {{ statusClass .Vitals.Apache }}" id="apache-status">{{ statusLabel .Vitals.Apache "Running" "Down" }}</span></div></div>
        <div class="cell"><div class="field-name">MariaDB</div><div class="field-value"><span class="badge {{ statusClass .Vitals.MariaDB }}" id="mariadb-status">{{ statusLabel .Vitals.MariaDB "Running" "Down" }}</span></div></div>
        <div class="cell"><div class="field-name">firewalld</div><div class="field-value"><span class="badge {{ statusClass .Vitals.Firewalld }}" id="firewalld-status">{{ statusLabel .Vitals.Firewalld "Running" "Down" }}</span></div></div>
        <div class="cell"><div class="field-name">Go</div><div class="field-value" id="go-version">{{ .Vitals.GoVersion }}</div></div>
        <div class="cell"><div class="field-name">Uptime</div><div class="field-value" id="uptime">{{ .Vitals.Uptime }}</div></div>
        <div class="cell"><div class="field-name">Disk /</div><div class="field-value" id="disk-usage">{{ .Vitals.DiskUsage }}</div></div>
        <div class="cell"><div class="field-name">Memory</div><div class="field-value" id="memory-usage">{{ .Vitals.MemoryUsage }}</div></div>

        <div class="cell">
          <div class="field-name">CPU Utilization</div>
          <div class="field-value">
            <div class="metric-line">
              <span class="metric-value" id="cpu-usage">{{ cpuText .Vitals.CPUUtilization }}</span>
              <span class="metric-note">120ms sample</span>
            </div>
            <div class="meter">
              <span id="cpu-meter" class="meter-fill" style="--level: 0%"></span>
            </div>
          </div>
        </div>

        <div class="cell">
          <div class="field-name">Load Average</div>
          <div class="field-value">
            <div class="metric-line">
              <span class="metric-value" id="load-average">{{ number .Vitals.Load.OneMinute }}</span>
              <span class="metric-note" id="load-cores">1m / {{ .Vitals.Load.Cores }} cores</span>
            </div>
            <div class="meter">
              <span id="load-meter" class="meter-fill {{ meterClass .Vitals.Load.Percent }}" style="--level: {{ .Vitals.Load.Percent }}%"></span>
            </div>
          </div>
        </div>

        <div class="cell">
          <div class="field-name">Load Trend</div>
          <div class="field-value" id="load-trend">{{ number .Vitals.Load.OneMinute }} / {{ number .Vitals.Load.FiveMinute }} / {{ number .Vitals.Load.FifteenMinute }}</div>
        </div>
      </div>
    </section>

    <section class="module">
      <h2 class="module-heading" data-section="03">Network</h2>

      <div class="grid">
        <div class="cell"><div class="field-name">IMDSv2 Token</div><div class="field-value"><span class="badge {{ statusClass .Network.IMDS }}" id="imds-status">{{ statusLabel .Network.IMDS "Issued" "Unavailable" }}</span></div></div>
        <div class="cell"><div class="field-name">DNS Resolution</div><div class="field-value"><span class="badge {{ statusClass .Network.DNS }}" id="dns-status">{{ statusLabel .Network.DNS "Resolving" "Failing" }}</span></div></div>
        <div class="cell"><div class="field-name">Outbound Internet</div><div class="field-value"><span class="badge {{ statusClass .Network.Internet }}" id="internet-status">{{ statusLabel .Network.Internet "Reachable" "Unreachable" }}</span></div></div>
      </div>
    </section>

    <section class="module">
      <h2 class="module-heading" data-section="04">Data Store</h2>

      <div class="grid">
        <div class="cell"><div class="field-name">MariaDB Connection</div><div class="field-value"><span class="badge {{ statusClass .Database.Status }}" id="database-status">{{ statusLabel .Database.Status "Connected" "Failed" }}</span></div></div>
        <div class="cell"><div class="field-name">Console Opened</div><div class="field-value" id="page-views">{{ pageViews .Database.PageViews }}</div></div>
      </div>
    </section>

    <section class="module">
      <h2 class="module-heading" data-section="05">Recent System Errors</h2>

      <ul class="error-list" id="system-errors" aria-live="polite">
        {{ if .RecentErrors }}
          {{ range .RecentErrors }}<li>{{ . }}</li>{{ end }}
        {{ else }}
          <li class="empty">No recent error level journal records are visible to this service.</li>
        {{ end }}
      </ul>

      <p class="note">
        This section reads the newest journal records from severity <code>err</code> through <code>alert</code>. An empty list can be normal when no recent errors exist.
      </p>
    </section>

    <footer>
      Rendered at {{ .GeneratedAt }}. Deployment log: <code>/var/log/user-data-status.txt</code>
    </footer>
  </main>
</div>

<script>
(function () {
  "use strict";

  var refreshInterval = 5000;

  function setText(id, value) {
    var element = document.getElementById(id);

    if (element && value !== undefined && value !== null) {
      element.textContent = String(value);
    }
  }

  function setBadge(id, isOK, okLabel, failedLabel) {
    var element = document.getElementById(id);

    if (!element) {
      return;
    }

    element.classList.toggle("ok", isOK);
    element.classList.toggle("fail", !isOK);
    element.textContent = isOK ? okLabel : failedLabel;
  }

  function setMeter(id, value) {
    var element = document.getElementById(id);

    if (!element) {
      return;
    }

    var percentage = Math.max(0, Math.min(100, Number(value) || 0));

    element.style.setProperty("--level", percentage + "%");
    element.classList.toggle("is-warning", percentage >= 70 && percentage < 90);
    element.classList.toggle("is-high", percentage >= 90);
  }

  function renderStatus(data) {
    var strip = document.querySelector(".status-strip");
    var label = document.getElementById("overall-label");
    var detail = document.getElementById("overall-detail");

    if (!strip || !label || !detail) {
      return;
    }

    strip.classList.toggle("ok", data.overallOk);
    strip.classList.toggle("warn", !data.overallOk);

    label.textContent = data.overallOk
      ? "All systems nominal"
      : "Attention needed";

    detail.textContent = data.overallOk
      ? "Every service and check below is reporting healthy."
      : data.failedSummary;
  }

  function renderErrors(errors) {
    var list = document.getElementById("system-errors");

    if (!list) {
      return;
    }

    list.replaceChildren();

    if (!Array.isArray(errors) || errors.length === 0) {
      var empty = document.createElement("li");
      empty.className = "empty";
      empty.textContent = "No recent error level journal records are visible to this service.";
      list.appendChild(empty);
      return;
    }

    errors.forEach(function (error) {
      var item = document.createElement("li");
      item.textContent = String(error);
      list.appendChild(item);
    });
  }

  function render(data) {
    renderStatus(data);

    setText("clock", data.generatedAt);
    setText("instance-id", data.identity.instanceId);
    setText("identity-instance-id", data.identity.instanceId);
    setText("instance-type", data.identity.instanceType);
    setText("ami-id", data.identity.amiId);
    setText("region", data.identity.region);
    setText("availability-zone", data.identity.az);
    setText("identity-az", data.identity.az);
    setText("vpc-id", data.identity.vpcId);
    setText("subnet-id", data.identity.subnetId);
    setText("note-subnet", data.identity.subnetId);
    setText("private-ip", data.identity.privateIp);
    setText("public-ip", data.identity.publicIp);
    setText("public-dns", data.identity.publicDns);

    setText("go-version", data.vitals.goVersion);
    setText("uptime", data.vitals.uptime);
    setText("disk-usage", data.vitals.diskUsage);
    setText("memory-usage", data.vitals.memUsage);

    setText(
      "cpu-usage",
      data.vitals.cpuUtilization === null
        ? "N/A"
        : Number(data.vitals.cpuUtilization).toFixed(1) + "%"
    );

    setMeter("cpu-meter", data.vitals.cpuUtilization);
    setText("load-average", Number(data.vitals.load.oneMinute).toFixed(2));
    setText("load-cores", "1m / " + data.vitals.load.cores + " cores");

    setText(
      "load-trend",
      Number(data.vitals.load.oneMinute).toFixed(2) + " / " +
      Number(data.vitals.load.fiveMinute).toFixed(2) + " / " +
      Number(data.vitals.load.fifteenMinute).toFixed(2)
    );

    setMeter("load-meter", data.vitals.load.percent);

    setBadge("apache-status", data.vitals.apache === "OK", "Running", "Down");
    setBadge("mariadb-status", data.vitals.mariadb === "OK", "Running", "Down");
    setBadge("firewalld-status", data.vitals.firewalld === "OK", "Running", "Down");
    setBadge("imds-status", data.network.imds === "OK", "Issued", "Unavailable");
    setBadge("dns-status", data.network.dns === "OK", "Resolving", "Failing");
    setBadge("internet-status", data.network.internet === "OK", "Reachable", "Unreachable");
    setBadge("database-status", data.database.status === "OK", "Connected", "Failed");

    setText(
      "page-views",
      Number.isInteger(data.database.pageViews)
        ? data.database.pageViews + " times"
        : data.database.pageViews
    );

    renderErrors(data.recentErrors);
  }

  async function refresh() {
    var url = new URL(window.location.href);
    url.searchParams.set("format", "json");

    try {
      var response = await fetch(url.toString(), {
        cache: "no-store",
        headers: {
          "Accept": "application/json"
        }
      });

      if (!response.ok) {
        throw new Error("Status request returned " + response.status);
      }

      render(await response.json());
    } catch (error) {
      console.warn("Dashboard refresh failed:", error);
    }
  }

  window.setInterval(refresh, refreshInterval);
})();
</script>
</body>
</html>
{{ end }}`
