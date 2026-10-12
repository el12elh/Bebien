<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && !empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
            $dir = __DIR__ . '/../uploads/sponsors';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = uniqid('sponsor_') . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $name);
            // Plus de niveau (or / argent / bronze) : un partenaire = un nom, un logo, un lien
            $stmt = $pdo->prepare('INSERT INTO sponsors (nom, logo_path, lien_url, ordre_affichage) VALUES (?,?,?,?)');
            $stmt->execute([trim($_POST['nom']), 'uploads/sponsors/' . $name, $_POST['lien_url'] ?: null, (int)$_POST['ordre_affichage']]);
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM sponsors WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE sponsors SET actif = 1 - actif WHERE id=?')->execute([(int)$_POST['id']]);
    }
    header('Location: sponsors');
    exit;
}

$sponsors = $pdo->query('SELECT * FROM sponsors ORDER BY ordre_affichage, id')->fetchAll();
$pageTitle = 'Partenaires & Institutions';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="page-title">Partenaires &amp; Institutions</h2>
  <p class="text-muted">Les logos actifs s'affichent en bandeau défilant sur l'espace public et sur la page d'accueil, dans l'ordre d'affichage (le plus petit en premier).</p>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Ajouter un partenaire ou une institution</h5>
      <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-3">
          <label class="form-label">Nom</label>
          <input type="text" name="nom" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Logo</label>
          <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Lien (site web)</label>
          <input type="url" name="lien_url" class="form-control" placeholder="https://...">
        </div>
        <div class="col-md-2">
          <label class="form-label">Ordre</label>
          <input type="number" name="ordre_affichage" class="form-control" value="0">
        </div>
        <div class="col-12">
          <button class="btn btn-success">Ajouter</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!$sponsors): ?>
    <p class="text-muted">Aucun partenaire pour le moment.</p>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($sponsors as $s): ?>
      <div class="col-6 col-md-3">
        <div class="card text-center h-100">
          <img src="../<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>" class="card-img-top p-3" style="height:100px;object-fit:contain">
          <div class="card-body">
            <h6><?= htmlspecialchars($s['nom']) ?></h6>
            <span class="badge <?= $s['actif'] ? 'bg-success' : 'bg-danger' ?>"><?= $s['actif'] ? 'Actif' : 'Masqué' ?></span>
            <div class="d-flex gap-1 mt-2">
              <form method="post" class="flex-fill"><input type="hidden" name="action" value="toggle"><input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-secondary w-100"><?= $s['actif'] ? 'Masquer' : 'Afficher' ?></button></form>
              <form method="post" class="flex-fill" onsubmit="return confirm('Supprimer ce partenaire ?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100">Supprimer</button></form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
