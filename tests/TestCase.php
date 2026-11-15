<?php

namespace Funseco\Scoreboard\Tests;

use Funseco\Core\Testing\TestCase as CoreTestCase;
use Funseco\Widgets\WidgetsServiceProvider;
use Funseco\Records\RecordsServiceProvider;
use Funseco\Karma\KarmaServiceProvider;
use Funseco\Rankings\RankingsServiceProvider;
use Funseco\Scoreboard\ScoreboardServiceProvider;

abstract class TestCase extends CoreTestCase
{
    /** Dependencies first, then this plugin (isolated runs load only these). */
    protected function plugins(): array
    {
        return [
            WidgetsServiceProvider::class,
            RecordsServiceProvider::class,
            KarmaServiceProvider::class,
            RankingsServiceProvider::class,
            ScoreboardServiceProvider::class,
        ];
    }
}
