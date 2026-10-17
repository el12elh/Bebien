<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Plan';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <?php require_once __DIR__ . '/includes/sponsor_strip.php'; ?>
  <h2 class="page-title">Plan du tournoi</h2>
  <div class="card mb-4">
    <div class="card-body text-center">
        <img
          src="uploads/plan_tournoi.png"
          alt="Plan du site du tournoi avec les emplacements des terrains M8, M10, M12 et du village équipe"
          class="img-fluid"
        >
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
