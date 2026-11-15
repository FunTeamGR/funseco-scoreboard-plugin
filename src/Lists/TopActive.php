<?php
namespace Funseco\Scoreboard\Lists;

use Funseco\Core\Models\Player;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Row;

/** Race winners by how recently they were seen (players.updated_at: touched at connect and disconnect). */
class TopActive extends BaseList
{
    protected string $key = 'top_active_players';
    protected string $defaultTitle = 'Top Active Players';

    public function rows(int $limit): array
    {
        $rows = [];
        $today = now()->startOfDay();
        foreach (Player::where('wins', '>', 0)->orderByDesc('updated_at')->orderBy('id')->limit($limit)->get() as $player) {
            $days = $player->updated_at ? (int) $player->updated_at->copy()->startOfDay()->diffInDays($today, true) : 0;
            $sign = config('scoreboard.active_days_negative') ? '-' : '';
            $rows[] = new Row($player->nickname, $days === 0 ? 'Today' : $sign.$days.' d', $player->login, count($rows) + 1);
        }

        return $rows;
    }
}
