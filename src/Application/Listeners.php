<?php
namespace Funseco\Scoreboard\Application;

use Funseco\Core\SharedKernel\Events\MapEnded;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\PlayerDisconnected;
use Funseco\Core\SharedKernel\Events\PlayerFinished;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Lists\AverageLastRound;
use Funseco\Scoreboard\Presentation\ToplistWidget;
use Funseco\Scoreboard\Presentation\Windows;

/** Event wiring of the scoreboard plugin (SharedKernel events only). */
class Listeners
{
    public function startup(): void
    {
        app(ScoreScreen::class)->refresh(fn () => app(ToplistWidget::class)->showToAll());
    }

    public function mapStarted(): void
    {
        app(ScoreScreen::class)->hide();
        app(ToplistWidget::class)->showToAll();
    }

    /**
     * End of race: the lists are recomputed first (after the map list arrives, i.e. after every
     * other listener of this event ran), then shown. A restart only closes them.
     */
    public function mapEnded(MapEnded $event): void
    {
        $screen = app(ScoreScreen::class);
        if ($event->restart) {
            $screen->hide();

            return;
        }
        if ($event->wasWarmup) {
            return;
        }
        $screen->addRoundPoints($event->rankings);
        $screen->refresh(fn () => $screen->show());
    }

    public function playerFinished(PlayerFinished $event): void
    {
        AverageLastRound::record($event->player->login, $event->score);
    }

    public function playerConnected(PlayerConnected $event): void
    {
        app(Lists::class)->forget('top_active_players');
        $screen = app(ScoreScreen::class);
        $screen->active() ? $screen->showTo($event->player) : app(ToplistWidget::class)->show($event->player);
    }

    public function playerDisconnected(PlayerDisconnected $event): void
    {
        app(Windows::class)->forget($event->player->login);
    }

    public function playerWon(): void
    {
        $lists = app(Lists::class);
        $screen = app(ScoreScreen::class);
        foreach (['top_winners', 'top_active_players'] as $key) {
            $lists->forget($key);
            $screen->update($key);
        }
    }
}
