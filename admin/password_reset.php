<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
$message = null;
$token = $_GET['token'] ?? null;

if (!$token) {
    http_response_code(400);
    die('Token manquant.');
}

$stmt = db()->prepare('SELECT id, username, reset_expires FROM users WHERE reset_token = ? AND actif = 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user || !$user['reset_expires'] || new DateTime($user['reset_expires']) < new DateTime()) {
    http_response_code(400);
    die('Token invalide ou expiré.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password === '' || $confirm === '') {
        $error = 'Veuillez saisir et confirmer votre nouveau mot de passe.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $passwordStrength = validatePasswordStrength($password);
        if (!$passwordStrength['valid']) {
            $error = $passwordStrength['message'];
        } else {
            db()->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            $message = 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.';
        }
    }
}

$pageTitle = 'Réinitialiser le mot de passe';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/public_nav.php';
?>
<div class="container d-flex align-items-center justify-content-center" style="min-height: 90vh;">
  <div class="card shadow-sm" style="max-width: 520px; width: 100%;">
    <div class="card-body p-4">
      <h4 class="mb-3 text-center"><i class="bi bi-key-fill"></i> Réinitialiser le mot de passe</h4>
      <p class="text-muted text-center small">Définissez un nouveau mot de passe pour <strong><?= htmlspecialchars($user['username']) ?></strong>.</p>
      <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if (!$message): ?>
        <form method="post">
          <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
          <div class="mb-3">
            <label class="form-label">Nouveau mot de passe</label>
            <input type="password" name="password" class="form-control" required autofocus>
            <div class="form-text">Au moins 12 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmer le mot de passe</label>
            <input type="password" name="confirm" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-success w-100">Réinitialiser</button>
        </form>
      <?php else: ?>
        <div class="text-center mt-3">
          <a href="signin">Retour à la connexion</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
