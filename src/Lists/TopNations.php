<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Services\Stats\NationCounts;
use Funseco\Core\SharedKernel\Values\Nation;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

/** Number of known players per nation. Not numbered. */
class TopNations extends BaseList
{
    protected string $key = 'top_nations';
    protected string $defaultTitle = 'Top Nations';

    public function rows(int $limit): array
    {
        $rows = [];
        foreach (array_slice(NationCounts::all(), 0, $limit, true) as $code => $count) {
            $rows[] = new Row(Nation::name($code), Format::number($count), null, null, NationCounts::flag($code));
        }

        return $rows;
    }
}
