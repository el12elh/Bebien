<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tournament.php';
requireRole('admin');

$pdo = db();
$nbTeams = $pdo->query('SELECT COUNT(*) c FROM teams')->fetch()['c'];
$nbMatches = $pdo->query('SELECT COUNT(*) c FROM matches')->fetch()['c'];
$nbterminés = $pdo->query("SELECT COUNT(*) c FROM matches WHERE status='Terminé'")->fetch()['c'];
$nbEnCours = $pdo->query("SELECT COUNT(*) c FROM matches WHERE status='Live'")->fetch()['c'];
$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Tableau de bord</h2>
  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-center p-3"><div class="fs-2 fw-bold"><?= $nbTeams ?></div><div class="text-muted">Équipes engagées</div></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><div class="fs-2 fw-bold text-primary"><?= $nbMatches ?></div><div class="text-muted">Matchs planifiés</div></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><div class="fs-2 fw-bold text-warning"><?= $nbEnCours ?></div><div class="text-muted">Matchs en cours</div></div></div>
    <div class="col-md-3"><div class="card text-center p-3 shadow-none bg-transparent"><div class="fs-2 fw-bold" style="color: var(--site-primary);"><?= $nbterminés ?></div><div class="text-muted">Matchs terminés</div></div></div>
  </div>

  <div class="row g-3">
    <?php foreach ($categories as $cat): ?>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Catégorie <?= htmlspecialchars($cat['nom']) ?></h5>
            <p class="card-text text-muted small">
              Poules matin configurées : <?= $cat['nb_poules_matin'] ?><br>
              Poules après-midi configurées : <?= $cat['nb_poules_aprem'] ?>
            </p>
            <a href="pools_draw.php?category_id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-success">Gérer les poules</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-info mt-4">
    <strong>Étapes recommandées le jour J :</strong>
    <ol class="mb-0">
      <li>Vérifier les équipes engagées</li>
      <li>Tirer au sort les poules du matin — le planning est généré automatiquement</li>
      <li>Saisir les scores au fil des matchs via l'interface table de marque</li>
      <li>Corriger, si besoin, le résultat d'un match dans « Matchs & Scores »</li>
      <li>Vérifier les classements après les poules du matin</li>
      <li>Générer les poules de l'après-midi une fois les poules du matin terminées</li>
      <li>Suivre le classement général sur l'espace public</li>
    </ol>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>