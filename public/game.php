<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user      = require_login();
$curr_game = current_game($user);
if ($curr_game === null) {
    redirect('setup.php');
}
if ($curr_game['status'] === 'finished') {
    redirect('recap.php');
}

$board = board_for($curr_game['level']);
$i     = $curr_game['current'];
$name  = $curr_game['players'][$i];
$pos   = $curr_game['positions'][$i];
$last  = $curr_game['last_move'];

page_header('Game on', 'game', '', false);
?>
<div class="page-head">
  <p class="eyebrow"><?= level_badge($curr_game['level'], $board['label']) ?></p>
  <h1>Game on</h1>
</div>

<div class="game-layout">
  <div class="game-board-wrap">
    <?= render_board($curr_game) ?>
    <?= board_legend() ?>
  </div>

  <div class="game-side">
    <section class="card turn-card" aria-labelledby="turn-h">
      <div class="turn-head">
        <h2 id="turn-h">Turn <?= (int)$curr_game['turn_number'] + 1 ?></h2>
        <span class="muted small"><?= e($name) ?> at <?= $pos === 0 ? 'the start' : 'cell ' . (int)$pos ?></span>
      </div>

      <ul class="players">
        <?php foreach ($curr_game['players'] as $k => $p): ?>
          <li class="player-row player-row--<?= $k + 1 ?><?= $k === $i ? ' player-row--active' : '' ?>">
            <span class="token token--p<?= $k + 1 ?>">P<?= $k + 1 ?></span>
            <span class="name"><?= e($p) ?><?= $curr_game['skips'][$k] ? ' 💤' : '' ?></span>
            <span class="pos"><?= (int)$curr_game['positions'][$k] ?> / <?= BOARD_SIZE ?></span>
            <span class="progress"><span style="width: <?= min(100, (int)$curr_game['positions'][$k]) ?>%"></span></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="roll-area">
        <?= die_html($last !== null ? (int)$last['roll'] : 6) ?>
        <form method="post" action="roll.php">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--primary btn--roll btn--block">🎲 Roll the die</button>
        </form>
      </div>
    </section>

    <?php if ($last !== null): ?>
    <section class="card narrator" aria-labelledby="narrator-h">
      <p class="eyebrow" id="narrator-h">AI Narrator</p>
      <?php foreach ($last['narration'] as $line): ?>
        <p class="narrator-text"><?= e($line) ?></p>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="card" aria-labelledby="hist-h">
      <h2 id="hist-h">Roll history</h2>
      <?php if ($curr_game['roll_history'] === []): ?>
        <p class="muted">No rolls yet.</p>
      <?php else: ?>
        <ol class="rule-list">
        <?php foreach (array_slice(array_reverse($curr_game['roll_history']), 0, 10) as $h): ?>
          <li>Turn <?= (int)$h['turn'] ?>: <strong><?= e($curr_game['players'][$h['player']]) ?></strong> rolled <?= (int)$h['roll'] ?></li>
        <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <div class="game-tools">
      <a class="btn btn--ghost" href="setup.php">New game</a>
      <a class="btn btn--ghost" href="leaderboard.php">Leaderboard</a>
    </div>
  </div>
</div>
<?php page_footer();
