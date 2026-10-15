<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$messages = db()->query('SELECT id, name, email, subject, message, created_at FROM contact_messages ORDER BY created_at DESC, id DESC')->fetchAll();
$pageTitle = 'Messages de contact';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="page-title">Messages de contact</h2>
  <p class="text-muted">Les messages envoyés depuis le formulaire de contact sont conservés ici, même si la notification email échoue.</p>

  <?php if (!$messages): ?>
    <div class="alert alert-info">Aucun message pour le moment.</div>
  <?php else: ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($messages as $item): ?>
        <article class="card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <strong><?= htmlspecialchars($item['subject'] !== '' ? $item['subject'] : '(Sans sujet)') ?></strong>
            <time class="text-muted small" datetime="<?= htmlspecialchars($item['created_at']) ?>"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($item['created_at']))) ?></time>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong><?= htmlspecialchars($item['name']) ?></strong>
              · <a href="mailto:<?= htmlspecialchars($item['email']) ?>"><?= htmlspecialchars($item['email']) ?></a>
            </div>
            <div class="text-break"><?= nl2br(htmlspecialchars($item['message'])) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
