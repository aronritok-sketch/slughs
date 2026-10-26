<?php
/**
 * Slugshop – adatátadó a régi (Joomla + VirtueMart) oldalról az új WordPressnek.
 *
 * A Joomla gyökerébe kell feltölteni (ahol a configuration.php van). Csak olvas, csak a tokennel válaszol, JSON-t ad.
 * Az átvétel után TÖRÖLD: személyes adatokat (vásárlók, rendelések) ad ki.
 *
 *   ?t=<token>&a=info                              verziók, nyelv, darabszámok, a kódtáblák
 *   ?t=<token>&a=categories
 *   ?t=<token>&a=products|customers|orders&after=<azonosító>&limit=<db>
 */

const SLUG_TOKEN = '__TOKEN__';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

if (strlen(SLUG_TOKEN) < 32 || !hash_equals(SLUG_TOKEN, (string) ($_GET['t'] ?? ''))) {
    out(['error' => 'token'], 403);
}

if (!is_file(__DIR__ . '/configuration.php')) {
    out(['error' => 'A configuration.php nincs ebben a mappában: a fájlt a Joomla gyökerébe kell tenni.'], 500);
}
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
require_once __DIR__ . '/configuration.php';
$cfg = new JConfig();
mysqli_report(MYSQLI_REPORT_OFF);
$host = (string) $cfg->host;
$port = null;
$socket = null;
if (preg_match('/^(.*):(\d+)$/', $host, $m)) {
    [$host, $port] = [$m[1], (int) $m[2]];
} elseif (strpos($host, ':/') !== false) {
    [$host, $socket] = explode(':', $host, 2);
}
$db = @new mysqli($host, (string) $cfg->user, (string) $cfg->password, (string) $cfg->db, $port, $socket);
if ($db->connect_errno) {
    out(['error' => 'Adatbázis: ' . $db->connect_error], 500);
}
$db->set_charset('utf8mb4');
$P = (string) $cfg->dbprefix;

function rows(string $sql): array
{
    global $db;
    $res = $db->query($sql);
    if (!$res) {
        out(['error' => 'SQL: ' . $db->error, 'sql' => $sql], 500);
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

function table_exists(string $t): bool
{
    global $db;
    $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
    return $res && $res->num_rows > 0;
}

/** Csoportosítás egy kulcs szerint (egy lekérdezés N sorra, nem N lekérdezés). */
function group(array $rows, string $key): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r[$key]][] = $r;
    }
    return $out;
}

function ids(array $rows, string $key): string
{
    $ids = array_values(array_unique(array_map('intval', array_column($rows, $key))));
    return $ids ? implode(',', $ids) : '0';
}

/** A VirtueMart nyelvi táblái: hu_hu, ha van; különben az első. */
function vm_lang(): string
{
    global $P, $db;
    $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($P) . "virtuemart\\_products\\_%'");
    $langs = [];
    while ($res && ($r = $res->fetch_row())) {
        if (preg_match('/virtuemart_products_([a-z]{2}_[a-z]{2})$/', $r[0], $m)) {
            $langs[] = $m[1];
        }
    }
    return in_array('hu_hu', $langs, true) ? 'hu_hu' : ($langs[0] ?? 'hu_hu');
}

$L = vm_lang();
$a = (string) ($_GET['a'] ?? 'info');
$after = max(0, (int) ($_GET['after'] ?? 0));
$limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));

/** Név a nyelvi táblából (fizetési és szállítási módok). */
function names(string $base, string $id, string $col): array
{
    global $P, $L;
    $t = $P . $base . '_' . $L;
    if (!table_exists($t)) {
        return [];
    }
    $out = [];
    foreach (rows("SELECT `$id` AS id, `$col` AS name FROM `$t`") as $r) {
        $out[$r['id']] = $r['name'];
    }
    return $out;
}

switch ($a) {
    case 'info':
        $count = fn($t) => table_exists($P . $t) ? (int) rows("SELECT COUNT(*) AS n FROM `{$P}$t`")[0]['n'] : null;
        $vm = table_exists($P . 'extensions') ? rows("SELECT manifest_cache FROM `{$P}extensions` WHERE element = 'com_virtuemart' LIMIT 1") : [];
        $countries = [];
        foreach (table_exists($P . 'virtuemart_countries') ? rows("SELECT virtuemart_country_id AS id, country_2_code AS code FROM `{$P}virtuemart_countries`") : [] as $r) {
            $countries[$r['id']] = $r['code'];
        }
        $states = [];
        foreach (table_exists($P . 'virtuemart_orderstates') ? rows("SELECT order_status_code AS code, order_status_name AS name FROM `{$P}virtuemart_orderstates`") : [] as $r) {
            $states[$r['code']] = $r['name'];
        }
        $currencies = [];
        foreach (table_exists($P . 'virtuemart_currencies') ? rows("SELECT virtuemart_currency_id AS id, currency_code_3 AS code FROM `{$P}virtuemart_currencies`") : [] as $r) {
            $currencies[$r['id']] = $r['code'];
        }
        $calcs = table_exists($P . 'virtuemart_calcs') ? rows("SELECT virtuemart_calc_id AS id, calc_name AS name, calc_kind AS kind, calc_value_mathop AS op, calc_value AS value, published FROM `{$P}virtuemart_calcs`") : [];
        out([
            'ok' => true,
            'joomla' => class_exists('JVersion') ? (new JVersion())->getShortVersion() : null,
            'virtuemart' => $vm ? (json_decode((string) $vm[0]['manifest_cache'], true)['version'] ?? null) : null,
            'php' => PHP_VERSION, 'lang' => $L, 'sitename' => (string) ($cfg->sitename ?? ''),
            'counts' => [
                'products' => $count('virtuemart_products'), 'categories' => $count('virtuemart_categories'),
                'customers' => $count('users'), 'orders' => $count('virtuemart_orders'),
            ],
            'countries' => $countries, 'orderstates' => $states, 'calcs' => $calcs, 'currencies' => $currencies,
            'payments' => names('virtuemart_paymentmethods', 'virtuemart_paymentmethod_id', 'payment_name'),
            'shipments' => names('virtuemart_shipmentmethods', 'virtuemart_shipmentmethod_id', 'shipment_name'),
        ]);

    case 'categories':
        $cats = rows("SELECT l.*, c.*, cc.category_parent_id
            FROM `{$P}virtuemart_categories` c
            LEFT JOIN `{$P}virtuemart_categories_$L` l ON l.virtuemart_category_id = c.virtuemart_category_id
            LEFT JOIN `{$P}virtuemart_category_categories` cc ON cc.category_child_id = c.virtuemart_category_id
            ORDER BY c.virtuemart_category_id");
        out(['items' => $cats]);

    case 'products':
        $items = rows("SELECT l.*, p.*
            FROM `{$P}virtuemart_products` p
            LEFT JOIN `{$P}virtuemart_products_$L` l ON l.virtuemart_product_id = p.virtuemart_product_id
            WHERE p.virtuemart_product_id > $after ORDER BY p.virtuemart_product_id LIMIT $limit");
        $in = ids($items, 'virtuemart_product_id');
        $prices = group(rows("SELECT * FROM `{$P}virtuemart_product_prices` WHERE virtuemart_product_id IN ($in) ORDER BY virtuemart_shoppergroup_id, price_quantity_start"), 'virtuemart_product_id');
        $cats = group(rows("SELECT pc.virtuemart_product_id, pc.virtuemart_category_id, l.slug, l.category_name
            FROM `{$P}virtuemart_product_categories` pc
            LEFT JOIN `{$P}virtuemart_categories_$L` l ON l.virtuemart_category_id = pc.virtuemart_category_id
            WHERE pc.virtuemart_product_id IN ($in) ORDER BY pc.ordering"), 'virtuemart_product_id');
        $media = group(rows("SELECT pm.virtuemart_product_id, m.file_url, m.file_title, m.file_mimetype
            FROM `{$P}virtuemart_product_medias` pm JOIN `{$P}virtuemart_medias` m ON m.virtuemart_media_id = pm.virtuemart_media_id
            WHERE pm.virtuemart_product_id IN ($in) ORDER BY pm.ordering"), 'virtuemart_product_id');
        foreach ($items as &$it) {
            $id = $it['virtuemart_product_id'];
            $it['prices'] = $prices[$id] ?? [];
            $it['categories'] = $cats[$id] ?? [];
            $it['media'] = $media[$id] ?? [];
        }
        unset($it);
        out(['items' => $items, 'next' => $items ? (int) end($items)['virtuemart_product_id'] : null]);

    case 'customers':
        $items = rows("SELECT u.id, u.name, u.username, u.email, u.block, u.registerDate, u.lastvisitDate
            FROM `{$P}users` u WHERE u.id > $after ORDER BY u.id LIMIT $limit");
        $in = ids($items, 'id');
        $info = table_exists($P . 'virtuemart_userinfos') ? group(rows("SELECT * FROM `{$P}virtuemart_userinfos` WHERE virtuemart_user_id IN ($in) ORDER BY virtuemart_userinfo_id"), 'virtuemart_user_id') : [];
        $groups = [];
        if (table_exists($P . 'user_usergroup_map') && table_exists($P . 'usergroups')) {
            $groups = group(rows("SELECT m.user_id, g.title FROM `{$P}user_usergroup_map` m JOIN `{$P}usergroups` g ON g.id = m.group_id WHERE m.user_id IN ($in)"), 'user_id');
        }
        foreach ($items as &$it) {
            $it['addresses'] = $info[$it['id']] ?? [];
            $it['groups'] = array_column($groups[$it['id']] ?? [], 'title');
        }
        unset($it);
        out(['items' => $items, 'next' => $items ? (int) end($items)['id'] : null]);

    case 'orders':
        $items = rows("SELECT * FROM `{$P}virtuemart_orders` WHERE virtuemart_order_id > $after ORDER BY virtuemart_order_id LIMIT $limit");
        $in = ids($items, 'virtuemart_order_id');
        $lines = group(rows("SELECT * FROM `{$P}virtuemart_order_items` WHERE virtuemart_order_id IN ($in) ORDER BY virtuemart_order_item_id"), 'virtuemart_order_id');
        $addr = group(rows("SELECT * FROM `{$P}virtuemart_order_userinfos` WHERE virtuemart_order_id IN ($in)"), 'virtuemart_order_id');
        $hist = table_exists($P . 'virtuemart_order_histories')
            ? group(rows("SELECT * FROM `{$P}virtuemart_order_histories` WHERE virtuemart_order_id IN ($in) ORDER BY virtuemart_order_history_id"), 'virtuemart_order_id') : [];
        foreach ($items as &$it) {
            $id = $it['virtuemart_order_id'];
            unset($it['order_pass']);
            $it['lines'] = $lines[$id] ?? [];
            $it['addresses'] = $addr[$id] ?? [];
            $it['history'] = $hist[$id] ?? [];
        }
        unset($it);
        out(['items' => $items, 'next' => $items ? (int) end($items)['virtuemart_order_id'] : null]);

    default:
        out(['error' => 'Ismeretlen kérés.'], 400);
}
