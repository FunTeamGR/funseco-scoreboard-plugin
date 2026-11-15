<?php

/*
 * Scoreboard plugin (records_eyepiece score lists, toplist widget and windows, /estat).
 *
 * List titles, positions, entries and icons: config/manialinks.php `scoretable_lists`.
 * Toplist widget: `toplist_widget`. Styles: `style.widget_score`, `style.window`.
 * Number format, shorten numbers, illuminate names, karma min votes / method: `features`.
 */
return [
    // Local records per map that count for "Most Records" (XAseco's capped records table).
    'max_records' => 50,

    // Top Active Players: "-3 d" as XAseco showed it (default) or "3 d" (false).
    'active_days_negative' => true,

    // Text in the window footer (opens the help window). XAseco: RECORDS-EYEPIECE/1.0.9.9
    'window_footer' => 'FUNSECO SCOREBOARD',

    // Image URLs (the eyepiece image host is dead). Empty = built-in ManiaLink styles below.
    'images' => [
        'widget_ok_normal'  => '',
        'widget_ok_focus'   => '',
    ],
    'fallback_styles' => [
        'widget_ok'         => ['Icons64x64_1', 'ArrowNext'],
    ],

    'messages' => [
        'disallow_windows_at_score' => '{#server}>> Could not display this window at score! Please try again when the race is started.',
        // /estat without (valid) parameter when no help window (/eyepiece) is installed.
        'estat_usage' => '{#server}> {#message}Usage: {#highlite}/estat <list>{#message}, list can be: {#highlite}{1}',
    ],
];
