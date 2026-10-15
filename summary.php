<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tournament.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$pageTitle = 'Récapitulatif';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <h2 class="page-title">Récapitulatif</h2>
  <form id="exportForm" class="card card-body mb-4" onsubmit="return false;">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Catégorie</label>
        <select name="category_id" class="form-select">
          <option value="0">Toutes</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Phase</label>
        <select name="phase" class="form-select">
          <option value="" selected>Matin et après-midi</option>
          <option value="matin">Matin</option>
          <option value="aprem">Après-midi</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label d-block">Contenu</label>
        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="poules" id="cPoules" checked><label class="form-check-label" for="cPoules">Poules</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="matchs" id="cMatchs" checked><label class="form-check-label" for="cMatchs">Matchs</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="classements" id="cClass" ><label class="form-check-label" for="cClass">Classements</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="challenge" id="cChal" ><label class="form-check-label" for="cChal">Challenge clubs</label></div>
      </div>
    </div>
  </form>

  <div class="d-flex align-items-center gap-3 mb-3">
    <a id="btnDownload" class="btn btn-success" href="export_pdf?go=1"><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</a>
    <a id="btnPrint" class="btn btn-outline-primary" href="export_pdf?go=1&inline=1" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimer</a>
  </div>
  <p class="small text-muted">Choisissez les filtres, puis téléchargez le PDF ou ouvrez-le pour l'imprimer.</p>
</div>
<script>
(function () {
  const form = document.getElementById('exportForm');
  const dl = document.getElementById('btnDownload');
  const pr = document.getElementById('btnPrint');
  function update() {
    const p = new URLSearchParams({ go: '1' });
    p.set('category_id', form.category_id.value);
    p.set('phase', form.phase.value);
    ['poules', 'matchs', 'classements', 'challenge'].forEach(function (k) {
      if (form[k].checked) { p.set(k, '1'); }
    });
    dl.href = 'export_pdf?' + p.toString();
    p.set('inline', '1');
    pr.href = 'export_pdf?' + p.toString();
  }
  form.addEventListener('change', update);
  update();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>