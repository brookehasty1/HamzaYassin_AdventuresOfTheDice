<?php
/**
 * 7. Log in — login.php
 * Purpose: sign in before playing (the back-end requires an account).
 * Form:    POST login.php → back-end attempt_login() → index.php
 */
$pageTitle = 'Log in';
$active    = 'login';
require_once __DIR__ . '/includes/app.php';

if (current_user() !== null) {
    redirect('index.php');
}

$errors   = [];
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Enter your username and password.';
    } elseif (!attempt_login($username, $password)) {
        $errors[] = 'Incorrect username or password.';
    } else {
        redirect('index.php');
    }
}

layout_header($pageTitle, $active);
?>

<div class="auth-wrap">
    <div class="page-head">
        <p class="eyebrow">Welcome back</p>
        <h1>Log in</h1>
        <p>Sign in to start or continue an adventure.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert--error" role="alert" id="error-summary" tabindex="-1">
            <span class="alert-icon" aria-hidden="true">⚠️</span>
            <div><strong><?= e($errors[0]) ?></strong></div>
        </div>
    <?php endif; ?>

    <form class="card" action="login.php" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input class="input" id="username" name="username" value="<?= e($username) ?>" required autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <div class="btn-row btn-row--end">
            <button class="btn btn--primary btn--lg" type="submit">Log in →</button>
        </div>
    </form>
    <p class="small muted auth-switch">No account yet? <a href="register.php">Create one</a>.</p>
</div>

<?php layout_footer(); ?>
