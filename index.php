<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/timer.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$liveStmt = $pdo->query('SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
              c1.nom_club c1, c1.logo_path AS club1_logo_path,
              CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS e1,
              c2.nom_club c2, c2.logo_path AS club2_logo_path,
              CASE WHEN club_team_counts2.team_count > 1 THEN t2.nom_equipe ELSE "" END AS e2
              FROM matches m
              JOIN categories c ON c.id=m.category_id
              JOIN pools p ON p.id=m.pool_id
              LEFT JOIN fields f ON f.id=m.field_id
              JOIN teams t1 ON t1.id=m.team1_id
              JOIN clubs c1 ON c1.id=t1.club_id
              JOIN (
                SELECT club_id, category_id, COUNT(*) AS team_count
                FROM teams
                GROUP BY club_id, category_id
              ) club_team_counts1 ON club_team_counts1.club_id = t1.club_id AND club_team_counts1.category_id = t1.category_id
              JOIN teams t2 ON t2.id=m.team2_id
              JOIN clubs c2 ON c2.id=t2.club_id
              JOIN (
                SELECT club_id, category_id, COUNT(*) AS team_count
                FROM teams
                GROUP BY club_id, category_id
              ) club_team_counts2 ON club_team_counts2.club_id = t2.club_id AND club_team_counts2.category_id = t2.category_id
              WHERE m.status = "Live" ORDER BY m.scheduled_at, m.category_id, m.field_id');
$live = $liveStmt->fetchAll();

$nextStmt = $pdo->query('SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
              c1.nom_club c1, c1.logo_path AS club1_logo_path,
              CASE WHEN club_team_counts1.team_count > 1 THEN t1.nom_equipe ELSE "" END AS e1,
              c2.nom_club c2, c2.logo_path AS club2_logo_path,
              CASE WHEN club_team_counts2.team_count > 1 THEN t2.nom_equipe ELSE "" END AS e2
              FROM matches m
              JOIN categories c ON c.id=m.category_id
              JOIN pools p ON p.id=m.pool_id
              LEFT JOIN fields f ON f.id=m.field_id
              JOIN teams t1 ON t1.id=m.team1_id
              JOIN clubs c1 ON c1.id=t1.club_id
              JOIN (
                SELECT club_id, category_id, COUNT(*) AS team_count
                FROM teams
                GROUP BY club_id, category_id
              ) club_team_counts1 ON club_team_counts1.club_id = t1.club_id AND club_team_counts1.category_id = t1.category_id
              JOIN teams t2 ON t2.id=m.team2_id
              JOIN clubs c2 ON c2.id=t2.club_id
              JOIN (
                SELECT club_id, category_id, COUNT(*) AS team_count
                FROM teams
                GROUP BY club_id, category_id
              ) club_team_counts2 ON club_team_counts2.club_id = t2.club_id AND club_team_counts2.category_id = t2.category_id
              WHERE m.status = "Programmé"
              ORDER BY m.scheduled_at, m.category_id, m.field_id
              LIMIT 12');
$next = $nextStmt->fetchAll();

$dateTournoi = getSetting('date_tournoi', '');
if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    if (new DateTime() > $tournamentDeadline) {
        $phase = 'aprem';
    }
    else {
        $phase = 'matin';
    }
}
$dateTournoi_formatted = $dateTournoi
    ? (new IntlDateFormatter(
        'fr_FR',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE
    ))->format(new DateTime($dateTournoi))
    : '';
$nomTournoi = getSetting('nom_tournoi');
$numeroEdition = getSetting('edition_tournoi');
$lieuTournoi = getSetting('lieu_tournoi');
$villeTournoi = getSetting('ville_tournoi');

$pageTitle = 'Accueil';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <div class="text-center my-4">
  <p>
      Bienvenue sur l'interface web de la
      <strong><?= $numeroEdition ?><sup>e</sup></strong>
      édition du <strong><?= $nomTournoi ?></strong>,
      qui aura lieu le <strong><?= $dateTournoi_formatted ?></strong> au <strong><?= $lieuTournoi ?></strong> à <strong><?= $villeTournoi ?></strong>.
  </p>
  <p class="text-muted">Suivi les résultats et classements en direct ici.</p>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
      <?php foreach ($categories as $c): ?>
        <a href="schedule?category_id=<?= $c['id'] ?>&phase=<?= $phase ?>" class="btn btn-outline-cat"><?= htmlspecialchars($c['nom']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($live): ?>
  <h4>
     Match<?= count($live) > 1 ? 's' : '' ?> en cours : <?= count($live) ?>
  </h4>
  <div class="row g-3 mb-4">
      <?php foreach ($live as $m): ?>
        <?php
          $score1 = $m['score1'] ?? null;
          $score2 = $m['score2'] ?? null;
          $winnerClass = '';
          if (is_numeric($score1) && is_numeric($score2) && $score1 !== $score2) {
            $winnerClass = $score1 > $score2 ? 'winner-team-1' : 'winner-team-2';
          }
        ?>
        <div class="col-md-3">
          <div class="card card-match <?= $m['status'] ?>">
            <div class="card-body">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span><?= htmlspecialchars($m['cat_nom']) ?> • <?= htmlspecialchars($m['pool_nom']) ?></span>
                <span><?= htmlspecialchars($m['field_nom'] ?? '') ?> <?= $m['scheduled_at'] ? '• ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($m['club1_logo_path'])): ?><img src="<?= htmlspecialchars($m['club1_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c1']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                  <span class="<?= $winnerClass === 'winner-team-1' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c1']) ?><small class="text-muted"><?= htmlspecialchars($m['e1']) ?></small></span>
                </div>
              <span class="fw-bold"><?= $m['score1'] ?? '-' ?></span> : <span class="fw-bold"><?= $m['score2'] ?? '-' ?></span>
                <div class="d-flex align-items-center gap-2">
                  <span class="<?= $winnerClass === 'winner-team-2' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c2']) ?><small class="text-muted"><?= htmlspecialchars($m['e2']) ?></small></span>
                  <?php if (!empty($m['club2_logo_path'])): ?><img src="<?= htmlspecialchars($m['club2_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c2']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                <span class="badge badge-status-<?= $m['status'] ?>"><?= $m['status'] ?></span>
                <?php renderLiveMatchTimer($m); ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <br>
  <h4>Prochains matchs</h4>
  <div class="row g-3 mb-4">
    <?php foreach ($next as $m): ?>
        <div class="col-md-3">
          <div class="card card-match <?= $m['status'] ?>">
            <div class="card-body">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span><?= htmlspecialchars($m['cat_nom']) ?> • <?= htmlspecialchars($m['pool_nom']) ?></span>
                <span><?= htmlspecialchars($m['field_nom'] ?? '') ?> <?= $m['scheduled_at'] ? '• ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($m['club1_logo_path'])): ?><img src="<?= htmlspecialchars($m['club1_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c1']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                  <span class="<?= $winnerClass === 'winner-team-1' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c1']) ?><small class="text-muted"><?= htmlspecialchars($m['e1']) ?></small></span>
                </div>
              <span class="text-muted">vs</span>
                <div class="d-flex align-items-center gap-2">
                  <span class="<?= $winnerClass === 'winner-team-2' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c2']) ?><small class="text-muted"><?= htmlspecialchars($m['e2']) ?></small></span>
                  <?php if (!empty($m['club2_logo_path'])): ?><img src="<?= htmlspecialchars($m['club2_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c2']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                </div>
              </div>
              <div class="text-end mt-1">
              <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                <span class="badge badge-status-<?= $m['status'] ?>"><?= $m['status'] ?></span>
              </div>
            </div>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$next): ?><p class="text-muted">Aucun match programmé.</p><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php if ($live): ?>
<script>
// Rafraîchissement automatique toutes les 30 secondes uniquement si un match est en cours
setTimeout(() => location.reload(), 30000);
</script>
<?php endif; ?>
</body></html>
