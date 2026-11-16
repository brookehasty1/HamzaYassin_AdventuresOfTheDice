<?php
declare(strict_types = 1);

function page_header(string $title): void {
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Adventures of the Dice</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="index.php">🎲 Adventures of the Dice</a>
  <nav aria-label="Main">
    <?php if ($user !== null): ?>
      <a href="setup.php">New game</a>
      <a href="game.php">Current game</a>
      <a href="leaderboard.php">Leaderboard</a>
      <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit">Log out (<?= e($user) ?>)</button>
      </form>
    <?php else: ?>
      <a href="login.php">Log in</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main>
  <h1><?= e($title) ?></h1>
  <?php foreach (take_flash() as $f): ?>
    <p class="flash <?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></p>
  <?php endforeach; ?>
<?php
}

function page_footer(): void {
    ?>
</main>
</body>
</html>
<?php
}

function error_list(array $errors): void {
    if ($errors === []) {
        return;
    }
    echo '<ul class="flash error" role="alert">';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul>';
}

function render_board(array $curr_game): string {
    $board = board_for($curr_game['level']);
    $playing = $curr_game['status'] === 'playing';
    $active = $playing ? $curr_game['positions'][$curr_game['current']] : -1;
    $lastMove = $curr_game['last_move'];

    $tokensAt = function (int $cell) use ($curr_game, $lastMove): string {
        $html = '';
        foreach ($curr_game['positions'] as $i => $pos) {
            if ($pos !== $cell) {
                continue;
            }
            $moved = ($lastMove !== null && $lastMove['player'] === $i && $lastMove['final'] === $cell) ? ' just-moved' : '';
            $html .= '<span class="token t' . $i . $moved . '" title="' . e($curr_game['players'][$i]) . '">P' . ($i + 1) . '</span>';
        }
        return $html;
    };

    $out = '<p class="start-line"><span class="label">Start:</span> ' . ($tokensAt(0) ?: '<span class="muted">no one yet</span>') . '</p>';
    $out .= '<ol class="board" aria-label="Game board, cells 1 to 100">';

    for ($row = 9; $row >= 0; $row--) {
        $cells = range($row * 10 + 1, $row * 10 + 10);
        if ($row % 2 === 1) {
            $cells = array_reverse($cells);
        }
        foreach ($cells as $n) {
            $classes = ['cell'];
            $note = '';
            if (isset($board['snakes'][$n])) {
                $classes[] = 'snake';
                $note = '🐍→' . $board['snakes'][$n];
            } elseif (isset($board['ladders'][$n])) {
                $classes[] = 'ladder';
                $note = '🪜→' . $board['ladders'][$n];
            } elseif (isset($board['events'][$n])) {
                $classes[] = 'event';
                $note = '✨';
            }
            if ($n === $active) {
                $classes[] = 'active';
            }
            $out .= '<li value="' . $n . '" class="' . implode(' ', $classes) . '">'
                  . '<span class="num">' . $n . '</span>'
                  . ($note !== '' ? '<span class="note">' . e($note) . '</span>' : '')
                  . $tokensAt($n) . '</li>';
        }
    }
    return $out . '</ol>';

}

function fmt_duration(int $seconds): string {
    return intdiv($seconds, 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT);
}