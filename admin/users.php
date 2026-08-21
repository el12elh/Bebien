<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

function sendPasswordSetupEmail(string $email, string $token, string $displayName = ''): bool {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $baseUrl = rtrim($scheme . $_SERVER['HTTP_HOST'], '/');
    $resetUrl = sprintf('%s/admin/password_reset?token=%s', $baseUrl, urlencode($token));

    $subject = 'Création de votre mot de passe Bébien';
    $subjectEncoded = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B')
        : '=?UTF-8?B?'.base64_encode($subject).'?=';

    $greetingName = trim($displayName) !== '' ? trim($displayName) : $email;
    $body = "Bonjour $greetingName,\n\n";
    $body .= "Un compte a été créé pour vous sur le site Bébien. \n";
    $body .= "Cliquez sur le lien ci-dessous pour définir votre mot de passe :\n";
    $body .= "$resetUrl\n\n";
    $body .= "Ce lien est valable pendant 30 jours.\n\n";
    $body .= "Si vous n'avez pas demandé ce compte, ignorez simplement ce message.\n\n";
    $body .= "Cordialement,\nL'équipe Bébien\n";

    $fromName = 'Bébien';
    $fromEmail = 'contact@bebien.feuzanderie.fr';
    $adminEmails = db()
        ->query("SELECT username FROM users WHERE role = 'admin' AND actif = 1")
        ->fetchAll(PDO::FETCH_COLUMN);
    $adminEmails = array_filter($adminEmails, static function ($adminEmail) {
        return filter_var($adminEmail, FILTER_VALIDATE_EMAIL);
    });
    $fromEncoded = '=?UTF-8?B?'.base64_encode($fromName).'?=';
    $headers = "From: $fromEncoded <$fromEmail>\r\n";
    if ($adminEmails) {
        $headers .= 'Bcc: ' . implode(', ', $adminEmails) . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return mail($email, $subjectEncoded, $body, $headers);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $email = trim($_POST['username'] ?? '');
        $nomAffichage = trim($_POST['nom_affichage'] ?? '');
        $role = $_POST['role'] ?? 'tdm';
        $fieldId = $role === 'tdm' ? ($_POST['field_id'] ?: null) : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Veuillez indiquer une adresse email valide.';
        } elseif (!in_array($role, ['admin', 'tdm'], true)) {
            $message = 'Rôle invalide.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'Un compte existe déjà pour cette adresse email.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
                $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                $insert = $pdo->prepare('INSERT INTO users (username, password_hash, reset_token, reset_expires, role, nom_affichage, field_id) VALUES (?,?,?,?,?,?,?)');
                $insert->execute([$email, $passwordHash, $token, $expires, $role, $nomAffichage ?: null, $fieldId]);

                if (sendPasswordSetupEmail($email, $token, $nomAffichage)) {
                    $message = 'Compte créé et lien d’activation envoyé par email.';
                } else {
                    $message = 'Compte créé, mais l’email n’a pas pu être envoyé. Vérifiez la configuration mail.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE users SET actif = 1 - actif WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($action === 'update_assignment') {
        $id = (int)$_POST['id'];
        $role = $_POST['role'] ?? 'tdm';
        $fieldId = ($_POST['field_id'] ?? '') !== '' ? (int)$_POST['field_id'] : null;

        if (!in_array($role, ['admin', 'tdm'], true)) {
            $message = 'Rôle invalide.';
        } else {
            if ($role === 'admin') {
                $fieldId = null;
            }

            if ($fieldId !== null) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM fields WHERE id = ?');
                $stmt->execute([$fieldId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $fieldId = null;
                }
            }

            $pdo->prepare('UPDATE users SET role = ?, field_id = ? WHERE id = ?')->execute([$role, $fieldId, $id]);

            $currentUser = currentUser();
            if ($currentUser && (int)$currentUser['id'] === $id) {
                $_SESSION['user']['role'] = $role;
                $_SESSION['user']['field_id'] = $fieldId;
            }

            $message = 'Rôle et terrain mis à jour.';
        }
    }
}

$fields = $pdo->query('SELECT * FROM fields ORDER BY id')->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY actif desc, role, username')->fetchAll();
$roleLabels = [
    'tdm' => 'TdM',
    'admin' => 'Admin'
];

$pageTitle = 'Table de marque';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Table de marque</h2>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Créer un compte</h5>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="username" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nom affiché</label>
          <input type="text" name="nom_affichage" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Rôle</label>
          <select name="role" class="form-select">
            <?php foreach ($roleLabels as $value => $label): ?>
              <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Terrain assigné</label>
          <select name="field_id" class="form-select">
            <option value="">— Aucun —</option>
            <?php foreach ($fields as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-success w-100">Créer</button>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-sm bg-white">
      <thead><tr><th>Nom</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><a href="mailto:<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['nom_affichage'] ?: $u['username']) ?></a><br>
          <?= $u['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
          <td class="d-flex flex-column flex-sm-row gap-1">
            <form method="post" class="d-flex gap-1 flex-wrap align-items-center">
              <input type="hidden" name="action" value="update_assignment">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <select name="role" class="form-select form-select-sm" style="min-width:90px;" aria-label="Rôle">
                <?php foreach ($roleLabels as $value => $label): ?>
                  <option value="<?= $value ?>"<?= $value === $u['role'] ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="field_id" class="form-select form-select-sm" style="min-width:130px;" aria-label="Terrain assigné">
                <option value="">— Aucun —</option>
                <?php foreach ($fields as $f): ?>
                  <option value="<?= $f['id'] ?>"<?= $f['id'] == $u['field_id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['nom']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-secondary icon-btn" type="submit" title="Modifier le rôle et le terrain" aria-label="Modifier le rôle et le terrain">
                <i class="bi bi-pencil-square"></i>
              </button>
            </form>
            <form method="post">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary icon-btn" type="submit" title="<?= $u['actif'] ? 'Désactiver le compte' : 'Activer le compte' ?>" aria-label="<?= $u['actif'] ? 'Désactiver le compte' : 'Activer le compte' ?>">
                <i class="bi <?= $u['actif'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
              </button>
            </form>
            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte ? Cette action est irréversible.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-outline-danger icon-btn" type="submit" title="Supprimer le compte" aria-label="Supprimer le compte">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
