<?php
/**
 * Copy WordPress must-use plugins onto the live ghazalify.com tree.
 * Run on the DirectAdmin host (same user as both sites):
 *   php /path/to/panel/tools/sync-wp-mu-plugins.php
 */
$src = dirname(__DIR__) . '/wordpress';
$dest = '/home/ghazali1/domains/ghazalify.com/public_html/wp-content/mu-plugins';
$ensure_dest = '/home/ghazali1/domains/ghazalify.com/public_html/wp-content';

if (!is_dir($dest)) {
    fwrite(STDERR, "mu-plugins not found: $dest\n");
    exit(1);
}

$mu = [
    'ghazal-seo.php',
    'ghazal-speed.php',
    'ghazal-cache.php',
    'ghazal-identity.php',
];
foreach ($mu as $file) {
    $from = $src . '/' . $file;
    if (!is_file($from)) {
        fwrite(STDERR, "missing $from\n");
        continue;
    }
    $to = $dest . '/' . $file;
    if (!copy($from, $to)) {
        fwrite(STDERR, "failed $file\n");
        exit(1);
    }
    echo "copied $file -> $to\n";
}

$ensure = [
    'ghazal-identity-ensure.php',
    'ghazal-seo-ensure.php',
];
foreach ($ensure as $file) {
    $from = $src . '/' . $file;
    if (!is_file($from)) {
        continue;
    }
    $to = $ensure_dest . '/' . $file;
    if (!copy($from, $to)) {
        fwrite(STDERR, "failed $file\n");
        exit(1);
    }
    echo "copied $file -> $to\n";
}

echo "ok\n";
