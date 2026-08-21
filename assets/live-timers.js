function formatLiveTimer(totalSeconds) {
  const seconds = Math.max(0, totalSeconds);
  const minutesPart = String(Math.floor(seconds / 60)).padStart(2, '0');
  const secondsPart = String(seconds % 60).padStart(2, '0');
  return minutesPart + ':' + secondsPart;
}

function tickLiveTimers() {
  document.querySelectorAll('[data-live-timer]').forEach((timer) => {
    if (timer.dataset.running !== '1') return;

    const nextRemaining = Math.max(0, parseInt(timer.dataset.remaining || '0', 10) - 1);
    timer.dataset.remaining = String(nextRemaining);
    timer.textContent = formatLiveTimer(nextRemaining);

    if (nextRemaining === 0) {
      timer.dataset.running = '0';
      timer.classList.add('live-timer-finished');
    }
  });
}

async function syncLiveTimers() {
  const timers = Array.from(document.querySelectorAll('[data-live-timer][data-match-id]'));
  const ids = [...new Set(timers.map((timer) => timer.dataset.matchId).filter(Boolean))];
  if (!ids.length) return;

  const currentScript = document.currentScript || document.querySelector('script[data-state-url]');
  const stateUrl = new URL(currentScript?.dataset.stateUrl || '/live_timers_state.php', window.location.href);
  stateUrl.searchParams.set('ids', ids.join(','));

  try {
    const response = await fetch(stateUrl, {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    });
    if (!response.ok) return;

    const payload = await response.json();
    if (!payload.ok || !Array.isArray(payload.timers)) return;

    const statesById = new Map(payload.timers.map((state) => [String(state.id), state]));
    timers.forEach((timer) => {
      const state = statesById.get(timer.dataset.matchId);
      if (!state) return;

      timer.dataset.running = state.running ? '1' : '0';
      timer.dataset.remaining = String(Math.max(0, parseInt(state.remaining_seconds || '0', 10)));
      timer.textContent = state.display || formatLiveTimer(parseInt(timer.dataset.remaining || '0', 10));
      timer.classList.toggle('live-timer-finished', Boolean(state.finished));
    });
  } catch (error) {
    // Ignore temporary network errors; the next sync or page refresh will catch up.
  }
}

setInterval(tickLiveTimers, 1000);
syncLiveTimers();
setInterval(syncLiveTimers, 1000);
