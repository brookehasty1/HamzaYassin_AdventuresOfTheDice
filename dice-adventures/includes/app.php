<?php
/**
 * Adventures of the Dice — shared front-end code for every page and for action.php.
 *
 *   1. Back-end connection (Brooke's src/bootstrap.php)
 *   2. Front-end display data + helpers built on the back-end
 *   3. Board / die rendering
 *   4. Page layout: layout_header() and layout_footer()
 *
 * The game rules, boards, sessions, logins, CSRF and leaderboard all come from
 * ../src. This file only adds what the styled pages need to display them.
 * (Names differ from src/view.php — draw_board(), layout_header() — so both can load.)
 */

/* ───────────── 1. Back-end connection ───────────── */
require_once __DIR__ . '/../../src/bootstrap.php';   // session, e(), csrf, auth, engine, leaderboard

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    // The back-end's default policy blocks all scripts, inline styles and web fonts.
    // Allow this front-end's own script, its inline style attributes and Google Fonts.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com; frame-ancestors 'none'");
}

/* ───────────── 2. Display data + helpers ───────────── */
const LEVELS = [
    'beginner' => ['label' => 'Beginner', 'icon' => '🟢', 'blurb' => 'A gentle climb. Good for your first adventure.'],
    'standard' => ['label' => 'Standard', 'icon' => '🟡', 'blurb' => 'Balanced board with a few nasty surprises.'],
    'expert'   => ['label' => 'Expert',   'icon' => '🔴', 'blurb' => 'Nine serpents. Very few ways up.'],
];

/* What each kind of board event does (the events themselves live in src/board_config.php) */
const EVENT_TYPES = [
    'bonus'   => ['icon' => '✨', 'label' => 'Bonus',   'desc' => 'Surge forward a few cells, or earn an extra roll.'],
    'penalty' => ['icon' => '🌫️', 'label' => 'Penalty', 'desc' => 'Get pushed back a few cells.'],
    'skip'    => ['icon' => '💤', 'label' => 'Skip',    'desc' => 'Doze off and miss your next turn.'],
    'warp'    => ['icon' => '🌀', 'label' => 'Warp',    'desc' => 'A portal whisks you to another cell.'],
];

function level_key(?string $level): string
{
    return array_key_exists((string) $level, LEVELS) ? $level : 'standard';
}

/** The logged-in player's game, or null. */
function active_game(): ?array
{
    $user = current_user();
    return $user === null ? null : current_game($user);
}

/** True while a game is in progress (enables "Continue Game" / "Back to Game"). */
function has_game_in_progress(): bool
{
    return (active_game()['status'] ?? null) === 'playing';
}

/** Event types that can appear on a level, e.g. ['bonus' => [15, 83], ...] */
function event_cells_by_type(array $board): array
{
    $byType = [];
    foreach ($board['events'] as $cell => $pool) {
        foreach ($pool as $def) {
            $byType[$def['type']][$cell] = $cell;
        }
    }
    return array_map('array_values', $byType);
}

/**
 * Probability map: for each fair roll 1–6, where would the piece land?
 * Uses the back-end's resolve_landing(). Position 0 = not on the board yet.
 * Returns [landing_cell => ['p' => probability, 'rolls' => [..], 'via' => raw cell]].
 */
function roll_probabilities(int $pos, array $board): array
{
    $map = [];
    for ($roll = 1; $roll <= 6; $roll++) {
        $res = resolve_landing($board, $pos, $roll);
        $end = $res['landing'];
        $map[$end]['rolls'][] = $roll;
        $map[$end]['via'] = min($res['raw'], BOARD_SIZE);
        $map[$end]['p'] = count($map[$end]['rolls']) / 6;
    }
    ksort($map);
    return $map;
}

/** Fewest rolls from the start to cell 100 (snakes/ladders only; events are random). Returns the cells visited. */
function optimal_path(array $board): array
{
    $prev  = [0 => null];
    $queue = [0];
    while ($queue) {
        $cell = array_shift($queue);
        if ($cell === BOARD_SIZE) {
            break;
        }
        for ($roll = 1; $roll <= 6; $roll++) {
            $next = resolve_landing($board, $cell, $roll)['landing'];
            if (!array_key_exists($next, $prev)) {
                $prev[$next] = $cell;
                $queue[] = $next;
            }
        }
    }
    $path = [];
    for ($c = BOARD_SIZE; $c !== null; $c = $prev[$c]) {
        array_unshift($path, $c);
    }
    return $path;
}

function mmss(int $seconds): string
{
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}

function cell_label(int $pos): string
{
    return $pos === 0 ? 'Start' : 'Cell ' . $pos;
}

/* ───────────── 3. Board / die rendering ───────────── */

/**
 * Render the 100-cell board (serpentine: 1 bottom-left, 100 top-left).
 *
 * @param array      $board     a level from board_for(): snakes, ladders, events
 * @param array      $positions player index => cell (0 = start, not drawn)
 * @param int        $current   index of the player whose turn it is (-1 = nobody)
 * @param array      $players   player names
 * @param array|null $probMap   output of roll_probabilities(), drawn as an overlay
 * @param string     $size      'full' | 'mini'
 */
function draw_board(array $board, array $positions = [], int $current = 0, array $players = [], ?array $probMap = null, string $size = 'full'): void
{
    $tails = array_flip($board['snakes']);
    $tops  = array_flip($board['ladders']);
    $mini  = $size === 'mini';

    echo '<ol class="board board--' . $size . '" aria-label="Game board, 100 cells">';
    for ($row = 9; $row >= 0; $row--) {
        $cells = range($row * 10 + 1, $row * 10 + 10);
        if ($row % 2 === 1) {
            $cells = array_reverse($cells);
        }
        foreach ($cells as $n) {
            $classes = ['cell', ($n + $row) % 2 ? 'cell--a' : 'cell--b'];
            $labels  = ["Cell $n"];
            $marker  = '';

            if (isset($board['snakes'][$n])) {
                $classes[] = 'cell--snake';
                $labels[]  = 'snake down to ' . $board['snakes'][$n];
                $marker    = '<span class="mark" aria-hidden="true">🐍<small>' . $board['snakes'][$n] . '</small></span>';
            } elseif (isset($board['ladders'][$n])) {
                $classes[] = 'cell--ladder';
                $labels[]  = 'ladder up to ' . $board['ladders'][$n];
                $marker    = '<span class="mark" aria-hidden="true">🪜<small>' . $board['ladders'][$n] . '</small></span>';
            } elseif (isset($board['events'][$n])) {
                $classes[] = 'cell--event';
                $labels[]  = 'mystery event';
                $marker    = '<span class="mark" aria-hidden="true">✨</span>';
            } elseif (isset($tails[$n])) {
                $classes[] = 'cell--tail';
            } elseif (isset($tops[$n])) {
                $classes[] = 'cell--top';
            }
            if ($n === 100) {
                $classes[] = 'cell--goal';
                $marker    = '<span class="mark" aria-hidden="true">🏁</span>';
            }

            $prob = '';
            if ($probMap !== null && isset($probMap[$n])) {
                $p         = $probMap[$n]['p'];
                $classes[] = 'cell--prob';
                $labels[]  = round($p * 100) . '% chance to land here';
                $prob      = '<span class="prob" style="--p:' . $p . '">' . round($p * 100) . '%</span>';
            }

            $tokens = '';
            foreach ($positions as $i => $pos) {
                if ($pos === $n) {
                    $active = $i === $current;
                    if ($active) {
                        $classes[] = 'cell--active';
                    }
                    $name    = $players[$i] ?? 'Player ' . ($i + 1);
                    $labels[] = $name . ($active ? ' (current turn)' : '') . ' is here';
                    $tokens .= '<span class="token token--p' . ($i + 1) . ($active ? ' token--active' : '') . '" aria-hidden="true">' . e(mb_substr($name, 0, 1)) . '</span>';
                }
            }

            echo '<li class="' . implode(' ', $classes) . '" aria-label="' . e(implode(', ', $labels)) . '">';
            echo '<span class="num" aria-hidden="true">' . $n . '</span>';
            if (!$mini) {
                echo $marker . $prob;
            }
            if ($tokens !== '') {
                echo '<span class="tokens">' . $tokens . '</span>';
            }
            echo '</li>';
        }
    }
    echo '</ol>';
}

/** Dice face markup for 1–6 */
function render_die(int $value, string $extra = ''): string
{
    $pips = str_repeat('<i></i>', max(1, min(6, $value)));
    return '<span class="die die--' . $value . ' ' . $extra . '" role="img" aria-label="Die showing ' . $value . '">' . $pips . '</span>';
}

/* ───────────── 4. Page layout ───────────── */

/**
 * Print the <head>, header and navigation, open <main>, and show any flash messages.
 * $active: 'home'|'setup'|'game'|'recap'|'leaderboard'|'rules'|'login'|'register'
 */
function layout_header(string $pageTitle = 'Adventures of the Dice', string $active = ''): void
{
    $user     = current_user();
    $navItems = [
        'home'        => ['index.php', 'Home'],
        'setup'       => ['setup.php', 'Setup'],
        'game'        => ['game.php', 'Game'],
        'recap'       => ['recap.php', 'Recap'],
        'leaderboard' => ['leaderboard.php', 'Leaderboard'],
        'rules'       => ['rules.php', 'Rules'],
    ];
    if ($user === null) {
        $navItems['login']    = ['login.php', 'Log in'];
        $navItems['register'] = ['register.php', 'Register'];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Adventures of the Dice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-<?= e($active) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php" aria-label="Adventures of the Dice home">
            <span class="brand-die" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
            <span class="brand-text">Dice Adventures</span>
        </a>
        <input class="nav-check" type="checkbox" id="nav-check" aria-controls="site-nav">
        <label class="nav-toggle" for="nav-check">
            <span class="sr-only">Menu</span><span aria-hidden="true">☰</span>
        </label>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <li><a href="<?= $href ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
                <?php endforeach; ?>
                <?php if ($user !== null): ?>
                    <li>
                        <form class="nav-form" action="action.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button type="submit">Log out <span class="nav-user">(<?= e($user) ?>)</span></button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="main" class="wrap" tabindex="-1">
<?php foreach (take_flash() as $f): $err = $f['type'] === 'error'; ?>
    <div class="alert alert--<?= $err ? 'error' : 'ok' ?>" role="<?= $err ? 'alert' : 'status' ?>">
        <span class="alert-icon" aria-hidden="true"><?= $err ? '⚠️' : '✅' ?></span>
        <div><strong><?= e($f['message']) ?></strong></div>
    </div>
<?php endforeach; ?>
<?php
}

/** Close <main> and print the footer (with the edge-case preview links). */
function layout_footer(): void
{
    ?>
</main>

<footer class="site-footer">
    <div class="wrap footer-inner">
        <p>Adventures of the Dice · a PHP board game</p>
        <details class="demo-states">
            <summary>Preview screen states</summary>
            <ul>
                <li><a href="setup.php?error=name">E1 · Setup validation error</a></li>
                <li><a href="index.php?expired=1">E2 · Session expired</a></li>
                <li><a href="leaderboard.php?level=expert">E3 · Empty leaderboard</a></li>
            </ul>
        </details>
    </div>
</footer>
</body>
</html>
<?php
}
