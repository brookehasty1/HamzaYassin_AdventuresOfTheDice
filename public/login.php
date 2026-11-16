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

page_header('Log in');
error_list($errors);
?>

<form method="post" action="login.php" novalidate>
  <?= csrf_field() ?>
  <label for="username">Username</label>
  <input id="username" name="username" value="<?= e($username) ?>" required autocomplete="username">

  <label for="password">Password</label>
  <input id="password" name="password" type="password" required autocomplete="current-password">

  <button type="submit">Log in</button>
</form>
<p>No account? <a href="register.php">Register</a>.</p>
<?php page_footer();