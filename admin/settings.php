<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

$fields = [
    'nom_tournoi' => 'Nom du tournoi',
    'date_tournoi' => 'Date du tournoi (YYYY-MM-DD)',
    'lieu_tournoi' => 'Lieu du tournoi',
    'points_victoire' => 'Points pour une victoire',
    'points_nul' => 'Points pour un match nul',
    'points_defaite' => 'Points pour une défaite',
    'points_forfait' => 'Points en cas de forfait',
    'bonus_offensif_actif' => 'Bonus offensif actif (1 ou 0)',
    'bonus_offensif_seuil_essais' => "Écart d'essais requis pour le bonus offensif",
    'bonus_offensif_points' => 'Points du bonus offensif',
    'bonus_defensif_actif' => 'Bonus défensif actif (1 ou 0)',
    'bonus_defensif_seuil_ecart' => "Écart de points max pour le bonus défensif (défaite)",
    'bonus_defensif_points' => 'Points du bonus défensif',
    'ordre_departage' => 'Ordre de départage (points,points_cartons,essais_pour,diff_essais,tirage_sort)',
    'duree_match_minutes' => 'Durée d\'un match (minutes)',
    'duree_periode_minutes' => 'Durée d\'une mi-temps / période (minutes)',
    'nb_mi_temps' => 'Nombre de mi-temps / périodes',
    'pause_entre_matchs_minutes' => 'Pause entre 2 matchs sur le même terrain (minutes)',
    'heure_debut_matin' => 'Heure de début — phase du matin (HH:MM)',
    'heure_debut_aprem' => 'Heure de début — phase de l\'après-midi (HH:MM)',
];

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    foreach ($fields as $key => $label) {
        if (isset($_POST[$key])) setSetting($key, $_POST[$key]);
    }
    $message = 'Paramètres enregistrés.';
}

$pageTitle = 'Paramètres';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Paramètres du tournoi</h2>
  <div class="alert alert-warning">
    <strong>Important :</strong> ajustez le barème de points et l'ordre de départage ci-dessous en stricte conformité
    avec votre dossier sportif officiel (fédération / comité départemental). Les valeurs par défaut sont indicatives.
  </div>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($fields as $key => $label): ?>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($label) ?></label>
              <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars(getSetting($key, '')) ?>" class="form-control">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <button class="btn btn-success big-btn mt-3">Enregistrer les paramètres</button>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
