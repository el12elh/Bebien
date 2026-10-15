<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tournament.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

// Sélection (par défaut : tout)
$submitted = isset($_GET['go']);
$categoryId = (int) ($_GET['category_id'] ?? 0);              // 0 = toutes les catégories
$phaseChoice = $_GET['phase'] ?? '';                           // '' = matin + après-midi
$phases = in_array($phaseChoice, ['matin', 'aprem'], true) ? [$phaseChoice] : ['matin', 'aprem'];
$show = [
    'poules' => !$submitted || isset($_GET['poules']),
    'matchs' => !$submitted || isset($_GET['matchs']),
    'classements' => !$submitted || isset($_GET['classements']),
    'challenge' => !$submitted || isset($_GET['challenge']),
];
$phaseLabels = ['matin' => 'Matin', 'aprem' => 'Après-midi'];

// Couleurs des zones du classement général du matin (comme standings.php)
$zoneColors = [1 => '#213b70', 2 => '#60A5FA', 3 => '#FACC15', 4 => '#FEF08A'];

/** Zone (1..4) d'une position (index 0) selon le découpage en $nbPools poules d'après-midi. */
function zoneForIndex(int $index, int $total, int $nbPools): int {
    $nbPools = max(1, $nbPools);
    $base = intdiv($total, $nbPools);
    $extra = $total % $nbPools;
    $start = 0;
    for ($n = 1; $n <= $nbPools; $n++) {
        $end = $start + $base + ($n <= $extra ? 1 : 0);
        if ($index < $end) {
            return min($n, 4);
        }
        $start = $end;
    }
    return min($nbPools, 4);
}

/** Formate un nombre de points sans décimales inutiles (12 / 12,5). */
function fmtPoints($value): string {
    return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',');
}

/** Pastille de rang : médailles pour les trois premiers (comme standings.php). */
function rankBadge(int $rang): string {
    $class = $rang >= 1 && $rang <= 3 ? ' rank-badge-' . $rang : '';
    return '<span class="rank-badge' . $class . '">' . $rang . '</span>';
}

$stmtTeams = $pdo->prepare(
    'SELECT t.nom_equipe, cl.nom_club, cl.logo_path,
            (SELECT COUNT(*) FROM teams t2 WHERE t2.club_id = t.club_id AND t2.category_id = t.category_id) AS nb_equipes_club
     FROM pool_teams pt
     JOIN teams t ON t.id = pt.team_id
     JOIN clubs cl ON cl.id = t.club_id
     WHERE pt.pool_id = ?
     ORDER BY cl.nom_club, t.nom_equipe'
);
$stmtMatches = $pdo->prepare(
    'SELECT m.status, m.score1, m.score2, m.scheduled_at, f.nom AS field_nom, p.nom AS pool_nom,
            c1.nom_club AS club1, c1.logo_path AS logo1, t1.nom_equipe AS eq1,
            (SELECT COUNT(*) FROM teams x WHERE x.club_id = t1.club_id AND x.category_id = t1.category_id) AS nb1,
            c2.nom_club AS club2, c2.logo_path AS logo2, t2.nom_equipe AS eq2,
            (SELECT COUNT(*) FROM teams y WHERE y.club_id = t2.club_id AND y.category_id = t2.category_id) AS nb2
     FROM matches m
     JOIN pools p ON p.id = m.pool_id
     LEFT JOIN fields f ON f.id = m.field_id
     JOIN teams t1 ON t1.id = m.team1_id
     JOIN clubs c1 ON c1.id = t1.club_id
     JOIN teams t2 ON t2.id = m.team2_id
     JOIN clubs c2 ON c2.id = t2.club_id
     WHERE m.category_id = ? AND m.phase = ?
     ORDER BY m.scheduled_at, f.nom, m.id'
);

/** Petit logo de club (HTML) devant un nom d'équipe. */
function exportLogo(?string $path): string {
    if (empty($path)) {
        return '';
    }
    return '<img src="/' . htmlspecialchars(ltrim($path, '/')) . '" alt="" style="height:18px;width:auto;max-width:34px;object-fit:contain;vertical-align:middle;margin-right:6px">';
}

/** Nom d'une équipe : club, + nom d'équipe seulement si le club en a plusieurs. */
function teamLabel(string $club, ?string $team, int $count): string {
    return $club . ($count > 1 && $team ? ' ' . $team : '');
}

$selected = [];
foreach ($categories as $c) {
    if ($categoryId === 0 || $categoryId === (int) $c['id']) {
        $selected[] = $c;
    }
}

$pageTitle = 'Export PDF';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<style>
  .export-block { margin-bottom: 1.5rem; }
  .export-block h4 { font-size: 1.15rem; margin: 1rem 0 0.5rem; }
  .export-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  .export-table th, .export-table td { border: 1px solid #cfd8e8; padding: 0.3rem 0.5rem; text-align: left; }
  .export-table th { background: #eef2fa; }
  .export-table .num { text-align: center; white-space: nowrap; }
  .export-table .zone-cell { width: 12px; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .export-table tr.podium td { background: #eef2fa; }
  .export-cat { border-bottom: 3px solid var(--site-accent); margin: 2rem 0 0.5rem; padding-bottom: 0.2rem; }
  .export-pools { display: flex; flex-wrap: wrap; gap: 0.75rem; }
  .export-pools > div { border: 1px solid #cfd8e8; border-radius: 0.4rem; padding: 0.5rem 0.75rem; min-width: 200px; }
  @media print {
    @page { size: A4; margin: 12mm; }
    .navbar, .sponsor-strip, .site-footer, .no-print { display: none !important; }
    body { background: #fff !important; }
    .container { max-width: 100% !important; padding: 0 !important; }
    .export-cat { break-before: page; margin-top: 0; }
    .export-cat.first { break-before: auto; }
    .export-table, .export-pools > div { break-inside: avoid; }
    .export-table tr.podium td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rank-badge, .podium-row td, .badge-coef, .table-club-rank th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card { break-inside: avoid; }
  }
</style>
<div class="container">
  <div class="no-print">
    <h2 class="page-title">Export PDF</h2>
    <form method="get" class="card card-body mb-4">
      <input type="hidden" name="go" value="1">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Catégorie</label>
          <select name="category_id" class="form-select">
            <option value="0">Toutes</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Phase</label>
          <select name="phase" class="form-select">
            <option value="" <?= $phaseChoice === '' ? 'selected' : '' ?>>Matin et après-midi</option>
            <option value="matin" <?= $phaseChoice === 'matin' ? 'selected' : '' ?>>Matin</option>
            <option value="aprem" <?= $phaseChoice === 'aprem' ? 'selected' : '' ?>>Après-midi</option>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label d-block">Contenu</label>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="poules" id="cPoules" <?= $show['poules'] ? 'checked' : '' ?>><label class="form-check-label" for="cPoules">Poules</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="matchs" id="cMatchs" <?= $show['matchs'] ? 'checked' : '' ?>><label class="form-check-label" for="cMatchs">Matchs</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="classements" id="cClass" <?= $show['classements'] ? 'checked' : '' ?>><label class="form-check-label" for="cClass">Classements</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="challenge" id="cChal" <?= $show['challenge'] ? 'checked' : '' ?>><label class="form-check-label" for="cChal">Challenge clubs</label></div>
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-outline-primary">Afficher</button>
        </div>
      </div>
    </form>
    <div class="d-flex align-items-center gap-3 mb-3">
      <?php
        $pdfQuery = ['go' => 1, 'category_id' => $categoryId, 'phase' => $phaseChoice];
        foreach (['poules', 'matchs', 'classements', 'challenge'] as $key) {
          if ($show[$key]) { $pdfQuery[$key] = 1; }
        }
      ?>
      <a class="btn btn-success" href="export_pdf?<?= htmlspecialchars(http_build_query($pdfQuery)) ?>"><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</a>
      <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer cette page</button>
    </div>
  </div>

  <h2 class="d-none d-print-block"><?= htmlspecialchars(getSetting('nom_tournoi', 'Tournoi')) ?></h2>

  <?php $firstBlock = true; ?>
  <?php foreach ($selected as $cat): ?>
    <?php foreach ($phases as $phase): ?>
      <?php
        $poolsStmt = $pdo->prepare('SELECT id, nom FROM pools WHERE category_id = ? AND phase = ? ORDER BY ordre_niveau, id');
        $poolsStmt->execute([(int) $cat['id'], $phase]);
        $pools = $poolsStmt->fetchAll();
        $stmtMatches->execute([(int) $cat['id'], $phase]);
        $matches = $stmtMatches->fetchAll();
        if (!$pools && !$matches) { continue; }
      ?>
      <h3 class="export-cat <?= $firstBlock ? 'first' : '' ?>"><?= htmlspecialchars($cat['nom']) ?> — <?= $phaseLabels[$phase] ?></h3>
      <?php $firstBlock = false; ?>

      <?php if ($show['poules'] && $pools): ?>
        <div class="export-block">
          <h4>Poules</h4>
          <div class="export-pools">
            <?php foreach ($pools as $pool): ?>
              <?php $stmtTeams->execute([$pool['id']]); $teams = $stmtTeams->fetchAll(); ?>
              <div>
                <strong><?= htmlspecialchars($pool['nom']) ?></strong>
                <ul class="mb-0 ps-3">
                  <?php foreach ($teams as $t): ?>
                    <li style="list-style:none;margin-left:-1rem"><?= exportLogo($t['logo_path'] ?? null) ?><?= htmlspecialchars(teamLabel($t['nom_club'], $t['nom_equipe'], (int) $t['nb_equipes_club'])) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($show['matchs'] && $matches): ?>
        <div class="export-block">
          <h4>Matchs</h4>
          <table class="export-table">
            <thead>
              <tr><th class="num">Heure</th><th>Terrain</th><th>Poule</th><th>Équipe 1</th><th class="num">Score</th><th>Équipe 2</th><th>État</th></tr>
            </thead>
            <tbody>
              <?php foreach ($matches as $m): ?>
                <tr>
                  <td class="num"><?= $m['scheduled_at'] ? date('H:i', strtotime($m['scheduled_at'])) : '–' ?></td>
                  <td><?= htmlspecialchars($m['field_nom'] ?? '–') ?></td>
                  <td><?= htmlspecialchars($m['pool_nom']) ?></td>
                  <td><?= exportLogo($m['logo1'] ?? null) ?><?= htmlspecialchars(teamLabel($m['club1'], $m['eq1'], (int) $m['nb1'])) ?></td>
                  <td class="num"><?= in_array($m['status'], ['Terminé', 'Live'], true) ? (int) $m['score1'] . ' - ' . (int) $m['score2'] : '–' ?></td>
                  <td><?= exportLogo($m['logo2'] ?? null) ?><?= htmlspecialchars(teamLabel($m['club2'], $m['eq2'], (int) $m['nb2'])) ?></td>
                  <td><?= htmlspecialchars($m['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($show['classements'] && $pools): ?>
        <div class="export-block">
          <h4>Classements</h4>
          <?php
            // Matin : un classement général par catégorie ; après-midi : un classement par tableau
            $tables = $phase === 'matin'
              ? [['pool_nom' => 'Classement général ' . $cat['nom'], 'classement' => classementGeneral((int) $cat['id'], 'matin')]]
              : classementCategorie((int) $cat['id'], $phase);
            $nbAprem = max(1, (int) ($cat['nb_poules_aprem'] ?? 3));
          ?>
          <?php foreach ($tables as $pool): ?>
            <?php $totalRows = count($pool['classement']); ?>
            <table class="export-table mb-3">
              <thead>
                <tr><th colspan="13"><?= htmlspecialchars($pool['pool_nom']) ?></th></tr>
                <tr><th class="num">#</th><th class="zone-cell"></th><th>Équipe</th><th class="num">Pts</th><th class="num">J</th><th class="num">V</th><th class="num">N</th><th class="num">D</th><th class="num"><span class="card-icon card-icon-yellow" title="Cartons jaunes"></span></th><th class="num"><span class="card-icon card-icon-red" title="Cartons rouges"></span></th><th class="num">+</th><th class="num">-</th><th class="num">=</th></tr>
              </thead>
              <tbody>
                <?php foreach ($pool['classement'] as $idx => $row): ?>
                  <tr>
                    <td class="num"><?= (int) $row['rang'] ?></td>
                    <td class="zone-cell"<?= $phase === 'matin' ? ' style="background:' . $zoneColors[zoneForIndex((int) $idx, $totalRows, $nbAprem)] . '"' : '' ?>></td>
                    <td>
                      <?= exportLogo($row['logo_path'] ?? null) ?>
                      <?= htmlspecialchars($row['nom_club'] . ($row['nom_equipe'] ? ' ' . $row['nom_equipe'] : '')) ?>
                    </td>
                    <td class="num"><strong><?= htmlspecialchars((string) $row['points']) ?></strong></td>
                    <td class="num"><?= (int) $row['joues'] ?></td>
                    <td class="num"><?= (int) $row['v'] ?></td>
                    <td class="num"><?= (int) $row['n'] ?></td>
                    <td class="num"><?= (int) $row['d'] ?></td>
                    <td class="num"><?= (int) ($row['jaunes'] ?? 0) ?></td>
                    <td class="num"><?= (int) ($row['rouges'] ?? 0) ?></td>
                    <td class="num"><?= (int) $row['pts_pour'] ?></td>
                    <td class="num"><?= (int) $row['pts_contre'] ?></td>
                    <td class="num"><?= (int) $row['diff'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if ($show['challenge']): ?>
    <?php $final = classementFinalClubs(); $marqueur = classementSerialMarqueur(); ?>
    <?php if ($final['clubs'] || $marqueur['clubs']): ?>
      <h3 class="export-cat <?= $firstBlock ? 'first' : '' ?>">Challenge — classement des clubs</h3>
      <?php $firstBlock = false; ?>
    <?php endif; ?>

    <?php if ($final['clubs']): ?>
      <?php $clubs = $final['clubs']; $cats = $final['categories']; ?>
      <div class="export-block">
        <h4>Classement final</h4>
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
        <p class="small text-muted mt-2 mb-0">Le club avec le moins de points est premier. En cas d'égalité : essais marqués l'après-midi.</p>
      </div>
    <?php endif; ?>

    <?php if ($marqueur['clubs']): ?>
      <?php $clubs = $marqueur['clubs']; $cats = $marqueur['categories']; ?>
      <div class="export-block">
        <h4>Serial marqueur</h4>
        <p class="text-muted">Les clubs qui ont marqué le plus d'essais, toutes catégories et tous matchs confondus (matin et après-midi).</p>
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
        <p class="small text-muted mt-2 mb-0">À égalité d'essais, le club qui a joué le moins de matchs passe devant.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($firstBlock): ?>
    <p class="text-muted">Rien à exporter pour cette sélection.</p>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>