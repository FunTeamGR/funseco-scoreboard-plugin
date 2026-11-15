<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\PlayerStats;
use Funseco\Scoreboard\Format;

class TopWinners extends BaseList
{
    protected string $key = 'top_winners';
    protected string $defaultTitle = 'Top Winners';

    public function rows(int $limit): array
    {
        return $this->playerRows(app(PlayerStats::class)->topWins($limit), fn ($n) => Format::number($n));
    }
}
