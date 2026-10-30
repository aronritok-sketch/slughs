<?php
/**
 * Slugshop – JUTA-kapcsolat, közös rész (raw_sync.php, elad.php). A Mandala szkriptjei alapján, WordPress nélkül,
 * közvetlenül az adatbázisba (gyors, a cron 30 percenként hívja). A téma figyelője (inc/juta.php) üríti a
 * gyorsítótárat és küldi az „Újra raktáron” leveleket.
 *
 * Futtatás: parancssorból (php raw_sync.php) vagy böngészőből / wgettel CSAK a config.php-ben megadott kulccsal
 * (…/juta/raw_sync.php?key=…). Kulcs nélkül böngészőből nem fut: minden megnyitás élesben írna.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
date_default_timezone_set('Europe/Budapest');

if (!is_file(__DIR__ . '/config.php')) {
    exit("Hiányzik a config.php (a config.sample.php alapján).\n");
}
$CFG = require __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    $key = (string) ($CFG['web_key'] ?? '');
    if (strlen($key) < 24 || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('Tiltva.');
    }
    header('Content-Type: text/html; charset=utf-8');
}

$DRY = !empty($CFG['dry']) || isset($_GET['dry']) || in_array('--dry', $argv ?? [], true);

function log_line(string $msg, string $level = 'INFO'): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$msg}";
    echo PHP_SAPI === 'cli' ? $line . "\n" : htmlspecialchars($line) . "<br>\n";
}

function db(): mysqli
{
    global $CFG;
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @mysqli_connect($CFG['db_host'], $CFG['db_user'], $CFG['db_pass'], $CFG['db_name']);
    if (!$db) {
        log_line('Adatbázis-kapcsolat: ' . mysqli_connect_error(), 'ERROR');
        exit(1);
    }
    mysqli_set_charset($db, 'utf8mb4');
    return $db;
}

/** Lekérdezés paraméterekkel → sorok. */
function q(mysqli $db, string $sql, array $params = []): array
{
    if (!$params) {
        $res = mysqli_query($db, $sql);
        if ($res === false) {
            throw new RuntimeException(mysqli_error($db) . ' | ' . $sql);
        }
        return $res === true ? [] : mysqli_fetch_all($res, MYSQLI_ASSOC);
    }
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($db) . ' | ' . $sql);
    }
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException($err);
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function table_exists(mysqli $db, string $table): bool
{
    return (bool) q($db, "SHOW TABLES LIKE '" . mysqli_real_escape_string($db, $table) . "'");
}

/** A WooCommerce a rendeléseket a HPOS-táblákban (wc_orders) tárolja-e (újabb telepítés), vagy a posts táblában. */
function hpos(mysqli $db, string $p): bool
{
    if (!table_exists($db, $p . 'wc_orders')) {
        return false;
    }
    $opt = q($db, "SELECT option_value FROM {$p}options WHERE option_name = 'woocommerce_custom_orders_table_enabled' LIMIT 1");
    return ($opt[0]['option_value'] ?? 'yes') === 'yes';
}

function to_utf8($v): string
{
    $v = (string) $v;
    if ($v === '' || (function_exists('mb_check_encoding') && mb_check_encoding($v, 'UTF-8'))) {
        return $v;
    }
    foreach (['CP852', 'ISO-8859-2', 'Windows-1250'] as $enc) {
        $c = @iconv($enc, 'UTF-8//IGNORE', $v);
        if ($c !== false && $c !== '') {
            return $c;
        }
    }
    return $v;
}

function parse_num($v): ?float
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    $v = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $v);
    $v = preg_replace('/[^0-9,\.\-\+]/', '', (string) $v);
    if ($v === '' || $v === '-' || $v === '+') {
        return null;
    }
    $comma = strpos($v, ',') !== false;
    $dot = strpos($v, '.') !== false;
    if ($comma && $dot) {
        $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(',', '.', str_replace('.', '', $v)) : str_replace(',', '', $v);
    } elseif ($comma) {
        $v = str_replace(',', '.', $v);
    } elseif ($dot && substr_count($v, '.') > 1) {
        $v = str_replace('.', '', $v);
    }
    return is_numeric($v) ? (float) $v : null;
}

/** Zárolás: egyszerre egy példány fusson. */
function lock_or_exit(string $file)
{
    $h = fopen($file, 'c');
    if (!$h || !flock($h, LOCK_EX | LOCK_NB)) {
        log_line('Egy másik példány már fut.', 'WARNING');
        exit(0);
    }
    return $h;
}
