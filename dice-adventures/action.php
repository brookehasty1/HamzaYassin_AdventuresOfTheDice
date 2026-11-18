<?php
/**
 * Handler (no screen) for every form in the game. Each form posts a hidden
 * "action" field plus the back-end's CSRF token:
 *
 *   start  → from setup.php    validate (back-end validate_setup), new_game() → game.php
 *                              (errors → setup.php with sticky values, edge case E1)
 *   roll   → from game.php     back-end play_turn() → game.php, or recap.php on a win
 *                              (no game in the session → index.php?expired=1, edge case E2)
 *   save   → from recap.php    back-end record_score() → leaderboard.php
 *   reset  → from game.php ("Quit") and recap.php ("Play Again") → setup.php
 *   logout → from the nav bar  back-end logout_user() → login.php
 */
require_once __DIR__ . '/includes/app.php';

/** Where each action goes back to if the request is not a valid POST. */
const ACTION_HOME = [
    'start' => 'setup.php', 'roll' => 'game.php', 'save' => 'recap.php',
    'reset' => 'setup.php', 'logout' => 'index.php',
];

$action = (string) ($_POST['action'] ?? '');
$back   = ACTION_HOME[$action] ?? 'index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset(ACTION_HOME[$action])) {
    redirect($back);
}
if (!csrf_valid()) {
    flash('error', 'Your form expired. Please try again.');
    redirect($back);
}

if ($action === 'logout') {
    logout_user();
    flash('success', 'You have been logged out.');
    redirect('login.php');
}

$user = require_login();   // every other action needs a logged-in player

switch ($action) {

    case 'start':
        $count = ($_POST['player_count'] ?? '2') === '1' ? 1 : 2;
        $input = [
            'level'   => (string) ($_POST['level'] ?? ''),
            'player1' => (string) ($_POST['player1'] ?? ''),
            'player2' => $count === 2 ? (string) ($_POST['player2'] ?? '') : '',
        ];

        // The back-end treats a blank Player 2 as a solo game, so check that here first.
        $errors = [];
        if ($count === 2 && trim($input['player2']) === '') {
            $errors['player2'] = 'Player 2 needs a name.';
        }
        [$backendErrors, $clean] = validate_setup($input, $user);

        // Attach each back-end message to the field it is about (for the error summary links).
        foreach ($backendErrors as $msg) {
            $field = match (true) {
                str_starts_with($msg, 'Player 1')    => 'player1',
                str_starts_with($msg, 'Player 2'),
                str_contains($msg, 'cannot be the same') => 'player2',
                default                              => 'level-standard',
            };
            $errors[$field] ??= $msg;
        }

        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old']    = ['player_count' => (string) $count] + $input;
            redirect('setup.php');
        }

        $_SESSION['game'] = new_game($clean['players'], $clean['level'], $user);
        unset($_SESSION['last_score']);
        redirect('game.php');

    case 'roll':
        $game = current_game($user);
        if ($game === null) {
            redirect('index.php?expired=1');
        }
        if ($game['status'] === 'playing') {
            play_turn($game);
            $_SESSION['game'] = $game;
        }
        redirect($game['status'] === 'finished' ? 'recap.php' : 'game.php');

    case 'save':
        $game = current_game($user);
        if ($game === null || $game['status'] !== 'finished') {
            redirect('index.php?expired=1');
        }
        if (!$game['recorded']) {
            record_score($game);
            $_SESSION['game']       = $game;
            // Remember which row is "You" on the leaderboard.
            $_SESSION['last_score'] = [$game['players'][$game['winner']], $game['ended_at'], $user];
        }
        redirect('leaderboard.php');

    case 'reset':
        unset($_SESSION['game'], $_SESSION['last_score']);   // keep the login and leaderboard
        redirect('setup.php');
}
