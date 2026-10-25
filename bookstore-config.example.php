<?php
/**
 * Copy to bookstore-config.php next to Acc api.php on the server (not for public git).
 * Token must match books.ghazalify.com config/panel-hook.php.
 */
return [
    'url' => 'https://books.ghazalify.com/api/acc-orders.php',
    'receipt_url' => 'https://books.ghazalify.com/api/acc-receipt.php',
    'token' => '',
];
