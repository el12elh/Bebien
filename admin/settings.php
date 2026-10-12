<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

/**
 * Les durées de mi-temps, le nombre de mi-temps et la pause entre matchs
 * se règlent désormais par catégorie (menu « Catégories »).
 */
$sections = [
    'Tournoi' => [
        'nom_tournoi' => 'Nom du tournoi',
        'edition_tournoi' => 'Édition du tournoi (ex : 26)',
        'date_tournoi' => 'Date du tournoi (AAAA-MM-JJ)',
        'lieu_tournoi' => 'Lieu du tournoi',
        'ville_tournoi' => 'Ville du tournoi',
        'heure_debut_matin' => 'Heure de début — matin (HH:MM)',
        'heure_debut_aprem' => 'Heure de début — après-midi (HH:MM)',
    ],
    'Barème des poules' => [
        'points_victoire' => 'Points pour une victoire',
        'points_nul' => 'Points pour un match nul',
        'points_defaite' => 'Points pour une défaite',
        'points_forfait' => 'Points en cas de forfait',
        'bonus_offensif_actif' => 'Bonus offensif actif (1 ou 0)',
        'bonus_offensif_seuil_essais' => "Écart d'essais requis pour le bonus offensif",
        'bonus_offensif_points' => 'Points du bonus offensif',
        'bonus_defensif_actif' => 'Bonus défensif actif (1 ou 0)',
        'bonus_defensif_seuil_ecart' => 'Écart de points max pour le bonus défensif (défaite)',
        'bonus_defensif_points' => 'Points du bonus défensif',
        'ordre_departage' => 'Ordre de départage (points,points_cartons,essais_pour,diff_essais,tirage_sort)',
    ],
];

// Liste à plat des clés enregistrables (le mode du classement final est un menu déroulant à part)
$editableKeys = [];
foreach ($sections as $fields) {
    foreach ($fields as $key => $label) {
        $editableKeys[] = $key;
    }
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    foreach ($editableKeys as $key) {
        if (isset($_POST[$key])) {
            setSetting($key, trim((string) $_POST[$key]));
        }
    }
    if (isset($_POST['classement_final_mode'])) {
        setSetting('classement_final_mode', $_POST['classement_final_mode'] === 'points' ? 'points' : 'rang');
    }
    $message = 'Paramètres enregistrés.';
}

$pageTitle = 'Paramètres';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="page-title">Paramètres du tournoi</h2>
  <div class="alert alert-warning">
    <strong>Important :</strong> ajustez le barème de points et l'ordre de départage ci-dessous en stricte conformité
    avec votre dossier sportif officiel (fédération / comité départemental). Les valeurs par défaut sont indicatives.
  </div>
  <div class="alert alert-info">
    Le nombre de mi-temps, la durée d'une mi-temps et la pause entre les matchs se règlent maintenant
    <strong>par catégorie</strong>, dans <a href="categories">Catégories</a>.
  </div>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">

    <?php foreach ($sections as $title => $fields): ?>
      <div class="card mb-4">
        <div class="card-header fw-bold"><?= htmlspecialchars($title) ?></div>
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
    <?php endforeach; ?>

    <div class="card mb-4">
      <div class="card-header fw-bold">Classement final des clubs</div>
      <div class="card-body">
        <label class="form-label">Méthode de calcul (résultats de l'après-midi, coefficient de chaque catégorie)</label>
        <?php $mode = getSetting('classement_final_mode', 'rang') === 'points' ? 'points' : 'rang'; ?>
        <select name="classement_final_mode" class="form-select">
          <option value="rang" <?= $mode === 'rang' ? 'selected' : '' ?>>Place finale de chaque équipe dans sa catégorie (1er = le plus de points)</option>
          <option value="points" <?= $mode === 'points' ? 'selected' : '' ?>>Points de classement obtenus l'après-midi</option>
        </select>
        <div class="form-text">
          Dans les deux cas, les points sont multipliés par le coefficient de la catégorie (réglé dans Catégories).
          La place finale est déterminée par le niveau du tableau de l'après-midi, puis par le rang dans ce tableau.
        </div>
      </div>
    </div>

    <button class="btn btn-success big-btn">Enregistrer les paramètres</button>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
