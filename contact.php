<?php
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$message = null;
$values = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => '',
];

function contactHeaderValue(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function contactEncodeHeader(string $value): string {
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B');
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function sendContactMessage(string $toEmail, array $values): bool {
    $toEmail = contactHeaderValue($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromName = 'Bébien';
    $fromEmail = 'contact@bebien.feuzanderie.fr';
    $replyToEmail = contactHeaderValue($values['email']);
    $replyToName = contactHeaderValue($values['name']);
    $subject = contactHeaderValue($values['subject']);
    $subjectText = $subject !== ''
        ? 'Contact site - ' . $subject
        : 'Contact site Bébien';

    $body = "Nouveau message depuis le formulaire de contact.\n\n";
    $body .= "Nom : " . $values['name'] . "\n";
    $body .= "Email : " . $values['email'] . "\n";
    $body .= "Sujet : " . ($values['subject'] !== '' ? $values['subject'] : '-') . "\n\n";
    $body .= "Message :\n" . $values['message'] . "\n";

    $headers = 'From: ' . contactEncodeHeader($fromName) . " <$fromEmail>\r\n";
    $headers .= 'Reply-To: ' . contactEncodeHeader($replyToName) . " <$replyToEmail>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

    return mail($toEmail, contactEncodeHeader($subjectText), $body, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();

    foreach ($values as $key => $_) {
        $values[$key] = trim($_POST[$key] ?? '');
    }

    if (trim($_POST['website'] ?? '') !== '') {
        $errors[] = 'Votre message n’a pas pu être envoyé.';
    }
    if ($values['name'] === '') {
        $errors[] = 'Veuillez indiquer votre nom.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez indiquer une adresse email valide.';
    }
    if ($values['message'] === '') {
        $errors[] = 'Veuillez saisir votre message.';
    } elseif (function_exists('mb_strlen') ? mb_strlen($values['message']) > 3000 : strlen($values['message']) > 3000) {
        $errors[] = 'Votre message ne doit pas dépasser 3000 caractères.';
    }

    if (!$errors) {
        $toEmail = trim((string) getSetting('contact_email', ''));
        if ($toEmail === '') {
            $toEmail = 'contact@bebien.feuzanderie.fr';
        }
        if (sendContactMessage($toEmail, $values)) {
            $message = 'Votre message a bien été envoyé.';
            $values = array_fill_keys(array_keys($values), '');
        } else {
            $errors[] = 'Le message n’a pas pu être envoyé. Veuillez réessayer plus tard.';
        }
    }
}

$dateTournoi = getSetting('date_tournoi', '');
if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    $phase = (new DateTime() > $tournamentDeadline) ? 'aprem' : 'matin';
}

$pageTitle = 'Contact';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <h2 class="mb-3">Contact</h2>
      <p class="text-muted mb-4">Envoyez un message à l'équipe organisatrice du <?= htmlspecialchars(getSetting('nom_tournoi', 'Challenge')) ?>.</p>

      <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card shadow-sm border-0">
        <div class="card-body p-4">
          <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
          <div class="d-none" aria-hidden="true">
            <label>Site web</label>
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="contact-name" class="form-label">Nom</label>
              <input id="contact-name" type="text" name="name" class="form-control" value="<?= htmlspecialchars($values['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="contact-email" class="form-label">Email</label>
              <input id="contact-email" type="email" name="email" class="form-control" value="<?= htmlspecialchars($values['email']) ?>" required>
            </div>
            <div class="col-12">
              <label for="contact-subject" class="form-label">Sujet</label>
              <input id="contact-subject" type="text" name="subject" class="form-control" value="<?= htmlspecialchars($values['subject']) ?>">
            </div>
            <div class="col-12">
              <label for="contact-message" class="form-label">Message</label>
              <textarea id="contact-message" name="message" class="form-control" rows="7" maxlength="3000" required><?= htmlspecialchars($values['message']) ?></textarea>
            </div>
          </div>

          <button type="submit" class="btn btn-success mt-4">
            <i class="bi bi-send"></i> Envoyer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
