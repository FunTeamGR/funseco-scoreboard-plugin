<?php
namespace Funseco\Scoreboard;

/** Number, time and name formatting shared by every list (owner config: manialinks.features). */
final class Format
{
    public const STUNTS = 4;

    public static function flag(string $key): bool
    {
        return filter_var(config("manialinks.$key"), FILTER_VALIDATE_BOOLEAN);
    }

    /** 1234567 -> "1,234,567" (english), "1.234.567" (german), "1 234 567" (french); optional "21k". */
    public static function number(int|float $n, int $decimals = 0): string
    {
        if (self::flag('features.shorten_numbers') && abs($n) > 1000) {
            return (string) intval($n / 1000).'k';
        }
        [$thousands, $point] = match (strtolower((string) config('manialinks.features.number_format', 'english'))) {
            'german' => ['.', ','],
            'french' => [' ', ','],
            default  => [',', '.'],
        };

        return number_format($n, $decimals, $point, $thousands);
    }

    /** m:ss.hh, h:mm:ss.hh from one hour; -1 is ???; Stunts scores are raw. */
    public static function time(int $score, bool $stunts = false): string
    {
        if ($stunts) {
            return (string) $score;
        }
        if ($score === -1) {
            return '???';
        }
        $hundredths = intdiv(max(0, $score) % 1000, 10);
        $total = intdiv(max(0, $score), 1000);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $seconds = $total % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $seconds, $hundredths)
            : sprintf('%d:%02d.%02d', $minutes, $seconds, $hundredths);
    }

    /**
     * Nickname / track name for a label: link codes and $S $H $W $I $L $O $N removed (colours stay),
     * optionally the dark colours $000..$555 lightened, newlines stripped. Not XML-escaped.
     */
    public static function name(?string $text): string
    {
        $text = str_replace('$$', "\0", (string) $text);
        $text = preg_replace('/\$[lhp]\[[^\]]*\]/i', '', $text);
        $text = preg_replace('/\$[shwilon]/i', '', $text);
        if (self::flag('features.illuminate_names')) {
            $text = preg_replace('/\$[0-5]{3}/', '$AAA', $text);
        }
        $text = str_replace(["\r", "\n"], '', $text);

        return str_replace("\0", '$$', $text);
    }

    public static function e(string $text): string
    {
        return htmlspecialchars(clean_utf8($text), ENT_QUOTES);
    }

    public static function stunts(): bool
    {
        return \Funseco\Core\Services\Cache\ServerInfo::getGameMode() === self::STUNTS;
    }
}
