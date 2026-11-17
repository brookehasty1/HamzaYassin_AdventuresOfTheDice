<?php
/**
 * 6. Rules & Events — rules.php
 * Purpose: explain the rules and the four event-tile types.
 * Session: R game_active ("Back to Game" vs "Start a Game"). Writes nothing.
 */
$pageTitle = 'Rules & Events';
$active    = 'rules';
require_once __DIR__ . '/includes/app.php';
page_header($pageTitle, $active);

$hasGame = !empty($_SESSION['game_active']);
?>

<div class="page-head">
    <p class="eyebrow">Field guide</p>
    <h1>Rules &amp; board events</h1>
    <p>Everything you need to know before your first roll.</p>
</div>

<div class="rules-grid">
    <section class="card" aria-labelledby="how-title">
        <h2 id="how-title">How to play</h2>
        <ol class="rule-list">
            <li>Every piece starts on <strong>cell 1</strong>. Players take turns in order.</li>
            <li>Press <strong>Roll Dice</strong>. The server rolls a fair die (<code>rand(1, 6)</code>) and moves your piece.</li>
            <li>Land on a <strong>🐍 snake head</strong> and you slide down to its tail.</li>
            <li>Land on a <strong>🪜 ladder base</strong> and you climb to its top.</li>
            <li>Land on an <strong>event cell</strong> and the AI Narrator triggers a board event (see right).</li>
            <li>The <strong>Probability Map</strong> shows where you might land before you roll. It never changes your roll.</li>
            <li>First player to <strong>reach or pass cell 100</strong> wins and goes to the Adventure Recap.</li>
        </ol>
    </section>

    <section class="card" aria-labelledby="events-title">
        <h2 id="events-title">Event tiles</h2>
        <div class="event-grid">
            <?php foreach (EVENT_TYPES as $type => $t):
                $cells = array_keys(array_filter(EVENT_CELLS, fn($c) => $c['type'] === $type)); ?>
                <div class="event-tile">
                    <span class="ico" aria-hidden="true"><?= $t['icon'] ?></span>
                    <strong><?= $t['label'] ?></strong>
                    <span><?= $t['desc'] ?></span>
                    <small>Cell<?= count($cells) > 1 ? 's' : '' ?> <?= implode(', ', $cells) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="card" aria-labelledby="diff-title" style="margin-top:18px">
    <h2 id="diff-title">Difficulty levels</h2>
    <div class="table-wrap">
        <table class="diff-table">
            <thead><tr><th scope="col">Level</th><th scope="col">Snakes</th><th scope="col">Ladders</th><th scope="col">Event cells</th><th scope="col">Longest slide</th></tr></thead>
            <tbody>
                <?php foreach (LEVELS as $key => $lvl):
                    $b = BOARDS[$key];
                    $slides = array_map(fn($h, $t) => $h - $t, array_keys($b['snakes']), $b['snakes']); ?>
                    <tr>
                        <td><span class="badge badge--<?= $key ?>"><?= $lvl['label'] ?></span></td>
                        <td><?= count($b['snakes']) ?></td>
                        <td><?= count($b['ladders']) ?></td>
                        <td><?= count(EVENT_CELLS) ?></td>
                        <td><?= max($slides) ?> cells</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="btn-row btn-row--end" style="margin-top:22px">
    <?php if ($hasGame): ?>
        <a class="btn btn--primary btn--lg" href="game.php">← Back to Game</a>
    <?php else: ?>
        <a class="btn btn--primary btn--lg" href="setup.php">🎲 Start a Game</a>
    <?php endif; ?>
</div>

<?php page_footer(); ?>
