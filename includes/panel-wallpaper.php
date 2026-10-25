<?php
/**
 * Per-user dashboard wallpaper. Username is the exact panel login.
 */

function ghazal_panel_wallpaper($username) {
    static $map = [
        '5011' => [
            'class' => 'lg-moon-5011',
            'css' => '/assets/css/teacher/moon-5011.css?v=m1',
        ],
        '4752' => [
            'class' => 'lg-wall-akhavan',
            'css' => '/assets/css/panel-wallpapers.css?v=w3',
        ],
        'm1377' => [
            'class' => 'lg-wall-akhavan',
            'css' => '/assets/css/panel-wallpapers.css?v=w3',
        ],
        '7682' => [
            'class' => 'lg-wall-matin',
            'css' => '/assets/css/panel-wallpapers.css?v=w3',
        ],
        '8282' => [
            'class' => 'lg-wall-matin',
            'css' => '/assets/css/panel-wallpapers.css?v=w3',
        ],
        'dragonkiller' => [
            'class' => 'lg-wall-matin',
            'css' => '/assets/css/panel-wallpapers.css?v=w3',
        ],
    ];
    $key = strtolower(trim((string)$username));
    return $map[$key] ?? null;
}
