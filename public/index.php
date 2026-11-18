<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

/** Logged in: go to the game (or setup). Logged out: show the landing page. */
$user = current_user();
if ($user !== null) {
    redirect(current_game($user) !== null ? 'game.php' : 'setup.php');
}

$levels = board_levels();
page_header('Welcome', '', '', false);
?>
<section class="hero">
  <div>
    <p class="eyebrow">Snakes &amp; ladders, reimagined</p>
    <h1>Adventures of the <span>Dice</span></h1>
    <p class="lead">Race to cell 100 across snakes, ladders and mysterious event cells. A transparent narrator tells the story of every move, but never touches the dice.</p>
    <div class="hero-actions">
      <a class="btn btn--primary btn--lg" href="register.php">Create an account</a>
      <a class="btn btn--ghost" href="login.php">Log in</a>
      <p class="hint">Free to play. One or two players.</p>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <?= render_board_html($levels['standard'], [], -1, true) ?>
    <?= die_html(5, 'die--5') ?>
    <?= die_html(3, 'die--3') ?>
  </div>
</section>

<ol class="steps">
  <li><strong>Register</strong><span>Make an account to keep your scores.</span></li>
  <li><strong>Pick a level</strong><span>Beginner, Standard or Expert.</span></li>
  <li><strong>Roll</strong><span>Fair dice, every time.</span></li>
  <li><strong>Reach 100</strong><span>Fewest rolls tops the leaderboard.</span></li>
</ol>

<h2 class="section-title">Choose your difficulty</h2>
<div class="levels">
  <?php
  $blurbs = [
      'beginner' => 'Gentle slopes, few snakes and plenty of ladders.',
      'standard' => 'A balanced board with a bit of everything.',
      'expert'   => 'Snakes everywhere. Bring luck.',
  ];
  foreach ($levels as $key => $b): ?>
    <div class="level-card level-card--<?= e($key) ?>">
      <h3><?= e($b['label']) ?></h3>
      <p><?= e($blurbs[$key] ?? '') ?></p>
      <div class="level-stats">
        <span>🐍 <?= count($b['snakes']) ?></span>
        <span>🪜 <?= count($b['ladders']) ?></span>
        <span>✨ <?= count($b['events']) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php page_footer();
