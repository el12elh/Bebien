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
$data = [];
if ($categoryId) {
  if ($general) {
    $data = [ ['pool_nom' => 'Général', 'classement' => classementGeneral($categoryId, $phase)] ];
  } else {
    $data = classementCategorie($categoryId, $phase);
  }
}
$liveStmt = $pdo->query('SELECT 1 FROM matches WHERE status = "Live" LIMIT 1');
$hasLive = (bool) $liveStmt->fetchColumn();

$pageTitle = 'Classements';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <h2 class="mb-3">Classements</h2>
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
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php if ($hasLive): ?>
<script>setTimeout(() => location.reload(), 30000);</script>
<?php endif; ?>
</body></html>
