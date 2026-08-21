<?php
require_once __DIR__ . '/../includes/auth.php';

$existingAdmins = (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    if (signin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        $user = currentUser();
        if ($user['role'] === 'admin') {
            header('Location: ../admin/');
        } else {
            header('Location: ../tdm/');
        }
        exit;
    }
    $error = 'Identifiants incorrects.';
}
$pageTitle = 'Connexion';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/public_nav.php';
?>
<div class="container d-flex align-items-center justify-content-center" style="min-height: 90vh;">
  <div class="card shadow-sm position-relative" style="max-width: 420px; width: 100%;">
    <div class="card-body p-4">
      <a href="../" class="position-absolute top-0 end-0 m-3 text-decoration-none fs-4" aria-label="Retour">&times;</a>
      <h4 class="mb-3 text-center"><i class="bi bi-shield-lock"></i> Connexion</h4>
      <p class="text-muted text-center small">Accès administrateur / table de marque</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Mot de passe</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success w-100">Se connecter</button>
      </form>
      <div class="text-center mt-2">
        Mot de passe oublié ?<br>
        <a href="password_reset_request">Réinitialiser votre mot de passe</a>
      </div>
      <?php if ($existingAdmins === 0): ?>
        <div class="text-center mt-2">
          <a href="/admin/signup">Créer un compte</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>