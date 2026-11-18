<?php
/**
 * 2. Game Setup — setup.php
 * Purpose: choose number of players, names and difficulty.
 * Form:    POST action.php (start) (back-end validate_setup() + new_game()) → game.php
 * Edge case E1: on validation failure action.php (start) redirects back here with
 *               $_SESSION['form_errors'] + $_SESSION['form_old'] (sticky values).
 *               setup.php?error=name previews that state.
 */
$pageTitle = 'Game Setup';
$active    = 'setup';
require_once __DIR__ . '/includes/app.php';
$user = require_login();
layout_header($pageTitle, $active);

$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

if (isset($_GET['error']) && !$errors) { // preview of edge case E1
    $errors = [
        'player1' => 'Player 1 needs a name.',
        'player2' => 'Player 2 name must be between 1 - 20 letters, numbers, spaces, hyphens, or underscores.',
    ];
    $old = ['player_count' => '2', 'player1' => '', 'player2' => '<script>', 'level' => 'standard'];
}

$count = ($old['player_count'] ?? '2') === '1' ? 1 : 2;
$p1    = $old['player1'] ?? $user;
$p2    = $old['player2'] ?? '';
$level = level_key($old['level'] ?? ($_GET['level'] ?? 'standard'));
?>

<div class="page-head">
    <p class="eyebrow">Step 1 of 2</p>
    <h1>Set up your adventure</h1>
    <p>Choose who's playing and how dangerous the board should be.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert--error" role="alert" id="error-summary" tabindex="-1">
        <span class="alert-icon" aria-hidden="true">⚠️</span>
        <div>
            <strong>Please fix <?= count($errors) ?> <?= count($errors) === 1 ? 'problem' : 'problems' ?> below</strong>
            <ul>
                <?php foreach ($errors as $field => $msg): ?>
                    <li><a href="#<?= e($field) ?>"><?= e($msg) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<form class="setup-grid" action="action.php" method="post" novalidate data-setup-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <div class="card">
        <fieldset>
            <legend>How many players?</legend>
            <div class="choice-group choice-group--2">
                <div class="choice">
                    <input type="radio" name="player_count" id="count1" value="1" <?= $count === 1 ? 'checked' : '' ?>>
                    <label for="count1"><strong>1 Player</strong><span>Solo run against the board</span></label>
                </div>
                <div class="choice">
                    <input type="radio" name="player_count" id="count2" value="2" <?= $count === 2 ? 'checked' : '' ?>>
                    <label for="count2"><strong>2 Players</strong><span>Take turns on one device</span></label>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Player names</legend>
            <div class="field">
                <label for="player1"><span class="player-dot player-dot--1" aria-hidden="true"></span>Player 1 name</label>
                <input class="input" type="text" id="player1" name="player1" value="<?= e($p1) ?>"
                       maxlength="20" required autocomplete="off"
                       aria-describedby="player1-help<?= isset($errors['player1']) ? ' player1-error' : '' ?>"
                       <?= isset($errors['player1']) ? 'aria-invalid="true"' : '' ?>>
                <span class="help" id="player1-help">1–20 letters, numbers, spaces, - or _.</span>
                <?php if (isset($errors['player1'])): ?>
                    <span class="error-msg" id="player1-error"><?= e($errors['player1']) ?></span>
                <?php endif; ?>
            </div>
            <div class="field" data-player2>
                <label for="player2"><span class="player-dot player-dot--2" aria-hidden="true"></span>Player 2 name</label>
                <input class="input" type="text" id="player2" name="player2" value="<?= e($p2) ?>"
                       maxlength="20" autocomplete="off"
                       aria-describedby="player2-help<?= isset($errors['player2']) ? ' player2-error' : '' ?>"
                       <?= isset($errors['player2']) ? 'aria-invalid="true"' : '' ?>
                       <?= $count === 1 ? 'disabled' : '' ?>>
                <span class="help" id="player2-help">Only needed for 2-player games.</span>
                <?php if (isset($errors['player2'])): ?>
                    <span class="error-msg" id="player2-error"><?= e($errors['player2']) ?></span>
                <?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Difficulty</legend>
            <div class="choice-group choice-group--3">
                <?php foreach (LEVELS as $key => $lvl): ?>
                    <div class="choice">
                        <input type="radio" name="level" id="level-<?= $key ?>" value="<?= $key ?>" <?= $key === $level ? 'checked' : '' ?>>
                        <label for="level-<?= $key ?>">
                            <strong><?= $lvl['icon'] ?> <?= $lvl['label'] ?></strong>
                            <span><?= count(board_for($key)['snakes']) ?> snakes · <?= count(board_for($key)['ladders']) ?> ladders</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="btn-row btn-row--end">
            <a class="btn btn--ghost" href="index.php">Back</a>
            <button class="btn btn--primary btn--lg" type="submit">Start Game →</button>
        </div>
    </div>

    <aside class="card preview-card" aria-labelledby="preview-title">
        <h2 id="preview-title">Board preview</h2>
        <?php foreach (LEVELS as $key => $lvl): ?>
            <div data-preview="<?= $key ?>" <?= $key === $level ? '' : 'hidden' ?>>
                <div class="preview-stats">
                    <span class="badge badge--<?= $key ?>"><?= $lvl['label'] ?></span>
                    <span class="badge">🐍 <?= count(board_for($key)['snakes']) ?> snakes</span>
                    <span class="badge">🪜 <?= count(board_for($key)['ladders']) ?> ladders</span>
                    <span class="badge">✨ <?= count(board_for($key)['events']) ?> event cells</span>
                </div>
                <?php draw_board(board_for($key), [], -1, [], null, 'full'); ?>
            </div>
        <?php endforeach; ?>
        <ul class="board-legend">
            <li><span class="sw sw--snake"></span>Snake head</li>
            <li><span class="sw sw--ladder"></span>Ladder base</li>
            <li><span class="sw sw--event"></span>Event cell</li>
        </ul>
    </aside>
</form>

<?php layout_footer(); ?>
