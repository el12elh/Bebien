<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $pdo->prepare('INSERT INTO categories (nom, nb_poules_matin, nb_poules_aprem) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['nom'], (int)$_POST['nb_poules_matin'], (int)$_POST['nb_poules_aprem']]);
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE categories SET nom=?, nb_poules_matin=?, nb_poules_aprem=? WHERE id=?');
        $stmt->execute([$_POST['nom'], (int)$_POST['nb_poules_matin'], (int)$_POST['nb_poules_aprem'], (int)$_POST['id']]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int)$_POST['id']]);
    }
    header('Location: categories');
    exit;
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$pageTitle = 'Catégories';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Catégories</h2>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Ajouter une catégorie</h5>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-3">
          <label class="form-label">Nom</label>
          <input type="text" name="nom" class="form-control" placeholder="ex: M8" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nb de poules du matin</label>
          <input type="number" name="nb_poules_matin" class="form-control" value="4" min="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nb de poules de l'après-midi</label>
          <input type="number" name="nb_poules_aprem" class="form-control" value="3" min="1" required>
        </div>
        <div class="col-md-3">
          <button class="btn btn-success w-100">Ajouter</button>
        </div>
      </form>
    </div>
  </div>

  <table class="table table-bordered bg-white">
    <thead><tr><th>Nom</th><th>Poules matin</th><th>Poules après-midi</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <tr>
          <td><input type="text" name="nom" value="<?= htmlspecialchars($c['nom']) ?>" class="form-control form-control-sm"></td>
          <td><input type="number" name="nb_poules_matin" value="<?= $c['nb_poules_matin'] ?>" class="form-control form-control-sm" min="1"></td>
          <td><input type="number" name="nb_poules_aprem" value="<?= $c['nb_poules_aprem'] ?>" class="form-control form-control-sm" min="1"></td>
          <td>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary icon-btn" type="submit" title="Modifier la catégorie" aria-label="Modifier la catégorie">
                <i class="bi bi-pencil-square"></i>
              </button>
            </div>
      </form>
      <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cette catégorie et toutes ses équipes/poules/matchs ?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-sm btn-outline-danger icon-btn" type="submit" title="Supprimer la catégorie" aria-label="Supprimer la catégorie">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </form>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>