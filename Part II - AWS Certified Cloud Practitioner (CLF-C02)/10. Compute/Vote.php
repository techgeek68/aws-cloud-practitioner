<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$ledgerFile = '/var/lib/cloud-poll/votes.json';
$choices    = ['AWS', 'Azure', 'GCP', 'On Prem'];
$dataDir    = dirname($ledgerFile);

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

$tally = file_exists($ledgerFile)
    ? json_decode(file_get_contents($ledgerFile), true) ?? array_fill_keys($choices, 0)
    : array_fill_keys($choices, 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission = json_decode(file_get_contents('php://input'), true);
    $choice     = $submission['choice'] ?? '';

    if (in_array($choice, $choices, true)) {
        $tally[$choice]++;
        file_put_contents($ledgerFile, json_encode($tally), LOCK_EX);
    }
}

echo json_encode([
    'tally' => $tally,
    'total' => array_sum($tally),
]);
