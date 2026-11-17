<?php
/**
 * 3. Game Board — game.php
 * Purpose: play turns on the PHP-rendered 10×10 board.
 * Session: R/W positions[], turn, current_player, roll_history[], last_event, events_log[], prediction
 * Form:    POST action.php (roll) → game.php (or recap.php when a player reaches 100)
 *
 * Front-end build: the board state comes from demo_game() so the screen can be
 * reviewed now. Names / player count chosen on setup.php are shown if present.
 */
$pageTitle = 'Game Board';
$active    = 'game';
require_once __DIR__ . '/includes/app.php';
page_header($pageTitle, $active);

$game = demo_game();
if (!empty($_SESSION['players'])) {
    $game['players'] = array_values($_SESSION['players']);
    if (count($game['players']) === 1) {
        $game['positions']   = [$game['positions'][0]];
        $game['last_roll']['player'] = 0;
        $game['last_event']['text']  = str_replace('Ava', e($game['players'][0]), $game['last_event']['text']);
    }
}
$players = $game['players'];
$board   = BOARDS[$game['difficulty']];
$cur     = $game['current_player'];
$curPos  = $game['positions'][$cur];
$probMap = roll_probabilities($curPos, $board);

$last      = $game['last_roll'];
$lastName  = $players[$last['player']] ?? $players[0];
$lastPred  = roll_probabilities($last['from'], $board);
$hitChance = $lastPred[$last['to']]['p'] ?? 0;
$elapsed   = time() - $game['start_time'];
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap">
    <div>
        <p class="eyebrow">Turn <?= $game['turn'] ?> · <?= LEVELS[$game['difficulty']]['label'] ?> board</p>
        <h1><?= e($players[$cur]) ?>'s turn</h1>
    </div>
    <span class="badge badge--<?= $game['difficulty'] ?>" style="font-size:.9rem;padding:6px 14px">
        ⏱ <?= sprintf('%02d:%02d', intdiv($elapsed, 60), $elapsed % 60) ?> played
    </span>
</div>

<div class="game-layout">
    <section class="game-board-wrap" aria-labelledby="board-title">
        <h2 id="board-title" class="sr-only">Board</h2>
        <div id="board" class="show-prob">
            <?php render_board($board, $game['positions'], $cur, $players, $probMap); ?>
        </div>
        <ul class="board-legend">
            <li><span class="sw sw--snake"></span>🐍 Snake head (↓ tail)</li>
            <li><span class="sw sw--ladder"></span>🪜 Ladder base (↑ top)</li>
            <li><span class="sw sw--event"></span>Event cell</li>
            <li><span class="sw sw--prob"></span>Possible landing</li>
        </ul>
    </section>

    <div class="game-side">
        <section class="card turn-card" aria-labelledby="players-title">
            <div class="turn-head">
                <h2 id="players-title">Players</h2>
                <span class="small muted">Turn <?= $game['turn'] ?></span>
            </div>
            <ul class="players">
                <?php foreach ($players as $i => $name): $pos = $game['positions'][$i]; ?>
                    <li class="player-row player-row--<?= $i + 1 ?><?= $i === $cur ? ' player-row--active' : '' ?>"
                        <?= $i === $cur ? 'aria-current="true"' : '' ?>>
                        <span class="token token--p<?= $i + 1 ?>" aria-hidden="true"><?= e(mb_substr($name, 0, 1)) ?></span>
                        <span class="name"><?= e($name) ?></span>
                        <span class="pos">Cell <?= $pos ?></span>
                        <span class="progress" aria-hidden="true"><span style="width:<?= $pos ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="roll-area">
                <?= render_die($last['roll'], 'last-die') ?>
                <form action="action.php" method="post" data-roll-form>
                    <input type="hidden" name="action" value="roll">
                    <input type="hidden" name="player" value="<?= $cur ?>">
                    <button class="btn btn--primary btn--roll btn--block" type="submit" autofocus>
                        🎲 Roll Dice
                    </button>
                </form>
            </div>
        </section>

        <section class="card narrator" aria-labelledby="narrator-title">
            <p class="eyebrow" id="narrator-title">🤖 AI Narrator</p>
            <p class="narrator-text" aria-live="polite"><?= $game['last_event']['text'] ?></p>
            <p class="narrator-meta"><?= e($lastName) ?> rolled a <?= $last['roll'] ?>: <?= $last['from'] ?> → <?= $last['landed'] ?><?= $last['landed'] !== $last['to'] ? ' → ' . $last['to'] : '' ?></p>
        </section>

        <section class="card prob-card" aria-labelledby="prob-title">
            <div class="head">
                <h2 id="prob-title">Probability Map</h2>
                <label class="switch">
                    <input type="checkbox" checked data-prob-toggle aria-controls="board">
                    Show on board
                </label>
            </div>
            <p class="small muted" style="margin-top:-4px">Where <?= e($players[$cur]) ?> could land from cell <?= $curPos ?>. Advisory only: your roll is always a fair 1–6.</p>
            <ul class="prob-list">
                <?php foreach ($probMap as $cell => $info):
                    $via = $info['via'];
                    $tag = '';
                    if (isset($board['snakes'][$via]))      { $tag = '<span class="tag tag--snake">🐍 from ' . $via . '</span>'; }
                    elseif (isset($board['ladders'][$via])) { $tag = '<span class="tag tag--ladder">🪜 from ' . $via . '</span>'; }
                    elseif (isset(EVENT_CELLS[$cell]))      { $tag = '<span class="tag tag--event">' . EVENT_TYPES[EVENT_CELLS[$cell]['type']]['icon'] . ' event</span>'; }
                ?>
                    <li>
                        <span class="cell-name">Cell <?= $cell ?></span>
                        <span class="bar" aria-hidden="true"><span style="width:<?= round($info['p'] * 100) ?>%"></span></span>
                        <span class="pct"><?= round($info['p'] * 100) ?>%</span>
                        <span class="small muted" style="grid-column:1/-1;margin-top:-4px">
                            Roll <?= implode(' or ', $info['rolls']) ?> <?= $tag ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card" aria-labelledby="compare-title">
            <h2 id="compare-title">Last roll: predicted vs actual</h2>
            <div class="compare">
                <?= render_die($last['roll']) ?>
                <dl>
                    <dt>Player</dt><dd><?= e($lastName) ?></dd>
                    <dt>Predicted</dt><dd><?= count($lastPred) ?> possible cells, <?= round($hitChance * 100) ?>% each</dd>
                    <dt>Actual</dt><dd>Cell <?= $last['to'] ?></dd>
                </dl>
                <p class="verdict">
                    <?php $best = max(array_keys($lastPred)); ?>
                    <?= $last['to'] === $best ? '🍀 Best possible outcome! Only a ' . round($hitChance * 100) . '% chance.' : 'An average roll. The best outcome was cell ' . $best . '.' ?>
                </p>
            </div>
        </section>

        <div class="game-tools">
            <a class="btn btn--ghost" href="rules.php">📖 Rules</a>
            <form action="action.php" method="post" style="flex:1;display:flex" data-confirm="Quit this game? Progress will be lost.">
                <input type="hidden" name="action" value="reset">
                <button class="btn btn--ghost btn--block" type="submit">Quit / Restart</button>
            </form>
        </div>
    </div>
</div>

<?php page_footer(); ?>
