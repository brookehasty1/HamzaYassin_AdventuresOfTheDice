<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user      = require_login();
$curr_game = current_game($user);
if ($curr_game === null) {
    redirect('setup.php');
}
if ($curr_game['status'] !== 'finished') {
    redirect('game.php');
}

$board   = board_for($curr_game['level']);
$wi      = (int)$curr_game['winner'];
$winner  = $curr_game['players'][$wi];
$seconds = max(0, ($curr_game['ended_at'] ?? time()) - $curr_game['started_at']);
$events  = $_SESSION['events_log'] ?? [];
$icons   = ['bonus' => '✨', 'penalty' => '🌫️', 'skip' => '💤', 'warp' => '🌀'];

page_header('Recap', '', '', false);
?>
<section class="winner-banner">
  <span class="trophy" aria-hidden="true">🏆</span>
  <div>
    <p class="eyebrow">Adventure complete</p>
    <h1><?= e($winner) ?> wins!</h1>
    <p>on <?= e($board['label']) ?></p>
  </div>
  <div class="stat-row">
    <div class="stat"><b><?= (int)$curr_game['roll_counts'][$wi] ?></b><span>Rolls</span></div>
    <div class="stat"><b><?= e(fmt_duration($seconds)) ?></b><span>Time</span></div>
    <div class="stat"><b><?= count($events) ?></b><span>Events</span></div>
  </div>
</section>

<div class="btn-row recap-actions">
  <a class="btn btn--primary" href="setup.php">Play again</a>
  <a class="btn btn--ghost" href="leaderboard.php?level=<?= e($curr_game['level']) ?>">View the leaderboard</a>
</div>

<div class="recap-grid" style="margin-top: 22px">
  <section class="card" aria-labelledby="events-h">
    <h2 id="events-h">Adventure Recap</h2>
    <p class="muted small">Every event that happened during this playthrough.</p>
    <?php if ($events === []): ?>
      <p class="muted">No board events were triggered.</p>
    <?php else: ?>
      <ol class="timeline">
        <?php foreach ($events as $ev): ?>
          <li class="t-event">
            <span class="ico" aria-hidden="true"><?= $icons[$ev['type']] ?? '✨' ?></span>
            <div><span class="turn">Turn <?= (int)$ev['turn'] ?> · cell <?= (int)$ev['cell'] ?></span><?= e($ev['text']) ?></div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="final-h">
    <h2 id="final-h">Final board</h2>
    <?= render_board($curr_game) ?>
    <?= board_legend() ?>
  </section>
</div>
<?php page_footer();
