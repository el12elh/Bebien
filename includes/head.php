<?php
/** @var string $pageTitle */
$nomTournoi = getSetting('nom_tournoi');
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bébien<?= $pageTitle !== null ? ' • ' . htmlspecialchars($pageTitle) : '' ?></title>
<meta name="description" content="La <?= htmlspecialchars($numeroEdition) ?>e édition du <?= htmlspecialchars($nomTournoi) ?> se déroulera le <?= htmlspecialchars($dateTournoi_formatted) ?> au <?= htmlspecialchars($lieuTournoi) ?> à <?= htmlspecialchars($villeTournoi) ?>.">
<link rel="icon" type="image/png" href="/assets/img/logo.png">
<link rel="icon" href="/assets/img/favicon.ico" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png">
<meta name="theme-color" content="#213b70">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
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