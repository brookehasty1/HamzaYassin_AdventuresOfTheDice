<?php
declare(strict_types = 1);

const LEADERBOARD_MAX = 100;

/** Add winner data to leaderboard */
function record_score(array &$curr_game) {
    /** Don't record until needed */
    if ($curr_game['status'] !== 'finished' || $curr_game['recorded'] || $curr_game['winner'] === null) {
        return;
    }
    $w = $curr_game['winner'];

    $_SESSION['leaderboard'][] = [
        'name'        => $curr_game['players'][$w],
        'owner'       => $curr_game['owner'],
        'level'       => $curr_game['level'],
        'rolls'       => $curr_game['roll_counts'][$w],
        'seconds'     => max(0, ($curr_game['ended_at'] ?? time()) - $curr_game['started_at']),
        'players'     => count($curr_game['players']),
        'finished_at' => $curr_game['ended_at'] ?? time(),
    ];

    if (count($_SESSION['leaderboard']) > LEADERBOARD_MAX) {
        $_SESSION['leaderboard'] = array_slice(ranked_leaderboard(), 0, LEADERBOARD_MAX);
    }
    $curr_game['recorded'] = true;
}

/** Can sort board by difficulty */
function ranked_leaderboard(?string $level = null): array {
    $rows = $_SESSION['leaderboard'] ?? [];
    if ($level !== null) {
        $rows = array_filter($rows, fn(array $r): bool => $r['level'] === $level);
    }
    /** Sort by number of dice rolls and finish time */
    usort($rows, fn(array $a, array $b): int =>
        [$a['rolls'], $a['seconds'], $a['finished_at']] <=> [$b['rolls'], $b['seconds'], $b['finished_at']]);
    return array_values($rows);
}

/** Validate difficulty that shows on the board */
function leaderboard_filter(array $query): array {
    /** Raw is selected difficulty */
    $raw = $query['level'] ?? '';
    if (!is_string($raw) || $raw === '' || $raw === 'all') {
        return [null, null]; /** Disregard difficulty */
    }

    if (!isset(board_levels()[$raw])) {
        return [null, 'Unknown level filter. Showing all levels.'];
    }
    return [$raw, null];
}
?>