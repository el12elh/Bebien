<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = (int) ($_GET['category_id'] ?? ($categories[0]['id'] ?? 0));
$phase = $_GET['phase'] ?? null;

// Phase par défaut : matin avant 13h le jour du tournoi, après-midi ensuite
if ($phase !== 'matin' && $phase !== 'aprem') {
  $phase = 'matin';
  $dateTournoi = getSetting('date_tournoi', '');
  if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    $phase = new DateTime() > $tournamentDeadline ? 'aprem' : 'matin';
  }
}

$pools = [];
if ($categoryId) {
  $stmt = $pdo->prepare('SELECT id, nom FROM pools WHERE category_id = ? AND phase = ? ORDER BY ordre_niveau, id');
  $stmt->execute([$categoryId, $phase]);
  $pools = $stmt->fetchAll();

  $stmtTeams = $pdo->prepare(
    'SELECT t.nom_equipe, cl.nom_club, cl.logo_path,
            (SELECT COUNT(*) FROM teams t2 WHERE t2.club_id = t.club_id AND t2.category_id = t.category_id) AS nb_equipes_club
     FROM pool_teams pt
     JOIN teams t ON t.id = pt.team_id
     JOIN clubs cl ON cl.id = t.club_id
     WHERE pt.pool_id = ?
     ORDER BY cl.nom_club, t.nom_equipe'
  );
  $stmtMatches = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'Terminé') AS done FROM matches WHERE pool_id = ?");

  foreach ($pools as &$pool) {
    $stmtTeams->execute([$pool['id']]);
    $pool['teams'] = $stmtTeams->fetchAll();
    $stmtMatches->execute([$pool['id']]);
    $counts = $stmtMatches->fetch();
    $pool['matchs_total'] = (int) ($counts['total'] ?? 0);
    $pool['matchs_termines'] = (int) ($counts['done'] ?? 0);
  }
  unset($pool);
}

$pageTitle = 'Poules';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <h2 class="page-title">Poules</h2>

  <form method="get" class="row g-2 mb-4">
    <div class="col-md-3">
      <select name="category_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="phase" class="form-select" onchange="this.form.submit()">
        <option value="matin" <?= $phase === 'matin' ? 'selected' : '' ?>>Matin</option>
        <option value="aprem" <?= $phase === 'aprem' ? 'selected' : '' ?>>Après-midi</option>
      </select>
    </div>
  </form>

  <?php if (!$pools): ?>
    <p class="text-muted">Les poules ne sont pas encore connues pour cette phase.</p>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($pools as $pool): ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span class="fw-bold"><?= htmlspecialchars($pool['nom']) ?></span>
              <span class="text-muted small"><?= count($pool['teams']) ?> équipes</span>
            </div>
            <ul class="list-group list-group-flush">
              <?php foreach ($pool['teams'] as $team): ?>
                <li class="list-group-item d-flex align-items-center gap-2">
                  <?php if (!empty($team['logo_path'])): ?>
                    <img src="/<?= htmlspecialchars(ltrim($team['logo_path'], '/')) ?>" alt="" class="club-logo">
                  <?php endif; ?>
                  <span class="fw-semibold"><?= htmlspecialchars($team['nom_club']) ?></span>
                  <?php if ((int) $team['nb_equipes_club'] > 1 && $team['nom_equipe'] !== ''): ?>
                    <span class="text-muted small"><?= htmlspecialchars($team['nom_equipe']) ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if ($pool['matchs_total'] > 0): ?>
              <div class="card-footer small text-muted">
                <?= $pool['matchs_termines'] ?> / <?= $pool['matchs_total'] ?> matchs terminés
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>