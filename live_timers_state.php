<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/timer.php';

$pdo = db();
ensureMatchTimerColumns($pdo);

$ids = array_values(array_unique(array_filter(array_map(
    'intval',
    explode(',', (string) ($_GET['ids'] ?? ''))
))));
$ids = array_slice($ids, 0, 50);

header('Content-Type: application/json');

if (!$ids) {
    echo json_encode(['ok' => true, 'timers' => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    'SELECT id, status, timer_status, timer_remaining_seconds, timer_started_at
     FROM matches
     WHERE id IN (' . $placeholders . ')'
);
$stmt->execute($ids);

$timers = [];
foreach ($stmt->fetchAll() as $match) {
    $timer = getMatchTimerState($match);
    $timers[] = [
        'id' => (int) $match['id'],
        'status' => $match['status'] ?? 'Programmé',
        'timer_status' => $match['timer_status'] ?? 'idle',
        'remaining_seconds' => (int) $timer['remaining_seconds'],
        'display' => $timer['display'],
        'running' => $timer['running'] ? 1 : 0,
        'finished' => $timer['finished'] ? 1 : 0,
    ];
}

echo json_encode(['ok' => true, 'timers' => $timers]);
