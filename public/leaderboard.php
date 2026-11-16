<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

require_login();

[$level, $notice] = leaderboard_filter($_GET);
if ($notice !== null) {
    flash('error', $notice);
}
$rows = ranked_leaderboard($level);

page_header('Leaderboard');
?>
<form method="get" action="leaderboard.php" class="inline">
  <label for="level">Show</label>
  <select id="level" name="level">
    <option value="all">All levels</option>
    <?php foreach (board_levels() as $key => $b): ?>
      <option value="<?= e($key) ?>" <?= $level === $key ? 'selected' : '' ?>><?= e($b['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
</form>

<?php if ($rows === []): ?>
  <p class="muted">No finished games yet. <a href="setup.php">Play one!</a></p>
<?php else: ?>
  <table>
    <caption>Fewest rolls wins; ties go to the shortest time played.</caption>
    <thead><tr><th scope="col">Rank</th><th scope="col">Winner</th><th scope="col">Level</th><th scope="col">Rolls</th><th scope="col">Time</th><th scope="col">Players</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $n => $r): ?>
      <tr>
        <th scope="row"><?= $n + 1 ?></th>
        <td><?= e($r['name']) ?></td>
        <td><?= e(board_levels()[$r['level']]['label']) ?></td>
        <td><?= (int)$r['rolls'] ?></td>
        <td><?= e(fmt_duration((int)$r['seconds'])) ?></td>
        <td><?= (int)$r['players'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php page_footer();