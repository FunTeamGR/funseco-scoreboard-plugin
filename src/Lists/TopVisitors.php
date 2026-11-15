<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Visits;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

class TopVisitors extends BaseList
{
    protected string $key = 'top_visitors';
    protected string $defaultTitle = 'Top Visitors';

    public function rows(int $limit): array
    {
        $visits = app(Visits::class)->top($limit);
        $players = $visits ? Player::whereIn('login', array_keys($visits))->get()->keyBy('login') : collect();
        $rows = [];
        foreach ($visits as $login => $count) {
            if ($player = $players->get($login)) {
                $rows[] = new Row($player->nickname, Format::number($count), $login, count($rows) + 1);
            }
        }

        return $rows;
    }
}
