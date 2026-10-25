<?php
/**
 * Copy to includes/rollcall-config.php on the server (not for public git).
 */
return [
    'mattermost_url' => 'https://co.ghazalify.com',
    'mattermost_bot_token' => '',
    'mattermost_channel_id' => '',
    'mattermost_webhook' => 'https://co.ghazalify.com/hooks/XXXX',
    'ai_training' => [
        'url' => 'https://co.ghazalify.com',
        'bot_token' => '',
        'channel_id' => '',
    ],
    'error_logs' => [
        'bot_token' => '',
        'channel_id' => '',
        'webhook' => '',
    ],
    'sms' => [
        'api_key' => '',
        'pattern_code' => 'aX3d0ttYHc',
        'late_pattern_code' => 'BY86nXCqQY',
        'welcome_pattern_code' => 'RMej2aBV8v',
        'study_plan_pattern_code' => 'zy6PWPVSVS',
        'sender' => '90008361',
        'url' => 'https://api.iranpayamak.com/ws/v1/sms/pattern',
    ],
    'alocom' => [
        'base' => 'https://pnlapi.alocom.co',
        'username' => '',
        'password' => '',
    ],
    'accounting' => [
        'host' => 'localhost',
        'database' => 'ghazali1_Acc',
        'user' => '',
        'password' => '',
    ],
    'identity' => [
        'secret' => '',
    ],
    'wordpress' => [
        'ensure_url' => 'https://ghazalify.com/wp-content/ghazal-identity-ensure.php',
    ],
];
