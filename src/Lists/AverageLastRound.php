<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

/**
 * Average of every finish of the map, per connected player. In memory, this map only; the score
 * screen shows it and clears it. Best is lowest, except in Stunts where the highest score wins
 * (XAseco sorted ascending there too).
 */
class AverageLastRound extends BaseList
{
    protected string $key = 'top_average_times';
    protected string $defaultTitle = 'Average Last Round';

    public static function record(string $login, int $score): void
    {
        if ($score > 0) {
            $all = state()->get('scoreboard:finishes', []);
            $all[$login][] = $score;
            state()->forever('scoreboard:finishes', $all);
        }
    }

    public static function clear(): void
    {
        state()->forget('scoreboard:finishes');
    }

    public function rows(int $limit): array
    {
        $averages = [];
        foreach (state()->get('scoreboard:finishes', []) as $login => $scores) {
            if ($scores && CurrentPlayers::has($login)) {
                $averages[$login] = intdiv(array_sum($scores), count($scores));
            }
        }
        $stunts = Format::stunts();
        uksort($averages, fn ($a, $b) => [$stunts ? $averages[$b] : $averages[$a], $a] <=> [$stunts ? $averages[$a] : $averages[$b], $b]);

        $rows = [];
        foreach (array_slice($averages, 0, $limit, true) as $login => $average) {
            $rows[] = new Row(CurrentPlayers::get($login)->nickname, Format::time($average, $stunts), $login, count($rows) + 1);
        }

        return $rows;
    }
}
