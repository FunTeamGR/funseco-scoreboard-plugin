<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\Values\RecordCollection;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

/** Shared by the Local and Dedimania lists: the current map's records in rank order. */
abstract class RecordList extends BaseList
{
    abstract protected function records(): RecordCollection;

    public function rows(int $limit): array
    {
        $stunts = Format::stunts();
        $rows = [];
        foreach ($this->records() as $record) {
            $score = $record->score();
            if ($score <= 0 || !$record->player || count($rows) >= $limit) {
                continue;
            }
            $rows[] = new Row($record->player->nickname, Format::time($score, $stunts), $record->player->login, count($rows) + 1);
        }

        return $rows;
    }
}
