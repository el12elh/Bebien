<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensureSubmitToken(): string {
    if (empty($_SESSION['submit_token'])) {
        $_SESSION['submit_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['submit_token'];
}

function verifySubmitToken(?string $token): bool {
    if (!is_string($token)) {
        return false;
    }

    return hash_equals(ensureSubmitToken(), $token);
}

function requireSubmitToken(): void {
    if (!verifySubmitToken($_POST['submit_token'] ?? null)) {
        http_response_code(403);
        die('Jeton de sécurité invalide.');
    }
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function signin(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND actif = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function validatePasswordStrength(string $password): array {
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'au moins 12 caractères';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'une lettre minuscule';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'une lettre majuscule';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'un chiffre';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'un caractère spécial';
    }

    return [
        'valid' => empty($errors),
        'message' => empty($errors)
            ? null
            : 'Le mot de passe doit contenir ' . implode(', ', $errors) . '.',
    ];
}

function signout(): void {
    unset($_SESSION['user']);
    session_destroy();
}

/** Redirige si l'utilisateur n'a pas le rôle requis. $role peut être 'admin', 'tdm' */
function requireRole(?string $role = null): void {
    $user = currentUser();
    if (!$user) {
        header('Location: signin');
        exit;
    }
    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        die('Accès refusé.');
    }
}
