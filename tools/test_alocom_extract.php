<?php
require_once __DIR__ . '/../includes/alocom-sign-in.php';

$failures = 0;
function expect($label, $got, $want) {
    global $failures;
    if ($got !== $want) {
        fwrite(STDERR, "FAIL $label\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n");
        $failures++;
        return;
    }
    echo "ok $label\n";
}

$public = 'https://event.alocom.co/class/ghazal_institute/ghazal109';
$token = 'tok_abc_123456789';
$direct = $public . '?token=' . $token;

expect('reject public', alocom_url_is_direct_entry($public), false);
expect('accept token query', alocom_url_is_direct_entry($direct), true);
expect('accept sign_in path', alocom_url_is_direct_entry('https://event.alocom.co/sign_in?x=1'), true);

expect(
    'prefer eventLink over public alocom_link',
    alocom_extract_sign_in_url([
        'alocom_link' => $public,
        'eventLink' => $direct,
        'token' => $token,
    ]),
    $direct
);

expect(
    'nested data.eventLink',
    alocom_extract_sign_in_url([
        'data' => [
            'eventLink' => $direct,
            'token' => $token,
            'userId' => 5962367,
        ],
        'alocom_link' => $public,
    ]),
    $direct
);

expect(
    'append token onto public link',
    alocom_extract_sign_in_url([
        'alocom_link' => $public,
        'token' => $token,
    ]),
    $direct
);

expect(
    'append token onto eventLink without query',
    alocom_extract_sign_in_url([
        'eventLink' => $public,
        'token' => $token,
    ]),
    $direct
);

expect(
    'ignore public-only payload',
    alocom_extract_sign_in_url([
        'url' => $public,
        'alocom_link' => $public,
        'link' => $public,
    ]),
    ''
);

if ($failures > 0) {
    fwrite(STDERR, "$failures failed\n");
    exit(1);
}
echo "all passed\n";
