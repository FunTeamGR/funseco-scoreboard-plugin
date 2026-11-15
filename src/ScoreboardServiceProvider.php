<?php
namespace Funseco\Scoreboard;

use Funseco\Core\SharedKernel\PublishesPluginConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Funseco\Core\SharedKernel\Settings\Setting;
use Funseco\Core\SharedKernel\Settings\SettingsRegistry;
use Funseco\Core\Services\ManiaLink\ManiaLinkService;
use Funseco\Core\SharedKernel\Events\MapEnded;
use Funseco\Core\SharedKernel\Events\MapStarted;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\PlayerDisconnected;
use Funseco\Core\SharedKernel\Events\PlayerFinished;
use Funseco\Core\SharedKernel\Events\PlayerWon;
use Funseco\Core\SharedKernel\Events\StartupEvent;
use Funseco\Core\SharedKernel\UI\WidgetRegistry;
use Funseco\Scoreboard\Application\Clicks;
use Funseco\Scoreboard\Application\Listeners;
use Funseco\Scoreboard\Commands\EstatCommand;
use Funseco\Scoreboard\Application\ScoreScreen;
use Funseco\Scoreboard\Presentation\ToplistWidget;
use Funseco\Scoreboard\Presentation\Windows;

class ScoreboardServiceProvider extends ServiceProvider
{
    use PublishesPluginConfig;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scoreboard.php', 'scoreboard');
        $this->mergeConfigDeep(__DIR__.'/../config/manialinks.php', 'manialinks');
        $this->publishPluginConfig(__DIR__.'/..');
        // All read lazily through config() where used: live.
        $this->app->make(SettingsRegistry::class)->declare('scoreboard', 'scoreboard', [
            Setting::int('max_records', 'Records per map counted for Most Records', min: 1),
            Setting::bool('active_days_negative', 'Top Active Players shows negative days'),
            Setting::string('window_footer', 'Window footer text'),
            Setting::url('images.widget_ok_normal', 'OK button image'),
            Setting::url('images.widget_ok_focus', 'OK button image (focus)'),
        ]);
        $this->app->singleton(Lists::class);
        $this->app->singleton(Windows::class);
        $this->app->singleton(ToplistWidget::class);
        $this->app->singleton(ScoreScreen::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        ToplistWidget::register(app(WidgetRegistry::class));

        $maniaLinks = app(ManiaLinkService::class);
        $keys = array_keys(app(Lists::class)->all());
        // list widgets are plain ManiaLinks (not clickable); their ids come from the same registry
        $maniaLinks->addActions(array_map(fn ($k) => 'score_list_'.$k, $keys));
        $maniaLinks->addActions(['score_window']);
        $maniaLinks->addActions(array_merge(
            ['score_toplist', 'score_close', 'score_help', 'score_first', 'score_prev5', 'score_prev', 'score_next', 'score_next5', 'score_last'],
            array_map(fn ($k) => 'score_ok_'.$k, $keys),
        ), Clicks::class);

        \Chat::register(EstatCommand::class);

        Event::listen(StartupEvent::class,       [Listeners::class, 'startup']);
        Event::listen(MapStarted::class,         [Listeners::class, 'mapStarted']);
        Event::listen(MapEnded::class,           [Listeners::class, 'mapEnded']);
        Event::listen(\Funseco\Core\SharedKernel\Events\WindowOpened::class, function ($e) {
            $e->owner === 'scoreboard' || app(Windows::class)->otherWindowOpened($e->player);
        });
        Event::listen(PlayerFinished::class,     [Listeners::class, 'playerFinished']);
        Event::listen(PlayerConnected::class,    [Listeners::class, 'playerConnected']);
        Event::listen(PlayerDisconnected::class, [Listeners::class, 'playerDisconnected']);
        Event::listen(PlayerWon::class,          [Listeners::class, 'playerWon']);
    }
}
