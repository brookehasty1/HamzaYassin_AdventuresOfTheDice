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

page_header('Log in', 'login', 'Welcome back, adventurer.');
error_list($errors);
?>
<div class="auth-wrap">
  <form class="card" method="post" action="login.php" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input class="input" id="username" name="username" value="<?= e($username) ?>" required autocomplete="username">
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
    </div>

    <button class="btn btn--primary btn--block" type="submit">Log in</button>
  </form>
  <p class="auth-switch">No account? <a href="register.php">Register</a></p>
</div>
<?php page_footer();
