<?php
/**
 * 8. Register — register.php
 * Purpose: create an account (back-end validate_registration() + register_user()).
 * Form:    POST register.php → login.php
 */
$pageTitle = 'Register';
$active    = 'register';
require_once __DIR__ . '/includes/app.php';

if (current_user() !== null) {
    redirect('index.php');
}

$errors   = [];
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    $errors = csrf_valid()
        ? validate_registration($username, $password, $confirm)
        : ['Your form has expired. Please try again.'];

    if ($errors === []) {
        register_user($username, $password);
        flash('success', 'Account created. Please log in.');
        redirect('login.php');
    }
}

layout_header($pageTitle, $active);
?>

<div class="auth-wrap">
    <div class="page-head">
        <p class="eyebrow">New explorer</p>
        <h1>Create an account</h1>
        <p>You need an account to play and to save scores to the leaderboard.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert--error" role="alert" id="error-summary" tabindex="-1">
            <span class="alert-icon" aria-hidden="true">⚠️</span>
            <div>
                <strong>Please fix <?= count($errors) ?> <?= count($errors) === 1 ? 'problem' : 'problems' ?></strong>
                <ul>
                    <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form class="card" action="register.php" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input class="input" id="username" name="username" value="<?= e($username) ?>" required
                   minlength="3" maxlength="20" autocomplete="username" aria-describedby="username-help">
            <span class="help" id="username-help">3–20 letters, numbers or underscores.</span>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" required
                   minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help">
            <span class="help" id="password-help">8–72 characters.</span>
        </div>
        <div class="field">
            <label for="confirm">Confirm password</label>
            <input class="input" id="confirm" name="confirm" type="password" required autocomplete="new-password">
        </div>
        <div class="btn-row btn-row--end">
            <button class="btn btn--primary btn--lg" type="submit">Create account →</button>
        </div>
    </form>
    <p class="small muted auth-switch">Already registered? <a href="login.php">Log in</a>.</p>
</div>

<?php layout_footer(); ?>
