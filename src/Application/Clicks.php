<?php
namespace Funseco\Scoreboard\Application;

use Funseco\Core\Models\Player;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Presentation\Windows;

/** ManiaLink clicks: toplist widget, window buttons, OK buttons, footer. One handler, routed by action name. */
class Clicks
{
    public function handle(Player $player, string $action): void
    {
        $windows = app(Windows::class);

        if ($action === 'score_close') {
            $windows->close($player);
        } elseif ($action === 'score_help') {
            $this->help($player);
        } elseif (str_starts_with($action, 'score_ok_')) {
            // windows are not opened over the score screen
            if (!app(ScoreScreen::class)->active() && ($list = app(Lists::class)->byKey(substr($action, strlen('score_ok_'))))) {
                $windows->openList($player, $list);
            }
        } elseif ($action === 'score_toplist') {
            if (!app(ScoreScreen::class)->active()) {
                $windows->openToplist($player);
            }
        } elseif (str_starts_with($action, 'score_')) {
            $windows->navigate($player, substr($action, strlen('score_')));
        }
    }

    /** The window footer opens the help window of the widgets plugin when it is installed. */
    public static function help(Player $player): void
    {
        if (\Chat::has('eyepiece')) {
            \Chat::run($player, 'eyepiece');
        }
    }
}
