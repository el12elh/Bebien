<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole();
$user = currentUser();
$pdo = db();
ensureMatchTimerColumns($pdo);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

$stmt = $pdo->prepare('SELECT score1, score2, status, timer_status, timer_period, timer_remaining_seconds, timer_started_at, timer_overtime FROM matches WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Match not found']);
    exit;
}

$timerStatus = $row['timer_status'] ?? 'idle';
$timerPeriod = (int) ($row['timer_period'] ?? 1);
$timerRemainingSeconds = (int) ($row['timer_remaining_seconds'] ?? 0);
$timerStartedAt = $row['timer_started_at'] ?? null;
$timerOvertime = (bool) ($row['timer_overtime'] ?? 0);

if ($timerStatus === 'running' && $timerStartedAt) {
    $startedAt = new DateTimeImmutable($timerStartedAt);
    $now = new DateTimeImmutable('now');
    $elapsed = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
    if ($elapsed < $timerRemainingSeconds) {
        $timerRemainingSeconds -= $elapsed;
    } else {
        $timerRemainingSeconds = 0;
        $timerOvertime = false;
        $timerStatus = 'idle';
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'score1' => (int) ($row['score1'] ?? 0),
    'score2' => (int) ($row['score2'] ?? 0),
    'status' => $row['status'] ?? 'Programmé',
    'timer_status' => $timerStatus,
    'timer_period' => $timerPeriod,
    'timer_remaining_seconds' => $timerRemainingSeconds,
    'timer_started_at' => $timerStartedAt,
    'timer_overtime' => $timerOvertime ? 1 : 0,
]);
