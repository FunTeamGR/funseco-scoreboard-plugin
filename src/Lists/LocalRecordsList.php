<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\SharedKernel\CurrentLocalRecords;
use Funseco\Core\SharedKernel\Values\RecordCollection;

class LocalRecordsList extends RecordList
{
    protected string $key = 'local_records';
    protected string $defaultTitle = 'Local Records';

    protected function records(): RecordCollection
    {
        return app(CurrentLocalRecords::class)->records();
    }
}
