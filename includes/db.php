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
            ensureUserResetColumns($pdo);
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