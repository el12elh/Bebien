<?php
/** @var string $pageTitle */
$nomTournoi = getSetting('nom_tournoi');
$logoTournoiPath = ltrim((string) getSetting('logo_tournoi_path', 'uploads/logo_tournoi.png'), '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? $nomTournoi) ?> - <?= htmlspecialchars($nomTournoi) ?></title>
<link rel="icon" type="image/png" href="/<?= htmlspecialchars($logoTournoiPath) ?>">
<link rel="apple-touch-icon" href="/<?= htmlspecialchars($logoTournoiPath) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/style.css" rel="stylesheet">
</head>
<body>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.navbar-toggler[data-bs-target]').forEach((button) => {
    const targetSelector = button.getAttribute('data-bs-target');
    const target = document.querySelector(targetSelector);
    if (!target) return;

    button.addEventListener('click', () => {
      const isExpanded = button.getAttribute('aria-expanded') === 'true';
      const nextExpanded = !isExpanded;
      button.setAttribute('aria-expanded', String(nextExpanded));
      target.classList.toggle('show', nextExpanded);
    });
  });
});
</script>