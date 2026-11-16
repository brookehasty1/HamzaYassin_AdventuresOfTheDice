<?php 
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$curr_game = current_game($user);
if ($curr_game === null) {
    redirect('setup.php');
}
if ($curr_game['status'] !== 'finished') {
    redirect('game.php');
}

$winner = $curr_game['players'][$curr_game['winner']];
$seconds = max(0, ($curr_game['ended_at'] ?? time()) - $curr_game['started_at']);

page_header('Adventure Recap');
?>
<p class="flash success" role="status">🏆 <strong><?= e($winner) ?></strong> wins on <?= e(board_for($curr_game['level'])['label']) ?>
  after <?= (int)$curr_game['roll_counts'][$curr_game['winner']] ?> rolls (<?= e(fmt_duration($seconds)) ?> played).</p>

<p>
  <a href="leaderboard.php?level=<?= e($curr_game['level']) ?>">View the leaderboard</a> ·
  <a href="setup.php">Play again</a>
</p>

<section aria-labelledby="events-h">
  <h2 id="events-h">Every event this adventure</h2>
  <?php if ($curr_game['events_log'] === []): ?>
    <p class="muted">No board events were triggered.</p>
  <?php else: ?>
    <ol>
    <?php foreach ($curr_game['events_log'] as $ev): ?>
      <li>Turn <?= (int)$ev['turn'] ?>, cell <?= (int)$ev['cell'] ?>: <?= e($ev['text']) ?></li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<section aria-labelledby="final-h">
  <h2 id="final-h">Final board</h2>
  <?= render_board($curr_game) ?>
</section>
<?php page_footer();