<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user   = require_login();
$errors = [];
$old    = ['level' => 'standard', 'player_count' => '1', 'player1' => $user, 'player2' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array_merge($old, array_map('strval', array_filter($_POST, 'is_scalar')));

    if (!csrf_valid()) {
        $errors[] = 'Your form has expired. Please try again.';
    } else {
        [$errors, $clean] = validate_setup($_POST, $user);
        if ($errors === []) {
            unset($_SESSION['last_event'], $_SESSION['events_log']);
            $_SESSION['game'] = new_game($clean['players'], $clean['level'], $user);
            redirect('game.php');
        }
    }
}

$levels = board_levels();
page_header('New game', 'setup', 'Pick a difficulty and who is playing.');
error_list($errors);
?>
<div class="setup-grid">
  <form class="card" method="post" action="setup.php" novalidate>
    <?= csrf_field() ?>
    <fieldset>
      <legend>Difficulty</legend>
      <div class="choice-group choice-group--3">
        <?php foreach ($levels as $key => $b): ?>
          <div class="choice">
            <input type="radio" id="level-<?= e($key) ?>" name="level" value="<?= e($key) ?>" <?= ($old['level'] ?? '') === $key ? 'checked' : '' ?>>
            <label for="level-<?= e($key) ?>">
              <strong><?= e($b['label']) ?></strong>
              <span><?= count($b['snakes']) ?> snakes · <?= count($b['ladders']) ?> ladders · <?= count($b['events']) ?> events</span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>Players</legend>
      <div class="choice-group choice-group--2">
        <div class="choice">
          <input type="radio" id="count-1" name="player_count" value="1" <?= ($old['player_count'] ?? '') === '1' ? 'checked' : '' ?>>
          <label for="count-1"><strong>1 player</strong><span>Beat the board</span></label>
        </div>
        <div class="choice">
          <input type="radio" id="count-2" name="player_count" value="2" <?= ($old['player_count'] ?? '') === '2' ? 'checked' : '' ?>>
          <label for="count-2"><strong>2 players</strong><span>Alternate turns</span></label>
        </div>
      </div>

      <div class="field">
        <label for="player1"><span class="player-dot player-dot--1"></span>Player 1 name</label>
        <input class="input" id="player1" name="player1" value="<?= e($old['player1'] ?? '') ?>" maxlength="20">
      </div>
      <div class="field field--p2">
        <label for="player2"><span class="player-dot player-dot--2"></span>Player 2 name</label>
        <input class="input" id="player2" name="player2" value="<?= e($old['player2'] ?? '') ?>" maxlength="20">
        <span class="help">Only used for 2-player games.</span>
      </div>
    </fieldset>

    <button class="btn btn--primary btn--lg" type="submit">Start game</button>
  </form>

  <aside class="card preview-card" aria-labelledby="preview-h">
    <p class="eyebrow">Preview</p>
    <h2 id="preview-h">Standard board</h2>
    <div class="preview-stats">
      <?= level_badge('standard', 'Standard') ?>
    </div>
    <?= render_board_html($levels['standard'], [], -1, true) ?>
    <?= board_legend() ?>
    <p class="small muted">Snakes and ladders move around with the difficulty you pick.</p>
  </aside>
</div>
<?php page_footer();
