<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole();
$user = currentUser();
$pdo = db();
ensureMatchTimerColumns($pdo);

$id = (int) ($_GET['id'] ?? 0);

function getTimerDurationSecondsForMatch(PDO $pdo, int $matchId): int
{
    $stmt = $pdo->prepare('SELECT c.nom FROM matches m JOIN categories c ON c.id=m.category_id WHERE m.id=?');
    $stmt->execute([$matchId]);
    $categoryName = strtoupper((string) ($stmt->fetchColumn() ?: ''));
    $periodSeconds = max(1, (int) getSetting('duree_periode_minutes', 7) * 60);

    return in_array($categoryName, ['M10', 'M12'], true)
        ? $periodSeconds * 2
        : $periodSeconds;
}

function isAjaxRequest(): bool
{
    return ($_POST['ajax'] ?? '') === '1';
}

function respondAfterPost(int $id, array $payload = []): void
{
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true] + $payload);
        exit;
    }

    header('Location: ?id=' . $id . '&saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();

    if (isset($_POST['timer_action'])) {
        $timerAction = $_POST['timer_action'];
        $now = new DateTimeImmutable('now');

        if ($timerAction === 'start') {
            $durationSeconds = getTimerDurationSecondsForMatch($pdo, $id);
            $stmt = $pdo->prepare('UPDATE matches SET status=?, timer_status=?, timer_period=?, timer_remaining_seconds=?, timer_started_at=?, timer_overtime=?, updated_by=? WHERE id=?');
            $stmt->execute(['Live', 'running', 1, $durationSeconds, $now->format('Y-m-d H:i:s'), 0, 'tdm:' . $user['username'], $id]);
        } elseif ($timerAction === 'pause') {
            $stmt = $pdo->prepare('SELECT timer_remaining_seconds, timer_started_at, timer_overtime FROM matches WHERE id=?');
            $stmt->execute([$id]);
            $state = $stmt->fetch();
            $remainingSeconds = (int) ($state['timer_remaining_seconds'] ?? 0);
            $isOvertime = (bool) ($state['timer_overtime'] ?? 0);
            if (!empty($state['timer_started_at'])) {
                $startedAt = new DateTimeImmutable($state['timer_started_at']);
                $elapsed = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
                $remainingSeconds = max(0, $remainingSeconds - $elapsed);
                $isOvertime = false;
            }
            $stmt = $pdo->prepare('UPDATE matches SET timer_status=?, timer_remaining_seconds=?, timer_started_at=NULL, timer_overtime=?, updated_by=? WHERE id=?');
            $stmt->execute(['paused', $remainingSeconds, $isOvertime ? 1 : 0, 'tdm:' . $user['username'], $id]);
        } elseif ($timerAction === 'resume') {
            $stmt = $pdo->prepare('UPDATE matches SET timer_status=?, timer_started_at=?, updated_by=? WHERE id=?');
            $stmt->execute(['running', $now->format('Y-m-d H:i:s'), 'tdm:' . $user['username'], $id]);
        } elseif ($timerAction === 'next_period') {
            $stmt = $pdo->prepare('SELECT timer_period FROM matches WHERE id=?');
            $stmt->execute([$id]);
            $state = $stmt->fetch();
            $currentPeriod = (int) ($state['timer_period'] ?? 1);
            $nextPeriod = min($currentPeriod + 1, max(1, (int) getSetting('nb_mi_temps', 2)));
            $durationSeconds = getTimerDurationSecondsForMatch($pdo, $id);
            $stmt = $pdo->prepare('UPDATE matches SET timer_status=?, timer_period=?, timer_remaining_seconds=?, timer_started_at=?, timer_overtime=?, updated_by=? WHERE id=?');
            $stmt->execute(['idle', $nextPeriod, $durationSeconds, null, 0, 'tdm:' . $user['username'], $id]);
        } elseif ($timerAction === 'reset') {
            $durationSeconds = getTimerDurationSecondsForMatch($pdo, $id);
            $stmt = $pdo->prepare('UPDATE matches SET status=?, score1=0, score2=0, cartons_jaunes1=0, cartons_jaunes2=0, cartons_rouges1=0, cartons_rouges2=0, timer_status=?, timer_period=?, timer_remaining_seconds=?, timer_started_at=?, timer_overtime=?, updated_by=? WHERE id=?');
            $stmt->execute(['Programmé', 'idle', 1, $durationSeconds, null, 0, 'tdm:' . $user['username'], $id]);
        } elseif ($timerAction === 'expire') {
            $stmt = $pdo->prepare('UPDATE matches SET timer_status=?, timer_remaining_seconds=?, timer_started_at=NULL, timer_overtime=?, updated_by=? WHERE id=?');
            $stmt->execute(['idle', 0, 0, 'tdm:' . $user['username'], $id]);
        }
        respondAfterPost($id);
    }

    if (($_POST['score_action'] ?? '') === 'autosave') {
        $s1 = max(0, (int) $_POST['score1']);
        $s2 = max(0, (int) $_POST['score2']);
        $j1 = $_POST['cartons_jaunes1'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_jaunes1']));
        $j2 = $_POST['cartons_jaunes2'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_jaunes2']));
        $r1 = $_POST['cartons_rouges1'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_rouges1']));
        $r2 = $_POST['cartons_rouges2'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_rouges2']));
        $stmt = $pdo->prepare('UPDATE matches SET score1=?, score2=?, cartons_jaunes1=?, cartons_jaunes2=?, cartons_rouges1=?, cartons_rouges2=?, updated_by=? WHERE id=?');
        $stmt->execute([$s1, $s2, $j1, $j2, $r1, $r2, 'tdm:' . $user['username'], $id]);
        respondAfterPost($id);
    }

    if (isset($_POST['status'])) {
        $s1 = max(0, (int) $_POST['score1']);
        $s2 = max(0, (int) $_POST['score2']);
        $status = $_POST['status'];
        $j1 = $_POST['cartons_jaunes1'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_jaunes1']));
        $j2 = $_POST['cartons_jaunes2'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_jaunes2']));
        $r1 = $_POST['cartons_rouges1'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_rouges1']));
        $r2 = $_POST['cartons_rouges2'] === '' ? 0 : max(0, min(5, (int) $_POST['cartons_rouges2']));
        $stmt = $pdo->prepare('UPDATE matches SET score1=?, score2=?, status=?, cartons_jaunes1=?, cartons_jaunes2=?, cartons_rouges1=?, cartons_rouges2=?, updated_by=? WHERE id=?');
        $stmt->execute([$s1, $s2, $status, $j1, $j2, $r1, $r2, 'tdm:' . $user['username'], $id]);

        if ($status === 'Terminé') {
            header('Location: ../?finished=1');
            exit;
        }
        header('Location: ?id=' . $id . '&saved=1');
        exit;
    }

    header('Location: ?id=' . $id . '&saved=1');
    exit;
}

$stmt = $pdo->prepare('SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
                        c1.nom_club c1, c1.logo_path AS club1_logo_path,
                        CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS e1,
                        c2.nom_club c2, c2.logo_path AS club2_logo_path,
                        CASE WHEN club_team_counts2.team_count > 1 THEN t2.nom_equipe ELSE "" END AS e2
                        FROM matches m
                        JOIN categories c ON c.id=m.category_id
                        JOIN pools p ON p.id=m.pool_id
                        LEFT JOIN fields f ON f.id=m.field_id
                        JOIN teams t1 ON t1.id=m.team1_id
                        JOIN clubs c1 ON c1.id=t1.club_id
                        JOIN (
                          SELECT club_id, category_id, COUNT(*) AS team_count
                          FROM teams
                          GROUP BY club_id, category_id
                        ) club_team_counts1 ON club_team_counts1.club_id = t1.club_id AND club_team_counts1.category_id = t1.category_id
                        JOIN teams t2 ON t2.id=m.team2_id
                        JOIN clubs c2 ON c2.id=t2.club_id
                        JOIN (
                          SELECT club_id, category_id, COUNT(*) AS team_count
                          FROM teams
                          GROUP BY club_id, category_id
                        ) club_team_counts2 ON club_team_counts2.club_id = t2.club_id AND club_team_counts2.category_id = t2.category_id
                        WHERE m.id = ?');
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { die('Match introuvable.'); }

$isContinuousCategory = in_array(strtoupper((string) $m['cat_nom']), ['M10', 'M12'], true);
$configuredPeriodSeconds = max(1, (int) getSetting('duree_periode_minutes', 7) * 60);
$timerDurationSeconds = $isContinuousCategory
  ? $configuredPeriodSeconds * 2
  : $configuredPeriodSeconds;
$totalPeriods = $isContinuousCategory
  ? 1
  : max(1, (int) getSetting('nb_mi_temps', 2));

$timerStatus = $m['timer_status'] ?? 'idle';
$timerPeriod = (int) ($m['timer_period'] ?? 1);
$timerRemainingSeconds = (int) ($m['timer_remaining_seconds'] ?? 0);
$timerStartedAt = $m['timer_started_at'] ?? null;
$timerOvertime = (bool) ($m['timer_overtime'] ?? 0);
$timerFinished = false;

if ($timerStatus === 'running' && $timerStartedAt) {
    $startedAt = new DateTimeImmutable($timerStartedAt);
    $now = new DateTimeImmutable('now');
    $elapsed = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
    $timerRemainingSeconds = max(0, $timerRemainingSeconds - $elapsed);
    if ($timerRemainingSeconds <= 0) {
        $timerRemainingSeconds = 0;
        $timerOvertime = false;
        $timerFinished = true;
        $timerStatus = 'idle';
    }
}
if ($m['status'] === 'Live' && $timerStatus === 'idle' && $timerRemainingSeconds === 0) {
    $timerFinished = true;
}
$initialTimerRemainingSeconds = ($timerRemainingSeconds > 0 || $timerStatus !== 'idle' || $timerOvertime || $timerFinished)
  ? $timerRemainingSeconds
  : $timerDurationSeconds;

$pageTitle = 'Saisie du score';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/tdm_nav.php';
?>
<div class="container" style="max-width:500px;">
  <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
  </div>
  <?php if (!empty($_GET['saved'])): ?><div class="alert alert-success text-center">Score enregistré.</div><?php endif; ?>
  <p class="text-center text-muted">
    <?= htmlspecialchars($m['cat_nom']) ?> • <?= htmlspecialchars($m['pool_nom']) ?> • 
    <?= htmlspecialchars($m['field_nom'] ?? '') ?> <?= $m['scheduled_at'] ? '• ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?>
  </p>

  <div class="timer-card border rounded p-3 mb-4">
    <div class="text-center">
      <div class="small text-uppercase text-muted">Minuteur</div>
      <div id="periodLabel" class="fw-bold fs-5">Période 1</div>
      <div id="timerDisplay" class="display-1 fw-bold">07:00</div>
      <div id="timerStatus" class="text-muted mb-3">Prêt à démarrer</div>
    </div>
    <div class="d-grid gap-2">
      <form method="post" class="d-grid gap-2">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <input type="hidden" name="timer_action" id="timerAction" value="start">
        <button type="submit" id="timerControlBtn" class="btn btn-primary">Lancer le match</button>
      </form>
      <form method="post" class="d-grid gap-2">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <input type="hidden" name="timer_action" value="reset">
        <button type="submit" id="resetBtn" class="btn btn-outline-secondary btn-sm">Reset</button>
      </form>
    </div>
  </div>

  <form method="post" id="scoreForm">
    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
    <div class="row text-center mb-4">
      <div class="col-6">
        <?php if (!empty($m['club1_logo_path'])): ?>
          <img src="/<?= htmlspecialchars($m['club1_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c1']) ?>" class="score-team-logo mb-2">
        <?php endif; ?>
        <h5 class="mb-2"><?= htmlspecialchars($m['c1']) ?> <small class="text-muted"><?= htmlspecialchars($m['e1']) ?></small></h5>
        <div class="d-flex flex-column align-items-center gap-2">
          <button type="button" class="btn btn-outline-success" onclick="incr(1,1)">+1</button>
          <input type="number" name="score1" id="score1" min="0" class="form-control score-input text-center fs-2" value="<?= (int)($m['score1'] ?? 0) ?>">
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="incr(1,-1)">-1</button>
          <div class="d-flex justify-content-center gap-2 mt-2">
            <label class="card-counter mb-0">
              <span class="card-icon card-icon-yellow" title="Carton jaune"></span>
              <input type="number" name="cartons_jaunes1" value="<?= htmlspecialchars($m['cartons_jaunes1'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm text-center" style="width:3.5rem;">
            </label>
            <label class="card-counter mb-0">
              <span class="card-icon card-icon-red" title="Carton rouge"></span>
              <input type="number" name="cartons_rouges1" value="<?= htmlspecialchars($m['cartons_rouges1'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm text-center" style="width:3.5rem;">
            </label>
          </div>
        </div>
      </div>
      <div class="col-6">
        <?php if (!empty($m['club2_logo_path'])): ?>
          <img src="/<?= htmlspecialchars($m['club2_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c2']) ?>" class="score-team-logo mb-2">
        <?php endif; ?>
        <h5 class="mb-2"><?= htmlspecialchars($m['c2']) ?> <small class="text-muted"><?= htmlspecialchars($m['e2']) ?></small></h5>
        <div class="d-flex flex-column align-items-center gap-2">
          <button type="button" class="btn btn-outline-success" onclick="incr(2,1)">+1</button>
          <input type="number" name="score2" id="score2" min="0" class="form-control score-input text-center fs-2" value="<?= (int)($m['score2'] ?? 0) ?>">
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="incr(2,-1)">-1</button>
          <div class="d-flex justify-content-center gap-2 mt-2">
            <label class="card-counter mb-0">
              <span class="card-icon card-icon-yellow" title="Carton jaune"></span>
              <input type="number" name="cartons_jaunes2" value="<?= htmlspecialchars($m['cartons_jaunes2'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm text-center" style="width:3.5rem;">
            </label>
            <label class="card-counter mb-0">
              <span class="card-icon card-icon-red" title="Carton rouge"></span>
              <input type="number" name="cartons_rouges2" value="<?= htmlspecialchars($m['cartons_rouges2'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm text-center" style="width:3.5rem;">
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button
          type="submit" name="status" value="Terminé" id="finishMatchBtn" class="btn btn-success"
          data-team1="<?= htmlspecialchars(trim($m['c1'] . $m['e1'])) ?>"
          data-team2="<?= htmlspecialchars(trim($m['c2'] . $m['e2'])) ?>"
          >
          Terminer le match
      </button>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function incr(team, delta) {
  const input = document.getElementById('score' + team);
  let v = parseInt(input.value || '0', 10) + delta;
  if (v < 0) v = 0;
  input.value = v;
  autosaveScore();
}

const PERIOD_DURATION = <?= json_encode($timerDurationSeconds) ?>;
const TOTAL_PERIODS = <?= json_encode($totalPeriods) ?>;
let remainingSeconds = <?= (int) $initialTimerRemainingSeconds ?>;
let currentPeriod = <?= (int) $timerPeriod ?> || 1;
let timerInterval = null;
let isRunning = <?= $timerStatus === 'running' ? 'true' : 'false' ?>;
let isOvertime = <?= $timerOvertime ? 'true' : 'false' ?>;

function formatTime(totalSeconds) {
  const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  return minutes + ':' + seconds;
}

function updateTimerUI() {
  const periodLabel = document.getElementById('periodLabel');
  const timerDisplay = document.getElementById('timerDisplay');
  const timerStatus = document.getElementById('timerStatus');
  const timerControlBtn = document.getElementById('timerControlBtn');
  const timerAction = document.getElementById('timerAction');
  const nextPeriodBtn = document.getElementById('nextPeriodBtn');

  if (periodLabel) periodLabel.textContent = 'Période ' + currentPeriod + ' / ' + TOTAL_PERIODS;
  if (timerDisplay) {
    timerDisplay.textContent = formatTime(remainingSeconds);
    const shouldBeRed = remainingSeconds === 0 || isOvertime;
    timerDisplay.classList.toggle('timer-display-red', shouldBeRed);
  }

  if (timerStatus) {
    if (!isRunning && remainingSeconds === PERIOD_DURATION && currentPeriod === 1 && !isOvertime) {
      timerStatus.textContent = 'Prêt à démarrer';
    } else if (!isRunning && remainingSeconds > 0) {
      timerStatus.textContent = 'En pause';
    } else if (remainingSeconds === 0) {
      timerStatus.textContent = 'Fin de la période';
    } else {
      timerStatus.textContent = 'Live';
    }
  }

  if (timerControlBtn && timerAction) {
    const hasStarted = isRunning || remainingSeconds < PERIOD_DURATION || currentPeriod > 1 || isOvertime;
    timerControlBtn.disabled = false;

    if (!hasStarted) {
      timerAction.value = 'start';
      timerControlBtn.textContent = 'Lancer le match';
      timerControlBtn.className = 'btn btn-primary';
    } else if (remainingSeconds === 0 && currentPeriod < TOTAL_PERIODS) {
      timerAction.value = 'next_period';
      timerControlBtn.textContent = currentPeriod === 1 ? 'Terminer la 1ère période' : 'Terminer la période ' + currentPeriod;
      timerControlBtn.className = 'btn btn-warning';
    } else if (remainingSeconds === 0) {
      timerAction.value = 'finish';
      timerControlBtn.textContent = 'Terminer le match';
      timerControlBtn.className = 'btn btn-success';
    } else if (isRunning) {
      timerAction.value = 'pause';
      timerControlBtn.textContent = 'Pause';
      timerControlBtn.className = 'btn btn-outline-warning';
    } else {
      timerAction.value = 'resume';
      timerControlBtn.textContent = 'Start';
      timerControlBtn.className = 'btn btn-primary';
    }
  }
  if (nextPeriodBtn) {
    const hasStarted = isRunning || remainingSeconds < PERIOD_DURATION || currentPeriod > 1 || isOvertime;
    nextPeriodBtn.closest('form').style.display = hasStarted ? 'block' : 'none';
    const isFirstHalf = currentPeriod === 1;
    nextPeriodBtn.disabled = currentPeriod >= TOTAL_PERIODS && !isOvertime;
    if (isFirstHalf) {
      nextPeriodBtn.textContent = 'Arrêter la 1re mi-temps';
    } else if (currentPeriod < TOTAL_PERIODS) {
      nextPeriodBtn.textContent = 'Passer à la période suivante';
    } else {
      nextPeriodBtn.textContent = 'Terminer le match';
    }
  }
}

function tickTimer() {
  remainingSeconds -= 1;
  if (remainingSeconds <= 0) {
    remainingSeconds = 0;
    isRunning = false;
    clearInterval(timerInterval);
    timerInterval = null;
    const timerForm = document.getElementById('timerControlBtn')?.closest('form');
    sendTimerAction('expire', timerForm);
  }
  updateTimerUI();
}

function startTimer() {
  if (timerInterval) return;
  isRunning = true;
  timerInterval = setInterval(tickTimer, 1000);
  updateTimerUI();
}

function pauseTimer() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    updateTimerUI();
    return;
  }

  const hasStarted = remainingSeconds < PERIOD_DURATION || currentPeriod > 1 || isOvertime;
  if (hasStarted) {
    isRunning = true;
    timerInterval = setInterval(tickTimer, 1000);
    updateTimerUI();
  }
}

function resetTimer() {
  clearInterval(timerInterval);
  timerInterval = null;
  isRunning = false;
  isOvertime = false;
  currentPeriod = 1;
  remainingSeconds = PERIOD_DURATION;
  document.querySelectorAll('#scoreForm input[type="number"]').forEach((input) => {
    input.value = '0';
  });
  updateTimerUI();
}

function nextPeriod() {
  if (currentPeriod < TOTAL_PERIODS) {
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    isOvertime = false;
    currentPeriod += 1;
    remainingSeconds = PERIOD_DURATION;
    updateTimerUI();
    return;
  }

  if (currentPeriod >= TOTAL_PERIODS) {
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    isOvertime = false;
    submitFinishMatch();
  }
}

function buildFinishConfirmMessage() {
  const finishBtn = document.getElementById('finishMatchBtn');
  const team1 = finishBtn?.dataset.team1 || '';
  const team2 = finishBtn?.dataset.team2 || '';
  const score1 = parseInt(document.getElementById('score1')?.value || '0', 10);
  const score2 = parseInt(document.getElementById('score2')?.value || '0', 10);

  return 'Confirmer la fin du match ?\n \n' + team1 + '   ' + score1 + ' - ' + score2 + '   ' + team2;
}

function submitFinishMatch() {
  const form = document.getElementById('scoreForm');
  const finishBtn = document.getElementById('finishMatchBtn');
  if (!form || !finishBtn) return;

  if (typeof form.requestSubmit === 'function') {
    form.requestSubmit(finishBtn);
  } else {
    finishBtn.click();
  }
}

function sendTimerAction(action, form) {
  if (!form) return;

  const formData = new FormData(form);
  formData.set('timer_action', action);
  formData.set('ajax', '1');

  fetch(window.location.href, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'fetch'
    }
  }).catch(() => {
    const timerStatus = document.getElementById('timerStatus');
    if (timerStatus) timerStatus.textContent = 'Erreur de synchronisation';
  });
}

let scoreAutosaveTimeout = null;

function autosaveScore() {
  clearTimeout(scoreAutosaveTimeout);
  scoreAutosaveTimeout = setTimeout(() => {
    const form = document.getElementById('scoreForm');
    if (!form) return;

    const formData = new FormData(form);
    formData.set('score_action', 'autosave');
    formData.set('ajax', '1');

    fetch(window.location.href, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'fetch'
      }
    }).catch(() => {
      const timerStatus = document.getElementById('timerStatus');
      if (timerStatus) timerStatus.textContent = 'Erreur de sauvegarde';
    });
  }, 250);
}

document.getElementById('timerControlBtn')?.addEventListener('click', (event) => {
  event.preventDefault();
  const timerAction = document.getElementById('timerAction');
  const actionToSubmit = timerAction?.value;
  const form = event.currentTarget.closest('form');
  if (actionToSubmit === 'finish') {
    submitFinishMatch();
    return;
  }
  if (actionToSubmit === 'next_period') {
    nextPeriod();
    sendTimerAction('next_period', form);
    return;
  }
  if (isRunning) {
    pauseTimer();
  } else {
    startTimer();
  }
  if (timerAction && actionToSubmit) {
    timerAction.value = actionToSubmit;
  }
  sendTimerAction(actionToSubmit, form);
});
document.getElementById('nextPeriodBtn')?.addEventListener('click', (event) => {
  event.preventDefault();
  nextPeriod();
  const form = event.currentTarget.closest('form');
  sendTimerAction('next_period', form);
});
document.getElementById('resetBtn')?.addEventListener('click', (event) => {
  event.preventDefault();
  if (!confirm('Réinitialiser ce match ? Le score, les cartons et le minuteur seront remis à zéro.')) {
    return;
  }
  resetTimer();
  const form = event.currentTarget.closest('form');
  sendTimerAction('reset', form);
});
document.getElementById('scoreForm')?.addEventListener('submit', (event) => {
  const submitter = event.submitter || document.activeElement;
  if (submitter?.name !== 'status' || submitter?.value !== 'Terminé') return;

  if (!confirm(buildFinishConfirmMessage())) {
    event.preventDefault();
  }
});
document.querySelectorAll('#scoreForm input[type="number"]').forEach((input) => {
  input.addEventListener('change', autosaveScore);
  input.addEventListener('blur', autosaveScore);
});
if (isRunning) {
  timerInterval = setInterval(tickTimer, 1000);
}
updateTimerUI();
</script>
</body></html>
