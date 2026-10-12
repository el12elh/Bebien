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
    $timing = getCategoryTiming((int) ($match['category_id'] ?? 0));
    $totalPeriods = $timing['nb_mi_temps'];
    $period = min($totalPeriods, max(1, (int) ($match['timer_period'] ?? 1)));
    $periodLabel = $period === 1 ? '1re' : $period . 'e';
    $periodNoun = $totalPeriods === 2 ? 'mi-temps' : 'période';
    $classes = 'live-timer' . ($timer['remaining_seconds'] === 0 ? ' live-timer-finished' : '');
    ?>
    <?php if ($totalPeriods > 1): ?>
        <span class="live-period"><?= htmlspecialchars($periodLabel . ' ' . $periodNoun) ?></span>
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
