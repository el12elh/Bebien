<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$user = currentUser();
?>
<nav class="navbar navbar-expand-lg navbar-rugby mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="/admin">
      <img src="../uploads/logo.png" alt="<?= htmlspecialchars(getSetting('nom_tournoi')) ?>" class="me-2 site-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navAdmin">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $currentPage==='accueil'?'active':'' ?>" href="/">Accueil</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='categories'?'active':'' ?>" href="categories">Catégories</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='teams'?'active':'' ?>" href="teams">Équipes</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='pools_draw'?'active':'' ?>" href="pools_draw">Poules</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='matches'?'active':'' ?>" href="matches">Matchs & Scores</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='planning'?'active':'' ?>" href="planning">Planning & Terrains</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='users'?'active':'' ?>" href="users">Users</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='sponsors'?'active':'' ?>" href="sponsors">Sponsors</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage==='settings'?'active':'' ?>" href="settings">Paramètres</a></li>
      </ul>
      <span class="navbar-text text-white me-3 small"><?= htmlspecialchars($user['nom_affichage'] ?? $user['username'] ?? '') ?></span>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a href="/tdm/" class="btn btn-outline-light btn-sm me-2">TdM</a>
      <?php endif; ?>
      <br>
      <a href="signout" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>
  </div>
</nav>
