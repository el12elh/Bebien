<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tournament.php';
requireRole('admin');
$pdo = db();

$message = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_field') {
        $categoryId = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $pdo->prepare('INSERT INTO fields (nom, category_id) VALUES (?, ?)')->execute([$_POST['nom'], $categoryId]);
    } elseif ($action === 'toggle_field') {
        $pdo->prepare('UPDATE fields SET actif = 1 - actif WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'delete_field') {
        $pdo->prepare('DELETE FROM fields WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'replan') {
        try {
            $catId = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
            planifierMatchs($catId, $_POST['phase']);
            $message = 'Planning régénéré avec succès.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    if (!$message && !$error) { header('Location: planning'); exit; }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$fields = $pdo->query('SELECT f.*, c.nom AS category_name FROM fields f LEFT JOIN categories c ON c.id = f.category_id ORDER BY f.id')->fetchAll();

$stmt = $pdo->query('SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
                      c1.nom_club c1, c1.logo_path AS c1_logo_path,
                      CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS team1_name,
                      c2.nom_club c2, c2.logo_path AS c2_logo_path,
                      CASE WHEN club_team_counts2.team_count > 1 THEN t2.nom_equipe ELSE "" END AS team2_name
                      FROM matches m
                      JOIN categories c ON c.id = m.category_id
                      JOIN pools p ON p.id = m.pool_id
                      LEFT JOIN fields f ON f.id = m.field_id
                      JOIN teams t1 ON t1.id = m.team1_id
                      JOIN teams t2 ON t2.id = m.team2_id
                      JOIN clubs c1 ON c1.id = t1.club_id
                      JOIN clubs c2 ON c2.id = t2.club_id
                      JOIN (
                          SELECT club_id, category_id, COUNT(*) AS team_count
                          FROM teams
                          GROUP BY club_id, category_id
                      ) club_team_counts1
                          ON club_team_counts1.club_id = t1.club_id
                          AND club_team_counts1.category_id = t1.category_id
                      JOIN (
                          SELECT club_id, category_id, COUNT(*) AS team_count
                          FROM teams
                          GROUP BY club_id, category_id
                      ) club_team_counts2
                          ON club_team_counts2.club_id = t2.club_id
                          AND club_team_counts2.category_id = t2.category_id
                      ORDER BY m.scheduled_at, c.id, m.id');
$matches = $stmt->fetchAll();

$pageTitle = 'Planning & Terrains';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Planning & Terrains</h2>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-body">
          <h5>Terrains</h5>
          <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="action" value="add_field">
            <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
            <div class="col-6">
              <input type="text" name="nom" class="form-control" placeholder="Nom du terrain" required>
            </div>
            <div class="col-6">
              <select name="category_id" class="form-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <button class="btn btn-success">Ajouter</button>
            </div>
          </form>
          <ul class="list-group">
            <?php foreach ($fields as $f): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <?= htmlspecialchars($f['nom']) ?>
                  <?php if (!empty($f['category_name'])): ?>
                    <small class="text-muted">(<?= htmlspecialchars($f['category_name']) ?>)</small>
                  <?php endif; ?>
                </div>
                <span>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="toggle_field">
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button class="btn btn-sm <?= $f['actif'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"><?= $f['actif'] ? 'Désactiver' : 'Activer' ?></button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce terrain ?');">
                    <input type="hidden" name="action" value="delete_field">
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">X</button>
                  </form>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <h5>Calendrier complet</h5>
      <div class="table-responsive">
      <table class="table table-sm table-bordered bg-white">
        <thead><tr><th>Heure</th><th>Terrain</th><th>Cat</th><th>Poule</th><th>Match</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($matches as $m): ?>
            <tr>
              <td><?= $m['scheduled_at'] ? date('H:i', strtotime($m['scheduled_at'])) : '—' ?></td>
              <td><?= htmlspecialchars($m['field_nom'] ?? '—') ?></td>
              <td><?= htmlspecialchars($m['cat_nom']) ?></td>
              <td><?= htmlspecialchars($m['pool_nom']) ?></td>
              <td>
            <?php if (!empty($m['c1_logo_path'])): ?>
              <img src="../<?= htmlspecialchars($m['c1_logo_path']) ?>" alt="Logo <?= htmlspecialchars($m['c1']) ?>" style="height:20px; width:auto; object-fit:contain; margin-right:4px; vertical-align:middle;">
            <?php endif; ?>
            <?= htmlspecialchars($m['c1']) ?><small class="text-muted"><?= htmlspecialchars($m['team1_name']) ?></small>
            vs
            <?php if (!empty($m['c2_logo_path'])): ?>
              <img src="../<?= htmlspecialchars($m['c2_logo_path']) ?>" alt="Logo <?= htmlspecialchars($m['c2']) ?>" style="height:20px; width:auto; object-fit:contain; margin:0 4px; vertical-align:middle;">
            <?php endif; ?>
            <?= htmlspecialchars($m['c2']) ?><small class="text-muted"><?= htmlspecialchars($m['team2_name']) ?></small>
          </td>
              <td><span class="badge badge-status-<?= $m['status'] ?>"><?= $m['status'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
