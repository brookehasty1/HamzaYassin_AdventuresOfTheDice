<?php
declare(strict_types=1);

/**
 * CLI test suite:  php tests/run_tests.php
 * No framework needed. Exit code 0 = all passed.
 */

require __DIR__ . '/../src/bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed++;
        echo "  FAIL $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
    }
}

function throws(callable $fn, string $class = Throwable::class): bool
{
    try {
        $fn();
    } catch (Throwable $t) {
        return $t instanceof $class;
    }
    return false;
}

/** Scripted dice: returns the given rolls in order. */
function dice(int ...$rolls): callable
{
    return function () use (&$rolls): int {
        return array_shift($rolls) ?? throw new RuntimeException('Out of scripted rolls');
    };
}

echo "Board data\n";
foreach (board_levels() as $key => $b) {
    check("$key board is valid", validate_board($b) === [], implode('; ', validate_board($b)));
}
$L = board_levels();
check('beginner has 3 snakes / 3 ladders', count($L['beginner']['snakes']) === 3 && count($L['beginner']['ladders']) === 3);
check('standard has 6 snakes / 5 ladders', count($L['standard']['snakes']) === 6 && count($L['standard']['ladders']) === 5);
check('expert has 9 snakes / 4 ladders',   count($L['expert']['snakes']) === 9 && count($L['expert']['ladders']) === 4);
$bad = $L['beginner'];
$bad['snakes'][4] = 2; // snake head on a ladder base
check('validate_board catches a snake/ladder overlap', validate_board($bad) !== []);

echo "Landing rules\n";
$B = board_for('beginner');
check('normal move',             resolve_landing($B, 10, 3)['landing'] === 13);
check('snake slides down',       resolve_landing($B, 26, 6)['landing'] === 10);
check('ladder climbs up',        resolve_landing($B, 0, 4)['landing'] === 25);
check('overshoot 100 = finish',  resolve_landing($B, 98, 6)['landing'] === 100);

echo "Dice fairness\n";
$counts = array_fill(1, 6, 0);
for ($i = 0; $i < 12000; $i++) {
    $counts[roll_die()]++;
}
check('all six faces occur ~2000x (1700..2300)', min($counts) > 1700 && max($counts) < 2300, json_encode($counts));
$seqA = $seqB = [];
pick_event($B['events'][15], 1, 5, 15);
for ($i = 0; $i < 20; $i++) { $seqA[] = roll_die(); }
pick_event($B['events'][15], 1, 5, 15);
for ($i = 0; $i < 20; $i++) { $seqB[] = roll_die(); }
check('event seeding does not make dice repeat', $seqA !== $seqB);

echo "Event engine (UG)\n";
$pool = $B['events'][15];
check('same seed+turn+cell => same event', pick_event($pool, 7, 3, 15) === pick_event($pool, 7, 3, 15));
$seen = [];
for ($t = 1; $t <= 60; $t++) { $seen[pick_event($pool, 7, $t, 15)['type'] . ($t >= 0 ? '' : '')] = true; }
check('different turns reach different event types', count($seen) >= 2, json_encode(array_keys($seen)));
check('bonus moves forward',        apply_event(event('bonus', 'm', ['move' => 5]), 15)['final'] === 20);
check('bonus caps at 100',          apply_event(event('bonus', 'm', ['move' => 5]), 98)['final'] === 100);
check('penalty moves back',         apply_event(event('penalty', 'm', ['move' => 3]), 15)['final'] === 12);
check('penalty floors at cell 1',   apply_event(event('penalty', 'm', ['move' => 5]), 2)['final'] === 1);
check('warp jumps to target',       apply_event(event('warp', 'm', ['to' => 77]), 67)['final'] === 77);
check('skip flags a lost turn',     apply_event(event('skip', 'm'), 42)['skip'] === true);
check('extra-roll flag set',        apply_event(event('bonus', 'm', ['move' => 0, 'extra_roll' => true]), 15)['extra_roll'] === true);
check('bad event definition rejected', event_definition_problem(['type' => 'warp', 'msg' => 'x']) !== null);

echo "Turn engine\n";
$g = new_game(['Ann', 'Bob'], 'beginner', 'u', 1);
$m = play_turn($g, dice(4));
check('ladder: Ann 0 -> 25',                 $g['positions'][0] === 25 && $m['hazard']['type'] === 'ladder');
check('turn passes to Bob',                  $g['current'] === 1);
check('narrator mentions the ladder',        str_contains(implode(' ', $m['narration']), 'ladder'));
$g['positions'][1] = 26;
$m = play_turn($g, dice(6));
check('snake: Bob 26 -> 10',                 $g['positions'][1] === 10 && $m['hazard']['type'] === 'snake');
check('narrator dramatises the snake',       str_contains(implode(' ', $m['narration']), 'serpent hisses'));
check('roll history recorded',               count($g['roll_history']) === 2 && $g['roll_history'][0]['roll'] === 4);
check('Ann/Bob roll counts',                 $g['roll_counts'] === [1, 1]);

$g = new_game(['Solo'], 'beginner', 'u', 1);
$g['positions'][0] = 97;
$m = play_turn($g, dice(6));
check('overshoot wins and caps at 100',      $g['status'] === 'finished' && $g['positions'][0] === 100 && $g['winner'] === 0);
check('no play after the game ends',         throws(fn() => play_turn($g, dice(1)), LogicException::class));
check('invalid die value rejected',          throws(function () { $x = new_game(['A'], 'beginner'); play_turn($x, fn() => 7); }, UnexpectedValueException::class));

// Find a seed that yields a specific event at a given cell/turn.
function seed_for(array $pool, int $turn, int $cell, callable $want): int
{
    for ($s = 0; $s < 5000; $s++) {
        if ($want(pick_event($pool, $s, $turn, $cell))) { return $s; }
    }
    throw new RuntimeException('no seed found');
}
$pool15 = $B['events'][15];
$seed = seed_for($pool15, 1, 15, fn($e) => !empty($e['extra_roll']));
$g = new_game(['Ann', 'Bob'], 'beginner', 'u', $seed);
$g['positions'][0] = 12;
$m = play_turn($g, dice(3));
check('extra-roll event keeps the turn',     $g['current'] === 0 && $m['event']['extra_roll'] === true);
check('event stored in last_event + log',    $g['last_event']['type'] === 'bonus' && count($g['events_log']) === 1);
check('event narration is in the move',      str_contains(implode(' ', $m['narration']), 'extra roll'));

$pool42 = $B['events'][42];
$seed = seed_for($pool42, 1, 42, fn($e) => $e['type'] === 'skip');
$g = new_game(['Ann', 'Bob'], 'beginner', 'u', $seed);
$g['positions'][0] = 38;
play_turn($g, dice(4)); // Ann -> 42, skip event
check('skip event flags Ann',                $g['skips'][0] === true && $g['current'] === 1);
$m = play_turn($g, dice(2)); // Bob moves; Ann's turn is consumed
check('skipped player loses next turn',      $g['current'] === 1 && $g['skips'][0] === false);
check('skip notice is narrated',             str_contains(implode(' ', $m['narration']), 'loses this turn'));

echo "Simulated full games\n";
foreach (array_keys(board_levels()) as $level) {
    $ok = true; $totalRolls = 0;
    for ($n = 0; $n < 300 && $ok; $n++) {
        $g = new_game(['A', 'B'], $level, 'u');
        $guard = 0;
        while ($g['status'] === 'playing' && $guard++ < 5000) { play_turn($g); }
        $ok = $g['status'] === 'finished' && max($g['positions']) === 100 && min($g['positions']) >= 0;
        $totalRolls += $g['turn_number'];
    }
    check("$level: 300 two-player games all finish (avg " . round($totalRolls / 300) . " rolls total)", $ok);
}

echo "Setup validation\n";
[$errs, $clean] = validate_setup(['level' => 'expert', 'player_count' => '2', 'player1' => 'Ann', 'player2' => 'Bob']);
check('valid setup accepted',                $errs === [] && $clean['players'] === ['Ann', 'Bob']);
[$errs] = validate_setup(['level' => 'nope', 'player_count' => '3', 'player1' => '<b>']);
check('bad level / count / name rejected',   count($errs) >= 3);
[$errs] = validate_setup(['level' => 'standard', 'player_count' => '2', 'player1' => 'Sam', 'player2' => 'sam']);
check('duplicate names rejected',            $errs !== []);
[$errs, $clean] = validate_setup(['level' => 'standard', 'player_count' => '1', 'player1' => ''], 'Kim');
check('blank player 1 defaults to username', $errs === [] && $clean['players'] === ['Kim']);
[$errs] = validate_setup([]);
check('missing fields rejected',             $errs !== []);

echo "Auth\n";
$_SESSION = [];
check('valid registration passes',           validate_registration('dice_fan', 'longenough1', 'longenough1') === []);
check('short username rejected',             validate_registration('ab', 'longenough1', 'longenough1') !== []);
check('short password rejected',             validate_registration('dice_fan', 'short', 'short') !== []);
check('mismatched confirm rejected',         validate_registration('dice_fan', 'longenough1', 'different11') !== []);
check('markup in username rejected',         validate_registration('<script>', 'longenough1', 'longenough1') !== []);
register_user('dice_fan', 'longenough1');
check('password stored hashed, not plain',   $_SESSION['users']['dice_fan']['hash'] !== 'longenough1' && password_verify('longenough1', $_SESSION['users']['dice_fan']['hash']));
check('duplicate username rejected (case-insensitive)', validate_registration('DICE_FAN', 'longenough1', 'longenough1') !== []);
check('wrong password fails',                attempt_login('dice_fan', 'wrongpass') === false && !isset($_SESSION['user']));
check('unknown user fails',                  attempt_login('nobody', 'longenough1') === false);
check('correct password logs in',            attempt_login('DICE_FAN', 'longenough1') === true && current_user() === 'dice_fan');
logout_user();
check('logout clears login but keeps accounts', current_user() === null && isset($_SESSION['users']['dice_fan']));

echo "Leaderboard\n";
$_SESSION = [];
$mk = function (string $who, string $level, int $rolls, int $secs) {
    $g = new_game([$who], $level, 'u');
    $g['status'] = 'finished'; $g['winner'] = 0; $g['roll_counts'] = [$rolls];
    $g['started_at'] = 1000; $g['ended_at'] = 1000 + $secs;
    record_score($g);
    return $g;
};
$mk('Slow', 'standard', 40, 300);
$mk('Fast', 'standard', 20, 100);
$g3 = $mk('TieQuick', 'expert', 20, 50);
$ranked = ranked_leaderboard();
check('ranked by rolls then time',           array_column($ranked, 'name') === ['TieQuick', 'Fast', 'Slow']);
check('level filter works',                  array_column(ranked_leaderboard('standard'), 'name') === ['Fast', 'Slow']);
record_score($g3);
check('record_score is idempotent',          count($_SESSION['leaderboard']) === 3);
$unfinished = new_game(['X'], 'standard', 'u');
record_score($unfinished);
check('unfinished games are not recorded',   count($_SESSION['leaderboard']) === 3);
check('bad level filter ignored with notice', leaderboard_filter(['level' => 'bogus'])[1] !== null && leaderboard_filter(['level' => 'bogus'])[0] === null);
check('good level filter accepted',          leaderboard_filter(['level' => 'expert']) === ['expert', null]);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
