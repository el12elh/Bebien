<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$sponsors = $pdo->query('SELECT * FROM sponsors WHERE actif = 1 ORDER BY niveau, ordre_affichage')->fetchAll();

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

$pageTitle = 'Sponsors';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">

  <h2 class="mb-3">Sponsors & Partenaires</h2>
  <p class="text-muted mb-4">Merci à nos sponsors et partenaires pour leur soutien au tournoi.</p>

  <?php if (!$sponsors): ?>
    <p class="text-muted">Aucun sponsor disponible pour le moment.</p>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($sponsors as $s): ?>
        <div class="col-md-3 col-sm-6">
          <div class="card h-100 shadow-sm border-0">
            <?php if (!empty($s['lien_url'])): ?>
              <a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener noreferrer" class="d-block text-center p-3">
                <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>" style="height:120px; width:100%; object-fit:contain;">
              </a>
            <?php else: ?>
              <div class="text-center p-3">
                <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>" style="height:120px; width:100%; object-fit:contain;">
              </div>
            <?php endif; ?>
            <div class="card-body text-center">
            <h5 class="card-title mb-2"><?= htmlspecialchars($s['nom']) ?></h5>
            <?php
            $levelClass = 'badge-niveau';
            if ($s['niveau'] === 'or') {
                $levelClass .= ' badge-or';
            } elseif ($s['niveau'] === 'argent') {
                $levelClass .= ' badge-argent';
            } elseif ($s['niveau'] === 'bronze') {
                $levelClass .= ' badge-bronze';
            } else {
                $levelClass .= ' bg-secondary';
            }
            ?>
            <span class="badge <?= $levelClass ?>">
            <?= $s['niveau'] !== 'partenaire' ? 'Sponsor - ' . ucfirst(htmlspecialchars($s['niveau'])) : 'Partenaire' ?>
            </span>
              <?php if (!empty($s['lien_url'])): ?>
                <div class="mt-3">
                  <a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary sponsor-site-btn" aria-label="Visiter le site web">
                    <i class="bi bi-globe"></i>
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
