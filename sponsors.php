<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$sponsors = $pdo->query('SELECT * FROM sponsors WHERE actif = 1 ORDER BY ordre_affichage, id')->fetchAll();

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

$pageTitle = 'Partenaires & Institutions';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <h2 class="page-title">Partenaires &amp; Institutions</h2>
  <p class="text-muted mb-4">Merci à nos partenaires et aux institutions qui soutiennent le tournoi.</p>

  <?php if (!$sponsors): ?>
    <p class="text-muted">Aucun partenaire à afficher pour le moment.</p>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($sponsors as $s): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card partner-card h-100">
            <?php if (!empty($s['lien_url'])): ?>
              <a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener noreferrer" class="partner-logo" aria-label="Visiter le site de <?= htmlspecialchars($s['nom']) ?>">
                <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>">
              </a>
            <?php else: ?>
              <div class="partner-logo">
                <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>">
              </div>
            <?php endif; ?>
            <div class="card-body text-center">
              <h5 class="partner-name mb-0"><?= htmlspecialchars($s['nom']) ?></h5>
              <?php if (!empty($s['lien_url'])): ?>
                <div class="mt-3">
                  <a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary sponsor-site-btn" aria-label="Visiter le site web de <?= htmlspecialchars($s['nom']) ?>">
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
