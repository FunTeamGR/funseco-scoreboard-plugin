<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\PlayerStats;

class TopRanks extends BaseList
{
    protected string $key = 'top_rankings';
    protected string $defaultTitle = 'Top Ranks';

    public function rows(int $limit): array
    {
        return $this->playerRows(app(PlayerStats::class)->topRanks($limit), fn ($a) => sprintf('%.1f', $a));
    }
}
