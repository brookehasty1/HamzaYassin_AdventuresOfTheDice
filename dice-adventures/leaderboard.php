<?php
/**
 * 5. Leaderboard — leaderboard.php
 * Purpose: ranked results (fewest rolls, then fastest time).
 * Session: R last_score_id (highlights your row)
 * Filter:  GET leaderboard.php?level=beginner|standard|expert (unknown value → All)
 * Edge case E3: no rows for the filter (or no scores file) → empty state.
 */
$pageTitle = 'Leaderboard';
$active    = 'leaderboard';
require_once __DIR__ . '/includes/app.php';
page_header($pageTitle, $active);

$filter = $_GET['level'] ?? 'all';
if (!array_key_exists($filter, LEVELS)) {
    $filter = 'all';
}
$youId = $_SESSION['last_score_id'] ?? 41;

$rows = array_values(array_filter(demo_scores(), fn($s) => $filter === 'all' || $s['level'] === $filter));
usort($rows, fn($a, $b) => [$a['rolls'], $a['time']] <=> [$b['rolls'], $b['time']]);
$rows   = array_slice($rows, 0, 10);
$medals = ['🥇', '🥈', '🥉'];
?>

<div class="page-head">
    <p class="eyebrow">Hall of champions</p>
    <h1>Leaderboard</h1>
    <p>Ranked by fewest rolls to reach cell 100, then by fastest time.</p>
</div>

<ul class="filters" aria-label="Filter by difficulty">
    <li><a href="leaderboard.php" <?= $filter === 'all' ? 'aria-current="true"' : '' ?>>All</a></li>
    <?php foreach (LEVELS as $key => $lvl): ?>
        <li><a href="leaderboard.php?level=<?= $key ?>" <?= $filter === $key ? 'aria-current="true"' : '' ?>><?= $lvl['icon'] ?> <?= $lvl['label'] ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (!$rows): $lvl = LEVELS[$filter] ?? null; ?>
    <section class="empty-state" aria-labelledby="empty-title">
        <?= render_die(1) ?>
        <h2 id="empty-title">No <?= $lvl ? $lvl['label'] . ' ' : '' ?>games yet</h2>
        <p>
            <?= $filter === 'expert' ? 'Be the first to conquer all ' . count(BOARDS['expert']['snakes']) . ' snakes!' : 'Finish a game to claim the top spot.' ?>
        </p>
        <a class="btn btn--primary" href="setup.php<?= $lvl ? '?level=' . $filter : '' ?>">
            Start <?= $lvl ? 'an ' . $lvl['label'] : 'a' ?> Game
        </a>
    </section>
<?php else: ?>
    <div class="table-wrap">
        <table class="scores">
            <caption>Top <?= count($rows) ?> · <?= $filter === 'all' ? 'all difficulties' : LEVELS[$filter]['label'] ?></caption>
            <thead>
                <tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Level</th><th scope="col">Rolls</th><th scope="col">Time</th><th scope="col">Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $s): ?>
                    <tr class="<?= $s['id'] === $youId ? 'is-you' : '' ?>">
                        <td class="rank"><?= isset($medals[$i]) ? '<span class="medal" aria-hidden="true">' . $medals[$i] . '</span> ' : '' ?><?= $i + 1 ?></td>
                        <td class="player"><?= e($s['name']) ?></td>
                        <td><span class="badge badge--<?= $s['level'] ?>"><?= LEVELS[$s['level']]['label'] ?></span></td>
                        <td><?= $s['rolls'] ?></td>
                        <td><?= $s['time'] ?></td>
                        <td><time datetime="<?= $s['date'] ?>"><?= date('M j', strtotime($s['date'])) ?></time></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="btn-row btn-row--end" style="margin-top:10px">
        <a class="btn btn--primary" href="setup.php">🎲 Play Again</a>
    </div>
<?php endif; ?>

<?php page_footer(); ?>
