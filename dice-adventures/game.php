<?php
/**
 * 3. Game Board — game.php
 * Purpose: play turns on the PHP-rendered 10×10 board.
 * Session: R $_SESSION['game'] (back-end new_game() / play_turn() state)
 * Form:    POST action.php (roll) → game.php (or recap.php when a player reaches 100)
 */
$pageTitle = 'Game Board';
$active    = 'game';
require_once __DIR__ . '/includes/app.php';

$user = require_login();
$game = current_game($user);
if ($game === null) {
    flash('error', 'Start a game first.');
    redirect('setup.php');
}
if ($game['status'] === 'finished') {
    redirect('recap.php');
}

$players = $game['players'];
$level   = $game['level'];
$board   = board_for($level);
$cur     = $game['current'];
$curPos  = $game['positions'][$cur];
$probMap = roll_probabilities($curPos, $board);
$last    = $game['last_move'];
$elapsed = time() - $game['started_at'];

if ($last !== null) {
    $lastPred  = roll_probabilities($last['from'], $board);
    $hitChance = $lastPred[$last['landing']]['p'] ?? 0;
    $best      = max(array_keys($lastPred));
}

layout_header($pageTitle, $active);
?>

<div class="page-head" style="display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap">
    <div>
        <p class="eyebrow">Turn <?= $game['turn_number'] + 1 ?> · <?= LEVELS[$level]['label'] ?> board</p>
        <h1><?= e($players[$cur]) ?>'s turn</h1>
    </div>
    <span class="badge badge--<?= $level ?>" style="font-size:.9rem;padding:6px 14px">
        ⏱ <?= mmss($elapsed) ?> played
    </span>
</div>

<div class="game-layout">
    <section class="game-board-wrap" aria-labelledby="board-title">
        <h2 id="board-title" class="sr-only">Board</h2>
        <div id="board" class="show-prob">
            <?php draw_board($board, $game['positions'], $cur, $players, $probMap); ?>
        </div>
        <ul class="board-legend">
            <li><span class="sw sw--snake"></span>🐍 Snake head (↓ tail)</li>
            <li><span class="sw sw--ladder"></span>🪜 Ladder base (↑ top)</li>
            <li><span class="sw sw--event"></span>✨ Mystery event</li>
            <li><span class="sw sw--prob"></span>Possible landing</li>
        </ul>
    </section>

    <div class="game-side">
        <section class="card turn-card" aria-labelledby="players-title">
            <div class="turn-head">
                <h2 id="players-title">Players</h2>
                <span class="small muted">Turn <?= $game['turn_number'] + 1 ?></span>
            </div>
            <ul class="players">
                <?php foreach ($players as $i => $name): $pos = $game['positions'][$i]; ?>
                    <li class="player-row player-row--<?= $i + 1 ?><?= $i === $cur ? ' player-row--active' : '' ?>"
                        <?= $i === $cur ? 'aria-current="true"' : '' ?>>
                        <span class="token token--p<?= $i + 1 ?>" aria-hidden="true"><?= e(mb_substr($name, 0, 1)) ?></span>
                        <span class="name"><?= e($name) ?><?= $game['skips'][$i] ? ' <span title="Misses next turn">💤</span>' : '' ?></span>
                        <span class="pos"><?= cell_label($pos) ?></span>
                        <span class="progress" aria-hidden="true"><span style="width:<?= $pos ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="roll-area">
                <?= render_die($last['roll'] ?? 1, 'last-die') ?>
                <form action="action.php" method="post" data-roll-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="roll">
                    <button class="btn btn--primary btn--roll btn--block" type="submit" autofocus>
                        🎲 Roll Dice
                    </button>
                </form>
            </div>
        </section>

        <section class="card narrator" aria-labelledby="narrator-title">
            <p class="eyebrow" id="narrator-title">🤖 AI Narrator</p>
            <?php if ($last === null): ?>
                <p class="narrator-text" aria-live="polite">The adventure begins! <?= e($players[$cur]) ?> steps up to the start line. Roll to enter the board.</p>
            <?php else: ?>
                <p class="narrator-text" aria-live="polite"><?= implode('<br>', array_map('e', $last['narration'])) ?></p>
                <p class="narrator-meta">
                    <?= e($last['name']) ?> rolled a <?= $last['roll'] ?>:
                    <?= $last['from'] === 0 ? 'Start' : $last['from'] ?> → <?= $last['raw'] ?><?= $last['landing'] !== $last['raw'] ? ' → ' . $last['landing'] : '' ?><?= $last['final'] !== $last['landing'] ? ' → ' . $last['final'] : '' ?>
                </p>
            <?php endif; ?>
        </section>

        <section class="card prob-card" aria-labelledby="prob-title">
            <div class="head">
                <h2 id="prob-title">Probability Map</h2>
                <label class="switch">
                    <input type="checkbox" checked data-prob-toggle aria-controls="board">
                    Show on board
                </label>
            </div>
            <p class="small muted" style="margin-top:-4px">Where <?= e($players[$cur]) ?> could land from <?= $curPos === 0 ? 'the start' : 'cell ' . $curPos ?>. Advisory only: your roll is always a fair 1–6.</p>
            <ul class="prob-list">
                <?php foreach ($probMap as $cell => $info):
                    $via = $info['via'];
                    $tag = '';
                    if (isset($board['snakes'][$via]))      { $tag = '<span class="tag tag--snake">🐍 from ' . $via . '</span>'; }
                    elseif (isset($board['ladders'][$via])) { $tag = '<span class="tag tag--ladder">🪜 from ' . $via . '</span>'; }
                    elseif (isset($board['events'][$cell])) { $tag = '<span class="tag tag--event">✨ event</span>'; }
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
            <?php if ($last === null): ?>
                <p class="small muted">No rolls yet. After the first roll, this compares the prediction with where the piece really landed.</p>
            <?php else: ?>
                <div class="compare">
                    <?= render_die($last['roll']) ?>
                    <dl>
                        <dt>Player</dt><dd><?= e($last['name']) ?></dd>
                        <dt>Predicted</dt><dd><?= count($lastPred) ?> possible cells, <?= round($hitChance * 100) ?>% chance of this one</dd>
                        <dt>Actual</dt><dd>Cell <?= $last['landing'] ?><?= $last['final'] !== $last['landing'] ? ' (event → ' . $last['final'] . ')' : '' ?></dd>
                    </dl>
                    <p class="verdict">
                        <?= $last['landing'] === $best ? '🍀 Best possible outcome! Only a ' . round($hitChance * 100) . '% chance.' : 'An average roll. The best outcome was cell ' . $best . '.' ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <div class="game-tools">
            <a class="btn btn--ghost" href="rules.php">📖 Rules</a>
            <form action="action.php" method="post" style="flex:1;display:flex" data-confirm="Quit this game? Progress will be lost.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <button class="btn btn--ghost btn--block" type="submit">Quit / Restart</button>
            </form>
        </div>
    </div>
</div>

<?php layout_footer(); ?>
