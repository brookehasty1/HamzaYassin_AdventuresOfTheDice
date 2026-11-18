<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

if (current_user() !== null) {
    redirect('index.php');
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your form has expired. Please try again.';
    } else {
        $errors = validate_registration($username, $password, $confirm);
        if ($errors === []) {
            register_user($username, $password);
            flash('success', 'Account created. Please log in.');
            redirect('login.php');
        }
    }
}

page_header('Register', 'register', 'Create an account to track your scores.');
error_list($errors);
?>
<div class="auth-wrap">
  <form class="card" method="post" action="register.php" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input class="input" id="username" name="username" value="<?= e($username) ?>" required minlength="3" maxlength="20" autocomplete="username">
      <span class="help">3 - 20 letters, numbers, or underscores.</span>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input class="input" id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
      <span class="help">Between 8 and 72 characters.</span>
    </div>

    <div class="field">
      <label for="confirm">Confirm password</label>
      <input class="input" id="confirm" name="confirm" type="password" required autocomplete="new-password">
    </div>

    <button class="btn btn--primary btn--block" type="submit">Create account</button>
  </form>
  <p class="auth-switch">Already registered? <a href="login.php">Log in</a></p>
</div>
<?php page_footer();
