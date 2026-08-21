<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tournament.php';

requireRole('admin');
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = (int) ($_GET['category_id'] ?? ($categories[0]['id'] ?? 0));

$message = null;
$error = null;

// parseRankList(), formatRankList(), calculerRangsParDefaut() et validerBucketsRangs()
// vivent désormais dans includes/tournament.php (réutilisables, testables, partagées).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $categoryId = (int) $_POST['category_id'];

    try {
        if ($_POST['action'] === 'draw') {
            $nbPoules = (int) $_POST['nb_poules'];

            tirerPoulesMatin($categoryId, $nbPoules);

            $pdo->prepare(
                'UPDATE categories SET nb_poules_matin = ? WHERE id = ?'
            )->execute([$nbPoules, $categoryId]);

            $message = "Tirage effectué : $nbPoules poule(s) créée(s), calendrier et terrains générés automatiquement.";

        } elseif ($_POST['action'] === 'delete_all') {
            $pdo->prepare(
                "DELETE FROM pools WHERE category_id = ? AND phase = 'matin'"
            )->execute([$categoryId]);

            $message = 'Toutes les poules du matin et leurs matchs ont été supprimés pour cette catégorie.';
        } elseif ($_POST['action'] === 'generate') {
            $catStmt = $pdo->prepare('SELECT nb_poules_aprem FROM categories WHERE id = ?');
            $catStmt->execute([$categoryId]);
            $nbPoolsAprem = (int) ($catStmt->fetchColumn() ?: 3);
            $nbPoolsAprem = max(1, $nbPoolsAprem);

            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE category_id = ?');
            $stmtCount->execute([$categoryId]);
            $nbTeamsCat = (int) $stmtCount->fetchColumn();

            $baseSize = intdiv($nbTeamsCat, $nbPoolsAprem);
            $extraTeams = $nbTeamsCat % $nbPoolsAprem;

            $buckets = [];
            $cursor = 1;
            $names = $_POST['nom'] ?? [];
            for ($i = 0; $i < $nbPoolsAprem; $i++) {
                $size = $baseSize + ($i < $extraTeams ? 1 : 0);
                $start = $cursor;
                $end = $size > 0 ? $cursor + $size - 1 : $cursor - 1;
                $name = trim((string) ($names[$i] ?? ''));
                if ($name === '') {
                    $name = match ($i) {
                        0 => 'Zone 1',
                        1 => 'Zone 2',
                        default => 'Zone 3',
                    };
                }
                $buckets[] = [
                    'nom' => $name,
                    'ranks' => range($start, $end),
                ];
                $cursor = $end + 1;
            }

            $result = genererPoulesApresMidiGlobal($categoryId, $buckets);
            $message = 'Poules de l\'après-midi générées en fonction du classement général.';
            if (!empty($result['warnings'])) {
                $message .= ' ' . implode(' ', $result['warnings']);
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$currentCat = null;

foreach ($categories as $c) {
    if ((int) $c['id'] === $categoryId) {
        $currentCat = $c;
        break;
    }
}

$pools = [];
$poolsAprem = [];
$nbTeamsCat = 0;

if ($categoryId) {

    // Nombre d'équipes dans la catégorie
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM teams WHERE category_id = ?'
    );
    $stmt->execute([$categoryId]);
    $nbTeamsCat = (int) $stmt->fetchColumn();

    // Poules
    $stmt = $pdo->prepare(
        "SELECT *
         FROM pools
         WHERE category_id = ?
           AND phase = 'matin'
         ORDER BY ordre_niveau"
    );
    $stmt->execute([$categoryId]);
    $pools = $stmt->fetchAll();

    // Équipes de chaque poule matin
    $stmtTeams = $pdo->prepare(
        'SELECT 
            CASE 
                WHEN club_team_counts.team_count > 1 THEN t.nom_equipe
                ELSE ""
            END AS nom_equipe,
            cl.nom_club,
            cl.logo_path AS club_logo_path
        FROM pool_teams pt
        JOIN teams t ON t.id = pt.team_id
        JOIN clubs cl ON cl.id = t.club_id
        JOIN (
            SELECT club_id, category_id, COUNT(*) AS team_count
            FROM teams
            GROUP BY club_id, category_id
        ) club_team_counts
            ON club_team_counts.club_id = t.club_id
            AND club_team_counts.category_id = t.category_id
        WHERE pt.pool_id = ?'
    );

    foreach ($pools as &$p) {
        $stmtTeams->execute([$p['id']]);
        $p['teams'] = $stmtTeams->fetchAll();
    }

    unset($p);

    $stmtAprem = $pdo->prepare(
        "SELECT * FROM pools WHERE category_id = ? AND phase = 'aprem' ORDER BY ordre_niveau"
    );
    $stmtAprem->execute([$categoryId]);
    $poolsAprem = $stmtAprem->fetchAll();

    foreach ($poolsAprem as &$p) {
        $stmtTeams->execute([$p['id']]);
        $p['teams'] = $stmtTeams->fetchAll();
    }

    unset($p);
   
}

$pageTitle = 'Poules';

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>

<div class="container">
    <h2 class="mb-4">Tirage au sort des poules du matin</h2>

    <form method="get" class="mb-3">
        <select
            name="category_id"
            class="form-select w-auto d-inline-block"
            onchange="this.form.submit()"
        >
            <?php foreach ($categories as $c): ?>
                <option
                    value="<?= $c['id'] ?>"
                    <?= $categoryId == $c['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($c['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($currentCat): ?>
        <div class="card mb-4">
            <div class="card-body">
                <p>
                    <?= $nbTeamsCat ?>
                    équipes engagées en
                    <?= htmlspecialchars($currentCat['nom']) ?>.
                </p>
                <form
                    method="post"
                    class="row g-2 align-items-end"
                    onsubmit="return confirm('Le tirage au sort remplacera les poules du matin existantes (et leurs matchs déjà saisis). Continuer ?');"
                >
                    <input type="hidden" name="action" value="draw">
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                    <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <div class="col-md-3">
                        <label class="form-label">Nombre de poules</label>
                        <input
                            type="number"
                            name="nb_poules"
                            min="1"
                            value="<?= $currentCat['nb_poules_matin'] ?>"
                            class="form-control"
                            required
                        >
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success w-100">
                            <i class="bi bi-shuffle"></i>
                            Lancer le tirage au sort
                        </button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">
                    Le tirage est aléatoire et répartit équitablement les équipes
                    entre les poules. Le calendrier des matchs et l'attribution
                    des terrains/horaires sont générés automatiquement.
                </p>
                <form
                    method="post"
                    class="mt-2"
                    onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les poules du matin ainsi que leurs matchs pour cette catégorie ?');"
                >
                    <input type="hidden" name="action" value="delete_all">
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                    <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <button class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i>
                        Supprimer toutes les poules de cette catégorie
                    </button>
                </form>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($pools as $p): ?>
                <div class="col-md-3">
                    <div class="card pool-card">
                        <div class="card-header fw-bold">
                            <?= htmlspecialchars($p['nom']) ?>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($p['teams'] as $t): ?>
                                <li class="list-group-item d-flex align-items-center gap-2">
                                    <?php if (!empty($t['club_logo_path'])): ?>
                                        <img
                                            src="../<?= htmlspecialchars($t['club_logo_path']) ?>"
                                            alt="Logo de <?= htmlspecialchars($t['nom_club']) ?>"
                                            style="height:24px; width:auto; object-fit:contain;"
                                        >
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($t['nom_club']) ?> <small class="text-muted"><?= htmlspecialchars($t['nom_equipe']) ?></small></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$pools): ?>
                <p class="text-muted">
                    Aucune poule tirée pour l'instant.
                </p>
            <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php
// Vérifie que toutes les poules du matin sont terminées
$poolsMatin = [];
$allFinished = false;
if ($categoryId) {
    $stmt = $pdo->prepare("SELECT * FROM pools WHERE category_id=? AND phase='matin' ORDER BY ordre_niveau");
    $stmt->execute([$categoryId]);
    $poolsMatin = $stmt->fetchAll();
    if ($poolsMatin) {
        $stmt2 = $pdo->prepare("SELECT COUNT(*) c FROM matches m JOIN pools p ON p.id=m.pool_id WHERE p.category_id=? AND p.phase='matin' AND m.status != 'Terminé'");
        $stmt2->execute([$categoryId]);
        $allFinished = $stmt2->fetch()['c'] == 0;
    }
    // taille de la plus grande poule (pour proposer une répartition par défaut)
    $maxPoolSize = 0;
    foreach ($poolsMatin as $p) {
        $stmt3 = $pdo->prepare('SELECT COUNT(*) c FROM pool_teams WHERE pool_id=?');
        $stmt3->execute([$p['id']]);
        $maxPoolSize = max($maxPoolSize, $stmt3->fetch()['c']);
    }
}

if ($allFinished): ?>
<br/>
<div class="container">
  <h2 class="mb-4">Génération des poules de l'après-midi</h2>

  <form method="get" class="mb-3">
    <select name="category_id" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $categoryId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if ($poolsMatin): ?>
  <div class="card mb-4">
    <div class="card-body">
      <p class="small text-muted">Les poules de l'après-midi sont générées à partir du classement général des poules du matin. Vous pouvez adapter le nom et les rangs de chaque tableau.</p>
      <form method="post" id="afternoonForm">
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
        <?php
          // Rangs par défaut proportionnels au nombre d'équipes engagées, utilisés
          // uniquement quand une poule n'existe pas encore (sinon on ré-affiche
          // fidèlement rangs_config, la config réellement utilisée la dernière fois).
          $nbPoolsAprem = $currentCat['nb_poules_aprem'] ?? 3;
          $nomsParDefaut = ['1 - Cup', '2 - Plat', '3 - Bowl', '4 - Shield'];
          $zone1 = (int) ceil($nbTeamsCat / 4);
          $zone2 = (int) ceil($nbTeamsCat / 4);
          $zone3 = (int) ceil($nbTeamsCat / 4);
          $zone4 = max(0, $nbTeamsCat - $zone1 - $zone2 - $zone3);
          $rangsParDefaut = [
              ['from' => 1, 'to' => $zone1],
              ['from' => $zone1 + 1, 'to' => $zone1 + $zone2],
              ['from' => $zone1 + $zone2 + 1, 'to' => $zone1 + $zone2 + $zone3],
              ['from' => $zone1 + $zone2 + $zone3 + 1, 'to' => $nbTeamsCat],
          ];
        ?>
        <?php for ($i = 0; $i < $nbPoolsAprem; $i++): ?>
          <?php
            $nomChamp = $poolsAprem[$i]['nom'] ?? $nomsParDefaut[$i] ?? "Tableau " . ($i + 1);
            $rangsChamp = $poolsAprem[$i]['rangs_config']
                ?? (isset($rangsParDefaut[$i]) ? $rangsParDefaut[$i]['from'] . '-' . $rangsParDefaut[$i]['to'] : '');
          ?>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Nom de la poule n°<?= $i + 1 ?></label>
              <input type="text" name="nom[]" class="form-control" value="<?= htmlspecialchars($nomChamp) ?>">
            </div>
          </div>
        <?php endfor; ?>
        <button class="btn btn-success" onclick="return confirm('Cela remplacera les poules de l\'après-midi existantes. Continuer ?');">
          <i class="bi bi-shuffle"></i>
          Générer les poules de l'après-midi
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($poolsAprem as $p): ?>
      <div class="col-md-3">
        <div class="card pool-card">
          <div class="card-header fw-bold"><?= htmlspecialchars($p['nom']) ?></div>
          <ul class="list-group list-group-flush">
            <?php 
              $totalEquipes = count($p['teams']);
              $groupe1 = (int) ceil($totalEquipes / 4);
              $groupe2 = (int) ceil($totalEquipes / 4);
              $groupe3 = (int) ceil($totalEquipes / 4);
              $groupe4 = $totalEquipes - $groupe1 - $groupe2 - $groupe3;
            ?>
            <?php foreach ($p['teams'] as $index => $t): 
              $position = $index + 1;
              if ($position <= $groupe1) {
                  $zoneClass = 'zone-1';
              } elseif ($position <= $groupe1 + $groupe2) {
                  $zoneClass = 'zone-2';
              } elseif ($position <= $groupe1 + $groupe2 + $groupe3) {
                  $zoneClass = 'zone-3';
              } else {
                  $zoneClass = 'zone-4';
              }
            ?>
            <li class="list-group-item d-flex align-items-center gap-2">
                <?php if (!empty($t['club_logo_path'])): ?>
                    <img
                        src="../<?= htmlspecialchars($t['club_logo_path']) ?>"
                        alt="Logo de <?= htmlspecialchars($t['nom_club']) ?>"
                        style="height:24px; width:auto; object-fit:contain;"
                    >
                <?php endif; ?>
                <span><?= htmlspecialchars($t['nom_club']) ?><small class="text-muted"> <?= htmlspecialchars($t['nom_equipe']) ?></small></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  
  <div class="row g-3">
    <?php if (!$allFinished && $poolsAprem): ?>
      <?php foreach ($poolsAprem as $p): ?>
        <div class="col-md-4">
          <div class="card pool-card">
            <div class="card-header fw-bold"><?= htmlspecialchars($p['nom']) ?></div>
            <ul class="list-group list-group-flush">
              <?php foreach ($p['teams'] as $t): ?>
                <li class="list-group-item">
                  <?= htmlspecialchars($t['nom_club']) ?>
                  <?php if (!empty($t['nom_equipe'])): ?>
                    — <?= htmlspecialchars($t['nom_equipe']) ?>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('rowsContainer')?.addEventListener('click', (e) => {
  if (e.target.classList.contains('remove-row')) {
    e.target.closest('.aprem-row').remove();
  }
});
</script>
</body>
</html>