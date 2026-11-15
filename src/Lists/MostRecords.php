<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\RecordBook;
use Funseco\Scoreboard\Format;

/** Players by number of local records held (within the capped rank); players with none are left out. */
class MostRecords extends BaseList
{
    protected string $key = 'most_records';
    protected string $defaultTitle = 'Most Records';

    public function rows(int $limit): array
    {
        $counts = array_filter(app(RecordBook::class)->recordCounts((int) config('scoreboard.max_records', 50)), fn ($n) => $n > 0);
        uksort($counts, fn ($a, $b) => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return $this->playerRows(array_slice($counts, 0, $limit, true), fn ($n) => Format::number($n));
    }
}
