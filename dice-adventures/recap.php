<?php
/**
 * 4. Adventure Recap — recap.php
 * Purpose: end-of-game story (events log) + AI path analysis.
 * Session: R events_log[], roll_history[], winner, start_time, difficulty
 * Forms:   POST action.php (save) → leaderboard.php · POST action.php (reset) → setup.php
 */
$pageTitle = 'Adventure Recap';
$active    = 'recap';
require_once __DIR__ . '/includes/app.php';
page_header($pageTitle, $active);

$r     = demo_recap();
$icons = ['snake' => '🐍', 'ladder' => '🪜', 'event' => '🌀', 'win' => '🏆'];

/* ---- path chart geometry ---- */
$w = 520; $h = 240; $padL = 34; $padB = 26; $padT = 10; $padR = 10;
$maxTurn = max(count($r['path']) - 1, 1);
$x = fn($t) => round($padL + $t / $maxTurn * ($w - $padL - $padR), 1);
$y = fn($c) => round($padT + (1 - $c / 100) * ($h - $padT - $padB), 1);
$line = fn(array $pts) => implode(' ', array_map(fn($t, $c) => $x($t) . ',' . $y($c), array_keys($pts), $pts));
?>

<section class="winner-banner" aria-labelledby="winner-title">
    <span class="trophy" aria-hidden="true">🏆</span>
    <div>
        <p class="eyebrow">Adventure complete</p>
        <h1 id="winner-title"><?= e($r['winner']) ?> wins!</h1>
        <p><?= LEVELS[$r['level']]['label'] ?> board · reached cell 100</p>
    </div>
    <div class="stat-row">
        <div class="stat"><b><?= $r['rolls'] ?></b><span>rolls</span></div>
        <div class="stat"><b><?= $r['time'] ?></b><span>time played</span></div>
        <div class="stat"><b><?= count($r['timeline']) - 1 ?></b><span>board events</span></div>
    </div>
</section>

<div class="recap-grid">
    <section class="card" aria-labelledby="timeline-title">
        <h2 id="timeline-title">Adventure timeline</h2>
        <ol class="timeline">
            <?php foreach ($r['timeline'] as $ev): ?>
                <li class="t-<?= $ev['type'] ?>">
                    <span class="ico" aria-hidden="true"><?= $icons[$ev['type']] ?></span>
                    <span><span class="turn">Turn <?= $ev['turn'] ?></span><?= e($ev['text']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="card" aria-labelledby="path-title">
        <p class="eyebrow">🤖 AI Path Analysis</p>
        <h2 id="path-title">Your path vs the optimal path</h2>
        <svg class="path-chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img"
             aria-label="Line chart: <?= e($r['winner']) ?> took <?= $r['rolls'] ?> rolls; the optimal path needs <?= $r['optimal'] ?>.">
            <g class="grid">
                <?php foreach ([0, 25, 50, 75, 100] as $c): ?>
                    <line x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= $y($c) ?>" y2="<?= $y($c) ?>"/>
                    <text x="<?= $padL - 6 ?>" y="<?= $y($c) + 4 ?>" text-anchor="end"><?= $c ?></text>
                <?php endforeach; ?>
                <?php foreach ([0, 5, 10, 15, $maxTurn] as $t): ?>
                    <text x="<?= $x($t) ?>" y="<?= $h - 6 ?>" text-anchor="middle"><?= $t ?></text>
                <?php endforeach; ?>
            </g>
            <polyline class="optimal" points="<?= $line($r['optimal_path']) ?>"/>
            <polyline class="actual" points="<?= $line($r['path']) ?>"/>
            <?php
            $prev = null;
            foreach ($r['path'] as $t => $c) {
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
            <li><span class="ln"></span><?= e($r['winner']) ?>'s path</li>
            <li><span class="ln ln--opt"></span>Optimal path</li>
            <li><span class="sw" style="background:var(--snake);border-radius:50%"></span>Snake</li>
            <li><span class="sw" style="background:var(--ladder);border-radius:50%"></span>Ladder / jump</li>
        </ul>
        <div class="analysis">
            <div class="a-snake"><b><?= $r['turns_lost'] ?></b><span>turns lost to <?= $r['snakes_hit'] ?> snakes</span></div>
            <div class="a-ladder"><b>+<?= $r['ladder_cells'] ?></b><span>cells gained from ladders</span></div>
            <div class="a-opt"><b><?= $r['optimal'] ?></b><span>rolls on the optimal path</span></div>
        </div>
    </section>
</div>

<div class="btn-row recap-actions">
    <form action="action.php" method="post">
        <input type="hidden" name="action" value="save">
        <button class="btn btn--primary btn--lg" type="submit">💾 Save to Leaderboard</button>
    </form>
    <form action="action.php" method="post">
        <input type="hidden" name="action" value="reset">
        <button class="btn btn--lg" type="submit">Play Again</button>
    </form>
</div>

<?php page_footer(); ?>
