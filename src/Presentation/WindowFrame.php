<?php
namespace Funseco\Scoreboard\Presentation;

use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Scoreboard\Format;

/**
 * The eyepiece-style window frame (x -64..64, y 48..-48): headline, close button, footer and a
 * button strip. Kept in this plugin so it works standalone; one window per player.
 */
class WindowFrame
{
    /** Paging buttons of the six-button strip: x => [substyle, action]. */
    public const SIX = [
        6.6 => ['ArrowFirst', 'score_first'], 9.9 => ['ArrowFastPrev', 'score_prev5'], 13.2 => ['ArrowPrev', 'score_prev'],
        16.5 => ['ArrowNext', 'score_next'], 19.8 => ['ArrowFastNext', 'score_next5'], 23.1 => ['ArrowLast', 'score_last'],
    ];

    public static function id(): int
    {
        return ManiaLink::getAction('score_window');
    }

    public static function closeXml(): string
    {
        return '<manialink id="'.self::id().'"></manialink>';
    }

    /**
     * @param array{0: string, 1: string} $icon style, substyle
     * @param string $buttons XML placed in the button frame at (x, y)
     */
    public static function xml(string $title, array $icon, string $content, string $buttons = '', string $buttonsAt = '52.2 -53.2'): string
    {
        $w = (array) config('manialinks.style.window');
        $e = Format::e(...);
        $xml  = '<manialink id="'.self::id().'">';
        if (Format::flag('style.window.lightbox.enabled')) {
            $xml .= '<quad posn="-64 48 18.49" sizen="128 96" bgcolor="'.$e($w['lightbox']['bgcolor'] ?? '0008').'"/>';
        }
        $xml .= '<frame posn="-40.1 30.45 18.50">';
        $xml .= '<quad posn="0.8 -0.8 0.01" sizen="78.4 53.7" bgcolor="'.$e($w['window_bgcolor'] ?? '001B').'"/>';
        $xml .= '<quad posn="-0.2 0.2 0.04" sizen="80.4 55.7" style="Bgs1InRace" substyle="BgCard3"/>';
        $xml .= '<quad posn="0.8 -1.3 0.02" sizen="78.4 3" bgcolor="'.$e($w['headline_bgcolor'] ?? '09FC').'"/>';
        $xml .= '<quad posn="0.8 -4.3 0.03" sizen="78.4 0.1" bgcolor="FFF9"/>';
        $xml .= '<quad posn="1.8 -1 0.04" sizen="3.2 3.2" style="'.$e($icon[0]).'" substyle="'.$e($icon[1]).'"/>';
        $xml .= '<label posn="5.5 -1.9 0.04" sizen="74 0" textsize="2" scale="0.9" textcolor="'.$e($w['headline_textcolor'] ?? 'FFFF').'" text="'.$e($title).'"/>';
        $xml .= '<quad posn="2.7 -54.1 0.04" sizen="11 1" bgcolor="0000" action="'.ManiaLink::getAction('score_help').'"/>';
        $xml .= '<label posn="2.7 -54.1 0.04" sizen="30 1" textsize="1" scale="0.7" textcolor="000F" text="'.$e((string) config('scoreboard.window_footer')).'"/>';
        $xml .= '<frame posn="77.4 1.3 0.05">'
            .'<quad posn="0 0 0.01" sizen="4 4" style="Icons64x64_1" substyle="ArrowDown"/>'
            .'<quad posn="1.1 -1.35 0.02" sizen="1.8 1.75" bgcolor="EEEF"/>'
            .'<quad posn="0.65 -0.7 0.03" sizen="2.6 2.6" style="Icons64x64_1" substyle="Close" action="'.ManiaLink::getAction('score_close').'"/>'
            .'</frame>';
        if ($buttons !== '') {
            $xml .= '<frame posn="'.$buttonsAt.'">'.$buttons.'</frame>';
        }

        return $xml.$content.'</frame></manialink>';
    }

    /** One paging button; an unusable one is two stacked gold stars without action. */
    public static function button(float $x, string $substyle, ?string $action): string
    {
        if ($action === null) {
            return '<quad posn="'.$x.' 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>'
                .'<quad posn="'.$x.' 0 0.13" sizen="3.2 3.2" style="Icons64x64_1" substyle="StarGold"/>';
        }

        return '<quad posn="'.$x.' 0 0.12" sizen="3.2 3.2" style="Icons64x64_1" substyle="'.$substyle.'" action="'.ManiaLink::getAction($action).'"/>';
    }

    public static function sixButtons(int $page, int $pages): string
    {
        $xml = '';
        foreach (self::SIX as $x => [$substyle, $action]) {
            $back = in_array($action, ['score_first', 'score_prev5', 'score_prev'], true);
            $usable = $back ? $page > 1 : $page < $pages;
            $xml .= self::button($x, $substyle, $usable ? $action : null);
        }

        return $xml;
    }

    public static function pair(int $page, int $pages): string
    {
        return self::button(4.95, 'ArrowPrev', $page > 1 ? 'score_prev' : null)
            .self::button(8.25, 'ArrowNext', $page < $pages ? 'score_next' : null);
    }
}
