<?php
/**
 * CLI scoring checks for NEO-60 and Big Five. No database.
 */
require_once dirname(__DIR__) . '/includes/psych-tests.php';

function pt_assert($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "ok  $msg\n";
}

function fill($n, $v) {
    $a = [];
    for ($i = 1; $i <= $n; $i++) {
        $a[$i] = $v;
    }
    return $a;
}

$neo_items = psych_tests_neo60_items();
$bf_items = psych_tests_bigfive_items();
pt_assert(count($neo_items) === 60, 'NEO has 60 items');
pt_assert(count($bf_items) === 65, 'Big Five has 65 items');

$neo = psych_tests_def('neo60');
foreach ($neo['factors'] as $code => $items) {
    pt_assert(count($items) === 12, "NEO $code has 12 items");
}
$bf = psych_tests_def('bigfive');
foreach ($bf['factors'] as $code => $items) {
    pt_assert(count($items) === 13, "Big Five $code has 13 items");
}

pt_assert(psych_tests_item_score('neo60', 2, 4) === 4, 'NEO non-reverse 4 stays 4');
pt_assert(psych_tests_item_score('neo60', 1, 1) === 5, 'NEO reverse 1 becomes 5');
pt_assert(psych_tests_item_score('neo60', 1, 5) === 1, 'NEO reverse 5 becomes 1');
pt_assert(psych_tests_item_score('bigfive', 40, 3) === 3, 'Big Five never reverses');

$all3 = psych_tests_score('neo60', fill(60, 3));
foreach (['N', 'E', 'O', 'A', 'C'] as $code) {
    pt_assert($all3['factors'][$code]['mean'] === 3.0, "NEO all-3 $code mean is 3");
}

$all1 = psych_tests_score('neo60', fill(60, 1));
pt_assert($all1['factors']['N']['mean'] === 2.33, 'NEO all-1 N mean 2.33 (4 reverse)');
pt_assert($all1['factors']['E']['mean'] === 2.33, 'NEO all-1 E mean 2.33');
pt_assert($all1['factors']['O']['mean'] === 3.33, 'NEO all-1 O mean 3.33 (7 reverse)');
pt_assert($all1['factors']['A']['mean'] === 3.67, 'NEO all-1 A mean 3.67 (8 reverse)');
pt_assert($all1['factors']['C']['mean'] === 2.33, 'NEO all-1 C mean 2.33');

$custom = fill(60, 2);
$custom[1] = 1; // reverse N → 5
$one = psych_tests_score('neo60', $custom);
pt_assert($one['item_scores'][1] === 5, 'custom reverse item 1');
pt_assert($one['item_scores'][2] === 2, 'custom non-reverse item 2');

$bf4 = psych_tests_score('bigfive', fill(65, 4));
foreach (['E', 'A', 'C', 'N', 'O'] as $code) {
    pt_assert($bf4['factors'][$code]['mean'] === 4.0, "Big Five all-4 $code mean is 4");
    pt_assert($bf4['factors'][$code]['sum'] === 52, "Big Five all-4 $code sum is 52");
}

$bf_mix = fill(65, 1);
for ($i = 1; $i <= 13; $i++) {
    $bf_mix[$i] = 5;
}
$mix = psych_tests_score('bigfive', $bf_mix);
pt_assert($mix['factors']['E']['mean'] === 5.0, 'Big Five E block 5');
pt_assert($mix['factors']['A']['mean'] === 1.0, 'Big Five A block 1');

echo "all scoring checks passed\n";
