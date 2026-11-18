<?php
declare(strict_types = 1);

/**
 * Page chrome + shared HTML pieces. Class names come from assets/style.css.
 */

function page_header(string $title, string $active = '', string $lead = '', bool $showHeading = true): void {
    $user = current_user();
    $link = function (string $href, string $label, string $key) use ($active): string {
        $cur = $key === $active ? ' aria-current="page"' : '';
        return '<li><a href="' . $href . '"' . $cur . '>' . e($label) . '</a></li>';
    };
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Adventures of the Dice</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/addons.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="index.php">
      <span class="brand-die" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
      <span class="brand-text">Adventures of the Dice</span>
    </a>
    <nav class="site-nav" aria-label="Main">
      <ul>
      <?php if ($user !== null): ?>
        <?= $link('setup.php', 'New game', 'setup') ?>
        <?= $link('game.php', 'Current game', 'game') ?>
        <?= $link('leaderboard.php', 'Leaderboard', 'leaderboard') ?>
        <li>
          <form method="post" action="logout.php" class="nav-form">
            <?= csrf_field() ?>
            <button type="submit">Log out <span class="nav-user">(<?= e($user) ?>)</span></button>
          </form>
        </li>
      <?php else: ?>
        <?= $link('login.php', 'Log in', 'login') ?>
        <?= $link('register.php', 'Register', 'register') ?>
      <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>
<main id="main" class="wrap" tabindex="-1">
  <?php foreach (take_flash() as $f): ?>
    <?php
      $kind = match ($f['type']) { 'error' => 'error', 'success' => 'ok', default => 'warn' };
      $icon = match ($kind) { 'error' => '⚠️', 'ok' => '✅', default => '💡' };
    ?>
    <div class="alert alert--<?= e($kind) ?>" role="<?= $kind === 'error' ? 'alert' : 'status' ?>">
      <span class="alert-icon" aria-hidden="true"><?= $icon ?></span>
      <p><?= e($f['message']) ?></p>
    </div>
  <?php endforeach; ?>
  <?php if ($showHeading): ?>
  <div class="page-head">
    <h1><?= e($title) ?></h1>
    <?php if ($lead !== ''): ?><p><?= e($lead) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
<?php
}

function page_footer(): void {
    ?>
</main>
<footer class="site-footer">
  <div class="wrap footer-inner">
    <p>🎲 Adventures of the Dice</p>
    <p>Dice are fair. The narrator only tells the story.</p>
  </div>
</footer>
</body>
</html>
<?php
}

function error_list(array $errors): void {
    if ($errors === []) {
        return;
    }
    echo '<div class="alert alert--error" role="alert"><span class="alert-icon" aria-hidden="true">⚠️</span><ul>';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}

/** A CSS die showing 1-6 pips. */
function die_html(int $n, string $extra = ''): string {
    $n = max(1, min(6, $n));
    return '<span class="die die--' . $n . ($extra !== '' ? ' ' . e($extra) : '') . '" role="img" aria-label="Die showing ' . $n . '">'
         . str_repeat('<i></i>', $n) . '</span>';
}

function level_badge(string $key, string $label): string {
    return '<span class="badge badge--' . e($key) . '">' . e($label) . '</span>';
}

function board_legend(): string {
    return '<ul class="board-legend">'
         . '<li><span class="sw sw--snake"></span> Snake</li>'
         . '<li><span class="sw sw--ladder"></span> Ladder</li>'
         . '<li><span class="sw sw--event"></span> Event</li>'
         . '</ul>';
}

/**
 * The board grid. $tokens = list of ['cell'=>int,'player'=>int,'name'=>string,'active'=>bool].
 */
function render_board_html(array $board, array $tokens = [], int $active = -1, bool $mini = false): string
{
    $tails = $tops = [];
    foreach ($board['snakes'] as $tail) {
        $tails[$tail] = true;
    }
    foreach ($board['ladders'] as $top) {
        $tops[$top] = true;
    }

    $byCell = [];
    foreach ($tokens as $t) {
        $byCell[$t['cell']][] = $t;
    }
    $tokenHtml = function (array $t): string {
        return '<span class="token token--p' . ($t['player'] + 1) . (!empty($t['active']) ? ' token--active' : '')
             . '" title="' . e($t['name']) . '">P' . ($t['player'] + 1) . '</span>';
    };

    $out = '<ol class="board' . ($mini ? ' board--mini' : '') . '" aria-label="Game board, cells 1 to 100">';
    for ($row = 9; $row >= 0; $row--) {
        $cells = range($row * 10 + 1, $row * 10 + 10);
        if ($row % 2 === 1) {
            $cells = array_reverse($cells);
        }
        foreach ($cells as $n) {
            $classes = ['cell', (($n + intdiv($n - 1, 10)) % 2 === 0) ? 'cell--a' : 'cell--b'];
            $mark = '';
            if (isset($board['snakes'][$n])) {
                $classes[] = 'cell--snake';
                $to   = $board['snakes'][$n];
                $mark = '<span class="mark" title="Snake: cell ' . $n . ' to ' . $to . '"><span aria-hidden="true">🐍</span><small>' . $to . '</small></span>';
            } elseif (isset($board['ladders'][$n])) {
                $classes[] = 'cell--ladder';
                $to   = $board['ladders'][$n];
                $mark = '<span class="mark" title="Ladder: cell ' . $n . ' to ' . $to . '"><span aria-hidden="true">🪜</span><small>' . $to . '</small></span>';
            } elseif (isset($board['events'][$n])) {
                $classes[] = 'cell--event';
                $mark = '<span class="mark" title="Event cell"><span aria-hidden="true">✨</span></span>';
            } elseif ($n === BOARD_SIZE) {
                $mark = '<span class="mark" title="Finish"><span aria-hidden="true">🏁</span></span>';
            }
            if (isset($tails[$n])) {
                $classes[] = 'cell--tail';
            }
            if (isset($tops[$n])) {
                $classes[] = 'cell--top';
            }
            if ($n === BOARD_SIZE) {
                $classes[] = 'cell--goal';
            }
            if ($n === $active) {
                $classes[] = 'cell--active';
            }

            $toks = '';
            if (!empty($byCell[$n])) {
                $toks = '<span class="tokens">' . implode('', array_map($tokenHtml, $byCell[$n])) . '</span>';
            }

            $out .= '<li value="' . $n . '" class="' . implode(' ', $classes) . '">'
                  . '<span class="num">' . $n . '</span>' . $mark . $toks . '</li>';
        }
    }
    return $out . '</ol>';
}

/** Board for a live game (tokens and active cell). */
function render_board(array $curr_game): string {
    $board   = board_for($curr_game['level']);
    $playing = $curr_game['status'] === 'playing';
    $tokens  = [];
    $start   = '';
    foreach ($curr_game['positions'] as $i => $pos) {
        $t = ['cell' => (int)$pos, 'player' => $i, 'name' => $curr_game['players'][$i],
              'active' => $playing && $i === $curr_game['current']];
        if ($pos === 0) {
            $start .= '<span class="token token--p' . ($i + 1) . '" title="' . e($t['name']) . '">P' . ($i + 1) . '</span>';
        } else {
            $tokens[] = $t;
        }
    }
    $active = $playing ? $curr_game['positions'][$curr_game['current']] : -1;

    $out = '<p class="start-line muted small">Start: ' . ($start !== '' ? $start : 'no one there') . '</p>';
    return $out . render_board_html($board, $tokens, $active);
}

function fmt_duration(int $seconds): string {
    return intdiv($seconds, 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT);
}
