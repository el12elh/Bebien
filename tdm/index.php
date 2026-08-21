<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/timer.php';
requireRole(); // admin ou tdm
$user = currentUser();
$pdo = db();

$sql = 'SELECT m.*, c.nom cat_nom, p.nom pool_nom, f.nom field_nom,
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
        WHERE m.status != "Terminé"';
$params = [];
if (!empty($user['field_id'])) {
    $sql .= ' AND m.field_id = ?';
    $params[] = $user['field_id'];
}
$sql .= ' ORDER BY m.scheduled_at, m.id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

$pageTitle = 'Espace Table de marque';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/tdm_nav.php';
?>
<div class="container">
  <?php if (!empty($_GET['finished'])): ?><div class="alert alert-success text-center">Match terminé.</div><?php endif; ?>
  <?php if (!empty($user['field_id'])): ?>
    <p class="text-muted">Matchs affichés pour votre terrain assigné.</p>
  <?php endif; ?>
  
  <div class="d-grid gap-3">
    <?php foreach ($matches as $m): ?>
      <?php
        $score1 = $m['score1'] ?? null;
        $score2 = $m['score2'] ?? null;
        $winnerClass = '';
        if (is_numeric($score1) && is_numeric($score2) && $score1 !== $score2) {
          $winnerClass = $score1 > $score2 ? 'winner-team-1' : 'winner-team-2';
        }
      ?>
      <a href="score/?id=<?= $m['id'] ?>" class="text-decoration-none">
        <div class="card card-match <?= $m['status'] ?>">
          <div class="card-body">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span><?= htmlspecialchars($m['cat_nom']) ?> • <?= htmlspecialchars($m['pool_nom']) ?></span>
              <span><?= htmlspecialchars($m['field_nom'] ?? '') ?> <?= $m['scheduled_at'] ? '• ' . date('H:i', strtotime($m['scheduled_at'])) : '' ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($m['club1_logo_path'])): ?><img src="../<?= htmlspecialchars($m['club1_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c1']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
                <span class="<?= $winnerClass === 'winner-team-1' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c1']) ?><small class="text-muted"><?= htmlspecialchars($m['e1']) ?></small></span>
              </div>
              <?php if ($m['status'] === 'Programmé'): ?>
                <span class="text-muted">vs</span>
              <?php else: ?>
                <span class="fw-bold"><?= $m['score1'] ?? '-' ?></span>
                <span class="text-muted">:</span>
                <span class="fw-bold"><?= $m['score2'] ?? '-' ?></span>
              <?php endif; ?>
              <div class="d-flex align-items-center gap-2">
                <span class="<?= $winnerClass === 'winner-team-2' ? 'team-name winner-team' : '' ?> text-nowrap"><?= htmlspecialchars($m['c2']) ?><small class="text-muted"><?= htmlspecialchars($m['e2']) ?></small></span>
                <?php if (!empty($m['club2_logo_path'])): ?><img src="../<?= htmlspecialchars($m['club2_logo_path']) ?>" alt="Logo de <?= htmlspecialchars($m['c2']) ?>" style="height:20px; width:auto; object-fit:contain;"><?php endif; ?>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
              <span class="badge badge-status-<?= $m['status'] ?>"><?= $m['status'] ?></span>
              <?php renderLiveMatchTimer($m); ?>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$matches): ?><p class="text-center text-muted mt-4">Aucun match programmé pour le moment.</p><?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body></html>
