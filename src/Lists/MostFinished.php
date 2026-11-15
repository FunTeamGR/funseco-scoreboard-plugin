<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\PlayerStats;
use Funseco\Scoreboard\Format;

class MostFinished extends BaseList
{
    protected string $key = 'most_finished';
    protected string $defaultTitle = 'Most Finished';

    public function rows(int $limit): array
    {
        return $this->playerRows(app(PlayerStats::class)->finishCounts($limit), fn ($n) => Format::number($n));
    }
}
