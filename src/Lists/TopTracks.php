<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Models\Map;
use Funseco\Core\SharedKernel\KarmaStats;
use Funseco\Scoreboard\Row;

/**
 * Best karma among the maps currently on the server. Method tmkarma: percentage of the weighted
 * votes (Fantastic 100, Beautiful 80, Good 60, Bad 40, Poor 20, Waste 0); rasp: sum of the votes.
 * Maps below the minimum number of votes, or with karma below 1, are left out.
 * The server's map list is loaded by ServerMaps before the lists are computed.
 */
class TopTracks extends BaseList
{
    public const WEIGHTS = [3 => 100, 2 => 80, 1 => 60, -1 => 40, -2 => 20, -3 => 0];

    protected string $key = 'top_tracks';
    protected string $defaultTitle = 'Top Tracks';

    public function rows(int $limit): array
    {
        $uids = state()->get('scoreboard:server_maps', []);
        if (!$uids) {
            return [];
        }
        $order = array_flip($uids);
        $maps = Map::whereIn('uid', $uids)->get()->keyBy('id');
        $minVotes = (int) config('manialinks.features.karma.min_votes', 30);
        $method = (string) config('manialinks.features.karma.calculation_method', 'tmkarma');

        $karma = [];
        foreach (app(KarmaStats::class)->votesByMap() as $mapId => $votes) {
            $total = array_sum($votes);
            if (!$maps->has($mapId) || $total === 0 || $total < $minVotes) {
                continue;
            }
            if ($method === 'rasp') {
                $value = 0;
                foreach ($votes as $score => $n) {
                    $value += $score * $n;
                }
            } else {
                $weighted = 0;
                foreach ($votes as $score => $n) {
                    $weighted += (self::WEIGHTS[$score] ?? 0) * $n;
                }
                $value = intdiv($weighted, $total);
            }
            if ($value >= 1) {
                $karma[$mapId] = $value;
            }
        }
        uksort($karma, fn ($a, $b) => [$karma[$b], $order[$maps[$a]->uid]] <=> [$karma[$a], $order[$maps[$b]->uid]]);

        $rows = [];
        foreach (array_slice($karma, 0, $limit, true) as $mapId => $value) {
            $rows[] = new Row($maps[$mapId]->name, (string) $value, null, count($rows) + 1);
        }

        return $rows;
    }
}
