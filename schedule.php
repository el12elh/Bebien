<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/timer.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = $_GET['category_id'] ?? '';
$phase = $_GET['phase'] ?? null;
$poolId = $_GET['pool_id'] ?? '';

$dateTournoi = getSetting('date_tournoi', '');
if ($phase !== 'matin' && $phase !== 'aprem') {
  $phase = '';
  if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    $phase = new DateTime() > $tournamentDeadline ? 'aprem' : 'matin';
  }
}

$poolQuery = 'SELECT p.* FROM pools p';
$poolParams = [];
$where = [];
if ($categoryId !== '') {
    $where[] = 'p.category_id = ?';
    $poolParams[] = $categoryId;
}
if ($phase !== '') {
    $where[] = 'p.phase = ?';
    $poolParams[] = $phase;
}
if ($where) {
    $poolQuery .= ' WHERE ' . implode(' AND ', $where);
}
$poolQuery .= ' ORDER BY p.nom';
$pools = $pdo->prepare($poolQuery);
$pools->execute($poolParams);
$pools = $pools->fetchAll();

$sql = 'SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
         c1.nom_club c1, c1.logo_path AS club1_logo_path,
         CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS e1,
         c2.nom_club c2, c2.logo_path AS club2_logo_path,
         CASE WHEN club_team_counts2.team_count > 1 THEN t2.nom_equipe ELSE "" END AS e2
    FROM matches m
    JOIN categories c ON c.id = m.category_id
    JOIN pools p ON p.id = m.pool_id
    LEFT JOIN fields f ON f.id = m.field_id
    JOIN teams t1 ON t1.id = m.team1_id
    JOIN clubs c1 ON c1.id = t1.club_id
    JOIN (
      SELECT club_id, category_id, COUNT(*) AS team_count
      FROM teams
      GROUP BY club_id, category_id
    ) club_team_counts1 ON club_team_counts1.club_id = t1.club_id AND club_team_counts1.category_id = t1.category_id
    JOIN teams t2 ON t2.id = m.team2_id
    JOIN clubs c2 ON c2.id = t2.club_id
    JOIN (
      SELECT club_id, category_id, COUNT(*) AS team_count
      FROM teams
      GROUP BY club_id, category_id
    ) club_team_counts2 ON club_team_counts2.club_id = t2.club_id AND club_team_counts2.category_id = t2.category_id
    WHERE 1=1';
$params = [];
if ($categoryId !== '') { $sql .= ' AND m.category_id=?'; $params[] = $categoryId; }
if ($phase !== '') { $sql .= ' AND m.phase=?'; $params[] = $phase; }
if ($poolId !== '') { $sql .= ' AND m.pool_id=?'; $params[] = $poolId; }
$sql .= ' ORDER BY m.scheduled_at, f.nom';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();
$hasLive = in_array('Live', array_column($matches, 'status'), true);

$pageTitle = 'Calendrier';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';

?>

<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <h2 class="mb-3">Calendrier des matchs</h2>
  <form method="get" class="row g-2 mb-4">
    <div class="col-md-3">
      <select name="category_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $categoryId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="phase" class="form-select" onchange="this.form.submit()">
        <option value="matin" <?= $phase === 'matin' ? 'selected' : '' ?>>Matin</option>
        <option value="aprem" <?= $phase === 'aprem' ? 'selected' : '' ?>>Après-midi</option>
      </select>
    </div>
    <div class="col-md-3">
      <select name="pool_id" class="form-select" onchange="this.form.submit()">
        <option value="">Toutes les poules</option>
        <?php foreach ($pools as $pool): ?>
          <option value="<?= $pool['id'] ?>" <?= $poolId == $pool['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pool['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <?php foreach ($matches as $m): ?>
      <?php
        $score1 = $m['score1'] ?? null;
        $score2 = $m['score2'] ?? null;
        $winnerClass = '';
        if (is_numeric($score1) && is_numeric($score2) && $score1 !== $score2) {
          $winnerClass = $score1 > $score2 ? 'winner-team-1' : 'winner-team-2';
        }
      ?>
      <div class="col-md-3">
        <div class="card card-match <?= $m['status'] ?>">
          <div class="card-body">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span><?= htmlspecialchars($m['cat_nom']) ?> • <?= htmlspecialchars($m['pool_nom']) ?></span>
              <span><?= htmlspecialchars($m['field_nom'] ?? '') ?> <?= $m['scheduled_at'] ? '• ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($m['club1_logo_path'])): ?><img src="<?= htmlspecialchars($m['club1_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c1']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                <span class="<?= $winnerClass === 'winner-team-1' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c1']) ?><small class="text-muted"><?= htmlspecialchars($m['e1']) ?></small></span>
              </div>
              <?php if ($m['status'] === 'Programmé'): ?>
                <span class="text-muted">vs</span>
              <?php else: ?>
                <span class="fw-bold"><?= $m['score1'] ?? '-' ?></span>
                <span class="text-muted">:</span>
                <span class="fw-bold"><?= $m['score2'] ?? '-' ?></span>
              <?php endif; ?>
              <div class="d-flex align-items-center gap-2">
                <span class="<?= $winnerClass === 'winner-team-2' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c2']) ?><small class="text-muted"><?= htmlspecialchars($m['e2']) ?></small></span>
                <?php if (!empty($m['club2_logo_path'])): ?><img src="<?= htmlspecialchars($m['club2_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c2']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
              <span class="badge badge-status-<?= $m['status'] ?>"><?= $m['status'] ?></span>
              <?php renderLiveMatchTimer($m); ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$matches): ?><p class="text-muted">Aucun match.</p><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php if ($hasLive): ?>
<script>setTimeout(() => location.reload(), 30000);</script>
<?php endif; ?>
</body></html>
