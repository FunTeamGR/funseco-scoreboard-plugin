<?php
namespace Funseco\Scoreboard\Lists;

use Illuminate\Support\Collection;
use Funseco\Core\Models\Player;
use Funseco\Scoreboard\Row;

abstract class BaseList implements TopList
{
    protected string $key;
    protected string $defaultTitle = '';

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return (string) config("manialinks.scoretable_lists.{$this->key}.title", $this->defaultTitle);
    }

    /** @param int[] $ids @return Collection<int, Player> keyed by id */
    protected function players(array $ids): Collection
    {
        return $ids ? Player::whereIn('id', $ids)->get()->keyBy('id') : collect();
    }

    /**
     * Rows for "player id => figure" ordered best first.
     *
     * @param array<int, int|float> $figures
     * @return Row[]
     */
    protected function playerRows(array $figures, callable $score, bool $ranked = true): array
    {
        $players = $this->players(array_keys($figures));
        $rows = [];
        foreach ($figures as $id => $value) {
            if ($player = $players->get($id)) {
                $rows[] = new Row($player->nickname, $score($value), $player->login, $ranked ? count($rows) + 1 : null);
            }
        }

        return $rows;
    }
}
