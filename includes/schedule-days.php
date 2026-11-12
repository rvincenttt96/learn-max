<?php
/**
 * Free weekday schedule for classes (exactly 2 days / week).
 * Stored in classes.schedule_type as comma-separated codes: sat,tue
 * Legacy values even/odd still resolve for older rows.
 */

if (function_exists('schedule_days_normalize')) {
    return;
}

function schedule_day_defs(): array
{
    // Ordered for Iranian week start (شنبه).
    return [
        'sat' => ['w' => 6, 'fa' => 'شنبه', 'en' => 'Sat'],
        'sun' => ['w' => 0, 'fa' => 'یکشنبه', 'en' => 'Sun'],
        'mon' => ['w' => 1, 'fa' => 'دوشنبه', 'en' => 'Mon'],
        'tue' => ['w' => 2, 'fa' => 'سه‌شنبه', 'en' => 'Tue'],
        'wed' => ['w' => 3, 'fa' => 'چهارشنبه', 'en' => 'Wed'],
        'thu' => ['w' => 4, 'fa' => 'پنجشنبه', 'en' => 'Thu'],
    ];
}

function schedule_days_order(): array
{
    return array_keys(schedule_day_defs());
}

function schedule_days_code_for_w(int $w): ?string
{
    foreach (schedule_day_defs() as $code => $def) {
        if ((int) $def['w'] === $w) {
            return $code;
        }
    }
    return null;
}

function schedule_days_today_code($now = null): ?string
{
    date_default_timezone_set('Asia/Tehran');
    $ts = $now === null ? time() : (int) $now;
    $w = (int) date('w', $ts);
    if ($w === 5) {
        return null; // Friday
    }
    return schedule_days_code_for_w($w);
}

function schedule_days_legacy_expand(string $raw): array
{
    $t = strtolower(trim($raw));
    if ($t === 'even' || strpos($t, 'زوج') !== false) {
        return ['sat', 'mon', 'wed'];
    }
    if ($t === 'odd' || strpos($t, 'فرد') !== false) {
        return ['sun', 'tue', 'thu'];
    }
    return [];
}

function schedule_days_normalize($raw): array
{
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $legacy = schedule_days_legacy_expand($raw);
        if ($legacy) {
            return $legacy;
        }
        $parts = preg_split('/[\s,|\/]+/u', $raw) ?: [];
    }

    $alias = [
        'saturday' => 'sat', 'sat' => 'sat', 'شنبه' => 'sat', '6' => 'sat',
        'sunday' => 'sun', 'sun' => 'sun', 'یکشنبه' => 'sun', '0' => 'sun',
        'monday' => 'mon', 'mon' => 'mon', 'دوشنبه' => 'mon', '1' => 'mon',
        'tuesday' => 'tue', 'tue' => 'tue', 'سه‌شنبه' => 'tue', 'سه شنبه' => 'tue', '2' => 'tue',
        'wednesday' => 'wed', 'wed' => 'wed', 'چهارشنبه' => 'wed', '3' => 'wed',
        'thursday' => 'thu', 'thu' => 'thu', 'پنجشنبه' => 'thu', 'پنج‌شنبه' => 'thu', '4' => 'thu',
        'even' => '__even__', 'odd' => '__odd__',
    ];

    $defs = schedule_day_defs();
    $out = [];
    foreach ($parts as $part) {
        $key = strtolower(trim((string) $part));
        $key = str_replace(['ي', 'ك'], ['ی', 'ک'], $key);
        if ($key === '' || $key === 'fri' || $key === 'friday' || $key === 'جمعه' || $key === '5') {
            continue;
        }
        if ($key === '__even__' || $key === 'even' || $key === 'زوج') {
            foreach (['sat', 'mon', 'wed'] as $c) {
                $out[$c] = true;
            }
            continue;
        }
        if ($key === '__odd__' || $key === 'odd' || $key === 'فرد') {
            foreach (['sun', 'tue', 'thu'] as $c) {
                $out[$c] = true;
            }
            continue;
        }
        $code = $alias[$key] ?? null;
        if ($code === '__even__' || $code === '__odd__') {
            continue;
        }
        if ($code && isset($defs[$code])) {
            $out[$code] = true;
        }
    }

    $ordered = [];
    foreach (schedule_days_order() as $code) {
        if (!empty($out[$code])) {
            $ordered[] = $code;
        }
    }
    return $ordered;
}

function schedule_days_encode(array $days): string
{
    $days = schedule_days_normalize($days);
    return implode(',', $days);
}

function schedule_days_from_input($input): array
{
    if (is_array($input)) {
        if (isset($input['schedule_days']) && is_array($input['schedule_days'])) {
            return schedule_days_normalize($input['schedule_days']);
        }
        if (isset($input['schedule_type'])) {
            return schedule_days_normalize($input['schedule_type']);
        }
        return schedule_days_normalize($input);
    }
    return schedule_days_normalize($input);
}

function schedule_days_require_two($days): string
{
    $days = schedule_days_from_input($days);
    if (count($days) !== 2) {
        throw new Exception('دقیقاً ۲ روز از هفته را برای کلاس انتخاب کنید.');
    }
    return schedule_days_encode($days);
}

function schedule_days_label($schedule_type, string $lang = 'fa'): string
{
    $days = schedule_days_normalize($schedule_type);
    if (!$days) {
        return $lang === 'en' ? 'Not set' : 'نامشخص';
    }
    $defs = schedule_day_defs();
    $parts = [];
    foreach ($days as $code) {
        $parts[] = $lang === 'en' ? ($defs[$code]['en'] ?? $code) : ($defs[$code]['fa'] ?? $code);
    }
    return implode(' / ', $parts);
}

function schedule_days_badge_class($schedule_type): string
{
    $days = schedule_days_normalize($schedule_type);
    if ($days === ['sat', 'mon', 'wed'] || (string) $schedule_type === 'even') {
        return 'day-even';
    }
    if ($days === ['sun', 'tue', 'thu'] || (string) $schedule_type === 'odd') {
        return 'day-odd';
    }
    return 'day-custom';
}

function schedule_days_matches($schedule_type, $now = null): bool
{
    $today = schedule_days_today_code($now);
    if ($today === null) {
        return false;
    }
    $days = schedule_days_normalize($schedule_type);
    return in_array($today, $days, true);
}

function schedule_days_matches_class(array $class, $now = null): bool
{
    $type = (string) ($class['schedule_type'] ?? '');
    $classType = strtolower(trim((string) ($class['class_type'] ?? '')));
    // Private without days: keep previous behavior (even-slot weekdays).
    if (($type === '' || $type === 'private' || strpos($type, 'خصوصی') !== false) && $classType === 'private') {
        $today = schedule_days_today_code($now);
        return in_array($today, ['sat', 'mon', 'wed'], true);
    }
    return schedule_days_matches($type, $now);
}

/**
 * SQL fragment: rows that meet today (legacy even/odd OR FIND_IN_SET day code).
 * $column must be a trusted identifier like c.schedule_type.
 */
function schedule_days_sql_column(string $column = 'c.schedule_type'): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
        return 'schedule_type';
    }
    return $column;
}

function schedule_days_sql_today(string $column = 'c.schedule_type'): string
{
    $code = schedule_days_today_code();
    if ($code === null) {
        return '0=1';
    }
    $w = (int) (schedule_day_defs()[$code]['w'] ?? -1);
    $legacy = in_array($w, [6, 1, 3], true) ? 'even' : 'odd';
    $col = schedule_days_sql_column($column);
    $codeSql = "'" . str_replace("'", "''", $code) . "'";
    $legacySql = "'" . $legacy . "'";
    return "($col = $legacySql OR FIND_IN_SET($codeSql, REPLACE($col, ' ', '')) > 0)";
}

/** Match legacy even/odd buckets or any free-picked day inside that set. */
function schedule_days_sql_bucket(string $bucket, string $column = 'c.schedule_type'): string
{
    $bucket = strtolower(trim($bucket));
    $codes = ($bucket === 'even')
        ? ['sat', 'mon', 'wed']
        : (($bucket === 'odd') ? ['sun', 'tue', 'thu'] : []);
    if (!$codes) {
        return '0=1';
    }
    $col = schedule_days_sql_column($column);
    $legacySql = "'" . str_replace("'", "''", $bucket) . "'";
    $parts = ["$col = $legacySql"];
    foreach ($codes as $code) {
        $codeSql = "'" . str_replace("'", "''", $code) . "'";
        $parts[] = "FIND_IN_SET($codeSql, REPLACE($col, ' ', '')) > 0";
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function schedule_days_picker_markup(string $fieldName = 'schedule_days[]', $selected = null, array $opts = []): string
{
    $selected = schedule_days_normalize($selected);
    $idPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($opts['id_prefix'] ?? 'schday'));
    $exact = (int) ($opts['exact'] ?? 2);
    $html = '<div class="schedule-days-picker" data-exact="' . $exact . '">';
    $html .= '<div class="schedule-days-grid">';
    foreach (schedule_day_defs() as $code => $def) {
        $id = $idPrefix . '-' . $code;
        $checked = in_array($code, $selected, true) ? ' checked' : '';
        $html .= '<label class="schedule-day-chip" for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<input type="checkbox" class="js-day" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' . $checked . '>';
        $html .= '<span>' . htmlspecialchars($def['fa'], ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</label>';
    }
    $html .= '</div>';
    $html .= '<div class="schedule-days-hint">دقیقاً ۲ روز را انتخاب کنید.</div>';
    $html .= '</div>';
    return $html;
}

function schedule_days_picker_assets(): string
{
    return <<<'CSS'
<style>
.schedule-days-picker { display:grid; gap:8px; }
.schedule-days-grid {
  display:grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap:8px;
}
.schedule-day-chip {
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  margin:0;
  padding:10px 8px;
  border:1px solid rgba(25,56,184,.35);
  border-radius:12px;
  background:#0B1220;
  color:#E8FBFA;
  cursor:pointer;
  user-select:none;
  font-size:13px;
  font-weight:600;
}
.schedule-day-chip input { accent-color:#51B5B2; }
.schedule-day-chip:has(input:checked) {
  border-color:#51B5B2;
  background:rgba(25,56,184,.28);
  box-shadow:0 0 0 1px rgba(81,181,178,.35) inset;
}
.schedule-days-hint { font-size:12px; color:#94a3b8; }
.day-custom { background:rgba(25,56,184,.25); color:#B8EDEC; }
@media (max-width: 576px) {
  .schedule-days-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
<script>
(function () {
  function enforce(picker) {
    var exact = parseInt(picker.getAttribute('data-exact') || '2', 10) || 2;
    var boxes = Array.prototype.slice.call(picker.querySelectorAll('input[type="checkbox"]'));
    boxes.forEach(function (box) {
      box.addEventListener('change', function () {
        var checked = boxes.filter(function (b) { return b.checked; });
        if (checked.length > exact) {
          box.checked = false;
        }
      });
    });
  }
  function boot() {
    document.querySelectorAll('.schedule-days-picker').forEach(enforce);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.ghazalScheduleDaysBoot = boot;
})();
</script>
CSS;
}
