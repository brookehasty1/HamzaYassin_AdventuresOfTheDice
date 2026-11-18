<?php
/**
 * 4. Adventure Recap — recap.php
 * Purpose: end-of-game story (events log) + AI path analysis.
 * Session: R $_SESSION['game'] (moves[], events_log[], winner, roll_counts[], started_at/ended_at)
 * Forms:   POST action.php (save) → leaderboard.php · POST action.php (reset) → setup.php
 */
$pageTitle = 'Adventure Recap';
$active    = 'recap';
require_once __DIR__ . '/includes/app.php';

$user = require_login();
$game = current_game($user);
if ($game === null) {
    flash('error', 'Start a game first.');
    redirect('setup.php');
}
if ($game['status'] !== 'finished') {
    redirect('game.php');
}

$board  = board_for($game['level']);
$w      = $game['winner'];
$winner = $game['players'][$w];
$multi  = count($game['players']) > 1;

/* ---- build the timeline + winner's path from the back-end move records ---- */
$timeline = [];
$path     = [0];          // winner's cell after each of their rolls (0 = start)
$snakesHit = $cellsLost = $ladderCells = 0;

foreach ($game['moves'] as $m) {
    $who = $multi ? $m['name'] . ': ' : '';
    if ($m['hazard'] !== null) {
        $h = $m['hazard'];
        $timeline[] = ['turn' => $m['turn'], 'type' => $h['type'], 'ico' => $h['type'] === 'snake' ? '🐍' : '🪜',
                       'text' => $who . ucfirst($h['type']) . ' ' . $h['from'] . ' → ' . $h['to']];
    }
    if ($m['event'] !== null) {
        $ev  = $m['event'];
        $txt = EVENT_TYPES[$ev['type']]['label'] . ' at ' . $ev['cell'];
        if ($ev['to'] !== $ev['cell']) { $txt .= ' → ' . $ev['to']; }
        if ($ev['extra_roll'])         { $txt .= ' (extra roll)'; }
        if ($ev['skip'])               { $txt .= ' (misses a turn)'; }
        $timeline[] = ['turn' => $m['turn'], 'type' => 'event', 'ico' => EVENT_TYPES[$ev['type']]['icon'], 'text' => $who . $txt];
    }
    if ($m['won']) {
        $timeline[] = ['turn' => $m['turn'], 'type' => 'win', 'ico' => '🏆', 'text' => $who . 'Reached cell 100 🏆'];
    }

    if ($m['player'] === $w) {
        $path[] = $m['final'];
        if (($m['hazard']['type'] ?? '') === 'snake')  { $snakesHit++; $cellsLost += $m['hazard']['from'] - $m['hazard']['to']; }
        if (($m['hazard']['type'] ?? '') === 'ladder') { $ladderCells += $m['hazard']['to'] - $m['hazard']['from']; }
    }
}

$rolls      = $game['roll_counts'][$w];
$seconds    = max(0, ($game['ended_at'] ?? time()) - $game['started_at']);
$optimal    = optimal_path($board);
$turnsLost  = (int) round($cellsLost / 3.5);   // an average roll moves 3.5 cells
$eventCount = count($timeline) - 1;

/* ---- path chart geometry ---- */
$cw = 520; $ch = 240; $padL = 34; $padB = 26; $padT = 10; $padR = 10;
$maxTurn = max(count($path) - 1, count($optimal) - 1, 1);
$step    = max(1, (int) ceil($maxTurn / 4));
$ticks   = array_unique([...range(0, $maxTurn, $step), $maxTurn]);
$x = fn($t) => round($padL + $t / $maxTurn * ($cw - $padL - $padR), 1);
$y = fn($c) => round($padT + (1 - $c / 100) * ($ch - $padT - $padB), 1);
$line = fn(array $pts) => implode(' ', array_map(fn($t, $c) => $x($t) . ',' . $y($c), array_keys($pts), $pts));

layout_header($pageTitle, $active);
?>

<section class="winner-banner" aria-labelledby="winner-title">
    <span class="trophy" aria-hidden="true">🏆</span>
    <div>
        <p class="eyebrow">Adventure complete</p>
        <h1 id="winner-title"><?= e($winner) ?> wins!</h1>
        <p><?= LEVELS[$game['level']]['label'] ?> board · reached cell 100</p>
    </div>
    <div class="stat-row">
        <div class="stat"><b><?= $rolls ?></b><span>rolls</span></div>
        <div class="stat"><b><?= mmss($seconds) ?></b><span>time played</span></div>
        <div class="stat"><b><?= $eventCount ?></b><span>board events</span></div>
    </div>
</section>

<div class="recap-grid">
    <section class="card" aria-labelledby="timeline-title">
        <h2 id="timeline-title">Adventure timeline</h2>
        <ol class="timeline">
            <?php foreach ($timeline as $ev): ?>
                <li class="t-<?= $ev['type'] ?>">
                    <span class="ico" aria-hidden="true"><?= $ev['ico'] ?></span>
                    <span><span class="turn">Turn <?= $ev['turn'] ?></span><?= e($ev['text']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="card" aria-labelledby="path-title">
        <p class="eyebrow">🤖 AI Path Analysis</p>
        <h2 id="path-title">Your path vs the optimal path</h2>
        <svg class="path-chart" viewBox="0 0 <?= $cw ?> <?= $ch ?>" role="img"
             aria-label="Line chart: <?= e($winner) ?> took <?= $rolls ?> rolls; the optimal path needs <?= count($optimal) - 1 ?>.">
            <g class="grid">
                <?php foreach ([0, 25, 50, 75, 100] as $c): ?>
                    <line x1="<?= $padL ?>" x2="<?= $cw - $padR ?>" y1="<?= $y($c) ?>" y2="<?= $y($c) ?>"/>
                    <text x="<?= $padL - 6 ?>" y="<?= $y($c) + 4 ?>" text-anchor="end"><?= $c ?></text>
                <?php endforeach; ?>
                <?php foreach ($ticks as $t): ?>
                    <text x="<?= $x($t) ?>" y="<?= $ch - 6 ?>" text-anchor="middle"><?= $t ?></text>
                <?php endforeach; ?>
            </g>
            <polyline class="optimal" points="<?= $line($optimal) ?>"/>
            <polyline class="actual" points="<?= $line($path) ?>"/>
            <?php
            $prev = null;
            foreach ($path as $t => $c) {
                if ($prev !== null && $c < $prev) {
                    echo '<circle class="dot-snake" r="5" cx="' . $x($t) . '" cy="' . $y($c) . '"/>';
                } elseif ($prev !== null && $c - $prev > 6) {
                    echo '<circle class="dot-ladder" r="5" cx="' . $x($t) . '" cy="' . $y($c) . '"/>';
                }
                $prev = $c;
            }
            ?>
        </svg>
        <ul class="chart-legend">
            <li><span class="ln"></span><?= e($winner) ?>'s path</li>
            <li><span class="ln ln--opt"></span>Optimal path</li>
            <li><span class="sw" style="background:var(--snake);border-radius:50%"></span>Snake / setback</li>
            <li><span class="sw" style="background:var(--ladder);border-radius:50%"></span>Ladder / jump</li>
        </ul>
        <div class="analysis">
            <div class="a-snake"><b><?= $turnsLost ?></b><span>turns lost to <?= $snakesHit ?> snake<?= $snakesHit === 1 ? '' : 's' ?></span></div>
            <div class="a-ladder"><b>+<?= $ladderCells ?></b><span>cells gained from ladders</span></div>
            <div class="a-opt"><b><?= count($optimal) - 1 ?></b><span>rolls on the optimal path</span></div>
        </div>
    </section>
</div>

<div class="btn-row recap-actions">
    <?php if ($game['recorded']): ?>
        <a class="btn btn--primary btn--lg" href="leaderboard.php">✅ Saved · View Leaderboard</a>
    <?php else: ?>
        <form action="action.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <button class="btn btn--primary btn--lg" type="submit">💾 Save to Leaderboard</button>
        </form>
    <?php endif; ?>
    <form action="action.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <button class="btn btn--lg" type="submit">Play Again</button>
    </form>
</div>

<?php layout_footer(); ?>
