<?php
/**
 * Handler (no screen) for every form in the game. Each form posts a hidden
 * "action" field:
 *
 *   start → from setup.php    validate, write the starting session → game.php
 *                             (errors → setup.php with sticky values, edge case E1)
 *   roll  → from game.php     move the current player → game.php / recap.php
 *   save  → from recap.php    add the result to the leaderboard → leaderboard.php
 *   reset → from game.php ("Quit") and recap.php ("Play Again") → setup.php
 */
require_once __DIR__ . '/includes/app.php';

function go(string $page): never
{
    header('Location: ' . $page);
    exit;
}

$action = $_POST['action'] ?? '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

switch ($action) {

    case 'start':
        if (!$isPost) {
            go('setup.php');
        }
        $count = ($_POST['player_count'] ?? '2') === '1' ? 1 : 2;
        $names = [trim($_POST['player1'] ?? '')];
        if ($count === 2) {
            $names[] = trim($_POST['player2'] ?? '');
        }
        $level = $_POST['level'] ?? '';

        $errors = [];
        foreach ($names as $i => $name) {
            $field = 'player' . ($i + 1);
            if ($name === '') {
                $errors[$field] = 'Player ' . ($i + 1) . ' needs a name.';
            } elseif (!preg_match('/^[A-Za-z0-9 ]{1,15}$/', $name)) {
                $errors[$field] = 'Player ' . ($i + 1) . ': use only letters, numbers and spaces (1–15 characters).';
            }
        }
        if (!array_key_exists($level, LEVELS)) {
            $errors['level-standard'] = 'Choose a difficulty level.';
        }

        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old']    = [
                'player_count' => (string) $count,
                'player1'      => $_POST['player1'] ?? '',
                'player2'      => $_POST['player2'] ?? '',
                'level'        => $level,
            ];
            go('setup.php');
        }

        $_SESSION['players']        = $names;
        $_SESSION['difficulty']     = $level;
        $_SESSION['positions']      = array_fill(0, $count, 1);
        $_SESSION['current_player'] = 0;
        $_SESSION['turn']           = 1;
        $_SESSION['roll_history']   = [];
        $_SESSION['events_log']     = [];
        $_SESSION['skip_next']      = array_fill(0, $count, false);
        $_SESSION['start_time']     = time();
        $_SESSION['game_active']    = true;
        go('game.php');

    case 'roll':
        // FRONT-END STUB. The back-end sprint replaces this with:
        //   $roll = rand(1, 6); move piece; apply snake/ladder; check EVENT_CELLS;
        //   update positions[], roll_history[], last_event, events_log[], current_player;
        //   if position >= 100 → recap.php, else → game.php.
        if ($isPost && empty($_SESSION['game_active'])) {
            go('index.php?expired=1'); // edge case E2
        }
        go('game.php');

    case 'save':
        // FRONT-END STUB. The back-end sprint appends the result to scores.json
        // and stores the new row id in $_SESSION['last_score_id'].
        if ($isPost) {
            $_SESSION['last_score_id'] = 41; // demo row "Sam" in demo_scores()
            $_SESSION['game_active']   = false;
        }
        go('leaderboard.php');

    case 'reset':
        if ($isPost) {
            $_SESSION = [];
            session_destroy();
        }
        go('setup.php');

    default:
        go('setup.php');
}
