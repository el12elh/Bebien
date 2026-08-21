<?php 
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$user = currentUser();
$navPhase = isset($phase) && $phase !== '' ? $phase : ($_GET['phase'] ?? '');
$navCategoryId = isset($categoryId) && $categoryId !== ''
  ? $categoryId
  : ($_GET['category_id'] ?? '');
$navQuery = http_build_query(array_filter([
  'category_id' => $navCategoryId,
  'phase' => $navPhase,
], static fn ($value) => $value !== '' && $value !== null));
$navQuery = $navQuery !== '' ? '?' . $navQuery : '';
?>
<nav class="navbar navbar-expand-lg navbar-rugby mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
      <img src="/uploads/logo.png" alt="<?= htmlspecialchars(getSetting('nom_tournoi')) ?>" class="me-2 site-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navPublic">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $currentPage==='/'?'active':'' ?>" href="/">Accueil</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='schedule'?'active':'' ?>" href="schedule<?= $navQuery ?>">Calendrier</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='standings'?'active':'' ?>" href="standings<?= $navQuery ?>">Classements</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='sponsors'?'active':'' ?>" href="sponsors">Sponsors</a></li>
      </ul>
        <?php if ($user): ?>
            <span class="navbar-text text-white me-3 small"><?= htmlspecialchars($user['nom_affichage'] ?? $user['username'] ?? '') ?></span>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
              <a href="/admin/" class="btn btn-outline-light btn-sm me-2">Admin</a>
            <?php endif; ?>
              <a href="/tdm/" class="btn btn-outline-light btn-sm me-2">TdM</a>
              <br>
            <a href="admin/signout" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        <?php else: ?>
            <a href="/admin/signin" class="btn btn-outline-light btn-sm"></i>Admin / TdM</a></li>
        <?php endif; ?>

    </div>
  </div>
</nav>
