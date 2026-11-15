<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Scoreboard\Row;

/** One ranked list of the score screen, the toplist window and /estat. */
interface TopList
{
    /** Key in config/manialinks.php `scoretable_lists` (also the /estat-independent identity). */
    public function key(): string;

    /** Owner title from the config. */
    public function title(): string;

    /** @return Row[] best first, at most $limit */
    public function rows(int $limit): array;
}
