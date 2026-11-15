<?php
namespace Funseco\Scoreboard\Application;

use Funseco\Core\Models\Player;
use Funseco\Core\Servers\TmForever\Values\PlayerRankingsCollection;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Lists\AverageLastRound;
use Funseco\Scoreboard\Lists\TopList;
use Funseco\Scoreboard\Presentation\ListWidget;
use Funseco\Scoreboard\Presentation\ToplistWidget;
use Funseco\Scoreboard\Presentation\Windows;
use Funseco\Scoreboard\RoundPoints;

/** The lists shown while the score screen is up (end of race until the next map starts). */
class ScoreScreen
{
    private const ROUND_MODES = [0, 5];
    private const RANKING_LIMIT = 300;

    public function __construct(private Lists $lists, private ToplistWidget $toplist, private Windows $windows)
    {
    }

    public function active(): bool
    {
        return (bool) state()->get('scoreboard:active', false);
    }

    /**
     * Load the server's map list (Top Tracks only counts maps on the server), then recompute every
     * list, so the end of race never renders old figures.
     */
    public function refresh(?callable $then = null): void
    {
        $this->lists->keepLive(); // before the current map changes under the async calls below
        \Server::query()->GetChallengeList(5000, 0, function ($result) use ($then) {
            $rows = is_array($result[0] ?? null) ? $result[0] : [];
            state()->forever('scoreboard:server_maps', array_values(array_filter(array_column($rows, 'UId'))));
            $this->lists->refresh();
            if ($then) {
                $then();
            }
        });
    }

    /** Rounds / Cup: every ranked player's race score is added to his lifetime round points. */
    public function addRoundPoints(PlayerRankingsCollection $rankings): void
    {
        if (!in_array(ServerInfo::getGameMode(), self::ROUND_MODES, true)) {
            return;
        }
        $scores = [];
        foreach ($rankings->take(self::RANKING_LIMIT) as $ranking) {
            if ((int) $ranking->score > 0) {
                $scores[(string) $ranking->login] = (int) $ranking->score;
            }
        }
        if (!$scores) {
            return;
        }
        foreach (Player::whereIn('login', array_keys($scores))->pluck('id', 'login') as $login => $id) {
            RoundPoints::firstOrCreate(['player_id' => $id], ['points' => 0])->increment('points', $scores[$login]);
        }
    }

    /** End of race: close the race widgets and open windows, show every enabled list to everybody. */
    public function show(): void
    {
        if (CurrentPlayers::all()->isEmpty()) {
            AverageLastRound::clear();

            return;
        }
        $this->toplist->hideToAll();
        $this->windows->closeAll();

        $screen = [];
        foreach ($this->lists->scoreScreen() as $key => $list) {
            $screen[$key] = $this->listXml($list);
            ManiaLink::displayAll($screen[$key]);
        }
        state()->forever('scoreboard:screen', $screen);
        state()->forever('scoreboard:active', true);
        AverageLastRound::clear();
    }

    /**
     * A new map (or a restart): close every list. The controller may have restarted at the podium, so
     * the lists on the players' screens are not necessarily the ones it remembers: close them all.
     */
    public function hide(): void
    {
        $this->lists->releaseLive();
        $keys = array_unique([...array_keys((array) state()->get('scoreboard:screen', [])), ...array_keys($this->lists->all())]);
        foreach ($keys as $key) {
            ManiaLink::displayAll('<manialink id="'.ManiaLink::getAction('score_list_'.$key).'"></manialink>');
        }
        state()->forget('scoreboard:screen');
        state()->forget('scoreboard:active');
    }

    /** A player joining during the score screen gets the lists too (XAseco gave him race widgets). */
    public function showTo(Player $player): void
    {
        foreach ((array) state()->get('scoreboard:screen', []) as $xml) {
            ManiaLink::displayLogin($player, $xml);
        }
    }

    /** Re-send one list to everybody while the score screen is up (e.g. a winner was just counted). */
    public function update(string $key): void
    {
        $screen = (array) state()->get('scoreboard:screen', []);
        if (!$this->active() || !isset($screen[$key]) || !($list = $this->lists->byKey($key))) {
            return;
        }
        $screen[$key] = $this->listXml($list);
        state()->forever('scoreboard:screen', $screen);
        ManiaLink::displayAll($screen[$key]);
    }

    private function listXml(TopList $list): string
    {
        $entries = max(1, (int) config("manialinks.scoretable_lists.{$list->key()}.entries", 8));

        return ListWidget::xml($list, $this->lists->rows($list, $entries));
    }
}
