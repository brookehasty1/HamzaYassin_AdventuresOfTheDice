<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';


$user = require_login();
require_post('game.php');

$curr_game = current_game($user);
if ($curr_game === null) {
    flash('error', 'Start a game first');
    redirect('setup.php');
}

if ($curr_game['status'] !== 'playing') {
    redirect('recap.php');
}

play_turn($curr_game);
record_score($curr_game);
$_SESSION['game'] = $curr_game;

redirect($curr_game['status'] === 'finished' ? 'recap.php' : 'game.php');
?>