<?php
/**
 * Adventures of the Dice — shared code for every page and for action.php.
 *
 *   1. Session start
 *   2. Board configuration + front-end demo data
 *   3. Board / die rendering
 *   4. Page layout: page_header() and page_footer()
 *
 * Everything under "DEMO STATE" is placeholder data so every screen can be
 * previewed before the back-end (action.php roll / save) is written.
 * When the back-end is ready, replace demo_game() / demo_scores() with reads
 * from $_SESSION and scores.json.
 */

/* ───────────── 1. Session start ───────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ───────────── 2. Board configuration + demo data ───────────── */
const LEVELS = [
    'beginner' => ['label' => 'Beginner', 'icon' => '🟢', 'blurb' => 'A gentle climb. Good for your first adventure.'],
    'standard' => ['label' => 'Standard', 'icon' => '🟡', 'blurb' => 'Balanced board with a few nasty surprises.'],
    'expert'   => ['label' => 'Expert',   'icon' => '🔴', 'blurb' => 'Nine serpents. Very few ways up.'],
];

/* head => tail for snakes, base => top for ladders */
const BOARDS = [
    'beginner' => [
        'snakes'  => [47 => 12, 62 => 19, 87 => 24],
        'ladders' => [4 => 25, 21 => 39, 50 => 91],
    ],
    'standard' => [
        'snakes'  => [17 => 7, 47 => 12, 54 => 34, 64 => 60, 87 => 24, 98 => 79],
        'ladders' => [2 => 38, 9 => 31, 28 => 84, 51 => 72, 71 => 91],
    ],
    'expert' => [
        'snakes'  => [16 => 6, 31 => 9, 47 => 12, 49 => 11, 62 => 19, 74 => 53, 87 => 24, 95 => 75, 99 => 78],
        'ladders' => [4 => 14, 28 => 48, 40 => 59, 63 => 81],
    ],
];

/* Event cells used by the AI Narrator (same on every level) */
const EVENT_CELLS = [
    15 => ['type' => 'bonus',   'move' => 5,  'msg' => 'Found a shortcut!'],
    42 => ['type' => 'warp',    'to'   => 58, 'msg' => 'A swirling portal opens!'],
    67 => ['type' => 'penalty', 'move' => -4, 'msg' => 'Quicksand slows you down.'],
    83 => ['type' => 'skip',    'msg'  => 'You stop to rest and lose your next turn.'],
];

const EVENT_TYPES = [
    'bonus'   => ['icon' => '✨', 'label' => 'Bonus',   'desc' => 'Jump forward a few extra cells.'],
    'penalty' => ['icon' => '🪨', 'label' => 'Penalty', 'desc' => 'Slide back a few cells.'],
    'skip'    => ['icon' => '⏸️', 'label' => 'Skip',    'desc' => 'Lose your next turn.'],
    'warp'    => ['icon' => '🌀', 'label' => 'Warp',    'desc' => 'Teleport straight to a target cell.'],
];

function level_key(?string $level): string
{
    return array_key_exists((string) $level, LEVELS) ? $level : 'standard';
}

/** Where a piece ends up after landing on $cell (snake / ladder applied). */
function resolve_cell(int $cell, array $board): int
{
    $cell = min($cell, 100);
    return $board['snakes'][$cell] ?? $board['ladders'][$cell] ?? $cell;
}

/**
 * Probability map: for each fair roll 1–6, where would the piece land?
 * Returns [landing_cell => ['p' => probability, 'rolls' => [..], 'via' => original cell]].
 */
function roll_probabilities(int $pos, array $board): array
{
    $map = [];
    for ($roll = 1; $roll <= 6; $roll++) {
        $raw = min($pos + $roll, 100);
        $end = resolve_cell($raw, $board);
        $map[$end]['rolls'][] = $roll;
        $map[$end]['via'] = $raw;
        $map[$end]['p'] = count($map[$end]['rolls']) / 6;
    }
    ksort($map);
    return $map;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* DEMO STATE (replace with $_SESSION once the back-end exists)       */
/* ------------------------------------------------------------------ */

function demo_game(): array
{
    return [
        'players'        => ['Sam', 'Ava'],
        'difficulty'     => 'standard',
        'positions'      => [43, 31],
        'current_player' => 0,
        'turn'           => 7,
        'last_roll'      => ['player' => 1, 'roll' => 5, 'from' => 4, 'landed' => 9, 'to' => 31],
        'last_event'     => ['type' => 'ladder', 'text' => '🪜 Ava spots a rope ladder at cell 9 and scrambles all the way up to cell 31!'],
        'start_time'     => time() - 312,
    ];
}

function demo_recap(): array
{
    return [
        'winner'   => 'Sam',
        'level'    => 'standard',
        'rolls'    => 19,
        'time'     => '06:42',
        'optimal'  => 6,
        'snakes_hit'   => 3,
        'turns_lost'   => 4,
        'ladder_cells' => 61,
        'timeline' => [
            ['turn' => 1,  'type' => 'ladder',  'text' => 'Ladder 2 → 38'],
            ['turn' => 4,  'type' => 'event',   'text' => 'Warp portal 42 → 58'],
            ['turn' => 6,  'type' => 'snake',   'text' => 'Snake 64 → 60'],
            ['turn' => 9,  'type' => 'event',   'text' => 'Quicksand 67 → 63'],
            ['turn' => 11, 'type' => 'ladder',  'text' => 'Ladder 71 → 91'],
            ['turn' => 13, 'type' => 'snake',   'text' => 'Snake 98 → 79'],
            ['turn' => 16, 'type' => 'snake',   'text' => 'Snake 87 → 24'],
            ['turn' => 19, 'type' => 'win',     'text' => 'Reached cell 100 🏆'],
        ],
        /* position after each of Sam's turns, for the path chart */
        'path'    => [0 => 1, 1 => 38, 2 => 41, 3 => 44, 4 => 58, 5 => 61, 6 => 60, 7 => 62, 8 => 65, 9 => 63, 10 => 68, 11 => 91, 12 => 94, 13 => 79, 14 => 82, 15 => 85, 16 => 24, 17 => 28, 18 => 84, 19 => 100],
        'optimal_path' => [0 => 1, 1 => 2, 2 => 38, 3 => 42, 4 => 58, 5 => 71, 6 => 91, 7 => 97, 8 => 100],
    ];
}

/* No Expert rows on purpose: ?level=expert shows the empty state (edge case E3). */
function demo_scores(): array
{
    return [
        ['id' => 41, 'name' => 'Sam',  'level' => 'standard', 'rolls' => 19, 'time' => '06:42', 'date' => '2026-11-15'],
        ['id' => 38, 'name' => 'Ava',  'level' => 'standard', 'rolls' => 21, 'time' => '05:40', 'date' => '2026-11-14'],
        ['id' => 36, 'name' => 'Leo',  'level' => 'beginner', 'rolls' => 14, 'time' => '03:15', 'date' => '2026-11-13'],
        ['id' => 35, 'name' => 'Maya', 'level' => 'beginner', 'rolls' => 17, 'time' => '04:02', 'date' => '2026-11-12'],
        ['id' => 33, 'name' => 'Jin',  'level' => 'standard', 'rolls' => 24, 'time' => '07:58', 'date' => '2026-11-10'],
        ['id' => 30, 'name' => 'Rosa', 'level' => 'beginner', 'rolls' => 22, 'time' => '05:11', 'date' => '2026-11-08'],
    ];
}

/* ───────────── 3. Board / die rendering ───────────── */

/**
 * Render the 100-cell board (serpentine: 1 bottom-left, 100 top-left).
 *
 * @param array      $board     ['snakes' => [...], 'ladders' => [...]]
 * @param array      $positions player index => cell
 * @param int        $current   index of the player whose turn it is
 * @param array      $players   player names
 * @param array|null $probMap   output of roll_probabilities(), drawn as an overlay
 * @param string     $size      'full' | 'mini'
 */
function render_board(array $board, array $positions = [], int $current = 0, array $players = [], ?array $probMap = null, string $size = 'full'): void
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
            } elseif (isset(EVENT_CELLS[$n])) {
                $type      = EVENT_CELLS[$n]['type'];
                $classes[] = 'cell--event';
                $labels[]  = EVENT_TYPES[$type]['label'] . ' event';
                $marker    = '<span class="mark" aria-hidden="true">' . EVENT_TYPES[$type]['icon'] . '</span>';
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
 * Print the <head>, header and navigation, and open <main>.
 * $active: 'home'|'setup'|'game'|'recap'|'leaderboard'|'rules'
 */
function page_header(string $pageTitle = 'Adventures of the Dice', string $active = ''): void
{
    $navItems  = [
        'home'        => ['index.php', 'Home'],
        'setup'       => ['setup.php', 'Setup'],
        'game'        => ['game.php', 'Game'],
        'recap'       => ['recap.php', 'Recap'],
        'leaderboard' => ['leaderboard.php', 'Leaderboard'],
        'rules'       => ['rules.php', 'Rules'],
    ];
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
    <script src="assets/js/app.js" defer></script>
</head>
<body class="page-<?= e($active) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php" aria-label="Adventures of the Dice home">
            <span class="brand-die" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
            <span class="brand-text">Dice Adventures</span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <span class="sr-only">Menu</span><span aria-hidden="true">☰</span>
        </button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <li><a href="<?= $href ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="main" class="wrap" tabindex="-1">
<?php
}

/** Close <main> and print the footer (with the edge-case preview links). */
function page_footer(): void
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
