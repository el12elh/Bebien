<?php
$sponsors = db()->query('SELECT * FROM sponsors WHERE actif = 1 ORDER BY niveau, ordre_affichage')->fetchAll();
if ($sponsors):
?>

<h3 class="sponsors-title">Sponsors & Partenaires</h3>
<div class="sponsor-strip">
  <div class="sponsor-track">
    <?php foreach ($sponsors as $s): ?>
      <?php if ($s['lien_url']): ?><a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener"><?php endif; ?>
      <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>" title="<?= htmlspecialchars($s['nom']) ?>">
      <?php if ($s['lien_url']): ?></a><?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ($sponsors as $s): // dupliqué pour un défilement continu ?>
      <?php if ($s['lien_url']): ?><a href="<?= htmlspecialchars($s['lien_url']) ?>" target="_blank" rel="noopener"><?php endif; ?>
      <img src="<?= htmlspecialchars($s['logo_path']) ?>" alt="<?= htmlspecialchars($s['nom']) ?>" title="<?= htmlspecialchars($s['nom']) ?>">
      <?php if ($s['lien_url']): ?></a><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<br>
