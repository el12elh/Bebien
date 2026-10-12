<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tournament.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = (int) ($_GET['category_id'] ?? ($categories[0]['id'] ?? 0));
$phase = $_GET['phase'] ?? null;
$category = null;
foreach ($categories as $item) {
  if ((int) $item['id'] === $categoryId) {
    $category = $item;
    break;
  }
}
$nbPoolsAprem = max(1, (int) ($category['nb_poules_aprem'] ?? 3));

$dateTournoi = getSetting('date_tournoi', '');
if ($phase !== 'matin' && $phase !== 'aprem') {
  $phase = 'matin';
  if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    $phase = new DateTime() > $tournamentDeadline ? 'aprem' : 'matin';
  }
}

$general = isset($_GET['general'])
  ? $_GET['general'] === '1'
  : $phase !== 'aprem';
$view = ($_GET['view'] ?? 'poules') === 'clubs' ? 'clubs' : 'poules';
$final = $view === 'clubs' ? classementFinalClubs() : null;
$marqueur = $view === 'clubs' ? classementSerialMarqueur() : null;

/** Formate un nombre de points sans décimales inutiles (12 / 12,5). */
function fmtPoints($value): string {
    return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',');
}

/** Pastille de rang : médailles pour les trois premiers. */
function rankBadge(int $rang): string {
    $class = $rang >= 1 && $rang <= 3 ? ' rank-badge-' . $rang : '';
    return '<span class="rank-badge' . $class . '">' . $rang . '</span>';
}

$data = [];
if ($view === 'poules' && $categoryId) {
  if ($general) {
    $data = [ ['pool_nom' => 'Général', 'classement' => classementGeneral($categoryId, $phase)] ];
  } else {
    $data = classementCategorie($categoryId, $phase);
  }
}
$liveStmt = $pdo->query('SELECT 1 FROM matches WHERE status = "Live" LIMIT 1');
$hasLive = (bool) $liveStmt->fetchColumn();

$pageTitle = $view === 'clubs' ? 'Classement des clubs' : 'Classements';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <h2 class="mb-3">Classements</h2>
  <ul class="nav nav-pills pill-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $view === 'poules' ? 'active' : '' ?>" href="standings?view=poules">Poules</a></li>
    <li class="nav-item"><a class="nav-link <?= $view === 'clubs' ? 'active' : '' ?>" href="standings?view=clubs">Clubs</a></li>
  </ul>

  <?php if ($view === 'poules'): ?>
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
  </form>

  <?php if (!$data): ?>
    <p class="text-muted">Aucun classement disponible pour le moment.</p>
  <?php endif; ?>

  <?php
    $poolZoneColors = [
      '#213b70',
      '#60A5FA',
      '#FACC15',
      '#FEF08A',
      '#16A34A',
      '#86EFAC',
      '#DC2626',
      '#FCA5A5',
    ];
  ?>
  <div class="row g-4">
    <?php foreach ($data as $poolIndex => $pool): ?>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header fw-bold">
            <?= htmlspecialchars($pool['pool_nom']) ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-rank mb-0">
              <thead class="table-light">
                <tr>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th>Pts</th>
                  <th>J</th>
                  <th>V</th>
                  <th>N</th>
                  <th>D</th>
                  <th><span class="card-icon card-icon-yellow" title="Cartons jaunes"></span></th>
                  <th><span class="card-icon card-icon-red" title="Cartons rouges"></span></th>
                  <th>+</th>
                  <th>-</th>
                  <th>=</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  $totalEquipes = count($pool['classement']);
                  $poolZoneColor = ($phase === 'aprem' && !$general)
                    ? $poolZoneColors[$poolIndex % count($poolZoneColors)]
                    : null;
                  $zoneStyle = null;
                  if ($poolZoneColor === null) {
                    $baseSize = intdiv($totalEquipes, $nbPoolsAprem);
                    $extraTeams = $totalEquipes % $nbPoolsAprem;
                  } else {
                    $zoneStyle = 'background-color: ' . $poolZoneColor . ';';
                  }
                  foreach ($pool['classement'] as $index => $row): 
                    if ($poolZoneColor === null) {
                      $position = $index + 1;
                      $rankStart = 1;
                      $zoneClass = 'zone-' . $nbPoolsAprem;
                      for ($poolNumber = 1; $poolNumber <= $nbPoolsAprem; $poolNumber++) {
                        $poolSize = $baseSize + ($poolNumber <= $extraTeams ? 1 : 0);
                        $rankEnd = $rankStart + $poolSize - 1;
                        if ($position >= $rankStart && $position <= $rankEnd) {
                          $zoneClass = 'zone-' . min($poolNumber, 4);
                          break;
                        }
                        $rankStart = $rankEnd + 1;
                      }
                    } else {
                      $zoneClass = '';
                    }
                  ?>
                  <tr>
                    <td><?= $row['rang'] ?></td>
                    <td class="<?= $zoneClass ?? '' ?>"<?= $zoneStyle ? ' style="' . htmlspecialchars($zoneStyle) . '"' : '' ?>></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($row['logo_path'])): ?><img src="<?= htmlspecialchars($row['logo_path']) ?>" alt="Logo de <?= htmlspecialchars($row['nom_club']) ?>" style="height:24px; width:auto; object-fit:contain;"><?php endif; ?>
                        <span class="text-nowrap"><?= htmlspecialchars($row['nom_club']) ?><small class="text-muted"><?= htmlspecialchars($row['nom_equipe']) ?></small></span>
                      </div>
                    </td>
                    <td><strong><?= $row['points'] ?></strong></td>
                    <td><?= $row['joues'] ?></td>
                    <td><?= $row['v'] ?></td>
                    <td><?= $row['n'] ?></td>
                    <td><?= $row['d'] ?></td>
                    <td><?= $row['jaunes'] ?? 0 ?></td>
                    <td><?= $row['rouges'] ?? 0 ?></td>
                    <td><?= $row['pts_pour'] ?></td>
                    <td><?= $row['pts_contre'] ?></td>
                    <td><?= $row['diff'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <h3 class="section-title">Classement final</h3>
    <?php
      $clubs = $final['clubs'];
      $cats = $final['categories'];
    ?>
    <p class="text-muted">
      Toutes catégories confondues, d'après les résultats de l'après-midi.
      <?php foreach ($cats as $cat): ?>
        <span class="badge badge-coef"><?= htmlspecialchars($cat['nom']) ?> ×<?= (int) $cat['coef'] ?></span>
      <?php endforeach; ?>
    </p>
    <?php if (!$final['complete']): ?>
      <div class="alert alert-warning py-2">
        <i class="bi bi-hourglass-split"></i> Classement provisoire : tous les matchs de l'après-midi ne sont pas encore terminés.
      </div>
    <?php endif; ?>

    <?php if (!$clubs): ?>
      <p class="text-muted">Le classement sera disponible dès les premiers résultats de l'après-midi.</p>
    <?php else: ?>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-club-rank align-middle mb-0">
            <thead>
              <tr>
                <th class="col-rank">#</th>
                <th>Club</th>
                <?php foreach ($cats as $cat): ?>
                  <th class="text-end"><?= htmlspecialchars($cat['nom']) ?></th>
                <?php endforeach; ?>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clubs as $club): ?>
                <tr class="<?= $club['rang'] <= 3 ? 'podium-row' : '' ?>">
                  <td><?= rankBadge((int) $club['rang']) ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($club['logo_path'])): ?><img src="/<?= htmlspecialchars(ltrim($club['logo_path'], '/')) ?>" alt="" class="club-logo"><?php endif; ?>
                      <span class="fw-semibold"><?= htmlspecialchars($club['nom_club']) ?></span>
                    </div>
                  </td>
                  <?php foreach ($cats as $cat): ?>
                    <td class="text-end text-muted"><?= isset($club['par_categorie'][$cat['id']]) ? fmtPoints($club['par_categorie'][$cat['id']]) : '–' ?></td>
                  <?php endforeach; ?>
                  <td class="text-end"><strong class="score-figure"><?= fmtPoints($club['total']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p class="small text-muted mt-3 mb-0">
        <?php if ($final['mode'] === 'points'): ?>
          Chaque équipe rapporte ses points de classement de l'après-midi, multipliés par le coefficient de sa catégorie.
        <?php else: ?>
          Chaque équipe rapporte autant de points que de places derrière elle (+1) dans sa catégorie, multipliés par le coefficient de la catégorie.
        <?php endif; ?>
        Les points des équipes d'un même club s'additionnent. En cas d'égalité : essais marqués l'après-midi.
      </p>
    <?php endif; ?>

  <h3 class="section-title mt-5">Serial marqueur</h3>
    <?php
      $clubs = $marqueur['clubs'];
      $cats = $marqueur['categories'];
    ?>
    <p class="text-muted">Les clubs qui ont marqué le plus d'essais, toutes catégories et tous matchs confondus (matin et après-midi).</p>

    <?php if (!$clubs): ?>
      <p class="text-muted">Le classement sera disponible dès les premiers matchs terminés.</p>
    <?php else: ?>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-club-rank align-middle mb-0">
            <thead>
              <tr>
                <th class="col-rank">#</th>
                <th>Club</th>
                <?php foreach ($cats as $catNom): ?>
                  <th class="text-end"><?= htmlspecialchars($catNom) ?></th>
                <?php endforeach; ?>
                <th class="text-end">Matchs</th>
                <th class="text-end">Moy.</th>
                <th class="text-end">Essais</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clubs as $club): ?>
                <tr class="<?= $club['rang'] <= 3 ? 'podium-row' : '' ?>">
                  <td><?= rankBadge((int) $club['rang']) ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($club['logo_path'])): ?><img src="/<?= htmlspecialchars(ltrim($club['logo_path'], '/')) ?>" alt="" class="club-logo"><?php endif; ?>
                      <span class="fw-semibold"><?= htmlspecialchars($club['nom_club']) ?></span>
                    </div>
                  </td>
                  <?php foreach ($cats as $catId => $catNom): ?>
                    <td class="text-end text-muted"><?= $club['par_categorie'][$catId] ?? '–' ?></td>
                  <?php endforeach; ?>
                  <td class="text-end text-muted"><?= (int) $club['matchs'] ?></td>
                  <td class="text-end text-muted"><?= $club['matchs'] > 0 ? number_format($club['essais'] / $club['matchs'], 1, ',', '') : '–' ?></td>
                  <td class="text-end"><strong class="score-figure"><?= (int) $club['essais'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p class="small text-muted mt-3 mb-0">À égalité d'essais, le club qui a joué le moins de matchs passe devant.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php if ($hasLive): ?>
<script>setTimeout(() => location.reload(), 30000);</script>
<?php endif; ?>
</body></html>
