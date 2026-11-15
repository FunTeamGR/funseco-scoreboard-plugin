<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\RoundPoints;

class TopRoundscore extends BaseList
{
    protected string $key = 'top_roundscore';
    protected string $defaultTitle = 'Top Roundscore';

    public function rows(int $limit): array
    {
        $points = RoundPoints::where('points', '>', 0)->orderByDesc('points')->orderBy('player_id')->limit($limit)
            ->pluck('points', 'player_id')->map(fn ($n) => (int) $n)->all();

        return $this->playerRows($points, fn ($n) => Format::number($n));
    }
}
