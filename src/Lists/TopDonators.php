<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Donations;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

/** The biggest donators (stored coppers totals). */
class TopDonators extends BaseList
{
    protected string $key = 'top_donators';
    protected string $defaultTitle = 'Top Donators';

    public function rows(int $limit): array
    {
        $top = app(Donations::class)->top($limit);
        $players = $top ? Player::whereIn('login', array_keys($top))->get()->keyBy('login') : collect();
        $rows = [];
        foreach ($top as $login => $coppers) {
            if ($player = $players->get($login)) {
                $rows[] = new Row($player->nickname, Format::number($coppers).' C', $login, count($rows) + 1);
            }
        }

        return $rows;
    }
}
