<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pdo = db();

function handleLogoUpload(array $file, string $uploadFolder = 'uploads/teams'): ?string {
    if (empty($file['name'])) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','svg'])) return null;
    $dir = __DIR__ . '/../' . $uploadFolder;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = uniqid('upload_') . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $name);
    return $uploadFolder . '/' . $name;
}

function teamIsEngaged(PDO $pdo, int $teamId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM pool_teams WHERE team_id = ? LIMIT 1');
    $stmt->execute([$teamId]);
    if ($stmt->fetch()) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM matches WHERE team1_id = ? OR team2_id = ? LIMIT 1');
    $stmt->execute([$teamId, $teamId]);
    return (bool) $stmt->fetch();
}

function clubHasEngagedTeams(PDO $pdo, int $clubId): bool {
    $stmt = $pdo->prepare('SELECT pt.id FROM pool_teams pt JOIN teams t ON t.id = pt.team_id WHERE t.club_id = ? LIMIT 1');
    $stmt->execute([$clubId]);
    if ($stmt->fetch()) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT m.id FROM matches m JOIN teams t ON t.id = m.team1_id WHERE t.club_id = ? LIMIT 1');
    $stmt->execute([$clubId]);
    if ($stmt->fetch()) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT m.id FROM matches m JOIN teams t ON t.id = m.team2_id WHERE t.club_id = ? LIMIT 1');
    $stmt->execute([$clubId]);
    return (bool) $stmt->fetch();
}

$message = null;
$error = null;
$shouldRedirect = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSubmitToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_club') {
        $logo = handleLogoUpload($_FILES['logo_club'] ?? [], 'uploads/clubs');
        $clubName = trim((string)($_POST['nom_club'] ?? ''));
        if ($clubName !== '') {
            $stmt = $pdo->prepare('INSERT INTO clubs (nom_club, logo_path) VALUES (?,?)');
            $stmt->execute([$clubName, $logo]);
        }
    } elseif ($action === 'edit_club') {
      $id = (int)($_POST['id'] ?? 0);
      $clubName = trim((string)($_POST['nom_club'] ?? ''));
      if ($clubName !== '' && $id > 0) {
        if (!empty($_FILES['logo_club']['name'])) {
          $logo = handleLogoUpload($_FILES['logo_club'] ?? [], 'uploads/clubs');
          $stmt = $pdo->prepare('UPDATE clubs SET nom_club = ?, logo_path = ? WHERE id = ?');
          $stmt->execute([$clubName, $logo, $id]);
        } else {
          $stmt = $pdo->prepare('UPDATE clubs SET nom_club = ? WHERE id = ?');
          $stmt->execute([$clubName, $id]);
        }
      }
    } elseif ($action === 'add') {
        $clubName = trim((string)($_POST['nom_club'] ?? ''));
        $stmt = $pdo->prepare('INSERT INTO teams (category_id, club_id, nom_equipe) VALUES (?,?,?)');
        $stmt->execute([$_POST['category_id'], $_POST['club_id'] ?: null, $_POST['nom_equipe']]);
    } elseif ($action === 'edit_team') {
        $teamId = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $clubId = (int)($_POST['club_id'] ?? 0);
        $teamName = trim((string)($_POST['nom_equipe'] ?? ''));
        if ($teamId > 0 && $categoryId > 0 && $clubId > 0 && $teamName !== '') {
            $stmt = $pdo->prepare('UPDATE teams SET category_id = ?, club_id = ?, nom_equipe = ? WHERE id = ?');
            $stmt->execute([$categoryId, $clubId, $teamName, $teamId]);
        }
    } elseif ($action === 'delete') {
        $teamId = (int)($_POST['id'] ?? 0);
        if ($teamId > 0 && teamIsEngaged($pdo, $teamId)) {
            $error = 'Impossible de supprimer cette équipe : elle est déjà engagée dans une poule ou un match.';
            $shouldRedirect = false;
        } else {
            $pdo->prepare('DELETE FROM teams WHERE id = ?')->execute([$teamId]);
        }
    } elseif ($action === 'delete_club') {
        $clubId = (int)($_POST['id'] ?? 0);
        if ($clubId > 0 && clubHasEngagedTeams($pdo, $clubId)) {
            $error = 'Impossible de supprimer ce club : il contient une ou plusieurs équipes déjà engagées dans une poule ou un match.';
            $shouldRedirect = false;
        } else {
            $pdo->prepare('DELETE FROM clubs WHERE id = ?')->execute([$clubId]);
        }
    }
    if ($shouldRedirect && empty($error)) {
        header('Location: teams' . (!empty($_GET['category_id']) ? '?category_id=' . (int)$_GET['category_id'] : ''));
        exit;
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$clubNames = $pdo->query('SELECT * FROM clubs ORDER BY nom_club')->fetchAll(PDO::FETCH_ASSOC);
$editClub = null;
if (!empty($_GET['edit_club_id'])) {
  $stmtEdit = $pdo->prepare('SELECT * FROM clubs WHERE id=?');
  $stmtEdit->execute([(int)$_GET['edit_club_id']]);
  $editClub = $stmtEdit->fetch();
}
$editTeam = null;
if (!empty($_GET['edit_team_id'])) {
  $stmtEditTeam = $pdo->prepare('SELECT * FROM teams WHERE id=?');
  $stmtEditTeam->execute([(int)$_GET['edit_team_id']]);
  $editTeam = $stmtEditTeam->fetch();
}
$filterCat = $_GET['category_id'] ?? '';
$sql = 'SELECT t.*, c.nom AS cat_nom, cl.nom_club AS nom_club, cl.logo_path AS club_logo_path FROM teams t JOIN clubs cl ON cl.id = t.club_id JOIN categories c ON c.id = t.category_id';
$params = [];
if ($filterCat !== '') { $sql .= ' WHERE t.category_id = ?'; $params[] = $filterCat; }
$sql .= ' ORDER BY c.id, cl.nom_club, t.nom_equipe';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teams = $stmt->fetchAll();

$pageTitle = 'Équipes';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/admin_nav.php';
?>
<div class="container">
  <h2 class="mb-4">Équipes engagées</h2>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Créer un club</h5>
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add_club">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-4">
          <label class="form-label">Nom du club</label>
          <input type="text" name="nom_club" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Logo du club</label>
          <input type="file" name="logo_club" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Créer</button>
        </div>
      </form>
    </div>
  </div>

    <?php if ($editClub): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h5>Modifier le club</h5>
        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="edit_club">
          <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
          <input type="hidden" name="id" value="<?= $editClub['id'] ?>">
          <div class="col-md-4">
            <label class="form-label">Nom du club</label>
            <input type="text" name="nom_club" class="form-control" value="<?= htmlspecialchars($editClub['nom_club']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Logo du club</label>
            <?php if ($editClub['logo_path']): ?><div class="mb-1"><img src="../<?= htmlspecialchars($editClub['logo_path']) ?>" style="height:40px; object-fit:contain"></div><?php endif; ?>
            <input type="file" name="logo_club" class="form-control form-control-sm">
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100">Enregistrer</button>
          </div>
          <div class="col-md-2">
            <a href="teams.php" class="btn btn-outline-secondary w-100">Annuler</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($editTeam): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h5>Modifier l'équipe</h5>
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="edit_team">
          <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
          <input type="hidden" name="id" value="<?= $editTeam['id'] ?>">
          <div class="col-md-3">
            <label class="form-label">Catégorie</label>
            <select name="category_id" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $editTeam['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Club</label>
            <select name="club_id" class="form-select" required>
              <?php foreach ($clubNames as $club): ?>
                <option value="<?= $club['id'] ?>" <?= $editTeam['club_id'] == $club['id'] ? 'selected' : '' ?>><?= htmlspecialchars($club['nom_club']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Nom équipe</label>
            <input type="text" name="nom_equipe" class="form-control" value="<?= htmlspecialchars($editTeam['nom_equipe']) ?>" required>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Clubs existants</h5>
      <table class="table mb-0">
        <thead><tr><th>Logo</th><th>Nom</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($clubNames as $club): ?>
            <tr>
              <td><?php if ($club['logo_path']): ?><img src="../<?= htmlspecialchars($club['logo_path']) ?>" style="height:40px; object-fit:contain"><?php endif; ?></td>
              <td><?= htmlspecialchars($club['nom_club']) ?></td>
              <td>
                <div class="d-flex gap-2">
                  <a href="teams.php?edit_club_id=<?= $club['id'] ?>" class="btn btn-sm btn-outline-primary icon-btn" title="Modifier le club" aria-label="Modifier le club">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                  <form method="post" onsubmit="return confirm('Supprimer ce club ? Cela supprimera les équipes liées.');">
                    <input type="hidden" name="action" value="delete_club">
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
                    <input type="hidden" name="id" value="<?= $club['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger icon-btn" type="submit" title="Supprimer le club" aria-label="Supprimer le club">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$clubNames): ?><tr><td colspan="3" class="text-muted">Aucun club.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Ajouter une équipe</h5>
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
        <div class="col-md-2">
          <label class="form-label">Catégorie</label>
          <select name="category_id" class="form-select" required>
            <option value="">Sélectionner une catégorie</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Club</label>
          <select name="club_id" class="form-select" required>
            <option value="">Sélectionner un club</option>
            <?php if (!$clubNames): ?><option value="">Aucun club enregistré</option><?php endif; ?>
            <?php foreach ($clubNames as $club): ?>
              <option value="<?= htmlspecialchars($club['id']) ?>" data-name="<?= htmlspecialchars($club['nom_club']) ?>"><?= htmlspecialchars($club['nom_club']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="nom_club" value="">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nom équipe</label>
          <select name="nom_equipe" class="form-select" required>
            <option value="">Sélectionner une équipe</option>
            <option value="#1">1</option>
            <option value="#2">2</option>
            <option value="#3">3</option>
            <option value="#4">4</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
  
  <div class="card mb-4">
  <div class="card-body">
  <form method="get" class="mb-3">
    <select name="category_id" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
      <option value="">Toutes les catégories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <table class="table mb-0">
    <thead><tr><th>Catégorie</th><th>Équipe</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($teams as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['cat_nom']) ?></td>
        <td><?= htmlspecialchars($t['nom_club']) ?> <small class="text-muted"><?= htmlspecialchars($t['nom_equipe']) ?></small></td>
        <td>
          <div class="d-flex gap-2">
            <a href="teams.php?edit_team_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary icon-btn" title="Modifier l'équipe" aria-label="Modifier l'équipe">
              <i class="bi bi-pencil-square"></i>
            </a>
            <form method="post" onsubmit="return confirm('Supprimer cette équipe ?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="submit_token" value="<?= htmlspecialchars(ensureSubmitToken()) ?>">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm btn-outline-danger icon-btn" type="submit" title="Supprimer l'équipe" aria-label="Supprimer l'équipe">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$teams): ?><tr><td colspan="6" class="text-center text-muted">Aucune équipe.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>