<?php
/**
 * Panel, accounting-adjacent HTML, and dashboards must not appear in Google.
 */
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow', false);
}
