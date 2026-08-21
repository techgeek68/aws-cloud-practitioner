<?php
require_once __DIR__ . '/util.php';

$metadata     = get_instance_metadata();
$cpuPct       = get_cpu_usage_pct(0.35);
$class        = classify_utilization($cpuPct);
$isLoadActive = get_load_flag();
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>AWS EC2 Info & CPU Monitor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, sans-serif; margin: 20px; color: #222; }
    .grid { display: grid; grid-template-columns: 220px 1fr; gap: 8px; max-width: 780px; }
    .label { color: #555; } .value { font-weight: 600; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 12px;
             font-size: 12px; color: #fff; }
    .low  { background: #2d9b59; }
    .med  { background: #e6a700; }
    .high { background: #d93025; }
    .card { border: 1px solid #e1e4e8; border-radius: 6px; padding: 16px; margin: 16px 0; }
    .muted { color: #666; font-size: 13px; }
    .mono  { font-family: ui-monospace, monospace; }
  </style>
</head>
<body>
  <h1>EC2 Instance Metadata & CPU Monitor</h1>

  <div class="card">
    <h2>Instance Metadata</h2>
    <div class="grid">
      <div class="label">Instance ID</div>
        <div class="value mono"><?= h($metadata['instance_id']) ?></div>
      <div class="label">Instance type</div>
        <div class="value mono"><?= h($metadata['instance_type']) ?></div>
      <div class="label">Availability Zone</div>
        <div class="value mono"><?= h($metadata['availability_zone']) ?></div>
      <div class="label">Private IP</div>
        <div class="value mono"><?= h($metadata['local_ipv4']) ?></div>
      <div class="label">Public IP</div>
        <div class="value mono"><?= h($metadata['public_ipv4']) ?></div>
    </div>
  </div>

  <div class="card">
    <h2>CPU Utilization</h2>
    <?php $bc = $class === 'Low' ? 'low' : ($class === 'Medium' ? 'med' : 'high'); ?>
    <div class="grid">
      <div class="label">Approx. utilization</div>
      <div class="value">
        <?= number_format($cpuPct, 1) ?>%
        <span class="badge <?= $bc ?>"><?= h($class) ?></span>
      </div>
    </div>
    <div class="muted">Measured from /proc/stat. Approximate only.</div>
  </div>

  <div class="card">
    <h2>Actions</h2>
    <a href="load.php">CPU Load Generator</a> &nbsp;|&nbsp;
    <?php if ($isLoadActive): ?>
      <a href="load.php?action=stop">Stop Load</a>
    <?php else: ?>
      <a href="load.php?action=start">Start Load</a>
    <?php endif; ?>
    &nbsp;|&nbsp;
    <a href="index.php?ts=<?= time() ?>">Refresh</a>
  </div>

  <div class="muted">Server time: <?= date('Y-m-d H:i:s T') ?></div>
</body>
</html>
