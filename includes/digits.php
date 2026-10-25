<?php
/**
 * Convert Persian/Arabic digits to ASCII so login and codes match the database.
 */
function ghazal_normalize_digits($value) {
    $value = (string) $value;
    $from = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $to   = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($from, $to, $value);
}
