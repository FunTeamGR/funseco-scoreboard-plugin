<?php
namespace Funseco\Scoreboard\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Funseco\Core\Testing\FakesServer;
use Funseco\Scoreboard\Tests\TestCase;
use Funseco\Core\Models\Map;
use Funseco\Core\Models\Player;
use Funseco\Core\Servers\TmForever\Values\ChallengeInfo;
use Funseco\Core\Servers\TmForever\Values\CheckpointCollection;
use Funseco\Core\Servers\TmForever\Values\PlayerInfo;
use Funseco\Core\Servers\TmForever\Values\PlayerRankingsCollection;
use Funseco\Core\Services\Cache\CurrentMap;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\Services\Chat\Facades\Chat;
use Funseco\Core\SharedKernel\Events\MapEnded;
use Funseco\Core\SharedKernel\Events\MapStarted;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\PlayerFinished;
use Funseco\Core\SharedKernel\Events\PlayerWon;
use Funseco\Core\SharedKernel\Visits;
use Funseco\Karma\KarmaVote;
use Funseco\Records\Infrastructure\LocalRecord;
use Funseco\Records\Infrastructure\LocalRecords;
use Funseco\Scoreboard\Application\Listeners;
use Funseco\Scoreboard\Application\ScoreScreen;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Lists\AverageLastRound;
use Funseco\Scoreboard\Lists\DedimaniaList;
use Funseco\Scoreboard\Lists\LocalRecordsList;
use Funseco\Scoreboard\Lists\MostFinished;
use Funseco\Scoreboard\Lists\MostRecords;
use Funseco\Scoreboard\Lists\TopActive;
use Funseco\Scoreboard\Lists\TopNations;
use Funseco\Scoreboard\Lists\TopRanks;
use Funseco\Scoreboard\Lists\TopRoundscore;
use Funseco\Scoreboard\Lists\TopTracks;
use Funseco\Scoreboard\Lists\TopVisitors;
use Funseco\Scoreboard\Lists\TopVoters;
use Funseco\Scoreboard\Lists\TopWinners;
use Funseco\Scoreboard\Lists\HoursPlayed;
use Funseco\Scoreboard\Presentation\ListWidget;
use Funseco\Scoreboard\Presentation\ToplistWidget;
use Funseco\Scoreboard\Presentation\Windows;
use Funseco\Scoreboard\RoundPoints;
use Funseco\Rankings\Rank;
use Funseco\Rankings\Time;

class ScoreboardTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    private Player $alice;
    private Player $bob;
    private Player $carol;
    private Map $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeServer();
        CurrentPlayers::clear();
        ServerInfo::setGameMode(1);
        $this->alice = Player::factory()->create(['login' => 'alice', 'nickname' => '$f00Alice', 'nation' => 'GRE', 'wins' => 5, 'time_played' => 7300]);
        $this->bob = Player::factory()->create(['login' => 'bob', 'nickname' => '$o$sBob', 'nation' => 'GRE', 'wins' => 2, 'time_played' => 100]);
        $this->carol = Player::factory()->create(['login' => 'carol', 'nickname' => 'Carol', 'nation' => 'SCG', 'wins' => 0, 'time_played' => 0]);
        CurrentPlayers::add($this->alice);
        CurrentPlayers::add($this->bob);
        $this->map = Map::factory()->create(['name' => 'Speed', 'uid' => 'uid-speed']);
        CurrentMap::set($this->map);
    }

    private function lists(): Lists
    {
        return app(Lists::class);
    }

    private function names(array $rows): array
    {
        return array_map(fn ($r) => $r->name, $rows);
    }

    private function scoreXml(): string
    {
        return implode("\n", array_map(fn ($a) => $a[0], $this->sent('SendDisplayManialinkPage')));
    }

    private function endRace(bool $restart = false, ?PlayerRankingsCollection $rankings = null): void
    {
        app(Listeners::class)->mapEnded(new MapEnded($this->map, new ChallengeInfo(array_fill(0, 14, 0)), $rankings ?? new PlayerRankingsCollection(), false, $restart));
    }

    // ---- formatting ------------------------------------------------------------------------

    public function test_time_number_and_name_formats()
    {
        $this->assertSame('1:23.45', Format::time(83450));
        $this->assertSame('1:01:01.05', Format::time(3661050));
        $this->assertSame('???', Format::time(-1));
        $this->assertSame('777', Format::time(777, true));
        $this->assertSame('1,234', Format::number(1234));
        config(['manialinks.features.number_format' => 'german']);
        $this->assertSame('1.234', Format::number(1234));
        config(['manialinks.features.number_format' => 'french']);
        $this->assertSame('1 234', Format::number(1234));
        config(['manialinks.features.shorten_numbers' => 'true']);
        $this->assertSame('21k', Format::number(21500));
        $this->assertSame('$f00Al$$ice', Format::name("\$o\$f00Al\$\$i\$l[http://x]ce\$l\n"));
        config(['manialinks.features.illuminate_names' => 'true']);
        $this->assertSame('$AAAx$f00y', Format::name('$222x$f00y'));
    }

    // ---- lists -----------------------------------------------------------------------------

    public function test_top_winners_and_hours_played_and_top_ranks()
    {
        $this->assertSame(['$f00Alice', '$o$sBob'], $this->names($this->lists()->get(TopWinners::class)->rows(10)));
        $this->assertSame('5', $this->lists()->get(TopWinners::class)->rows(10)[0]->score);

        $hours = $this->lists()->get(HoursPlayed::class)->rows(10);
        $this->assertCount(1, $hours);
        $this->assertSame('2 h', $hours[0]->score);

        Rank::create(['player_id' => $this->bob->id, 'average' => 2.26]);
        Rank::create(['player_id' => $this->alice->id, 'average' => 12.34]);
        $ranks = $this->lists()->get(TopRanks::class)->rows(10);
        $this->assertSame(['2.3', '12.3'], array_map(fn ($r) => $r->score, $ranks));
        $this->assertSame(1, $ranks[0]->rank);
    }

    public function test_most_finished_and_most_records_leave_out_zero_counts()
    {
        $time = fn ($player) => Time::create(['map_id' => $this->map->id, 'player_id' => $player->id, 'score' => 40000, 'checkpoints' => []]);
        $time($this->alice);
        $time($this->alice);
        $time($this->bob);
        $finished = $this->lists()->get(MostFinished::class)->rows(10);
        $this->assertSame(['$f00Alice', '$o$sBob'], $this->names($finished));
        $this->assertSame(['2', '1'], array_map(fn ($r) => $r->score, $finished));

        LocalRecord::create(['map_id' => $this->map->id, 'player_id' => $this->bob->id, 'score' => 41000, 'checkpoints' => new CheckpointCollection([])]);
        $records = $this->lists()->get(MostRecords::class)->rows(10);
        $this->assertSame(['$o$sBob'], $this->names($records), 'Alice holds none and is not listed');
    }

    public function test_local_records_list_is_numbered_and_stunts_scores_are_raw()
    {
        LocalRecord::create(['map_id' => $this->map->id, 'player_id' => $this->alice->id, 'score' => 40010, 'checkpoints' => new CheckpointCollection([])]);
        LocalRecord::create(['map_id' => $this->map->id, 'player_id' => $this->bob->id, 'score' => 41230, 'checkpoints' => new CheckpointCollection([])]);
        $rows = $this->lists()->get(LocalRecordsList::class)->rows(10);
        $this->assertSame(['0:40.01', '0:41.23'], array_map(fn ($r) => $r->score, $rows));
        $this->assertSame([1, 2], array_map(fn ($r) => $r->rank, $rows));
        $this->assertSame('alice', $rows[0]->login);

        ServerInfo::setGameMode(4);
        $this->assertSame('40010', $this->lists()->get(LocalRecordsList::class)->rows(10)[0]->score);
        $this->assertFalse($this->lists()->get(DedimaniaList::class)->available());
        $this->assertArrayNotHasKey('dedimania_records', $this->lists()->scoreScreen());
    }

    public function test_average_last_round_uses_connected_players_and_sorts_by_mode()
    {
        AverageLastRound::record('alice', 40000);
        AverageLastRound::record('alice', 41001);
        AverageLastRound::record('bob', 39000);
        AverageLastRound::record('bob', 0);       // not a finish
        AverageLastRound::record('carol', 10000); // not connected
        $list = $this->lists()->get(AverageLastRound::class);

        $rows = $list->rows(10);
        $this->assertSame(['$o$sBob', '$f00Alice'], $this->names($rows));
        $this->assertSame('0:40.50', $rows[1]->score);

        ServerInfo::setGameMode(4);
        $this->assertSame(['$f00Alice', '$o$sBob'], $this->names($list->rows(10)), 'Stunts: highest first');
    }

    public function test_top_nations_fixes_codes_and_names_countries()
    {
        $rows = $this->lists()->get(TopNations::class)->rows(10);
        $this->assertSame(['Greece', 'Serbia'], $this->names($rows));
        $this->assertSame(['GRE', 'SRB'], array_map(fn ($r) => $r->flag, $rows));
        $this->assertSame(['2', '1'], array_map(fn ($r) => $r->score, $rows));
        $this->assertNull($rows[0]->rank);
    }

    public function test_top_active_orders_by_last_seen_and_formats_days()
    {
        $this->alice->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $this->bob->forceFill(['updated_at' => now()])->saveQuietly();
        $rows = $this->lists()->get(TopActive::class)->rows(10);
        $this->assertSame(['$o$sBob', '$f00Alice'], $this->names($rows));
        $this->assertSame(['Today', '-3 d'], array_map(fn ($r) => $r->score, $rows), 'XAseco shows it negative');

        config(['scoreboard.active_days_negative' => false]);
        $this->assertSame('3 d', $this->lists()->get(TopActive::class)->rows(10)[1]->score);
    }

    public function test_top_visitors_use_the_visits_contract()
    {
        $this->assertSame([], $this->lists()->get(TopVisitors::class)->rows(10), 'null default');
        $this->app->instance(Visits::class, new class implements Visits {
            public function top(int $limit): array { return ['bob' => 1200, 'alice' => 3]; }
        });
        $rows = $this->lists()->get(TopVisitors::class)->rows(10);
        $this->assertSame(['$o$sBob', '$f00Alice'], $this->names($rows));
        $this->assertSame('1,200', $rows[0]->score);
    }

    public function test_top_tracks_and_voters_from_karma_votes()
    {
        $good = Map::factory()->create(['name' => 'Good one', 'uid' => 'uid-good']);
        $few = Map::factory()->create(['name' => 'Few votes', 'uid' => 'uid-few']);
        $gone = Map::factory()->create(['name' => 'Not on server', 'uid' => 'uid-gone']);
        state()->forever('scoreboard:server_maps', ['uid-few', 'uid-good', 'uid-speed']);
        config(['manialinks.features.karma.min_votes' => 3]);

        $vote = fn ($map, $player, $score) => KarmaVote::create(['map_id' => $map->id, 'player_id' => $player->id, 'score' => $score]);
        foreach ([$this->alice, $this->bob, $this->carol] as $p) {
            $vote($good, $p, 3);
            $vote($gone, $p, 3);
        }
        $vote($few, $this->alice, 3);
        // Speed: 1 x Fantastic, 1 x Good, 1 x Waste = (100 + 60 + 0) / 3 = 53
        $vote($this->map, $this->alice, 3);
        $vote($this->map, $this->bob, 1);
        $vote($this->map, $this->carol, -3);

        $rows = $this->lists()->get(TopTracks::class)->rows(10);
        $this->assertSame(['Good one', 'Speed'], $this->names($rows));
        $this->assertSame(['100', '53'], array_map(fn ($r) => $r->score, $rows));

        config(['manialinks.features.karma.calculation_method' => 'rasp']);
        $this->assertSame(['9', '1'], array_map(fn ($r) => $r->score, $this->lists()->get(TopTracks::class)->rows(10)));

        $voters = $this->lists()->get(TopVoters::class)->rows(10);
        $this->assertSame('$f00Alice', $voters[0]->name);
        $this->assertSame('4', $voters[0]->score);
    }

    // ---- widgets ---------------------------------------------------------------------------

    public function test_list_widget_layouts()
    {
        LocalRecord::create(['map_id' => $this->map->id, 'player_id' => $this->alice->id, 'score' => 40010, 'checkpoints' => new CheckpointCollection([])]);
        $local = $this->lists()->get(LocalRecordsList::class);
        $xml = ListWidget::xml($local, $this->lists()->rows($local, 8));
        $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_list_local_records').'">', $xml);
        $this->assertStringContainsString('<frame posn="-63.5 9.85 0">', $xml);
        $this->assertStringContainsString('sizen="15.5 17.7"', $xml);       // 8 entries
        $this->assertStringContainsString('text="1."', $xml);               // numbered list
        $this->assertStringContainsString('posn="5.9 -3 0.002"', $xml);     // name label, row 0
        $this->assertStringNotContainsString('action=', $xml, 'score lists are not clickable');

        $winners = $this->lists()->get(TopWinners::class);
        $xml = ListWidget::xml($winners, $this->lists()->rows($winners, 6));
        $this->assertStringContainsString('sizen="15.5 14.1"', $xml);       // 6 entries
        $this->assertStringContainsString('posn="4.65 -3 0.002"', $xml);
        $this->assertStringNotContainsString('text="1."', $xml);
    }

    public function test_empty_list_still_draws_its_frame()
    {
        $xml = ListWidget::xml($this->lists()->get(MostRecords::class), []);
        $this->assertStringContainsString('text="Most Records"', $xml);
        $this->assertStringNotContainsString('scale="0.9"', $xml);
    }

    public function test_player_strings_are_escaped()
    {
        $this->alice->update(['nickname' => 'A"<b>&']);
        $xml = ListWidget::xml($this->lists()->get(TopWinners::class), $this->lists()->get(TopWinners::class)->rows(6));
        $this->assertStringContainsString('A&quot;&lt;b&gt;&amp;', $xml);
        $this->assertStringNotContainsString('<b>', $xml);
    }

    public function test_toplist_widget_is_pinned_from_the_config()
    {
        $xml = app(ToplistWidget::class)->xml();
        $this->assertStringContainsString('<frame posn="29.9 39.3 0">', $xml);
        $this->assertStringContainsString('text="MORE"', $xml);
        $this->assertStringContainsString('action="'.ManiaLink::getAction('score_toplist').'"', $xml);

        config(['manialinks.toplist_widget.gamemode.time_attack.enabled' => 'false']);
        $this->assertNull(app(ToplistWidget::class)->xml());
    }

    // ---- score screen ----------------------------------------------------------------------

    public function test_end_of_race_shows_the_enabled_lists_and_new_map_hides_them()
    {
        $this->responses['GetChallengeList'] = [['UId' => 'uid-speed']];
        $this->endRace();

        $xml = $this->scoreXml();
        foreach (['top_rankings', 'top_winners', 'most_records', 'most_finished', 'top_playtime', 'top_donators', 'top_nations', 'top_tracks', 'top_voters', 'top_visitors', 'local_records', 'dedimania_records', 'top_average_times'] as $key) {
            $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_list_'.$key).'">', $xml, $key);
        }
        foreach (['top_active_players', 'top_roundscore', 'top_betwins', 'top_winning_payouts'] as $key) {
            $this->assertArrayNotHasKey($key, $this->lists()->scoreScreen());
        }
        // race widgets are closed
        $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_toplist').'"></manialink>', $xml);
        $this->assertTrue(app(ScoreScreen::class)->active());

        $before = count($this->sent('SendDisplayManialinkPage'));
        app(Listeners::class)->mapStarted();
        $after = $this->sent('SendDisplayManialinkPage');
        $closed = implode('', array_map(fn ($a) => $a[0], array_slice($after, $before)));
        $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_list_top_winners').'"></manialink>', $closed);
        $this->assertStringContainsString('MORE', $closed, 'toplist widget is back');
        $this->assertFalse(app(ScoreScreen::class)->active());
    }

    public function test_new_map_closes_the_lists_even_after_a_controller_restart_during_the_score_screen()
    {
        $this->responses['GetChallengeList'] = [['UId' => 'uid-speed']];
        $this->endRace();
        // The controller restarted at the podium: the lists are still on the players' screens, its memory is empty.
        state()->forget('scoreboard:screen');
        state()->forget('scoreboard:active');
        $before = count($this->sent('SendDisplayManialinkPage'));

        app(Listeners::class)->mapStarted();

        $closed = implode('', array_map(fn ($a) => $a[0], array_slice($this->sent('SendDisplayManialinkPage'), $before)));
        foreach (['top_rankings', 'top_winners', 'local_records', 'dedimania_records'] as $key) {
            $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_list_'.$key).'"></manialink>', $closed, $key);
        }
    }

    public function test_restart_closes_the_lists_without_showing_new_ones()
    {
        $this->endRace();
        $count = count($this->sent('SendDisplayManialinkPage'));
        $this->endRace(true);
        $closed = implode('', array_map(fn ($a) => $a[0], array_slice($this->sent('SendDisplayManialinkPage'), $count)));
        $this->assertStringContainsString('></manialink>', $closed);
        $this->assertStringNotContainsString('text="Top Winners"', $closed);
        $this->assertFalse(app(ScoreScreen::class)->active());
    }

    public function test_nothing_is_shown_without_players()
    {
        CurrentPlayers::clear();
        $this->endRace();
        $this->assertSame([], $this->sent('SendDisplayManialinkPage'));
    }

    public function test_player_joining_at_score_gets_the_lists_and_in_race_the_toplist_widget()
    {
        $info = new PlayerInfo(array_fill(0, 12, 0));
        app(Listeners::class)->playerConnected(new PlayerConnected($this->carol, $info));
        $this->assertStringContainsString('MORE', implode('', array_map(fn ($a) => $a[1], $this->sent('SendDisplayManialinkPageToLogin'))));

        $this->endRace();
        $this->calls = [];
        app(Listeners::class)->playerConnected(new PlayerConnected($this->carol, $info));
        $sent = implode('', array_map(fn ($a) => $a[1], $this->sent('SendDisplayManialinkPageToLogin')));
        $this->assertStringContainsString('text="Top Winners"', $sent);
        $this->assertStringNotContainsString('text="MORE"', $sent);
    }

    public function test_average_is_shown_then_cleared_and_finishes_are_recorded_from_events()
    {
        app(Listeners::class)->playerFinished(new PlayerFinished($this->alice, $this->map, 40000, new CheckpointCollection([])));
        $this->endRace();
        $this->assertStringContainsString('0:40.00', $this->scoreXml());
        $this->assertSame([], $this->lists()->get(AverageLastRound::class)->rows(10));
    }

    public function test_a_win_updates_the_listed_winners_while_the_score_screen_is_up()
    {
        $this->endRace();
        $this->calls = [];
        $this->bob->update(['wins' => 9]);
        app(Listeners::class)->playerWon();
        $this->assertStringContainsString('text="9"', $this->scoreXml());
        $this->assertSame('9', $this->lists()->get(TopWinners::class)->rows(10)[0]->score);
    }

    public function test_round_points_are_collected_in_rounds_and_cup_only()
    {
        $rank = new PlayerRankingsCollection([
            (object) ['login' => 'alice', 'score' => 10, 'rank' => 1, 'best_time' => 1],
            (object) ['login' => 'bob', 'score' => 0, 'rank' => 2, 'best_time' => 1],
        ]);
        ServerInfo::setGameMode(1);
        $this->endRace(false, $rank);
        $this->assertSame(0, RoundPoints::count());

        ServerInfo::setGameMode(0);
        $this->endRace(false, $rank);
        $this->endRace(false, $rank);
        $rows = $this->lists()->get(TopRoundscore::class)->rows(10);
        $this->assertSame(['$f00Alice'], $this->names($rows));
        $this->assertSame('20', $rows[0]->score);
        $this->assertArrayHasKey('top_roundscore', $this->lists()->all());
    }

    // ---- windows ---------------------------------------------------------------------------

    public function test_estat_opens_the_full_window_with_four_columns()
    {
        Chat::run($this->alice, 'estat', ['topwinners']);
        $xml = $this->window();
        $this->assertStringContainsString('<frame posn="-40.1 30.45 18.50">', $xml);
        $this->assertStringContainsString('text="Top Winners"', $xml);
        $this->assertStringContainsString('<frame posn="2.5 -6.5 1">', $xml);
        $this->assertSame(4, substr_count($xml, 'sizen="17.75 46.88"'));
        $this->assertStringContainsString('text="5"', $xml);
        // Alice is online: highlight quad
        $this->assertStringContainsString('substyle="BgCardSystem"', $xml);
        $this->assertStringNotContainsString('score_first', $xml);
        $this->assertStringNotContainsString('ArrowFirst', $xml, 'one page: no paging buttons');
    }

    public function test_estat_is_case_insensitive_and_empty_lists_show_nothing()
    {
        Chat::run($this->alice, 'estat', ['TopWinners']);
        $this->assertNotSame('', $this->window());

        $this->calls = [];
        Chat::run($this->alice, 'estat', ['mostrecords']);
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
        $this->assertSame([], $this->messages());
    }

    public function test_estat_unknown_or_missing_parameter_opens_the_eyepiece_help()
    {
        // With the widgets plugin installed /eyepiece exists; /estat hands over to it (chat usage
        // text is only the fallback when it doesn't).
        $this->assertTrue(Chat::has('eyepiece'));
        foreach ([[], ['bogus']] as $args) {
            $this->calls = [];
            Chat::run($this->alice, 'estat', $args);
            $this->assertNotSame('', $this->window());
            $this->assertSame([], $this->messages());
        }

        ServerInfo::setGameMode(4);   // Stunts: Dedimania isn't offered either
        $this->calls = [];
        Chat::run($this->alice, 'estat', ['dedirecs']);
        $this->assertNotSame('', $this->window());
    }

    public function test_estat_is_refused_at_score()
    {
        $this->endRace();
        $this->calls = [];
        Chat::run($this->alice, 'estat', ['topwinners']);
        $this->assertStringContainsString('Could not display this window at score', $this->messages()[0]);
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
    }

    public function test_top_nations_window_has_flags_and_no_rank_numbers()
    {
        Chat::run($this->alice, 'estat', ['topnations']);
        $xml = $this->window();
        $this->assertStringContainsString('image="tmtp://Skins/Avatars/Flags/GRE.dds"', $xml);
        $this->assertStringContainsString('Flags/SRB.dds', $xml);
        $this->assertStringContainsString('text="Greece"', $xml);
        $this->assertStringNotContainsString('text="1."', $xml);
    }

    public function test_local_records_window_pages_and_shows_the_record_count()
    {
        $players = Player::factory()->count(130)->create();
        foreach ($players as $i => $p) {
            LocalRecord::create(['map_id' => $this->map->id, 'player_id' => $p->id, 'score' => 40000 + $i, 'checkpoints' => new CheckpointCollection([])]);
        }
        Chat::run($this->alice, 'estat', ['localrecs']);
        $xml = $this->window();
        $this->assertStringContainsString('Local Records   |   Page 1/2   |   130 Records', $xml);
        $this->assertStringContainsString('<frame posn="52.2 -53.2">', $xml);
        $this->assertStringContainsString('substyle="ArrowNext" action="'.ManiaLink::getAction('score_next').'"', $xml);
        $this->assertStringNotContainsString('substyle="ArrowPrev"', $xml);
        $this->assertStringContainsString('StarGold', $xml);
        $this->assertStringContainsString('text="100."', $xml);
        $this->assertStringNotContainsString('text="101."', $xml);

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_next'));
        $xml = $this->window();
        $this->assertStringContainsString('Page 2/2', $xml);
        $this->assertStringContainsString('text="101."', $xml);
        $this->assertStringContainsString('text="130."', $xml);
        $this->assertStringNotContainsString('text="100."', $xml);

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_first'));
        $this->assertStringContainsString('Page 1/2', $this->window());
    }

    public function test_toplist_widget_click_opens_the_window_and_ok_opens_a_list()
    {
        LocalRecords::clear();
        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_toplist'));
        $xml = $this->window();
        $this->assertStringContainsString('Top Rankings   |   Page 1/', $xml);
        $this->assertStringContainsString('<frame posn="2.5 -5.7 1">', $xml);
        $this->assertStringContainsString('<frame posn="67.05 -53.2">', $xml);
        // Local Records always (even empty); lists without data are skipped
        $this->assertStringContainsString('text="Local Records"', $xml);
        $this->assertStringContainsString('text="Top Winners"', $xml);
        $this->assertStringNotContainsString('text="Most Records"', $xml);
        $this->assertStringContainsString('action="'.ManiaLink::getAction('score_ok_top_winners').'"', $xml);

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_ok_top_winners'));
        $this->assertStringContainsString('text="Top Winners"', $this->window());
        $this->assertStringContainsString('<frame posn="2.5 -6.5 1">', $this->window());

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_close'));
        $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('score_window').'"></manialink>', $this->window());
        $this->assertNull(app(Windows::class)->xml($this->alice));
    }

    public function test_toplist_window_pages_four_lists_at_a_time()
    {
        Rank::create(['player_id' => $this->bob->id, 'average' => 2.26]);
        $this->app->instance(Visits::class, new class implements Visits {
            public function top(int $limit): array { return ['bob' => 3]; }
        });
        // Dedimania + Local + Top Ranks + Top Winners + Hours Played + Top Visitors + Top Nations = 7 lists
        app(Windows::class)->openToplist($this->alice);
        $this->assertStringContainsString('Page 1/2', $this->window());
        $this->assertStringContainsString('action="'.ManiaLink::getAction('score_next').'"', $this->window());
        $this->assertStringNotContainsString('text="Top Nations"', $this->window());

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_next'));
        $this->assertStringContainsString('Page 2/2', $this->window());
        $this->assertStringContainsString('text="Top Nations"', $this->window());
        $this->assertStringContainsString('StarGold', $this->window(), 'last page: next is unavailable');
    }

    public function test_widget_clicks_are_ignored_at_score()
    {
        $this->endRace();
        $this->calls = [];
        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('score_toplist'));
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
    }

    public function test_dedimania_window_hides_in_stunts_and_uses_current_records()
    {
        $this->app->instance(\Funseco\Core\SharedKernel\CurrentDedimaniaRecords::class, new class($this->alice) implements \Funseco\Core\SharedKernel\CurrentDedimaniaRecords {
            public function __construct(private Player $p) {}
            public function records(): \Funseco\Core\SharedKernel\Values\RecordCollection
            {
                $r = new \Funseco\Core\SharedKernel\Values\Record($this->p->id, 39000);
                $r->player = $this->p;
                return new \Funseco\Core\SharedKernel\Values\RecordCollection([$r]);
            }
        });
        Chat::run($this->alice, 'estat', ['dedirecs']);
        $this->assertStringContainsString('0:39.00', $this->window());
        $this->assertStringNotContainsString('dedimania.net', $this->window());
    }
}
