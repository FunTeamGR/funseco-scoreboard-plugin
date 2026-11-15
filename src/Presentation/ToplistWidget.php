<?php
namespace Funseco\Scoreboard\Presentation;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\UI\Region;
use Funseco\Core\SharedKernel\UI\WidgetRegistry;
use Funseco\Scoreboard\Format;

/** The "MORE RANKING" button of the race screen; a click opens the toplist window. */
class ToplistWidget
{
    public const NAME = 'toplist_widget';

    /**
     * Register in the WidgetRegistry. The owner config gives one position for every mode
     * (toplist_widget.pos_x / pos_y); it is pinned for each mode unless a mode sets its own.
     */
    public static function register(WidgetRegistry $registry): void
    {
        $registry->regionWidget(self::NAME, Region::TopRight, 50, width: 4.6, height: 6.5);

        $base = (array) config('manialinks.'.self::NAME);
        foreach (WidgetRegistry::GAMEMODES as $mode) {
            $own = (array) config('manialinks.'.self::NAME.".gamemode.$mode", []);
            config(['manialinks.'.self::NAME.".gamemode.$mode" => $own + array_filter([
                'enabled' => $base['enabled'] ?? null,
                'pos_x'   => $base['pos_x'] ?? null,
                'pos_y'   => $base['pos_y'] ?? null,
            ], fn ($v) => $v !== null)]);
        }
    }

    public static function id(): int
    {
        return ManiaLink::getAction('score_toplist');
    }

    public function show(Player $player): void
    {
        if ($xml = $this->xml()) {
            ManiaLink::displayLogin($player, $xml);
        }
    }

    public function showToAll(): void
    {
        if (($xml = $this->xml()) && CurrentPlayers::all()->isNotEmpty()) {
            ManiaLink::displayAll($xml);
        }
    }

    public function hideToAll(): void
    {
        ManiaLink::displayAll('<manialink id="'.self::id().'"></manialink>');
    }

    /** Null when the widget is disabled in the current game mode. */
    public function xml(): ?string
    {
        $placement = app(WidgetRegistry::class)->placement(self::NAME);
        if ($placement === null) {
            return null;
        }
        $c = (array) config('manialinks.'.self::NAME);
        $color = $c['text_color'] ?? 'FC0F';
        $e = Format::e(...);

        return '<manialink id="'.self::id().'"><frame posn="'.$placement->x.' '.$placement->y.' 0">'
            .'<format textsize="1"/>'
            .'<quad posn="0 0 0.001" sizen="4.6 6.5" style="'.$e($c['background_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($c['background_substyle'] ?? 'ProgressBar').'" action="'.ManiaLink::getAction('score_toplist').'"/>'
            .'<quad posn="0.7 -0.3 0.002" sizen="3.35 3" style="Icons128x128_1" substyle="Rankings"/>'
            .'<label posn="2.3 -3.4 0.1" sizen="3.65 2" halign="center" scale="0.9" text="MORE"/>'
            .'<label posn="2.3 -4.9 0.1" sizen="6.35 2" halign="center" scale="0.6" textcolor="'.$e($color).'" text="RANKING"/>'
            .'</frame></manialink>';
    }
}
