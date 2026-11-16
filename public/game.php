<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$curr_game = current_game($user);
if ($curr_game === null) {
    redirect('setup.php');
}

if ($curr_game['status'] === 'finished') {
    redirect('recap.php');
}

$board   = board_for($curr_game['level']);
$i       = $curr_game['current'];
$name    = $curr_game['players'][$i];
$pos     = $curr_game['positions'][$i];
$last    = $curr_game['last_move'];

page_header('Game on - ' . $board['label']);
?>
<p class="status" aria-live="polite">
  <strong>Turn <?= (int)$curr_game['turn_number'] + 1 ?>:</strong> <?= e($name) ?>
  (P<?= $i + 1 ?>) at <?= $pos === 0 ? 'the start' : 'cell ' . $pos ?>.
  <?php foreach ($curr_game['players'] as $k => $p): ?>
    <span class="chip">P<?= $k + 1 ?> <?= e($p) ?>: <?= (int)$curr_game['positions'][$k] ?><?= $curr_game['skips'][$k] ? ' 💤' : '' ?></span>
  <?php endforeach; ?>
</p>

<?php if ($last !== null): ?>
<section class="narrator" aria-labelledby="narrator-h">
  <h2 id="narrator-h">AI Narrator</h2>
  <?php foreach ($last['narration'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>

</section>
<?php endif; ?>

<form method="post" action="roll.php" class="roll-form">
  <?= csrf_field() ?>
  <button type="submit" class="primary">🎲 Roll the die — <?= e($name) ?></button>
</form>

<?= render_board($curr_game) ?>

<section aria-labelledby="hist-h">
  <h2 id="hist-h">Roll history</h2>
  <?php if ($curr_game['roll_history'] === []): ?>
    <p class="muted">No rolls yet.</p>
  <?php else: ?>
    <ol class="history">
    <?php foreach (array_slice(array_reverse($curr_game['roll_history']), 0, 10) as $history): ?>
      <li>Turn <?= (int)$history['turn'] ?> — <?= e($curr_game['players'][$history['player']]) ?> rolled <?= (int)$history['roll'] ?></li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>
<?php page_footer();
