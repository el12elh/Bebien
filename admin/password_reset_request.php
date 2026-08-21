<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Veuillez indiquer votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez indiquer une adresse email valide.';
    } else {
        $stmt = db()->prepare('SELECT id, username, nom_affichage FROM users WHERE username = ? AND actif = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            db()->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
                ->execute([$token, $expires, $user['id']]);

            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $baseUrl = rtrim($scheme . $_SERVER['HTTP_HOST'] , '/');
            $resetUrl = sprintf('%s/admin/password_reset?token=%s', $baseUrl, $token);

            $subject = 'Réinitialisation de votre mot de passe';
            if (!function_exists('mb_encode_mimeheader')) {
              // ensure mbstring is available; fall back to base64 encoding manually
              $subjectEncoded = '=?UTF-8?B?'.base64_encode($subject).'?=';
            } else {
              $subjectEncoded = mb_encode_mimeheader($subject, 'UTF-8', 'B');
            }
            $greetingName = trim($user['nom_affichage'] ?? '') !== '' ? trim($user['nom_affichage']) : $user['username'];
            $body = "Bonjour $greetingName,\n\n";
            $body .= "Vous avez demandé la réinitialisation de votre mot de passe pour l'accès administrateur/table de marque du site Bébien.\n\n";
            $body .= "Cliquez sur le lien ci-dessous pour définir un nouveau mot de passe :\n";
            $body .= "$resetUrl\n\n";
            $body .= "Ce lien est valable pendant 1 heure.\n\n";
            $body .= "Si vous n'avez pas demandé cette réinitialisation, ignorez simplement ce message.\n\n";
            $body .= "Cordialement,\nL'équipe Bébien\n";

            $fromName = 'Bébien';
            $fromEncoded = '=?UTF-8?B?'.base64_encode($fromName).'?=';
            $headers = "From: $fromEncoded <contact@bebien.feuzanderie.fr>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

            if (mail($email, $subjectEncoded, $body, $headers)) {
                $message = 'Si cet email est enregistré, vous recevrez bientôt un message contenant un lien de réinitialisation.';
            } else {
                $message = 'Le lien de réinitialisation n’a pas pu être envoyé par email. Veuillez contacter l’administrateur.';
            }
        } else {
            $message = 'Si cet email est enregistré, vous recevrez bientôt un message contenant un lien de réinitialisation.';
        }
    }
}

$pageTitle = 'Réinitialisation du mot de passe';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/public_nav.php';
?>
<div class="container d-flex align-items-center justify-content-center" style="min-height: 90vh;">
  <div class="card shadow-sm" style="max-width: 520px; width: 100%;">
    <div class="card-body p-4">
      <a href="../" class="position-absolute top-0 end-0 m-3 text-decoration-none fs-4" aria-label="Retour">&times;</a>
      <h4 class="mb-3 text-center"><i class="bi bi-key"></i> Mot de passe oublié</h4>
      <p class="text-muted text-center small">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
      <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-success w-100">Envoyer le lien</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
