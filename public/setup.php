<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user   = require_login();
$errors = [];
$prev_input    = ['level' => 'standard', 'player1' => $user, 'player2' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prev_input = array_merge($prev_input, array_map('strval', array_filter($_POST, 'is_scalar')));

    if (!csrf_valid()) {
        $errors[] = 'Your form has expired. Please try again.';
    } else {
        [$errors, $clean] = validate_setup($_POST, $user);
        if ($errors === []) {
            $_SESSION['game'] = new_game($clean['players'], $clean['level'], $user);
            redirect('game.php');
        }
    }
}

page_header('New game');
error_list($errors);
?>
<form method="post" action="setup.php" novalidate>
  <?= csrf_field() ?>
  <fieldset>
    <legend>Difficulty</legend>
    <?php foreach (board_levels() as $key => $b): ?>
      <label class="choice">
        <input type="radio" name="level" value="<?= e($key) ?>" <?= $prev_input['level'] === $key ? 'checked' : '' ?>>
        <?= e($b['label']) ?> — <?= count($b['snakes']) ?> snakes, <?= count($b['ladders']) ?> ladders, <?= count($b['events']) ?> event cells
      </label>
    <?php endforeach; ?>
  </fieldset>

  <fieldset>
    <legend>Players</legend>
    <label for="player1">Player 1 name</label>
    <input id="player1" name="player1" value="<?= e($prev_input['player1']) ?>" maxlength="20">
    <label for="player2">Player 2 name (leave blank for a solo game)</label>
    <input id="player2" name="player2" value="<?= e($prev_input['player2']) ?>" maxlength="20">
  </fieldset>

  <button type="submit">Start game</button>
</form>
<?php page_footer();