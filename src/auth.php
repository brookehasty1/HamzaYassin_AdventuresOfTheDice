<?php
declare(strict_types = 1);

function validate_registration(string $username, string $password, string $confirm): array {
    $errors = [];

    /** Username */
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3 - 20 characters that consist of letters, numbers, or underscores.';
    } elseif (isset($_SESSION['users'][strtolower($username)])) {
        $errors[] = 'Username is already taken.';
    }

    /** Password (bcrypt max is 72 bytes) */
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Passwords must be 8 - 72 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    return $errors;
}

/** Actually create user and put in user array */
function register_user(string $username, string $password): void {
    $_SESSION['users'][strtolower($username)] = [
        'username' => $username, /** Keep username same */
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
        'created'  => time(),
    ];
}

function attempt_login(string $username, string $password): bool {
    static $dummy = null;
    $user = $_SESSION['users'][strtolower($username)] ?? null;

    /** Fake hash to hide username */
    $hash = $user['hash'] ?? ($dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT));
    $same = password_verify($password, $hash);

    if ($user === null || !$same) {
        return false;
    }

    /** Rehash if algo changes */
    if (password_needs_rehash($user['hash'], PASSWORD_DEFAULT)) {
        $_SESSION['users'][strtolower($username)]['hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    /** Del old session */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = $user['username'];
    return true;
}

/** Save active session and give new id for security */
function logout_user(): void {
    unset($_SESSION['user'], $_SESSION['csrf']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
