<?php
require_once __DIR__ . '/util.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$action     = isset($_GET['action']) ? strtolower((string)$_GET['action']) : '';
$target     = isset($_GET['target']) ? max(10, min(95, (int)$_GET['target'])) : 60;
$maxWorkers = isset($_GET['workers'])
    ? max(1, min(64, (int)$_GET['workers'])) : max(1, min(8, cpu_core_count()));
$burstSec   = isset($_GET['burst']) ? max(1, min(20, (int)$_GET['burst'])) : 3;

$message = '';
if ($action === 'start') {
    if (!is_controller_running()) {
        $pid = start_controller($target, $maxWorkers, $burstSec, 1);
        $message = $pid ? "Controller started (PID $pid)"
            : 'Failed to start. Is php-cli installed?';
    } else { $message = 'Controller already running.'; }
}
if ($action === 'stop') {
    if (is_controller_running()) { stop_controller(); $message = 'Controller stopped.'; }
    else {
        set_load_flag(false); stop_all_bursts();
        $message = 'No controller was running. Bursts will exit shortly.';
    }
}

$isActive      = is_controller_running();
$controllerPid = get_controller_pid();
$cpu           = get_cpu_usage_pct(0.5);
$class         = classify_utilization($cpu);
$activeCount   = count(active_pids());
$defaultW      = max(1, min(8, cpu_core_count()));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>CPU Load Generator</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 20px; }
    .card { border: 1px solid #e1e4e8; border-radius: 6px; padding: 16px; margin: 16px 0; }
    .row  { display: grid; grid-template-columns: 220px 1fr; gap: 8px; }
    .mono { font-family: ui-monospace, monospace; }
    .muted { color: #666; font-size: 13px; }
  </style>
</head>
<body>
  <h1>CPU Load Generator</h1>
  <?php if ($message): ?>
    <div class="card"><strong><?= h($message) ?></strong></div>
  <?php endif; ?>
  <div class="card">
    <div class="row"><div>Status</div>
      <div class="mono"><?= $isActive ? "Active (PID $controllerPid)" : 'Idle' ?></div></div>
    <div class="row"><div>CPU usage</div>
      <div class="mono"><?= number_format($cpu, 1) ?>% (<?= h($class) ?>)</div></div>
    <div class="row"><div>Active workers</div>
      <div class="mono"><?= $activeCount ?></div></div>
    <div style="margin-top: 10px;">
      <a href="index.php">Back</a> &nbsp;
      <?php if ($isActive): ?>
        <a href="load.php?action=stop">Stop</a>
      <?php else: ?>
        <a href="load.php?action=start&target=<?= $target ?>&workers=<?= $maxWorkers ?>&burst=<?= $burstSec ?>">Start</a>
      <?php endif; ?>
      &nbsp; <a href="load.php">Refresh</a>
    </div>
  </div>
  <div class="muted">Server time: <?= date('Y-m-d H:i:s T') ?></div>
</body>
</html>
