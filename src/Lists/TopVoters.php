<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\KarmaStats;
use Funseco\Scoreboard\Format;

class TopVoters extends BaseList
{
    protected string $key = 'top_voters';
    protected string $defaultTitle = 'Top Voters';

    public function rows(int $limit): array
    {
        return $this->playerRows(app(KarmaStats::class)->voteCounts($limit), fn ($n) => Format::number($n));
    }
}
