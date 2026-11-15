<?php
namespace Funseco\Scoreboard;

/** One line of a list: display text only (already formatted and escaped-safe via Format). */
final class Row
{
    /**
     * @param string      $name  nickname / track name / country (raw; escaped when rendered)
     * @param string      $score the right-aligned figure, formatted
     * @param string|null $login player login, for the "online player" highlight
     * @param int|null    $rank  position for numbered lists
     * @param string|null $flag  Top Nations: corrected IOC code of the flag image
     */
    public function __construct(
        public readonly string $name,
        public readonly string $score = '',
        public readonly ?string $login = null,
        public readonly ?int $rank = null,
        public readonly ?string $flag = null,
    ) {
    }
}
