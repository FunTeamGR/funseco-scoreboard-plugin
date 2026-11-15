<?php
namespace Funseco\Scoreboard;

use Funseco\Scoreboard\Lists\AverageLastRound;
use Funseco\Scoreboard\Lists\DedimaniaList;
use Funseco\Scoreboard\Lists\HoursPlayed;
use Funseco\Scoreboard\Lists\LocalRecordsList;
use Funseco\Scoreboard\Lists\MostFinished;
use Funseco\Scoreboard\Lists\MostRecords;
use Funseco\Scoreboard\Lists\TopActive;
use Funseco\Scoreboard\Lists\TopDonators;
use Funseco\Scoreboard\Lists\TopList;
use Funseco\Scoreboard\Lists\TopNations;
use Funseco\Scoreboard\Lists\TopRanks;
use Funseco\Scoreboard\Lists\TopRoundscore;
use Funseco\Scoreboard\Lists\TopTracks;
use Funseco\Scoreboard\Lists\TopVisitors;
use Funseco\Scoreboard\Lists\TopVoters;
use Funseco\Scoreboard\Lists\TopWinners;
use Funseco\Core\Services\Cache\ServerInfo;

/**
 * The lists, their order and their /estat names. Coppers lists (finish winners, betwins)
 * and the dead Ultimania list are not built.
 */
class Lists
{
    /** Score-screen order. */
    private const SCORE_ORDER = [
        AverageLastRound::class, DedimaniaList::class, LocalRecordsList::class, TopRanks::class, TopWinners::class,
        MostRecords::class, MostFinished::class, HoursPlayed::class, TopDonators::class, TopNations::class, TopTracks::class,
        TopVoters::class, TopVisitors::class, TopActive::class, TopRoundscore::class,
    ];

    /** Order of the lists behind Dedimania and Local in the toplist window. */
    private const WINDOW_ORDER = [
        TopRanks::class, TopWinners::class, MostRecords::class, MostFinished::class, HoursPlayed::class, TopDonators::class,
        TopActive::class, TopRoundscore::class, TopVisitors::class, TopNations::class, TopVoters::class, TopTracks::class,
    ];

    /** /estat parameter => list class. */
    public const ESTAT = [
        'dedirecs' => DedimaniaList::class, 'localrecs' => LocalRecordsList::class, 'topnations' => TopNations::class,
        'topranks' => TopRanks::class, 'topwinners' => TopWinners::class, 'mostrecords' => MostRecords::class,
        'mostfinished' => MostFinished::class, 'topplaytime' => HoursPlayed::class, 'topdonators' => TopDonators::class, 'toptracks' => TopTracks::class,
        'topvoters' => TopVoters::class, 'topvisitors' => TopVisitors::class, 'topactive' => TopActive::class,
    ];

    /** Lists computed from live state rather than cached after the race. */
    private const LIVE = ['top_average_times', 'dedimania_records', 'local_records'];

    /** Rounds / Cup game mode ids. */
    private const ROUND_MODES = [0, 5];

    /** @var array<class-string, TopList> */
    private array $instances = [];

    public function get(string $class): TopList
    {
        return $this->instances[$class] ??= new $class();
    }

    /** @return TopList[] keyed by list key, in score-screen order */
    public function all(): array
    {
        $lists = [];
        foreach (self::SCORE_ORDER as $class) {
            $lists[$this->get($class)->key()] = $this->get($class);
        }

        return $lists;
    }

    public function byKey(string $key): ?TopList
    {
        return $this->all()[$key] ?? null;
    }

    /** Lists shown on the score screen: enabled in the config, mode rules applied. */
    public function scoreScreen(): array
    {
        return array_filter($this->all(), function (TopList $list) {
            if (!Format::flag("scoretable_lists.{$list->key()}.enabled")) {
                return false;
            }
            if ($list instanceof DedimaniaList) {
                return $list->available();
            }
            if ($list instanceof TopRoundscore) {
                return in_array(ServerInfo::getGameMode(), self::ROUND_MODES, true);
            }

            return true;
        });
    }

    /**
     * Lists of the toplist window: Dedimania (if the mode has it) and Local always, then the others only with data.
     *
     * @return TopList[]
     */
    public function toplistWindow(): array
    {
        $dedi = $this->get(DedimaniaList::class);
        $lists = $dedi->available() ? [$dedi] : [];
        $lists[] = $this->get(LocalRecordsList::class);
        foreach (self::WINDOW_ORDER as $class) {
            if ($this->rows($this->get($class), 1)) {
                $lists[] = $this->get($class);
            }
        }

        return $lists;
    }

    /** Rows of a list: live lists are computed now, the others come from the cache filled after each race. */
    public function rows(TopList $list, int $limit = 100): array
    {
        if (in_array($list->key(), self::LIVE, true)) {
            $kept = state()->get('scoreboard:live:'.$list->key());

            return $kept !== null ? array_slice($kept, 0, $limit) : $list->rows($limit);
        }
        $rows = state()->get($this->cacheKey($list));
        if ($rows === null) {
            $rows = $list->rows(100);
            state()->forever($this->cacheKey($list), $rows);
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * The live lists as they are when the race ends. The score screen is built a moment later, when the
     * server's current map is already the next one and the records of the map just played are gone.
     */
    public function keepLive(): void
    {
        foreach ($this->all() as $list) {
            if (in_array($list->key(), self::LIVE, true)) {
                state()->forever('scoreboard:live:'.$list->key(), $list->rows(100));
            }
        }
    }

    public function releaseLive(): void
    {
        foreach (self::LIVE as $key) {
            state()->forget('scoreboard:live:'.$key);
        }
    }

    /** Recompute every cached list (end of race, start). */
    public function refresh(): void
    {
        $this->forget();
        foreach ($this->all() as $list) {
            $this->rows($list);
        }
    }

    /** Drop cached rows: all, or one list (it is computed again on next use). */
    public function forget(?string $key = null): void
    {
        foreach ($key === null ? $this->all() : array_filter($this->all(), fn ($l) => $l->key() === $key) as $list) {
            state()->forget($this->cacheKey($list));
        }
    }

    private function cacheKey(TopList $list): string
    {
        return 'scoreboard:rows:'.$list->key();
    }
}
