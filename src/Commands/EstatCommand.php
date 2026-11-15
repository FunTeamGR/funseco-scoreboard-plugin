<?php
namespace Funseco\Scoreboard\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Scoreboard\Application\Clicks;
use Funseco\Scoreboard\Application\ScoreScreen;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Lists\DedimaniaList;
use Funseco\Scoreboard\Presentation\Windows;

/** /estat <list>: opens one of the ranking windows. Open to everyone; race only. */
class EstatCommand implements Command, Described
{
    use DeclaresCommand;

    protected string $name = 'estat';
    protected string $description = 'Display one of the MoreRankingLists (see: /eyepiece)';
    protected string $usage = '<list>';

    public function handle(Player $player, array $args): void
    {
        if (app(ScoreScreen::class)->active()) {
            \Server::query()->ChatSendServerMessageToLogin(format_colors((string) config('scoreboard.messages.disallow_windows_at_score')), $player->login);

            return;
        }
        $lists = app(Lists::class);
        $class = Lists::ESTAT[strtolower((string) ($args[0] ?? ''))] ?? null;
        $list = $class ? $lists->get($class) : null;

        if ($list === null || ($list instanceof DedimaniaList && !$list->available())) {
            $this->help($player);

            return;
        }
        app(Windows::class)->openList($player, $list);
    }

    /** The help window belongs to the widgets plugin; without it the parameters are listed in chat. */
    private function help(Player $player): void
    {
        if (\Chat::has('eyepiece')) {
            Clicks::help($player);

            return;
        }
        $params = array_keys(array_filter(Lists::ESTAT, fn ($class) => !(app(Lists::class)->get($class) instanceof DedimaniaList) || app(Lists::class)->get($class)->available()));
        $text = str_replace('{1}', implode(', ', $params), (string) config('scoreboard.messages.estat_usage'));
        \Server::query()->ChatSendServerMessageToLogin(format_colors($text), $player->login);
    }
}
