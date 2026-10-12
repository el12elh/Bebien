<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tournament.php';
requireRole('admin');
$pdo = db();

/** Lit et borne les réglages de jeu envoyés par le formulaire. */
function readCategoryForm(): array {
    $clamp = static fn ($value, int $min, int $max): int => max($min, min($max, (int) $value));
    return [
        'nom' => trim((string) ($_POST['nom'] ?? '')),
        'nb_poules_matin' => $clamp($_POST['nb_poules_matin'] ?? 1, 1, 26),
        'nb_poules_aprem' => $clamp($_POST['nb_poules_aprem'] ?? 1, 1, 26),
        'nb_mi_temps' => $clamp($_POST['nb_mi_temps'] ?? 2, 1, 6),
        'duree_mi_temps' => $clamp($_POST['duree_mi_temps'] ?? 7, 1, 90),
        'pause_minutes' => $clamp($_POST['pause_minutes'] ?? 3, 0, 60),
        'coef_classement' => $clamp($_POST['coef_classement'] ?? 1, 1, 10),
    ];
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $f = readCategoryForm();
        if ($f['nom'] === '') {
            $error = 'Le nom de la catégorie est obligatoire.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (nom, nb_poules_matin, nb_poules_aprem, nb_mi_temps, duree_mi_temps, pause_minutes, coef_classement) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$f['nom'], $f['nb_poules_matin'], $f['nb_poules_aprem'], $f['nb_mi_temps'], $f['duree_mi_temps'], $f['pause_minutes'], $f['coef_classement']]);
            header('Location: categories?saved=1');
            exit;
        }
    } elseif ($action === 'update') {
        $f = readCategoryForm();
        $id = (int) ($_POST['id'] ?? 0);
        if ($f['nom'] === '') {
            $error = 'Le nom de la catégorie est obligatoire.';
        } else {
            $stmt = $pdo->prepare('UPDATE categories SET nom=?, nb_poules_matin=?, nb_poules_aprem=?, nb_mi_temps=?, duree_mi_temps=?, pause_minutes=?, coef_classement=? WHERE id=?');
            $stmt->execute([$f['nom'], $f['nb_poules_matin'], $f['nb_poules_aprem'], $f['nb_mi_temps'], $f['duree_mi_temps'], $f['pause_minutes'], $f['coef_classement'], $id]);

            // Option : recalculer les horaires déjà générés avec les nouvelles durées
            if (!empty($_POST['replan'])) {
                try {
                    foreach (['matin', 'aprem'] as $phase) {
                        $check = $pdo->prepare('SELECT COUNT(*) FROM matches WHERE category_id = ? AND phase = ?');
                        $check->execute([$id, $phase]);
                        if ((int) $check->fetchColumn() > 0) {
                            planifierMatchs($id, $phase);
                        }
                    }
                    header('Location: categories?saved=1&replanned=1');
                    exit;
                } catch (Exception $e) {
                    $error = 'Catégorie enregistrée, mais les horaires n\'ont pas pu être recalculés : ' . $e->getMessage();
                }
            } else {
                header('Location: categories?saved=1');
                exit;
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        header('Location: categories');
        exit;
    }
}

if (!$error && !empty($_GET['saved'])) {
    $message = !empty($_GET['replanned'])
        ? 'Catégorie enregistrée et horaires recalculés.'
        : 'Catégorie enregistrée. Les horaires déjà générés ne changent pas : cochez « Recalculer les horaires » pour les mettre à jour.';
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$pageTitle = 'Catégories';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';

/** Champs de réglages de jeu, partagés entre le formulaire d'ajout et de modification. */
function renderCategoryFields(array $c): void {
    ?>
    <div class="col-12 col-md-4">
      <label class="form-label">Nom</label>
      <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($c['nom']) ?>" placeholder="ex : M8" required>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label">Poules du matin</label>
      <input type="number" name="nb_poules_matin" class="form-control" value="<?= (int) $c['nb_poules_matin'] ?>" min="1" required>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label">Poules de l'après-midi</label>
      <input type="number" name="nb_poules_aprem" class="form-control" value="<?= (int) $c['nb_poules_aprem'] ?>" min="1" required>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Mi-temps par match</label>
      <input type="number" name="nb_mi_temps" class="form-control js-timing" value="<?= (int) $c['nb_mi_temps'] ?>" min="1" max="6" required>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Minutes par mi-temps</label>
      <input type="number" name="duree_mi_temps" class="form-control js-timing" value="<?= (int) $c['duree_mi_temps'] ?>" min="1" max="90" required>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Pause entre matchs (min)</label>
      <input type="number" name="pause_minutes" class="form-control js-timing" value="<?= (int) $c['pause_minutes'] ?>" min="0" max="60" required>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Coef. classement final</label>
      <input type="number" name="coef_classement" class="form-control" value="<?= (int) $c['coef_classement'] ?>" min="1" max="10" required>
    </div>
    <div class="col-12">
      <div class="form-text js-timing-summary"></div>
    </div>
    <?php
}
?>
<div class="container">
  <h2 class="page-title">Catégories</h2>
  <p class="text-muted mb-4">Chaque catégorie a ses propres règles de jeu : elles servent au minuteur, au calcul des horaires et au classement final des clubs.</p>

  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Ajouter une catégorie</h5>
      <form method="post" class="row g-3 js-timing-form">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <?php renderCategoryFields(['nom' => '', 'nb_poules_matin' => 4, 'nb_poules_aprem' => 3, 'nb_mi_temps' => 2, 'duree_mi_temps' => 7, 'pause_minutes' => 3, 'coef_classement' => 1]); ?>
        <div class="col-12">
          <button class="btn btn-success">Ajouter la catégorie</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <?php foreach ($categories as $c): ?>
      <div class="col-12 col-xl-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-5"><?= htmlspecialchars($c['nom']) ?></span>
            <span class="text-muted small">
              <?= (int) $c['nb_mi_temps'] ?> × <?= (int) $c['duree_mi_temps'] ?> min · coef ×<?= (int) $c['coef_classement'] ?>
            </span>
          </div>
          <div class="card-body">
            <form method="post" class="row g-3 js-timing-form">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <?php renderCategoryFields($c); ?>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="replan" value="1" id="replan<?= (int) $c['id'] ?>">
                  <label class="form-check-label" for="replan<?= (int) $c['id'] ?>">Recalculer les horaires des matchs déjà générés</label>
                </div>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-success" type="submit"><i class="bi bi-check2"></i> Enregistrer</button>
              </div>
            </form>
            <form method="post" class="mt-2" onsubmit="return confirm('Supprimer cette catégorie et toutes ses équipes, poules et matchs ?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Supprimer la catégorie</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
// Résumé en direct : durée d'un match et créneau complet
document.querySelectorAll('.js-timing-form').forEach((form) => {
  const summary = form.querySelector('.js-timing-summary');
  const val = (name) => parseInt(form.querySelector('[name="' + name + '"]').value, 10) || 0;
  const refresh = () => {
    const match = val('nb_mi_temps') * val('duree_mi_temps');
    summary.textContent = 'Un match dure ' + match + ' min ; un créneau complet (avec la pause) dure ' + (match + val('pause_minutes')) + ' min.';
  };
  form.querySelectorAll('.js-timing').forEach((input) => input.addEventListener('input', refresh));
  refresh();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
