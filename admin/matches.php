<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_score') {
    requireSubmitToken();
    $id = (int) $_POST['match_id'];
    $s1 = $_POST['score1'] === '' ? null : max(0, (int)$_POST['score1']);
    $s2 = $_POST['score2'] === '' ? null : max(0, (int)$_POST['score2']);
    $status = $_POST['status'];
    $j1 = $_POST['cartons_jaunes1'] === '' ? 0 : max(0, min(5, (int)$_POST['cartons_jaunes1']));
    $j2 = $_POST['cartons_jaunes2'] === '' ? 0 : max(0, min(5, (int)$_POST['cartons_jaunes2']));
    $r1 = $_POST['cartons_rouges1'] === '' ? 0 : max(0, min(5, (int)$_POST['cartons_rouges1']));
    $r2 = $_POST['cartons_rouges2'] === '' ? 0 : max(0, min(5, (int)$_POST['cartons_rouges2']));
    $stmt = $pdo->prepare('UPDATE matches SET score1=?, score2=?, status=?, cartons_jaunes1=?, cartons_jaunes2=?, cartons_rouges1=?, cartons_rouges2=?, updated_by=? WHERE id=?');
    $stmt->execute([$s1, $s2, $status, $j1, $j2, $r1, $r2, 'admin:' . (currentUser()['username'] ?? ''), $id]);
    header('Location: matches?' . http_build_query($_GET));
    exit;
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = $_GET['category_id'] ?? '';
$phase = $_GET['phase'] ?? '';
$status = $_GET['status'] ?? '';
$poolId = $_GET['pool_id'] ?? '';

// Prepare pools list (filtered by category when provided)
$poolQuery = 'SELECT p.* FROM pools p';
$poolParams = [];
if ($categoryId !== '') {
  $poolQuery .= ' WHERE p.category_id = ? and p.phase = ?';
  $poolParams[] = $categoryId;
  $poolParams[] = $phase;
}
$poolQuery .= ' ORDER BY p.nom';
$pools = $pdo->prepare($poolQuery);
$pools->execute($poolParams);
$pools = $pools->fetchAll();

$sql = 'SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
               c1.nom_club c1, c1.logo_path AS c1_logo_path,
               CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS e1,
               c2.nom_club c2, c2.logo_path AS c2_logo_path,
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
        WHERE 1=1
        ';
$params = [];
if ($categoryId !== '') { $sql .= ' AND m.category_id = ?'; $params[] = $categoryId; }
if ($phase !== '') { $sql .= ' AND m.phase = ?'; $params[] = $phase; }
if ($status !== '') { $sql .= ' AND m.status = ?'; $params[] = $status; }
if ($poolId !== '') { $sql .= ' AND m.pool_id = ?'; $params[] = $poolId; }
$sql .= ' ORDER BY m.scheduled_at, m.category_id, m.id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

$pageTitle = 'Matchs & Saisie des scores';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Matchs & Saisie des scores</h2>

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
      <div class="col-md-3">
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <option value="Programmé" <?= $status === 'Programmé' ? 'selected' : '' ?>>Programmé</option>
        <option value="Live" <?= $status === 'Live' ? 'selected' : '' ?>>Live</option>
        <option value="Terminé" <?= $status === 'Terminé' ? 'selected' : '' ?>>Terminé</option>
      </select>
    </div>
  </form>

  <div class="row g-3">
  <?php foreach ($matches as $m): ?>
    <div class="col-md-6">
      <div class="card card-match <?= $m['status'] ?>">
        <div class="card-body">
          <div class="d-flex justify-content-between small text-muted mb-1">
            <span><?= htmlspecialchars($m['cat_nom']) ?> — <?= htmlspecialchars($m['pool_nom']) ?> (<?= $m['phase'] === 'matin' ? 'Matin' : 'Après-midi' ?>)</span>
            <span><?= $m['field_nom'] ? htmlspecialchars($m['field_nom']) : '—' ?> <?= $m['scheduled_at'] ? ' • ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?></span>
          </div>
          <form method="post" class="row g-2 align-items-center">
                <input type="hidden" name="action" value="save_score">
                <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">

                <!-- Équipe 1 -->
                <div class="col text-end text-nowrap">
                    <?php if (!empty($m['c1_logo_path'])): ?>
                        <img
                            src="../<?= htmlspecialchars($m['c1_logo_path']) ?>"
                            alt="Logo <?= htmlspecialchars($m['c1']) ?>"
                            style="height:24px; width:auto; object-fit:contain; vertical-align:middle;"
                        >
                    <?php endif; ?>

                    <span class="d-inline-block align-middle"><?= htmlspecialchars($m['c1']) ?></span>

                    <?php if (!empty($m['e1'])): ?>
                        <small class="text-muted ms-2"><?= htmlspecialchars($m['e1']) ?></small>
                    <?php endif; ?>
                    <div class="mt-1 text-muted small">
                        <span class="card-counter">
                          <span class="card-icon card-icon-yellow" title="Carton jaune"></span>
                          <input type="number" name="cartons_jaunes1" value="<?= htmlspecialchars($m['cartons_jaunes1'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm d-inline-block" style="width:3.5rem;">
                        </span>
                        <span class="card-counter">
                          <span class="card-icon card-icon-red" title="Carton rouge"></span>
                          <input type="number" name="cartons_rouges1" value="<?= htmlspecialchars($m['cartons_rouges1'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm d-inline-block" style="width:3.5rem;">
                        </span>
                    </div>
                </div>

                <!-- Score 1 -->
                <div class="col-auto">
                    <input
                        type="number"
                        name="score1"
                        value="<?= htmlspecialchars($m['score1'] ?? '') ?>"
                        class="form-control text-center"
                        style="width:55px;"
                        min="0"
                    >
                </div>

                <!-- Séparateur -->
                <div class="col-auto px-0">
                    -
                </div>

              <!-- Score 2 -->
              <div class="col-auto">
                  <input
                      type="number"
                      name="score2"
                      value="<?= htmlspecialchars($m['score2'] ?? '') ?>"
                      class="form-control text-center"
                      style="width:55px;"
                      min="0"
                  >
              </div>

              <!-- Équipe 2 -->
              <div class="col text-nowrap">
                  <?php if (!empty($m['c2_logo_path'])): ?>
                      <img
                          src="../<?= htmlspecialchars($m['c2_logo_path']) ?>"
                          alt="Logo <?= htmlspecialchars($m['c2']) ?>"
                          style="height:24px; width:auto; object-fit:contain; vertical-align:middle;"
                      >
                  <?php endif; ?>

                  <span class="d-inline-block align-middle"><?= htmlspecialchars($m['c2']) ?></span>

                  <?php if (!empty($m['e2'])): ?>
                      <small class="text-muted ms-2"><?= htmlspecialchars($m['e2']) ?></small>
                  <?php endif; ?>
                  <div class="mt-1 text-muted small">
                      <span class="card-counter">
                        <span class="card-icon card-icon-yellow" title="Carton jaune"></span>
                        <input type="number" name="cartons_jaunes2" value="<?= htmlspecialchars($m['cartons_jaunes2'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm d-inline-block" style="width:3.5rem;">
                      </span>
                      <span class="card-counter">
                        <span class="card-icon card-icon-red" title="Carton rouge"></span>
                        <input type="number" name="cartons_rouges2" value="<?= htmlspecialchars($m['cartons_rouges2'] ?? 0) ?>" min="0" max="5" class="form-control form-control-sm d-inline-block" style="width:3.5rem;">
                      </span>
                  </div>
              </div>

              <!-- Statut + bouton -->
              <div class="col-12 d-flex gap-2 mt-2">
                  <select name="status" class="form-select form-select-sm w-auto">
                      <option value="Programmé" <?= $m['status'] === 'Programmé' ? 'selected' : '' ?>>Programmé</option>
                      <option value="Live" <?= $m['status'] === 'Live' ? 'selected' : '' ?>>Live</option>
                      <option value="Terminé" <?= $m['status'] === 'Terminé' ? 'selected' : '' ?>>Terminé</option>
                  </select>

                  <button class="btn btn-sm btn-success">
                      Enregistrer
                  </button>
              </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$matches): ?><p class="text-muted">Aucun match trouvé pour ces filtres.</p><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>