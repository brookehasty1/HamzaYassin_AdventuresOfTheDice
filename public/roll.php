<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();
require_post('game.php');

$curr_game = current_game($user);
if ($curr_game === null) {
    flash('error', 'Start a game first.');
    redirect('setup.php');
}

if ($curr_game['status'] !== 'playing') {
    redirect('recap.php');
}

$move = play_turn($curr_game);
if ($move['event'] !== null) {
    $entry = $move['event'] + ['turn' => $move['turn'], 'player' => $move['name'],
                               'text' => narrate_event($move['event'], $move['name'])];
    $_SESSION['last_event']   = $entry;
    $_SESSION['events_log'][] = $entry;
}
record_score($curr_game);
$_SESSION['game'] = $curr_game;

redirect($curr_game['status'] === 'finished' ? 'recap.php' : 'game.php');
