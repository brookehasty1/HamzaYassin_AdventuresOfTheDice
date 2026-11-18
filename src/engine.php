<?php
declare(strict_types = 1);

function roll_die(): int {
    return random_int(1,6);
}

function validate_setup(array $input, string $defaultName = ''): array {
    $errors = [];
    $level  = (string)($input['level'] ?? '');
    if (!isset(board_levels()[$level])) {
        $errors[] = 'Choose difficulty.';
    }

    $count = (string)($input['player_count'] ?? '');
    if (!in_array($count, ['1', '2'], true)) {
        $errors[] = 'Choose either one or two players.';
        $count = '1';
    }

    $names = [];
    for ($i = 1; $i <= (int)$count; $i++) {
        $name = trim((string)($input["player$i"] ?? ''));
        if ($i === 1 && $name === '') {
            $name = $defaultName;
        }
        if (!preg_match('/^[\p{L}\p{N} _\-]{1,20}$/u', $name)) {
            $errors[] = "Player $i name must be between 1 - 20 letters, numbers, spaces, hyphens, or underscores.";
        }
        $names[] = $name;
    }

    if (count($names) === 2 && mb_strtolower($names[0]) === mb_strtolower($names[1])) {
        $errors[] = 'Player names cannot be the same.';
    }

    return [$errors, ['level' => $level, 'players' => $names]];
}



function new_game(array $players, string $level, string $owner = '', ?int $seed = null): array {
    board_for($level);
    $player_num = count($players);
    if ($player_num < 1 || $player_num > 2) {
        throw new InvalidArgumentException('1 or 2 players allowed.');
    }

    /** Seed makes events happen again for tests */
    return [
        'id'           => bin2hex(random_bytes(8)),
        'owner'        => $owner,
        'level'        => $level,
        'players'      => array_values($players),
        'positions'    => array_fill(0, $player_num, 0),     // 0 = not on the board yet
        'current'      => 0,                        // whose turn it is
        'turn_number'  => 0,                        // total rolls so far
        'skips'        => array_fill(0, $player_num, false), // pending "miss a turn" flags
        'roll_counts'  => array_fill(0, $player_num, 0),
        'roll_history' => [],                       // ['turn','player','roll']
        'moves'        => [],                       // full move records
        'last_move'    => null,
        'status'       => 'playing',                // playing | finished
        'winner'       => null,
        'started_at'   => time(),
        'ended_at'     => null,
        'seed'         => $seed ?? random_int(1, 1_000_000),
        'recorded'     => false,                    // leaderboard entry written?
    ];
}

/** Find who's turn it is after a play is made */
function advance_turn(array &$curr_game): array {
    $notices = [];
    $n    = count($curr_game['players']);
    $next = $curr_game['current'];

    for ($curr_gameuard = 0; $curr_gameuard <= $n; $curr_gameuard++) {
        $next = ($next + 1) % $n;
        /** Turn skipped */
        if ($curr_game['skips'][$next]) {
            $curr_game['skips'][$next] = false;
            $notices[] = "💤 {$curr_game['players'][$next]} is still dozing and loses this turn.";
            continue;
        }
        break;
    }
    $curr_game['current'] = $next;
    return $notices;
}

/** Defines what happens within a turn */
function play_turn(array &$curr_game, ?callable $dice = null): array {
    if ($curr_game['status'] !== 'playing') {
        throw new LogicException('The game has already ended.');
    }

    $dice ??= 'roll_die';

    $board = board_for($curr_game['level']);
    $i     = $curr_game['current'];
    $name  = $curr_game['players'][$i];
    $from  = $curr_game['positions'][$i];
    $turn  = ++$curr_game['turn_number'];

    /** Roll die */
    $roll = $dice();
    if (!is_int($roll) || $roll < 1 || $roll > 6) {
        throw new UnexpectedValueException('Die returned an invalid value.');
    }

    /** Find landing position */
    $res     = resolve_landing($board, $from, $roll);
    $landing = $res['landing'];
    $final   = $landing;
    $won     = $final >= BOARD_SIZE;
    $event   = null;
    $extra   = false;

    if (!$won && isset($board['events'][$landing])) {
        /** Special tile */
        $def = pick_event($board['events'][$landing], $curr_game['seed'], $turn, $landing);
        $out = apply_event($def, $landing);

        /** Update destination */
        $final = $out['final'];
        $extra = $out['extra_roll'];
        if ($out['skip']) {
            $curr_game['skips'][$i] = true;
        }

        $event = [
            'type'       => $def['type'],
            'msg'        => $def['msg'],
            'cell'       => $landing,
            'to'         => $final,
            'delta'      => $final - $landing,
            'extra_roll' => $extra,
            'skip'       => $out['skip'],
        ];
        $won = $final >= BOARD_SIZE;
    }

    /** Update actual position */
    $curr_game['positions'][$i] = $final;
    $curr_game['roll_counts'][$i]++;
    $curr_game['roll_history'][] = ['turn' => $turn, 'player' => $i, 'roll' => $roll];

    /** Store info for narraration */
    $move = [
        'turn'        => $turn,
        'player'      => $i,
        'name'        => $name,
        'from'        => $from,
        'roll'        => $roll,
        'raw'         => $res['raw'],
        'landing'     => $landing,          // after snake/ladder, before events
        'hazard'      => $res['hazard'],
        'event'       => $event,
        'final'       => $final,
        'won'         => $won,
        'narration'   => [],
    ];
    $move['narration'] = narrate_move($move, $name);

    if ($won) {
        $curr_game['status']   = 'finished';
        $curr_game['winner']   = $i;
        $curr_game['ended_at'] = time();
    } elseif (!$extra) {
        array_push($move['narration'], ...advance_turn($curr_game));
    }

    /** Save move */
    $curr_game['moves'][]   = $move;
    $curr_game['last_move'] = $move;
    return $move;
}
