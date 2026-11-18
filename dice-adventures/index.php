<?php
/**
 * 1. Home — index.php
 * Purpose: landing page; start or resume a game.
 * Session: R $_SESSION['game'] (enables "Continue Game")
 * Edge case E2: index.php?expired=1 shows the "session expired" banner.
 */
$pageTitle = 'Home';
$active    = 'home';
require_once __DIR__ . '/includes/app.php';
layout_header($pageTitle, $active);

$expired    = isset($_GET['expired']);
$hasGame    = !$expired && has_game_in_progress();
?>

<?php if ($expired): ?>
    <div class="alert alert--warn" role="alert">
        <span class="alert-icon" aria-hidden="true">⏱️</span>
        <div>
            <strong>Your game session has expired or could not be found.</strong>
            <p>We couldn't restore player positions. Start a new game to keep playing.</p>
        </div>
    </div>
<?php endif; ?>

<section class="hero" aria-labelledby="hero-title">
    <div>
        <p class="eyebrow">A server-driven board game</p>
        <h1 id="hero-title">Adventures of the <span>Dice</span></h1>
        <p class="lead">
            Roll the die, climb rickety ladders, dodge hissing serpents and trigger
            mysterious board events. First explorer to reach cell 100 claims the trophy.
        </p>
        <div class="hero-actions">
            <a class="btn btn--primary btn--lg" href="setup.php">🎲 New Game</a>
            <?php if ($hasGame): ?>
                <a class="btn" href="game.php">Continue Game</a>
            <?php else: ?>
                <span class="btn" aria-disabled="true">Continue Game</span>
                <p class="hint">No game in progress yet. Continue unlocks once you start one.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <?php draw_board(board_for('standard'), [43, 31], 0, ['P1', 'P2'], null, 'mini'); // decorative ?>
        <?= render_die(3) ?>
        <?= render_die(5) ?>
    </div>
</section>

<h2 class="section-title">How to play</h2>
<ol class="steps">
    <li><strong>Roll</strong><span>Press Roll Dice. PHP rolls a fair 1–6.</span></li>
    <li><strong>Move</strong><span>Your token hops forward that many cells.</span></li>
    <li><strong>Snakes &amp; ladders</strong><span>Land on a 🐍 head to slide down, on a 🪜 base to climb up.</span></li>
    <li><strong>Reach 100</strong><span>First to reach or pass cell 100 wins.</span></li>
</ol>

<h2 class="section-title">Pick your challenge</h2>
<div class="levels">
    <?php foreach (LEVELS as $key => $lvl): ?>
        <article class="level-card level-card--<?= $key ?>">
            <h3><?= $lvl['icon'] ?> <?= $lvl['label'] ?></h3>
            <div class="level-stats">
                <span>🐍 <?= count(board_for($key)['snakes']) ?> snakes</span>
                <span>🪜 <?= count(board_for($key)['ladders']) ?> ladders</span>
            </div>
            <p><?= e($lvl['blurb']) ?></p>
            <a class="btn btn--ghost" href="setup.php?level=<?= $key ?>">Play <?= $lvl['label'] ?></a>
        </article>
    <?php endforeach; ?>
</div>

<?php layout_footer(); ?>
