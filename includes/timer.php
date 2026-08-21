<?php

function formatTimerSeconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function getMatchTimerState(array $match): array
{
    $timerStatus = $match['timer_status'] ?? 'idle';
    $remainingSeconds = (int) ($match['timer_remaining_seconds'] ?? 0);
    $startedAt = $match['timer_started_at'] ?? null;

    if ($timerStatus === 'running' && $startedAt) {
        $elapsed = max(0, time() - (new DateTimeImmutable($startedAt))->getTimestamp());
        $remainingSeconds = max(0, $remainingSeconds - $elapsed);
    }

    return [
        'remaining_seconds' => $remainingSeconds,
        'display' => formatTimerSeconds($remainingSeconds),
        'running' => $timerStatus === 'running' && $remainingSeconds > 0,
        'finished' => $remainingSeconds === 0 && $timerStatus !== 'idle',
    ];
}

function renderLiveMatchTimer(array $match): void
{
    if (($match['status'] ?? '') !== 'Live') {
        return;
    }

    $timer = getMatchTimerState($match);
    $isM8 = strtoupper((string) ($match['cat_nom'] ?? '')) === 'M8';
    $period = max(1, (int) ($match['timer_period'] ?? 1));
    $periodSuffix = match ($period) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
    $classes = 'live-timer' . ($timer['remaining_seconds'] === 0 ? ' live-timer-finished' : '');
    ?>
    <?php if ($isM8): ?>
        <span class="live-period"><?= $period ?><sup><?= $periodSuffix ?></sup> Half</span>
    <?php endif; ?>
    <span
        class="<?= $classes ?>"
        data-live-timer
        data-match-id="<?= (int) ($match['id'] ?? 0) ?>"
        data-running="<?= $timer['running'] ? '1' : '0' ?>"
        data-remaining="<?= (int) $timer['remaining_seconds'] ?>"
        >
        <?= htmlspecialchars($timer['display']) ?>
    </span>
    <?php
}
