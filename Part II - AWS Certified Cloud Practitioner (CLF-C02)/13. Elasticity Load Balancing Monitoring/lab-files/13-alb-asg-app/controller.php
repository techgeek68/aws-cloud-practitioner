<?php
// controller.php - Background CPU load controller
// Usage: php controller.php <targetPct> <maxWorkers> <burstSeconds> <intervalSeconds>

require_once __DIR__ . '/util.php';

$targetPct   = isset($argv[1]) ? max(10, min(95, (int)$argv[1])) : 60;
$maxWorkers  = isset($argv[2]) ? max(1, min(64, (int)$argv[2])) : max(1, min(8, cpu_core_count()));
$burstSec    = isset($argv[3]) ? max(1, min(60, (int)$argv[3])) : 3;
$intervalSec = isset($argv[4]) ? max(1, min(10, (int)$argv[4])) : 1;

ignore_user_abort(true);
set_time_limit(0);
set_load_flag(true);
reset_pids_file();

while (get_load_flag()) {
    $cpu    = get_cpu_usage_pct(0.5);
    $alive  = active_pids();
    $active = count($alive);
    $spawn  = 0;
    if ($cpu < $targetPct) {
        $gap   = $targetPct - $cpu;
        $ideal = (int)ceil(max(1, $gap / 20));
        $slots = max(0, $maxWorkers - $active);
        $spawn = min($ideal, $slots);
    }
    if ($spawn > 0) spawn_cpu_burst_processes($burstSec, $spawn);
    sleep($intervalSec);
}
exit(0);
