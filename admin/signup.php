<?php
/**
 * Script à exécuter UNE FOIS en ligne de commande pour créer le premier
 * compte administrateur (ou n'importe quel compte tdm) :
 *
 *   php signup mon_email mon_mot_de_passe "Nom affiché" admin
 *
 * Le 4e argument (rôle) est optionnel : "admin" (par défaut) ou "tdm".
 * Ce script est aussi accessible via un navigateur mais se désactive
 * automatiquement dès qu'un compte admin existe déjà, par sécurité.
 */
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
$existingAdmins = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];

if (php_sapi_name() === 'cli') {
    $username = $argv[1] ?? null;
    $password = $argv[2] ?? null;
    $nom = $argv[3] ?? null;
    $role = $argv[4] ?? 'admin';
    if (!$username || !$password) {
        echo "Usage: php signup <email> <mot_de_passe> [nom_affiche] [role]\n";
        exit(1);
    }

    $passwordStrength = validatePasswordStrength($password);
    if (!$passwordStrength['valid']) {
        echo 'Mot de passe trop faible : ' . $passwordStrength['message'] . "\n";
        exit(1);
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, nom_affichage) VALUES (?,?,?,?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $nom]);
    echo "Compte '$username' ($role) créé avec succès.\n";
    exit;
}

$pageTitle = 'Créer le premier administrateur';
$message = null;
$error = null;

if ($existingAdmins > 0) {
    $error = 'Un compte administrateur existe déjà. Ce formulaire est désactivé pour des raisons de sécurité.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $existingAdmins === 0) {
    requireSubmitToken();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nom = trim($_POST['nom'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Veuillez fournir une adresse email et un mot de passe.';
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez fournir une adresse email valide.';
    } else {
        $passwordStrength = validatePasswordStrength($password);
        if (!$passwordStrength['valid']) {
            $error = $passwordStrength['message'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, nom_affichage) VALUES (?,?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin', $nom ?: null]);
            $message = 'Compte administrateur créé avec succès. Vous pouvez maintenant vous connecter.';
            $existingAdmins = 1;
        }
    }
}

require_once __DIR__ . '/../includes/head.php';
?>
<div class="container d-flex align-items-center justify-content-center" style="min-height: 90vh;">
  <div class="card shadow-sm" style="max-width: 520px; width: 100%;">
    <div class="card-body p-4">
      <h4 class="mb-3 text-center"><i class="bi bi-person-plus"></i> Créer le premier administrateur</h4>
      <p class="text-muted text-center small">Ce formulaire est disponible uniquement tant qu'aucun administrateur n'existe.</p>
      <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if ($existingAdmins === 0): ?>
        <form method="post">
          <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="username" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" required>
            <div class="form-text">Au moins 12 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Nom affiché</label>
            <input type="text" name="nom" class="form-control">
          </div>
          <button type="submit" class="btn btn-success w-100">Créer le compte admin</button>
        </form>
      <?php else: ?>
        <div class="text-center mt-3">
          <a href="signin.php" class="btn btn-success">Connexion administrateur</a>
        </div>
      <?php endif; ?>

      <div class="text-center mt-3">
        <a href="../">&larr; Retour au site public</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
