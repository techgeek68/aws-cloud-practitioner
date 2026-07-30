<?php
// util.php - Shared utilities: metadata, CPU metrics, load control

define('IMDS_BASE', 'http://169.254.169.254');
define('IMDS_TOKEN_PATH', '/latest/api/token');
define('IMDS_METADATA_BASE', '/latest/meta-data');
define('STATE_DIR', __DIR__);
define('FLAG_FILE', STATE_DIR . '/load.flag');
define('PIDS_FILE', STATE_DIR . '/load.pids');
define('CONTROLLER_PID_FILE', STATE_DIR . '/controller.pid');

function php_cli_path(): string {
    if (is_executable('/usr/bin/php')) return '/usr/bin/php';
    $p = trim((string)@shell_exec('command -v php 2>/dev/null'));
    return $p !== '' ? $p : 'php';
}

// --- Flag and PID helpers ---
function set_load_flag(bool $state): void {
    @file_put_contents(FLAG_FILE, $state ? "1" : "0", LOCK_EX);
}

function get_load_flag(): bool {
    if (!file_exists(FLAG_FILE)) return false;
    return trim((string)@file_get_contents(FLAG_FILE)) === "1";
}

function reset_pids_file(): void { @file_put_contents(PIDS_FILE, "", LOCK_EX); }

function record_pid(int $pid): void {
    @file_put_contents(PIDS_FILE, $pid . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function read_pids(): array {
    if (!file_exists(PIDS_FILE)) return [];
    $lines = array_filter(array_map('trim',
        explode("\n", (string)@file_get_contents(PIDS_FILE))));
    $pids = [];
    foreach ($lines as $l) { if ($l !== '' && ctype_digit($l)) $pids[] = (int)$l; }
    return $pids;
}

function is_pid_running(int $pid): bool {
    return $pid > 0 && is_dir('/proc/' . $pid);
}

function active_pids(): array {
    $pids = read_pids(); $alive = [];
    foreach ($pids as $p) { if (is_pid_running($p)) $alive[] = $p; }
    @file_put_contents(PIDS_FILE,
        implode(PHP_EOL, $alive) . (count($alive) ? PHP_EOL : ''), LOCK_EX);
    return $alive;
}

function stop_all_bursts(): void {
    foreach (read_pids() as $p)
        if (is_pid_running($p))
            @shell_exec('kill -TERM ' . escapeshellarg((string)$p) . ' >/dev/null 2>&1');
    @unlink(PIDS_FILE);
}

function get_controller_pid(): int {
    if (!file_exists(CONTROLLER_PID_FILE)) return 0;
    $pid = (int)trim((string)@file_get_contents(CONTROLLER_PID_FILE));
    return $pid > 0 ? $pid : 0;
}

function is_controller_running(): bool {
    $pid = get_controller_pid();
    return $pid > 0 && is_pid_running($pid);
}

function start_controller(int $t, int $w, int $b, int $i): ?int {
    $php = php_cli_path();
    $cmd = sprintf(
        'nohup %s %s %d %d %d %d >/dev/null 2>&1 & echo $!',
        escapeshellcmd($php), escapeshellarg(__DIR__ . '/controller.php'),
        $t, $w, $b, $i);
    $pid = (int)trim((string)@shell_exec($cmd));
    if ($pid > 0) { @file_put_contents(CONTROLLER_PID_FILE, (string)$pid, LOCK_EX); return $pid; }
    return null;
}

function stop_controller(): void {
    $pid = get_controller_pid();
    if ($pid > 0 && is_pid_running($pid))
        @shell_exec('kill -TERM ' . escapeshellarg((string)$pid) . ' >/dev/null 2>&1');
    @unlink(CONTROLLER_PID_FILE);
    set_load_flag(false);
    stop_all_bursts();
}

// --- EC2 Instance Metadata Service (IMDSv2 with IMDSv1 fallback) ---
function curl_request(string $url, array $headers = [],
    string $method = 'GET', float $timeout = 1.5): ?string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)ceil(min($timeout, 2.0)));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)ceil($timeout));
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_PROXY, '');
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($resp !== false && $code >= 200 && $code < 300) ? $resp : null;
}

function imdsv2_get_token(): ?string {
    return curl_request(IMDS_BASE . IMDS_TOKEN_PATH,
        ['X-aws-ec2-metadata-token-ttl-seconds: 21600'], 'PUT', 1.5);
}

function imds_get_path(string $path, ?string $token = null): ?string {
    $h = $token ? ['X-aws-ec2-metadata-token: ' . $token] : [];
    return curl_request(IMDS_BASE . $path, $h, 'GET', 1.5);
}

function get_instance_metadata(): array {
    $token = imdsv2_get_token();
    $paths = [
        'instance_id'       => IMDS_METADATA_BASE . '/instance-id',
        'instance_type'     => IMDS_METADATA_BASE . '/instance-type',
        'availability_zone' => IMDS_METADATA_BASE . '/placement/availability-zone',
        'local_ipv4'        => IMDS_METADATA_BASE . '/local-ipv4',
        'public_ipv4'       => IMDS_METADATA_BASE . '/public-ipv4',
    ];
    $out = [];
    foreach ($paths as $key => $p) {
        $val = imds_get_path($p, $token);
        if ($val === null && $token !== null) $val = imds_get_path($p, null);
        $out[$key] = ($val !== null && trim($val) !== '') ? trim($val) : 'N/A';
    }
    return $out;
}

// --- CPU measurement via /proc/stat ---
function read_proc_stat_aggregate(): ?array {
    $contents = @file_get_contents('/proc/stat');
    if ($contents === false) return null;
    foreach (explode("\n", trim($contents)) as $line) {
        if (strpos($line, 'cpu ') === 0) {
            $p = array_map('floatval', array_slice(preg_split('/\s+/', trim($line)), 1));
            $idle    = ($p[3] ?? 0) + ($p[4] ?? 0);
            $nonIdle = ($p[0]??0)+($p[1]??0)+($p[2]??0)+($p[5]??0)+($p[6]??0)+($p[7]??0);
            return ['idle' => $idle, 'total' => $idle + $nonIdle];
        }
    }
    return null;
}

function get_cpu_usage_pct(float $interval = 0.4): float {
    $a = read_proc_stat_aggregate(); if ($a === null) return 0.0;
    usleep((int)round(max($interval, 0.1) * 1_000_000));
    $b = read_proc_stat_aggregate(); if ($b === null) return 0.0;
    $dIdle  = $b['idle']  - $a['idle'];
    $dTotal = $b['total'] - $a['total'];
    if ($dTotal <= 0) return 0.0;
    return max(0.0, min(100.0, (1.0 - ($dIdle / $dTotal)) * 100.0));
}

function classify_utilization(float $pct): string {
    if ($pct < 30.0) return 'Low';
    if ($pct < 70.0) return 'Medium';
    return 'High';
}

function cpu_core_count(): int {
    $n = (int)trim((string)@shell_exec('nproc 2>/dev/null'));
    if ($n > 0) return $n;
    $c = 0;
    foreach (explode("\n", (string)@file_get_contents('/proc/stat')) as $l)
        if (preg_match('/^cpu[0-9]+\s/', $l)) $c++;
    return $c > 0 ? $c : 1;
}

// --- CPU burst spawning ---
function spawn_cpu_burst_processes(int $seconds, int $workers): array {
    $seconds = max(1, min($seconds, 1800));
    $workers = max(1, min($workers, 64));
    $php = php_cli_path();
    $pids = [];
    for ($i = 0; $i < $workers; $i++) {
        $code = '$end=microtime(true)+'.$seconds.';$x=0.0;'
              . 'while(microtime(true)<$end){for($j=0;$j<50000;$j++){$x+=sqrt($j+1)+cos($x);}}';
        $cmd = 'nohup ' . escapeshellcmd($php) . ' -r ' . escapeshellarg($code)
             . ' >/dev/null 2>&1 & echo $!';
        $pid = (int)trim((string)@shell_exec($cmd));
        if ($pid > 0) { $pids[] = $pid; record_pid($pid); }
    }
    return $pids;
}
