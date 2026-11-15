<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\CurrentDedimaniaRecords;
use Funseco\Core\SharedKernel\Values\RecordCollection;
use Funseco\Scoreboard\Format;

/** Dedimania has no Stunts records: the list is unavailable (and empty) in that mode. */
class DedimaniaList extends RecordList
{
    protected string $key = 'dedimania_records';
    protected string $defaultTitle = 'Dedimania Records';

    public function available(): bool
    {
        return !Format::stunts();
    }

    protected function records(): RecordCollection
    {
        return $this->available() ? app(CurrentDedimaniaRecords::class)->records() : new RecordCollection();
    }
}
