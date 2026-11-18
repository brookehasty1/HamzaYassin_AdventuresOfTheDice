<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();

[$level, $notice] = leaderboard_filter($_GET);
if ($notice !== null) {
    flash('error', $notice);
}
$rows   = ranked_leaderboard($level);
$medals = ['🥇', '🥈', '🥉'];

page_header('Leaderboard', 'leaderboard', 'Fewest rolls wins; ties go to the shortest time played.');
?>
<ul class="filters" aria-label="Filter by level">
  <li><a href="leaderboard.php"<?= $level === null ? ' aria-current="true"' : '' ?>>All levels</a></li>
  <?php foreach (board_levels() as $key => $b): ?>
    <li><a href="leaderboard.php?level=<?= e($key) ?>"<?= $level === $key ? ' aria-current="true"' : '' ?>><?= e($b['label']) ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($rows === []): ?>
  <div class="empty-state">
    <?= die_html(6) ?>
    <h2>No finished games yet</h2>
    <p>Win a game and your score lands here.</p>
    <a class="btn btn--primary" href="setup.php">Play one</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="scores">
      <caption>Fewest rolls wins; ties go to the shortest time played.</caption>
      <thead>
        <tr><th scope="col">Rank</th><th scope="col">Winner</th><th scope="col">Level</th><th scope="col">Rolls</th><th scope="col">Time</th><th scope="col">Players</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $n => $r): ?>
        <tr<?= ($r['owner'] ?? '') === $user ? ' class="is-you"' : '' ?>>
          <td class="rank"><?= isset($medals[$n]) ? '<span class="medal">' . $medals[$n] . '</span>' : $n + 1 ?></td>
          <td class="player"><?= e($r['name']) ?></td>
          <td><?= level_badge($r['level'], board_levels()[$r['level']]['label']) ?></td>
          <td><?= (int)$r['rolls'] ?></td>
          <td><?= e(fmt_duration((int)$r['seconds'])) ?></td>
          <td><?= (int)$r['players'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php page_footer();
