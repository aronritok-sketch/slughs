<?php
/**
 * JUTA → webshop: a keszlet.csv (a JUTA tölti fel, „;” elválasztó, CP852 vagy UTF-8) alapján vonalkód (= WooCommerce
 * cikkszám) szerint frissíti az árat, a készletet és – ha be van kapcsolva – az akciós árat.
 *
 * A Mandala raw_sync.php-ja alapján, a Slugshophoz: nincs nagyker ár és WPML; a nyitott rendelések mennyiségét levonja
 * (túleladás ellen); az ismeretlen vonalkódokból alapból nem lesz piszkozat, hanem párosító tábla (a web gyökerén kívül, config: report):
 * egyezik / csak a JUTA-ban / csak a webshopban / többszörös. A nevekhez, leírásokhoz, képekhez, kategóriákhoz nem nyúl.
 */

require __DIR__ . '/common.php';

$lock = lock_or_exit(__DIR__ . '/raw_sync.lock');
$p = (string) $CFG['prefix'];
$col = $CFG['columns'];
$saleCol = (string) ($CFG['sale_column'] ?? '');

log_line('JUTA → webshop szinkron indul' . ($DRY ? ' (PRÓBA, nem ír)' : ''));

if (!is_file($CFG['csv'])) {
    log_line('Nincs meg a CSV: ' . $CFG['csv'], 'ERROR');
    exit(1);
}
$db = db();

// ------------------------------------------------------------------ a CSV beolvasása

$h = fopen($CFG['csv'], 'r');
$header = null;
$rows = [];
while (($line = fgets($h)) !== false) {
    $line = rtrim(preg_replace('/^\xEF\xBB\xBF/', '', $line), "\r\n");
    if ($line === '') {
        continue;
    }
    $cells = str_getcsv($line, ';', "\0");
    if ($header === null) {
        $header = array_map(fn($x) => mb_strtolower(trim(to_utf8($x)), 'UTF-8'), $cells);
        continue;
    }
    $rows[] = $cells;
}
fclose($h);
$idx = [];
foreach (array_merge($col, $saleCol !== '' ? ['sale' => $saleCol] : []) as $k => $name) {
    $i = array_search(mb_strtolower($name, 'UTF-8'), (array) $header, true);
    $idx[$k] = $i === false ? null : $i;
}
foreach (['sku', 'price', 'stock'] as $must) {
    if ($idx[$must] === null) {
        log_line('A CSV fejlécéből hiányzik: ' . $col[$must] . ' (van: ' . implode(', ', (array) $header) . ')', 'ERROR');
        exit(1);
    }
}
if ($saleCol !== '' && $idx['sale'] === null) {
    log_line("Az akciós ár oszlopa ({$saleCol}) nincs a CSV-ben – akció nélkül fut.", 'WARNING');
    $saleCol = '';
}

$juta = [];       // vonalkód → [név, ár, akció, készlet]
$jutaDup = [];
foreach ($rows as $n => $r) {
    $sku = trim(to_utf8($r[$idx['sku']] ?? ''));
    if ($sku === '') {
        continue;
    }
    $name = trim(to_utf8($r[$idx['name']] ?? '') . ' ' . ($idx['name2'] !== null ? to_utf8($r[$idx['name2']] ?? '') : ''));
    $price = parse_num($r[$idx['price']] ?? '');
    $stock = parse_num($r[$idx['stock']] ?? '');
    if ($price === null || $stock === null) {
        log_line("Kihagyva (hibás ár vagy készlet): {$sku}", 'WARNING');
        continue;
    }
    if (isset($juta[$sku])) {
        $jutaDup[$sku] = true;
    }
    $juta[$sku] = ['name' => $name, 'price' => $price, 'sale' => $saleCol !== '' ? parse_num($r[$idx['sale']] ?? '') : null, 'stock' => max(0, (int) round($stock))];
}
log_line('CSV: ' . count($rows) . ' sor, ' . count($juta) . ' vonalkód');

// ------------------------------------------------------------------ a webshop termékei cikkszám szerint

$shop = [];       // cikkszám → [[id, név, típus, állapot], …]
foreach (q($db, "SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value AS sku FROM {$p}postmeta pm
    JOIN {$p}posts p ON p.ID = pm.post_id WHERE pm.meta_key = '_sku' AND pm.meta_value <> ''
    AND p.post_type IN ('product', 'product_variation') AND p.post_status NOT IN ('auto-draft', 'trash')") as $r) {
    $shop[trim($r['sku'])][] = $r;
}

// A nyitott, a JUTA-ba még be nem küldött rendelések mennyisége termékenként (a JUTA ezeket még nem vonta le).
$reserved = [];
if (!empty($CFG['reserve_open_orders'])) {
    $hp = hpos($db, $p);
    $st = array_map(fn($s) => "'" . mysqli_real_escape_string($db, $s) . "'", (array) $CFG['reserve_statuses']);
    $done = "'" . implode("','", array_map(fn($s) => mysqli_real_escape_string($db, $s), (array) $CFG['order_statuses'])) . "'";
    $orders = $hp
        ? "SELECT o.id FROM {$p}wc_orders o LEFT JOIN {$p}wc_orders_meta m ON m.order_id = o.id AND m.meta_key = '_elad_exported_file'
           WHERE o.type = 'shop_order' AND (o.status IN (" . implode(',', $st) . ") OR (o.status IN ({$done}) AND m.id IS NULL))"
        : "SELECT o.ID AS id FROM {$p}posts o LEFT JOIN {$p}postmeta m ON m.post_id = o.ID AND m.meta_key = '_elad_exported_file'
           WHERE o.post_type = 'shop_order' AND (o.post_status IN (" . implode(',', $st) . ") OR (o.post_status IN ({$done}) AND m.meta_id IS NULL))";
    foreach (q($db, "SELECT COALESCE(NULLIF(v.meta_value, '0'), pr.meta_value) AS pid, SUM(qt.meta_value) AS qty
        FROM {$p}woocommerce_order_items i
        JOIN ({$orders}) oo ON oo.id = i.order_id
        JOIN {$p}woocommerce_order_itemmeta pr ON pr.order_item_id = i.order_item_id AND pr.meta_key = '_product_id'
        LEFT JOIN {$p}woocommerce_order_itemmeta v ON v.order_item_id = i.order_item_id AND v.meta_key = '_variation_id'
        JOIN {$p}woocommerce_order_itemmeta qt ON qt.order_item_id = i.order_item_id AND qt.meta_key = '_qty'
        WHERE i.order_item_type = 'line_item' GROUP BY pid") as $r) {
        $reserved[(int) $r['pid']] = (int) round((float) $r['qty']);
    }
    if ($reserved) {
        log_line('Nyitott rendelésekben lefoglalva: ' . array_sum($reserved) . ' db, ' . count($reserved) . ' termék');
    }
}

// ------------------------------------------------------------------ írás

function set_meta(mysqli $db, string $p, int $id, string $key, string $value): void
{
    $has = q($db, "SELECT meta_id FROM {$p}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1", [$id, $key]);
    if ($has) {
        q($db, "UPDATE {$p}postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = ?", [$value, $id, $key]);
    } else {
        q($db, "INSERT INTO {$p}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
    }
}

function get_meta(mysqli $db, string $p, int $id, string $key): string
{
    $r = q($db, "SELECT meta_value FROM {$p}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1", [$id, $key]);
    return (string) ($r[0]['meta_value'] ?? '');
}

$money = fn(float $v) => number_format(max(0, $v), 2, '.', '');
$stats = ['updated' => 0, 'unchanged' => 0, 'created' => 0, 'only_juta' => 0, 'only_shop' => 0, 'multi' => 0, 'error' => 0];
$report = [['tipus', 'vonalkod', 'juta_nev', 'webshop_nev', 'webshop_id', 'webshop_allapot', 'juta_ar', 'juta_keszlet']];

foreach ($juta as $sku => $j) {
    $hits = $shop[(string) $sku] ?? [];
    if (count($hits) > 1 || isset($jutaDup[$sku])) {
        // többszörös: nem írjuk, kézi döntés kell (nem vonunk össze automatikusan)
        $stats['multi']++;
        foreach ($hits ?: [['ID' => '', 'post_title' => '', 'post_status' => '']] as $hit) {
            $report[] = ['tobbszoros', $sku, $j['name'], $hit['post_title'], $hit['ID'], $hit['post_status'], $j['price'], $j['stock']];
        }
        continue;
    }
    if (!$hits) {
        $stats['only_juta']++;
        $report[] = ['csak_juta', $sku, $j['name'], '', '', '', $j['price'], $j['stock']];
        if (!empty($CFG['create_drafts']) && !$DRY) {
            try {
                $now = date('Y-m-d H:i:s');
                $gmt = gmdate('Y-m-d H:i:s');
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $j['name'] . '-' . $sku))), '-');
                q($db, "INSERT INTO {$p}posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, to_ping, pinged,
                    post_content_filtered, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_parent,
                    guid, menu_order, post_type, post_mime_type, comment_count)
                    VALUES (1, ?, ?, '', ?, '', '', '', '', 'draft', 'closed', 'closed', ?, ?, ?, 0, '', 0, 'product', '', 0)",
                    [$now, $gmt, $j['name'] ?: 'JUTA ' . $sku, $slug, $now, $gmt]);
                $id = (int) mysqli_insert_id($db);
                foreach (['_sku' => $sku, '_regular_price' => $money($j['price']), '_price' => $money($j['price']), '_stock' => (string) $j['stock'],
                    '_stock_status' => $j['stock'] > 0 ? 'instock' : 'outofstock', '_manage_stock' => 'yes', '_backorders' => 'no',
                    '_tax_status' => 'taxable', '_tax_class' => '', '_virtual' => 'no', '_downloadable' => 'no'] as $k => $v) {
                    set_meta($db, $p, $id, $k, $v);
                }
                $tt = q($db, "SELECT tt.term_taxonomy_id FROM {$p}terms t JOIN {$p}term_taxonomy tt ON tt.term_id = t.term_id WHERE t.slug = 'simple' AND tt.taxonomy = 'product_type' LIMIT 1");
                if ($tt) {
                    q($db, "INSERT IGNORE INTO {$p}term_relationships (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)", [$id, (int) $tt[0]['term_taxonomy_id']]);
                }
                $stats['created']++;
                log_line("PISZKOZAT id={$id} {$sku} {$j['name']}");
            } catch (Throwable $e) {
                $stats['error']++;
                log_line("Piszkozat sikertelen {$sku}: " . $e->getMessage(), 'ERROR');
            }
        }
        continue;
    }
    $hit = $hits[0];
    $id = (int) $hit['ID'];
    $report[] = ['egyezik', $sku, $j['name'], $hit['post_title'], $id, $hit['post_status'], $j['price'], $j['stock']];
    try {
        $stock = max(0, $j['stock'] - ($reserved[$id] ?? 0));
        $status = $stock > 0 ? 'instock' : 'outofstock';
        $regular = $money($j['price']);
        $want = ['_regular_price' => $regular, '_stock' => (string) $stock, '_stock_status' => $status, '_manage_stock' => 'yes'];
        if ($saleCol !== '') {
            // az akció forrása a JUTA: ha van érvényes akciós ár, az él; ha nincs, a webshopban sincs akció
            $sale = $j['sale'];
            $on = $sale !== null && $sale > 0 && $sale < $j['price'];
            $want += ['_sale_price' => $on ? $money($sale) : '', '_price' => $on ? $money($sale) : $regular, '_sale_price_dates_from' => '', '_sale_price_dates_to' => ''];
        } else {
            // akció a webshopban (ha ott állítottak be): azt nem írjuk felül
            $curSale = (float) get_meta($db, $p, $id, '_sale_price');
            $on = $curSale > 0 && $curSale < $j['price'];
            $want['_price'] = $on ? $money($curSale) : $regular;
        }
        $changed = false;
        foreach ($want as $k => $v) {
            if (get_meta($db, $p, $id, $k) !== $v) {
                $changed = true;
                if (!$DRY) {
                    set_meta($db, $p, $id, $k, $v);
                }
            }
        }
        if ($changed && !$DRY && table_exists($db, $p . 'wc_product_meta_lookup')) {
            $price = (float) $want['_price'];
            if (q($db, "SELECT product_id FROM {$p}wc_product_meta_lookup WHERE product_id = ? LIMIT 1", [$id])) {
                q($db, "UPDATE {$p}wc_product_meta_lookup SET min_price = ?, max_price = ?, stock_quantity = ?, stock_status = ?, onsale = ? WHERE product_id = ?",
                    [$price, $price, $stock, $status, $on ? 1 : 0, $id]);
            } else {
                q($db, "INSERT INTO {$p}wc_product_meta_lookup (product_id, sku, min_price, max_price, stock_quantity, stock_status, onsale) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$id, $sku, $price, $price, $stock, $status, $on ? 1 : 0]);
            }
        }
        $stats[$changed ? 'updated' : 'unchanged']++;
        if ($changed) {
            log_line(sprintf('%s id=%d %s ár=%s%s készlet=%d%s', $DRY ? 'LENNE' : 'FRISSÍTVE', $id, $sku, $regular,
                $on ? ' akció=' . $want['_price'] : '', $stock, isset($reserved[$id]) ? ' (lefoglalva ' . $reserved[$id] . ')' : ''));
        }
    } catch (Throwable $e) {
        $stats['error']++;
        log_line("Hiba {$sku}: " . $e->getMessage(), 'ERROR');
    }
}

// a webshop olyan cikkszámai, amelyek nincsenek a JUTA-ban
foreach ($shop as $sku => $hits) {
    if (!isset($juta[$sku])) {
        foreach ($hits as $hit) {
            $stats['only_shop']++;
            $report[] = [count($hits) > 1 ? 'tobbszoros' : 'csak_webshop', $sku, '', $hit['post_title'], $hit['ID'], $hit['post_status'], '', ''];
        }
    }
}

// párosító tábla (Excelben nyitható: UTF-8 BOM, „;”)
$reportFile = (string) ($CFG['report'] ?? dirname(__DIR__, 2) . '/juta-parositas.csv');
$out = @fopen($reportFile, 'w');
if (!$out) {
    log_line('A párosító tábla nem írható: ' . $reportFile . ' (config.php: report)', 'ERROR');
    exit(1);
}
fwrite($out, "\xEF\xBB\xBF");
foreach ($report as $line) {
    fputcsv($out, $line, ';', '"', '\\');
}
fclose($out);

log_line(sprintf('KÉSZ – frissítve: %d, változatlan: %d, csak a JUTA-ban: %d, csak a webshopban: %d, többszörös: %d, új piszkozat: %d, hiba: %d',
    $stats['updated'], $stats['unchanged'], $stats['only_juta'], $stats['only_shop'], $stats['multi'], $stats['created'], $stats['error']));
log_line('Párosító tábla: ' . $reportFile);
flock($lock, LOCK_UN);
