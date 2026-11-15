<?php
namespace Funseco\Scoreboard\Presentation;

use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Lists\TopList;
use Funseco\Scoreboard\Row;

/** A score-screen list: one ManiaLink per list at the position and size set in manialinks.scoretable_lists. */
class ListWidget
{
    /** Lists with a rank label ("1.") in front; the others only a score and a name. */
    public const NUMBERED = ['top_average_times', 'dedimania_records', 'local_records'];

    public static function id(TopList $list): int
    {
        return ManiaLink::getAction('score_list_'.$list->key());
    }

    public static function height(int $entries): float
    {
        return round(1.8 * $entries + 3.3, 2);
    }

    /** @param Row[] $rows */
    public static function xml(TopList $list, array $rows): string
    {
        $c = (array) config("manialinks.scoretable_lists.{$list->key()}");
        $entries = max(1, (int) ($c['entries'] ?? 8));
        $style = (array) config('manialinks.style.widget_score');
        $codes = (string) ($style['formatting_codes'] ?? '');
        $default = $style['colors']['default'] ?? 'FFFF';
        $scores = $style['colors']['scores'] ?? 'DDDF';
        $numbered = in_array($list->key(), self::NUMBERED, true);
        $e = Format::e(...);

        $xml  = '<manialink id="'.self::id($list).'"><frame posn="'.($c['pos_x'] ?? 0).' '.($c['pos_y'] ?? 0).' 0">';
        $xml .= '<format textsize="1" textcolor="'.$default.'"/>';
        $xml .= '<quad posn="0 0 0.001" sizen="15.5 '.self::height($entries).'" style="'.$e($style['background_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['background_substyle'] ?? 'BgRacePlayerName').'"/>';
        $xml .= '<quad posn="0.4 -0.36 0.002" sizen="14.7 2" style="'.$e($style['title_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['title_substyle'] ?? 'ProgressBar').'"/>';
        $xml .= '<quad posn="0.6 -0.15 0.004" sizen="2.5 2.5" style="'.$e($c['icon_style'] ?? 'Icons128x128_1').'" substyle="'.$e($c['icon_substyle'] ?? 'Rankings').'"/>';
        $xml .= '<label posn="3.2 -0.55 0.004" sizen="10.2 0" textsize="1" text="'.$e($codes.$list->title()).'"/>';

        foreach (array_slice($rows, 0, $entries) as $i => $row) {
            $y = -(1.8 * $i + 3);
            if ($row->flag !== null) {
                $xml .= self::label(4, $y, 3.4, $scores, $row->score, 'right', $codes);
                $xml .= '<quad posn="4.65 '.($y + 0.3).' 0.002" sizen="2 2" image="'.$e(self::flagImage($row->flag)).'"/>';
                $xml .= self::label(7, $y, 8.75, $default, $row->name, 'left', $codes, false);
            } elseif ($numbered) {
                $xml .= self::label(2.1, $y, 1.7, $default, $row->rank.'.', 'right', $codes);
                $xml .= self::label(5.7, $y, 3.8, $scores, $row->score, 'right', $codes);
                $xml .= self::label(5.9, $y, 10.2, $default, $row->name, 'left', $codes, true);
            } else {
                $xml .= self::label(4, $y, 3.4, $scores, $row->score, 'right', $codes);
                $xml .= self::label(4.65, $y, 11.1, $default, $row->name, 'left', $codes, true);
            }
        }

        return $xml.'</frame></manialink>';
    }

    public static function flagImage(string $code): string
    {
        return 'tmtp://Skins/Avatars/Flags/'.($code === 'OTH' ? 'other' : $code).'.dds';
    }

    private static function label(float $x, float $y, float $w, string $color, string $text, string $align, string $codes, bool $clean = false): string
    {
        $text = $clean ? Format::name($text) : $text;

        return '<label posn="'.$x.' '.$y.' 0.002" sizen="'.$w.' 1.7" scale="0.9"'.($align === 'right' ? ' halign="right"' : '')
            .' textcolor="'.$color.'" text="'.Format::e($codes.$text).'"/>';
    }
}
