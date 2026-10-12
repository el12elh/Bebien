<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Vérification du schéma une seule fois (le fichier drapeau évite les SHOW COLUMNS à chaque requête).
            // Pour relancer les vérifications : supprimer includes/.schema_ok
            $schemaFlag = __DIR__ . '/.schema_ok';
            if (!is_file($schemaFlag)) {
                ensureUserResetColumns($pdo);
                ensureCategoryColumns($pdo);
                ensureMatchTimerColumns($pdo);
                @file_put_contents($schemaFlag, date('c'));
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die('Erreur de connexion à la base de données. Vérifiez config. (' . $e->getMessage() . ')');
        }
    }
    return $pdo;
}

function ensureUserResetColumns(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users') as $row) {
        $columns[] = $row['Field'];
    }

    $alterStatements = [];
    if (!in_array('reset_token', $columns, true)) {
        $alterStatements[] = "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL AFTER password_hash";
    }
    if (!in_array('reset_expires', $columns, true)) {
        $alterStatements[] = 'ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL AFTER reset_token';
    }

    foreach ($alterStatements as $statement) {
        $pdo->exec($statement);
    }
}

/**
 * Paramètres de jeu propres à chaque catégorie :
 *  - nb_mi_temps     : nombre de mi-temps / périodes
 *  - duree_mi_temps  : durée d'une mi-temps (minutes)
 *  - pause_minutes   : pause entre deux matchs (minutes)
 *  - coef_classement : coefficient dans le classement final des clubs
 *
 * Migration automatique : les colonnes sont créées au premier chargement et
 * pré-remplies pour reproduire le comportement précédent (M10/M12 = 1 période
 * continue de 2x la durée configurée, M8 = nombre de mi-temps des paramètres),
 * avec les coefficients M8 x1, M10 x2, M12 x3.
 */
function ensureCategoryColumns(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM categories') as $row) {
        $columns[] = $row['Field'];
    }

    $addedTiming = false;
    $addedCoef = false;

    if (!in_array('nb_mi_temps', $columns, true)) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN nb_mi_temps INT NOT NULL DEFAULT 2');
        $addedTiming = true;
    }
    if (!in_array('duree_mi_temps', $columns, true)) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN duree_mi_temps INT NOT NULL DEFAULT 7');
        $addedTiming = true;
    }
    if (!in_array('pause_minutes', $columns, true)) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN pause_minutes INT NOT NULL DEFAULT 3');
        $addedTiming = true;
    }
    if (!in_array('coef_classement', $columns, true)) {
        $pdo->exec('ALTER TABLE categories ADD COLUMN coef_classement INT NOT NULL DEFAULT 1');
        $addedCoef = true;
    }

    if (!$addedTiming && !$addedCoef) {
        return;
    }

    // Anciens paramètres globaux (lus directement : getSetting() n'est pas utilisable ici)
    $legacy = [];
    foreach ($pdo->query("SELECT k, v FROM settings WHERE k IN ('duree_periode_minutes','nb_mi_temps','pause_entre_matchs_minutes')") as $row) {
        $legacy[$row['k']] = $row['v'];
    }
    $legacyPeriod = max(1, (int) ($legacy['duree_periode_minutes'] ?? 7));
    $legacyHalves = max(1, (int) ($legacy['nb_mi_temps'] ?? 2));
    $legacyPause = max(0, (int) ($legacy['pause_entre_matchs_minutes'] ?? 3));

    $update = $pdo->prepare('UPDATE categories SET nb_mi_temps = ?, duree_mi_temps = ?, pause_minutes = ?, coef_classement = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, nom, nb_mi_temps, duree_mi_temps, pause_minutes, coef_classement FROM categories')->fetchAll() as $cat) {
        $name = strtoupper(trim((string) $cat['nom']));
        $continuous = in_array($name, ['M10', 'M12'], true);

        $nb = (int) $cat['nb_mi_temps'];
        $duree = (int) $cat['duree_mi_temps'];
        $pause = (int) $cat['pause_minutes'];
        $coef = (int) $cat['coef_classement'];

        if ($addedTiming) {
            $nb = $continuous ? 1 : $legacyHalves;
            $duree = $continuous ? $legacyPeriod * 2 : $legacyPeriod;
            $pause = $legacyPause;
        }
        if ($addedCoef) {
            $coef = $name === 'M12' ? 3 : ($name === 'M10' ? 2 : 1);
        }
        $update->execute([$nb, $duree, $pause, $coef, (int) $cat['id']]);
    }
}

/**
 * Réglages de jeu d'une catégorie (nombre de mi-temps, durées, pause, coefficient).
 * Valeurs de repli si la catégorie est introuvable : 2 x 7 min, pause 3 min, coef 1.
 */
function getCategoryTiming(int $categoryId): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT id, nom, nb_mi_temps, duree_mi_temps, pause_minutes, coef_classement FROM categories') as $c) {
            $nb = max(1, (int) $c['nb_mi_temps']);
            $duree = max(1, (int) $c['duree_mi_temps']);
            $pause = max(0, (int) $c['pause_minutes']);
            $cache[(int) $c['id']] = [
                'nom' => $c['nom'],
                'nb_mi_temps' => $nb,
                'duree_mi_temps' => $duree,
                'duree_match' => $nb * $duree,
                'pause' => $pause,
                'creneau' => $nb * $duree + $pause,
                'coef' => max(1, (int) $c['coef_classement']),
            ];
        }
    }
    return $cache[$categoryId] ?? [
        'nom' => '',
        'nb_mi_temps' => 2,
        'duree_mi_temps' => 7,
        'duree_match' => 14,
        'pause' => 3,
        'creneau' => 17,
        'coef' => 1,
    ];
}

function ensureUserRoleEnum(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $column = $stmt->fetch();
    if (!$column) {
        return;
    }

    if (strpos($column['Type'], 'tdm') === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','tdm') NOT NULL DEFAULT 'tdm'");
    }
}

/** Récupère un paramètre de settings, avec valeur par défaut si absent */
function getSetting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache[$key] ?? $default;
}

function setSetting(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?');
    $stmt->execute([$key, $value, $value]);
}

function ensureFieldsCategoryColumn(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM fields') as $row) {
        $columns[] = $row['Field'];
    }

    if (!in_array('category_id', $columns, true)) {
        $pdo->exec('ALTER TABLE fields ADD COLUMN category_id INT DEFAULT NULL AFTER nom');
    }
}

function ensureMatchTimerColumns(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM matches') as $row) {
        $columns[] = $row['Field'];
    }

    $alterStatements = [];
    if (!in_array('timer_status', $columns, true)) {
        $alterStatements[] = "ALTER TABLE matches ADD COLUMN timer_status ENUM('idle','running','paused') NOT NULL DEFAULT 'idle' AFTER status";
    }
    if (!in_array('timer_period', $columns, true)) {
        $alterStatements[] = 'ALTER TABLE matches ADD COLUMN timer_period INT NOT NULL DEFAULT 1 AFTER timer_status';
    }
    if (!in_array('timer_remaining_seconds', $columns, true)) {
        $alterStatements[] = 'ALTER TABLE matches ADD COLUMN timer_remaining_seconds INT NOT NULL DEFAULT 0 AFTER timer_period';
    }
    if (!in_array('timer_started_at', $columns, true)) {
        $alterStatements[] = 'ALTER TABLE matches ADD COLUMN timer_started_at DATETIME DEFAULT NULL AFTER timer_remaining_seconds';
    }
    if (!in_array('timer_overtime', $columns, true)) {
        $alterStatements[] = 'ALTER TABLE matches ADD COLUMN timer_overtime TINYINT(1) NOT NULL DEFAULT 0 AFTER timer_started_at';
    }

    foreach ($alterStatements as $statement) {
        $pdo->exec($statement);
    }
}