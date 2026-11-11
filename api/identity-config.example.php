<?php
/**
 * Copy to identity-config.php next to api.php on the server.
 * Always use HTTPS (never http://) so the shared secret is not sent in cleartext.
 * Do not commit identity-config.php.
 */
define("GHAZAL_IDENTITY_URL", "https://edu.example.com/identity-ensure.php");
define("GHAZAL_IDENTITY_SECRET", "");
define("GHAZAL_ERROR_PING_URL", "https://edu.example.com/error-ping.php");
