<?php
/**
 * Pick AloCom's direct-entry URL (eventLink + token). Never the public class path.
 */

function alocom_url_is_direct_entry($url) {
    $url = trim((string)$url);
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    $parts = parse_url($url);
    $path = strtolower((string)($parts['path'] ?? ''));
    $query = (string)($parts['query'] ?? '');
    if (strpos($path, 'sign_in') !== false || strpos($path, 'signin') !== false) {
        return true;
    }
    if ($query === '') {
        return false;
    }
    $params = [];
    parse_str($query, $params);
    foreach ($params as $k => $v) {
        $lk = strtolower((string)$k);
        if (!in_array($lk, ['token', 'ticket', 'auth', 'jwt', 'access_token', 'accesstoken'], true)) {
            continue;
        }
        if (is_string($v)) {
            return $v !== '';
        }
        return $v !== null && $v !== '';
    }
    return false;
}

function alocom_append_event_token($url, $token) {
    $url = trim((string)$url);
    $token = trim((string)$token);
    if ($url === '' || $token === '' || preg_match('#^https?://#i', $token)) {
        return $url;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (preg_match('/(?:^|[?&])(?:token|ticket|auth|jwt|access_token)=/i', $url)) {
        return $url;
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'token=' . rawurlencode($token);
}

function alocom_node_token($node) {
    if (!is_array($node)) {
        return '';
    }
    foreach (['token', 'Token', 'signInToken', 'sign_in_token', 'eventToken', 'event_token'] as $k) {
        $v = $node[$k] ?? null;
        if (is_string($v) && $v !== '' && !preg_match('#^https?://#i', $v) && strlen($v) >= 8) {
            return $v;
        }
    }
    return '';
}

function alocom_node_event_link($node) {
    if (!is_array($node)) {
        return '';
    }
    foreach ([
        'eventLink', 'event_link', 'EventLink',
        'signInUrl', 'sign_in_url', 'signin_url', 'login_url',
    ] as $k) {
        $v = $node[$k] ?? null;
        if (is_string($v) && preg_match('#^https?://#i', $v)) {
            return $v;
        }
    }
    return '';
}

function alocom_extract_sign_in_url($res) {
    $found = '';
    alocom_walk_sign_in($res, $found);
    return $found;
}

function alocom_walk_sign_in($node, &$found) {
    if ($found !== '') {
        return;
    }
    if (!is_array($node)) {
        return;
    }

    $link = alocom_node_event_link($node);
    $token = alocom_node_token($node);
    if ($link !== '') {
        $url = alocom_append_event_token($link, $token);
        if (alocom_url_is_direct_entry($url) || parse_url($url, PHP_URL_QUERY)) {
            $found = $url;
            return;
        }
    }

    if ($token !== '') {
        foreach (['alocom_link', 'link', 'url', 'eventUrl', 'event_url', 'redirect'] as $k) {
            $v = $node[$k] ?? '';
            if (!is_string($v) || !preg_match('#^https?://#i', $v)) {
                continue;
            }
            $base = preg_replace('/[?#].*$/', '', $v);
            $url = alocom_append_event_token($base, $token);
            if (alocom_url_is_direct_entry($url)) {
                $found = $url;
                return;
            }
        }
    }

    if (!empty($node['data']) && is_array($node['data'])) {
        alocom_walk_sign_in($node['data'], $found);
        if ($found !== '') {
            return;
        }
    }

    foreach ($node as $v) {
        if (is_array($v)) {
            alocom_walk_sign_in($v, $found);
            if ($found !== '') {
                return;
            }
        }
    }
}
