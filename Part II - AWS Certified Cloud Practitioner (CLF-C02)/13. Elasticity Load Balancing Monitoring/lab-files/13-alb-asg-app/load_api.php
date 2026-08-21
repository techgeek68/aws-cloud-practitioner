<?php
// api/load_api.php - JSON endpoint for triggering CPU load
require_once dirname(__DIR__) . '/util.php';
header('Content-Type: application/json');

$seconds    = max(1, min(isset($_GET['s']) ? (int)$_GET['s'] : 2, 1800));
$workers    = max(1, min(isset($_GET['w']) ? (int)$_GET['w'] : max(1, min(8, cpu_core_count())), 64));
$background = !isset($_GET['bg']) || (int)$_GET['bg'] === 1;

$result = ['ok' => true, 'mode' => $background ? 'background' : 'inline',
           'seconds' => $seconds, 'workers' => $workers];

$cpuBefore = get_cpu_usage_pct(0.3);

if ($background) {
    $pids = spawn_cpu_burst_processes($seconds, $workers);
    $result['spawned_pids'] = $pids;
    $result['message'] = empty($pids)
        ? 'Failed to spawn workers. Ensure php-cli is installed.'
        : 'Load generation active.';
} else {
    $end = microtime(true) + $seconds; $x = 0.0;
    while (microtime(true) < $end)
        for ($j = 0; $j < 50000; $j++) $x += sqrt($j + 1) + cos($x);
    $result['message'] = 'Inline load completed.';
}

$result['cpu_before_pct'] = round($cpuBefore, 1);
$result['cpu_after_pct']  = round(get_cpu_usage_pct(0.3), 1);
echo json_encode($result);
