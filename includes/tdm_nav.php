<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$user = currentUser();
?>
<nav class="navbar navbar-expand-lg navbar-rugby mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="/tdm">
      <img src="/uploads/logo.png" alt="<?= htmlspecialchars(getSetting('nom_tournoi')) ?>" class="me-2 site-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navTdm">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navTdm">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/">Accueil</a></li>
        <li class="nav-item"><a class="nav-link" href="/schedule">Calendrier</a></li>
        <li class="nav-item"><a class="nav-link" href="/standings">Classements</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='rules'?'active':'' ?>" href="/rules">Règlement</a></li>
        <li class="nav-item"><a class="nav-link" href="/sponsors">Sponsors</a></li>
        <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
      </ul>
      <span class="navbar-text text-white me-3 small"><?= htmlspecialchars($user['nom_affichage'] ?? $user['username'] ?? '') ?></span>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a href="/admin/" class="btn btn-outline-light btn-sm me-2">Admin</a>
      <?php endif; ?>
      <br>
      <a href="/admin/signout" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>
  </div>
</nav>
