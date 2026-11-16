<?php
declare(strict_types = 1);

/** Pick one event from a cell's pool. mt_srand() is seeded from the turn number */
function pick_event(array $pool, int $gameSeed, int $turn, int $cell): array
{
    mt_srand($gameSeed + $turn * 131 + $cell);
    $index = mt_rand(0, count($pool) - 1);
    mt_srand(); // restore random seeding
    return $pool[$index];
}

/** Apply an event definition to a cell. Pure: returns the outcome only. Events never chain into snakes/ladders */
function apply_event(array $def, int $cell): array
{
    $final = $cell;
    $extra = false;
    $skip  = false;

    switch ($def['type']) {
        case 'bonus':
            $final = min(BOARD_SIZE, $cell + $def['move']);
            $extra = !empty($def['extra_roll']);
            break;
        case 'penalty':
            $final = max(1, $cell - $def['move']);
            break;
        case 'skip':
            $skip = true;
            break;
        case 'warp':
            $final = $def['to'];
            break;
    }
    return ['final' => $final, 'extra_roll' => $extra, 'skip' => $skip];
}

/** Story-language line for an event. Numbers come from the computed result. */
function narrate_event(array $event, string $player): string
{
    $msg   = $event['msg'];
    $to    = $event['to'];
    $delta = abs($event['delta']);

    return match ($event['type']) {
        'bonus'   => $event['extra_roll']
            ? "🎁 $msg $player earns an extra roll!"
            : "✨ $msg $player surges ahead $delta cells to cell $to!",
        'penalty' => "🌫️ $msg $player is pushed back $delta cells to cell $to.",
        'skip'    => "💤 $msg $player will miss the next turn.",
        'warp'    => "🌀 $msg $player is whisked from cell {$event['cell']} to cell $to!",
    };
}

/** The "AI Narrator" text for one move (array of lines). */
function narrate_move(array $m, string $player): array
{
    $lines = [];
    $from  = $m['from'] === 0 ? 'the start' : 'cell ' . $m['from'];

    if ($m['raw'] >= BOARD_SIZE) {
        $lines[] = "🎲 $player rolls a {$m['roll']} and charges to the finish line!";
    } else {
        $lines[] = "🎲 $player rolls a {$m['roll']} and moves from $from to cell {$m['raw']}.";
    }

    if ($m['hazard'] !== null) {
        $lines[] = $m['hazard']['type'] === 'snake'
            ? "🐍 The serpent hisses — $player is dragged back to cell {$m['hazard']['to']}!"
            : "🪜 $player scrambles up a ladder to cell {$m['hazard']['to']}!";
    }

    if ($m['event'] !== null) {
        $lines[] = narrate_event($m['event'], $player);
    }

    if ($m['won']) {
        $lines[] = "🏆 $player reaches cell 100 and wins the adventure!";
    }
    return $lines;
}
