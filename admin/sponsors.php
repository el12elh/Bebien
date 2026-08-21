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
            $stmt = $pdo->prepare('INSERT INTO sponsors (nom, logo_path, lien_url, niveau, ordre_affichage) VALUES (?,?,?,?,?)');
            $stmt->execute([$_POST['nom'], 'uploads/sponsors/' . $name, $_POST['lien_url'] ?: null, $_POST['niveau'], (int)$_POST['ordre_affichage']]);
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM sponsors WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE sponsors SET actif = 1 - actif WHERE id=?')->execute([(int)$_POST['id']]);
    }
    header('Location: sponsors');
    exit;
}

$sponsors = $pdo->query('SELECT * FROM sponsors ORDER BY niveau, ordre_affichage')->fetchAll();
$pageTitle = 'Sponsors';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Sponsors & Partenaires</h2>
  <p class="text-muted">Les logos actifs s'affichent en bandeau défilant sur l'espace public et sur la page d'accueil.</p>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Ajouter un sponsor</h5>
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-3">
          <label class="form-label">Nom</label>
          <input type="text" name="nom" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Logo</label>
          <input type="file" name="logo" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Lien (site web)</label>
          <input type="url" name="lien_url" class="form-control" placeholder="https://...">
        </div>
        <div class="col-md-2">
          <label class="form-label">Niveau</label>
          <select name="niveau" class="form-select">
            <option value="or">Or</option>
            <option value="argent">Argent</option>
            <option value="bronze">Bronze</option>
            <option value="partenaire" selected>Partenaire</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label">Ordre</label>
          <input type="number" name="ordre_affichage" class="form-control" value="0">
        </div>
        <div class="col-md-12 mt-2">
          <button class="btn btn-success">Ajouter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($sponsors as $s): ?>
      <div class="col-md-3">
        <div class="card text-center">
          <img src="../<?= htmlspecialchars($s['logo_path']) ?>" class="card-img-top p-3" style="height:100px;object-fit:contain">
          <div class="card-body">
            <h6><?= htmlspecialchars($s['nom']) ?></h6>
            <span class="badge <?= 
                $s['niveau'] === 'or' ? 'badge-or' : 
                ($s['niveau'] === 'argent' ? 'badge-argent' : 
                ($s['niveau'] === 'bronze' ? 'badge-bronze' : 'bg-secondary'))
            ?>">
                <?= $s['niveau'] !== 'partenaire' ? 'Sponsor - ' . ucfirst(htmlspecialchars($s['niveau'])) : 'Partenaire' ?>
            </span>
            <span class="badge <?= $s['actif'] ? 'bg-success' : 'bg-danger' ?>"><?= $s['actif'] ? 'Actif' : 'Masqué' ?></span>
            <div class="d-flex gap-1 mt-2">
              <form method="post" class="flex-fill"><input type="hidden" name="action" value="toggle"><input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-secondary w-100"><?= $s['actif'] ? 'Désactiver' : 'Activer' ?></button></form>
              <form method="post" class="flex-fill" onsubmit="return confirm('Supprimer ce sponsor ?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100">Supprimer</button></form>
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
