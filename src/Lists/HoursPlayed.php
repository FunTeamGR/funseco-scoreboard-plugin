<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\PlayerStats;
use Funseco\Scoreboard\Format;

/** Players with more than one hour of play time. */
class HoursPlayed extends BaseList
{
    protected string $key = 'top_playtime';
    protected string $defaultTitle = 'Hours Played';

    public function rows(int $limit): array
    {
        return $this->playerRows(app(PlayerStats::class)->topPlaytime($limit, 3600), fn ($s) => Format::number((int) round($s / 3600)).' h');
    }
}
