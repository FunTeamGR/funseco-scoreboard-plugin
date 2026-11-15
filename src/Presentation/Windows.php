<?php
namespace Funseco\Scoreboard\Presentation;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Scoreboard\Format;
use Funseco\Scoreboard\Lists;
use Funseco\Scoreboard\Lists\LocalRecordsList;
use Funseco\Scoreboard\Lists\TopList;
use Funseco\Scoreboard\Lists\TopNations;
use Funseco\Scoreboard\Row;

/**
 * The toplist window (four lists per page) and the full list windows (25 rows x 4 columns per page).
 * The open window of a player is state: ['kind' => 'toplist'|'list', 'key' => list key, 'page' => n].
 */
class Windows
{
    private const PER_COLUMN = 25;
    private const LOCAL_LIMIT = 5000;

    public function __construct(private Lists $lists)
    {
    }

    public function openToplist(Player $player, int $page = 1): void
    {
        $this->put($player, ['kind' => 'toplist', 'page' => $page]);
        $this->render($player);
    }

    /** Opens the full window of a list; nothing happens for an empty list. */
    public function openList(Player $player, TopList $list, int $page = 1): bool
    {
        if (!$this->lists->rows($list, $this->limitOf($list))) {
            return false;
        }
        $this->put($player, ['kind' => 'list', 'key' => $list->key(), 'page' => $page]);
        $this->render($player);

        return true;
    }

    public function close(Player $player): void
    {
        $this->forget($player->login);
        ManiaLink::displayLogin($player, WindowFrame::closeXml());
    }

    public function closeAll(): void
    {
        foreach (CurrentPlayers::all() as $player) {
            $this->forget($player->login);
        }
        ManiaLink::displayAll(WindowFrame::closeXml());
    }

    public function forget(string $login): void
    {
        state()->forget($this->key($login));
    }

    public function navigate(Player $player, string $to): void
    {
        $window = state()->get($this->key($player->login));
        if (!$window) {
            return;
        }
        $pages = $this->pageCount($window);
        $page = match ($to) {
            'first' => 1, 'prev5' => $window['page'] - 5, 'prev' => $window['page'] - 1,
            'next' => $window['page'] + 1, 'next5' => $window['page'] + 5, 'last' => $pages,
            default => $window['page'],
        };
        $window['page'] = max(1, min($pages, $page));
        $this->put($player, $window);
        $this->render($player);
    }

    public function render(Player $player): void
    {
        if ($xml = $this->xml($player)) {
            ManiaLink::displayLogin($player, $xml);
        }
    }

    public function xml(Player $player): ?string
    {
        $window = state()->get($this->key($player->login));

        return match ($window['kind'] ?? null) {
            'toplist' => $this->toplistXml($window['page']),
            'list'    => ($list = $this->lists->byKey($window['key'])) ? $this->listXml($list, $window['page']) : null,
            default   => null,
        };
    }

    private function limitOf(TopList $list): int
    {
        return $list instanceof LocalRecordsList ? self::LOCAL_LIMIT : 100;
    }

    private function pageCount(array $window): int
    {
        if ($window['kind'] === 'toplist') {
            return max(1, (int) ceil(count($this->lists->toplistWindow()) / 4));
        }
        $list = $this->lists->byKey($window['key']);

        return max(1, (int) ceil(count($list ? $this->lists->rows($list, $this->limitOf($list)) : []) / (4 * self::PER_COLUMN)));
    }

    // ---- toplist window -------------------------------------------------------------------------

    private function toplistXml(int $page): string
    {
        $all = $this->lists->toplistWindow();
        $pages = max(1, (int) ceil(count($all) / 4));
        $page = max(1, min($pages, $page));
        $e = Format::e(...);
        $online = CurrentPlayers::all();
        $style = (array) config('manialinks.style.widget_score');
        $default = config('manialinks.style.window.colors.default', 'FFFF');
        $scores = config('manialinks.style.widget_score.colors.scores', 'DDDF');

        $content = '<frame posn="2.5 -5.7 1"><format textsize="1" textcolor="'.$e($default).'"/>';
        foreach (array_slice($all, ($page - 1) * 4, 4) as $k => $list) {
            $c = (array) config("manialinks.scoretable_lists.{$list->key()}");
            $content .= '<frame posn="'.(19.05 * $k).' 0">';
            $content .= '<quad posn="0 0 0.02" sizen="17.75 46.88" style="'.$e($style['background_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['background_substyle'] ?? 'BgRacePlayerName').'"/>';
            $content .= '<quad posn="14.15 -43.33 0.03" sizen="4 4" '.$this->okImage().' action="'.ManiaLink::getAction('score_ok_'.$list->key()).'"/>';
            $content .= '<quad posn="0.4 -0.36 0.04" sizen="16.95 2" style="'.$e($style['title_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['title_substyle'] ?? 'ProgressBar').'"/>';
            $content .= '<quad posn="0.6 0 0.05" sizen="2.5 2.5" style="'.$e($c['icon_style'] ?? 'Icons128x128_1').'" substyle="'.$e($c['icon_substyle'] ?? 'Rankings').'"/>';
            $content .= '<label posn="3.2 -0.55 0.05" sizen="17.3 0" textsize="1" text="'.$e($list->title()).'"/>';
            $content .= '<frame posn="0 -2.7 0.04">';
            foreach ($this->lists->rows($list, self::PER_COLUMN) as $i => $row) {
                $content .= $this->row($row, $i, 1.75, $online, $list instanceof TopNations, 'toplist', $scores);
            }
            $content .= '</frame></frame>';
        }
        $content .= '</frame>';

        return WindowFrame::xml(
            'Top Rankings   |   Page '.$page.'/'.$pages,
            ['Icons128x128_1', 'Rankings'],
            $content,
            WindowFrame::pair($page, $pages),
            '67.05 -53.2',
        );
    }

    // ---- full list window -----------------------------------------------------------------------

    private function listXml(TopList $list, int $page): string
    {
        $all = $this->lists->rows($list, $this->limitOf($list));
        $perPage = 4 * self::PER_COLUMN;
        $pages = max(1, (int) ceil(count($all) / $perPage));
        $page = max(1, min($pages, $page));
        $c = (array) config("manialinks.scoretable_lists.{$list->key()}");
        $style = (array) config('manialinks.style.widget_score');
        $e = Format::e(...);
        $online = CurrentPlayers::all();
        $scores = config('manialinks.style.widget_score.colors.scores', 'DDDF');
        $nations = $list instanceof TopNations;

        $title = $list->title();
        if ($list instanceof LocalRecordsList) {
            $title .= '   |   Page '.$page.'/'.$pages.'   |   '.Format::number(count($all)).(count($all) === 1 ? ' Record' : ' Records');
        }

        $content = '<frame posn="2.5 -6.5 1"><format textsize="1" textcolor="'.$e(config('manialinks.style.window.colors.default', 'FFFF')).'"/>';
        foreach ([0, 19.05, 38.1, 57.15] as $x) {
            $content .= '<quad posn="'.$x.' 0.8 0.02" sizen="17.75 46.88" style="'.$e($style['background_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['background_substyle'] ?? 'BgRacePlayerName').'"/>';
        }
        foreach (array_slice($all, ($page - 1) * $perPage, $perPage) as $n => $row) {
            $content .= '<frame posn="'.(19.05 * intdiv($n, self::PER_COLUMN)).' 0">'
                .$this->row($row, $n % self::PER_COLUMN, 1.83, $online, $nations, 'window', $scores)
                .'</frame>';
        }
        $content .= '</frame>';

        $buttons = $pages > 1 ? WindowFrame::sixButtons($page, $pages) : '';

        return WindowFrame::xml($title, [$c['icon_style'] ?? 'Icons128x128_1', $c['icon_substyle'] ?? 'Rankings'], $content, $buttons);
    }

    // ---- shared row -----------------------------------------------------------------------------

    private function row(Row $row, int $line, float $height, $online, bool $nations, string $where, string $scores): string
    {
        $e = Format::e(...);
        $y = -$height * $line;
        $style = (array) config('manialinks.style.window');
        $xml = '';

        if ($nations) {
            $flagY = $line === 0 ? 0.3 : -($height * $line - 0.3);

            return '<label posn="2.75 '.$y.' 0.02" sizen="2.5 1.7" halign="right" scale="0.9" textcolor="'.$scores.'" text="'.$e($row->score).'"/>'
                .'<quad posn="3.5 '.$flagY.' 0.02" sizen="2 2" image="'.$e(ListWidget::flagImage((string) $row->flag)).'"/>'
                .'<label posn="6.2 '.$y.' 0.02" sizen="11.2 1.7" scale="0.9" text="'.$e($row->name).'"/>';
        }

        if ($row->login !== null && $online->has($row->login) && Format::flag('features.mark_online_player_records')) {
            $hy = $line === 0 ? 0.2 : -($height * $line - 0.2);
            $xml .= '<quad posn="0.4 '.$hy.' '.($where === 'toplist' ? 0.01 : 0.03).'" sizen="16.95 1.83" style="'.$e($style['online_style'] ?? 'BgsPlayerCard').'" substyle="'.$e($style['online_substyle'] ?? 'BgCardSystem').'"/>';
        }

        return $xml
            .'<label posn="2.6 '.$y.' 0.02" sizen="2 1.7" halign="right" scale="0.9" text="'.$row->rank.'."/>'
            .'<label posn="6.4 '.$y.' 0.02" sizen="4 1.7" halign="right" scale="0.9" textcolor="'.$scores.'" text="'.$e($row->score).'"/>'
            .'<label posn="6.9 '.$y.' 0.02" sizen="11.2 1.7" scale="0.9" text="'.$e(Format::name($row->name)).'"/>';
    }

    private function okImage(): string
    {
        $image = (string) config('scoreboard.images.widget_ok_normal');
        if ($image !== '') {
            $focus = (string) config('scoreboard.images.widget_ok_focus');

            return 'image="'.Format::e($image).'"'.($focus !== '' ? ' imagefocus="'.Format::e($focus).'"' : '');
        }

        return 'style="'.Format::e(config('scoreboard.fallback_styles.widget_ok.0')).'" substyle="'.Format::e(config('scoreboard.fallback_styles.widget_ok.1')).'"';
    }

    private function put(Player $player, array $window): void
    {
        state()->forever($this->key($player->login), $window);
        event(new \Funseco\Core\SharedKernel\Events\WindowOpened($player, 'scoreboard'));
    }

    /** Another plugin opened a window: this one goes (windows never stack). */
    public function otherWindowOpened(Player $player): void
    {
        if (state()->get($this->key($player->login)) !== null) {
            $this->close($player);
        }
    }

    private function key(string $login): string
    {
        return 'scoreboard:window:'.$login;
    }
}
