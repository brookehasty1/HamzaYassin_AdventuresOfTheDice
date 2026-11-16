<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

/** If user in game, send there, else create game */
$user = current_user();
if ($user === null) {
    redirect('login.php');
}
redirect(current_game($user) !== null ? 'game.php' : 'setup.php');