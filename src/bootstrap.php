<?php 
declare(strict_types = 1);

error_reporting(E_ALL);

require_once __DIR__ . '/board_config.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/leaderboard.php';
require_once __DIR__ . '/view.php';

function start_app_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('DICESESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; frame-ancestors 'none'");
    start_app_session();
}

/** Make input HTML readable */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

// CSRF FUNCTIONS (Hacking to behave as the user)

/** Get and generate users security token */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Makes token HTML friendly */
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Check if token matches token originally stored and is valid  */
function csrf_valid(): bool {
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

// FLASH MESSAGES

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array {
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Checks if user exists; If so return og session else return null */
function current_user(): ?string {
    $sess_val = $_SESSION['user'] ?? null;
    if (!is_string($sess_val) || !isset($_SESSION['users'][mb_strtolower($sess_val)])) {
        unset($_SESSION['user']);
        return null;
    }
    return $sess_val;
}

function require_login(): string {
    $user = current_user();
    if ($user === null) {
        flash('error', 'Please log in to play.');
        redirect('login.php');
    }
    return $user;
}

/** Return curent game if game found in sessions */
function current_game(string $user): ?array {
    $curr_game = $_SESSION['game'] ?? null;
    return (is_array($curr_game) && (($curr_game['owner'] ?? null) === $user) ? $curr_game : null);
}

function require_post(string $backTo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect($backTo);
    }
    
    if (!csrf_valid()) {
        flash('error', 'Your form expired. Please try again.');
        redirect($backTo);
    }
}
?>