<?php
/**
 * Webshop → JUTA: a teljesített rendelésekből eladás-fájl (elad/eladNNNN.txt, CP852) a JUTA-nak, rendelésenként egy.
 * A Mandala elad.php-ja alapján: ugyanaz a fájlformátum ([FEJ] [VEVO] [ADAT] [LAB]), a Slugshop fizetési módjaival
 * (Viva = kártya; a leképezés a config.php-ben), és HPOS nélküli (posts táblás) rendelésekkel is.
 *
 * Csak az első futás napjától (elad-export-state.json) és csak a még be nem küldött rendeléseket (_elad_exported_file)
 * küldi; a régi boltból átvett rendeléseken „regi-bolt” jelölő van, azokat soha.
 */

require __DIR__ . '/common.php';

$lock = lock_or_exit(__DIR__ . '/elad-export.lock');
$p = (string) $CFG['prefix'];
$stateFile = __DIR__ . '/elad-export-state.json';
$metaKey = '_elad_exported_file';

function cp852(string $text): string
{
    $text = trim(str_replace(["\r\n", "\r", "\n"], ' ', $text));
    $c = @iconv('UTF-8', 'CP852//TRANSLIT//IGNORE', $text);
    return $c !== false ? $c : $text;
}

function add_line(string $key, $value): string
{
    $value = trim((string) $value);
    return $value === '' ? '' : $key . '=' . cp852($value) . "\r\n";
}

function short_text(string $text, int $len): string
{
    return mb_substr(trim($text), 0, $len, 'UTF-8');
}

function save_state(string $file, array $state): void
{
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Az állapotfájl nem menthető: ' . $file);
    }
}

function next_filename(string $dir): string
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Az export mappa nem hozható létre: ' . $dir);
    }
    $max = 0;
    foreach (glob($dir . '/elad*.txt') ?: [] as $f) {
        if (preg_match('/^elad(\d+)\.txt$/', basename($f), $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('elad%04d.txt', $max + 1);
}

/** Fizetési mód → JUTA. */
function fizmod(string $payment, bool $pickup): string
{
    global $CFG;
    if ($payment === 'cod' && $pickup) {
        return (string) $CFG['payment_pickup_cod'];
    }
    if (stripos($payment, 'viva') === 0 || in_array($payment, ['stripe', 'barion', 'simplepay', 'paypal', 'borgun'], true)) {
        return (string) $CFG['payment_map_card'];
    }
    return (string) ($CFG['payment_map'][$payment] ?? $CFG['payment_default']);
}

// ------------------------------------------------------------------ állapot (a kezdőnap: az első futás napja)

if (is_file($stateFile)) {
    $state = json_decode((string) file_get_contents($stateFile), true);
    if (!is_array($state) || empty($state['export_from_utc'])) {
        log_line('Hibás állapotfájl: ' . $stateFile, 'ERROR');
        exit(1);
    }
} else {
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Budapest'));
    $state = ['created_at' => date('Y-m-d H:i:s'), 'export_from_local' => $today->format('Y-m-d H:i:s'),
        'export_from_utc' => $today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'last_exported_order_id' => null, 'last_exported_filename' => null, 'last_exported_at' => null];
    if (!$DRY) {
        save_state($stateFile, $state);
    }
}
log_line('Webshop → JUTA indul' . ($DRY ? ' (PRÓBA, nem ír)' : '') . ', kezdőnap: ' . $state['export_from_local']);

$db = db();
$hp = hpos($db, $p);
$statuses = array_values((array) $CFG['order_statuses']);
$in = implode(',', array_fill(0, count($statuses), '?'));

// ------------------------------------------------------------------ a beküldendő rendelések (HPOS vagy posts tábla)

if ($hp) {
    $orders = q($db, "SELECT o.id, o.payment_method, o.billing_email FROM {$p}wc_orders o
        LEFT JOIN {$p}wc_orders_meta em ON em.order_id = o.id AND em.meta_key = ?
        WHERE o.type = 'shop_order' AND o.status IN ({$in}) AND COALESCE(o.date_updated_gmt, o.date_created_gmt) >= ? AND em.id IS NULL
        ORDER BY o.id", array_merge([$metaKey], $statuses, [$state['export_from_utc']]));
} else {
    $orders = q($db, "SELECT o.ID AS id, pm.meta_value AS payment_method, be.meta_value AS billing_email FROM {$p}posts o
        LEFT JOIN {$p}postmeta em ON em.post_id = o.ID AND em.meta_key = ?
        LEFT JOIN {$p}postmeta pm ON pm.post_id = o.ID AND pm.meta_key = '_payment_method'
        LEFT JOIN {$p}postmeta be ON be.post_id = o.ID AND be.meta_key = '_billing_email'
        WHERE o.post_type = 'shop_order' AND o.post_status IN ({$in}) AND o.post_modified_gmt >= ? AND em.meta_id IS NULL
        ORDER BY o.ID", array_merge([$metaKey], $statuses, [$state['export_from_utc']]));
}

function billing(mysqli $db, string $p, bool $hp, int $id): array
{
    if ($hp) {
        return q($db, "SELECT * FROM {$p}wc_order_addresses WHERE order_id = ? AND address_type = 'billing' LIMIT 1", [$id])[0] ?? [];
    }
    $out = [];
    foreach (q($db, "SELECT meta_key, meta_value FROM {$p}postmeta WHERE post_id = ? AND meta_key LIKE '\\_billing\\_%'", [$id]) as $r) {
        $out[substr($r['meta_key'], 9)] = $r['meta_value'];
    }
    return $out;
}

function set_order_meta(mysqli $db, string $p, bool $hp, int $id, string $key, string $value): void
{
    if ($hp) {
        q($db, "INSERT INTO {$p}wc_orders_meta (order_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
    }
    // a posts táblás tárolás (vagy a HPOS kompatibilitási másolata) is kapja meg, hogy kétszer ne menjen ki
    if (!$hp || q($db, "SELECT ID FROM {$p}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1", [$id])) {
        q($db, "INSERT INTO {$p}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
    }
}

$count = 0;
foreach ($orders as $order) {
    $id = (int) $order['id'];
    try {
        $b = billing($db, $p, $hp, $id);
        $company = (string) ($b['company'] ?? '');
        $name = trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? ''));
        $pickup = (bool) q($db, "SELECT i.order_item_id FROM {$p}woocommerce_order_items i JOIN {$p}woocommerce_order_itemmeta m
            ON m.order_item_id = i.order_item_id AND m.meta_key = 'method_id' AND m.meta_value IN ('local_pickup', 'pickup_location')
            WHERE i.order_id = ? AND i.order_item_type = 'shipping' LIMIT 1", [$id]);
        $c = "[FEJ]\r\n" . add_line('biztip', 'szamla') . add_line('felrerak', 'igen') . add_line('fmegjegyz', 'Webshop rendelés #' . $id) . "\r\n";
        $c .= "[VEVO]\r\n" . add_line('kod', '0') . add_line('kulsokod', 'woo-' . $id) . add_line('nev', short_text($company !== '' ? $company : $name, 25))
            . add_line('irsz', $b['postcode'] ?? '') . add_line('varos', short_text($b['city'] ?? '', 25)) . add_line('cim', short_text($b['address_1'] ?? '', 25))
            . add_line('cim2', short_text($b['address_2'] ?? '', 25)) . add_line('email', ($b['email'] ?? '') ?: ($order['billing_email'] ?? ''))
            . add_line('tel', $b['phone'] ?? '') . "\r\n";
        $items = q($db, "SELECT order_item_id, order_item_name FROM {$p}woocommerce_order_items WHERE order_id = ? AND order_item_type = 'line_item' ORDER BY order_item_id", [$id]);
        foreach ($items as $it) {
            $m = [];
            foreach (q($db, "SELECT meta_key, meta_value FROM {$p}woocommerce_order_itemmeta WHERE order_item_id = ?", [(int) $it['order_item_id']]) as $r) {
                $m[$r['meta_key']] = $r['meta_value'];
            }
            $pid = (int) ($m['_variation_id'] ?? 0) ?: (int) ($m['_product_id'] ?? 0);
            $sku = $pid ? (string) (q($db, "SELECT meta_value FROM {$p}postmeta WHERE post_id = ? AND meta_key = '_sku' LIMIT 1", [$pid])[0]['meta_value'] ?? '') : '';
            $qty = (float) ($m['_qty'] ?? 1);
            $gross = (float) ($m['_line_total'] ?? 0) + (float) ($m['_line_tax'] ?? 0);
            $c .= "[ADAT]\r\n" . ($sku !== '' ? add_line('vonalkod', $sku) : add_line('kulsokod', 'woo-product-' . $pid))
                . add_line('megnevezes', $it['order_item_name']) . add_line('mennyiseg', rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'))
                . add_line('eladar', number_format($qty > 0 ? $gross / $qty : $gross, 0, '', '')) . "\r\n";
            if ($sku === '') {
                log_line("#{$id}: a(z) „{$it['order_item_name']}” tételnek nincs cikkszáma – a JUTA nem tudja párosítani", 'WARNING');
            }
        }
        $c .= "[LAB]\r\n" . add_line('megjegyz1', 'Webshop rendelés #' . $id) . add_line('fizmod', fizmod((string) $order['payment_method'], $pickup));

        $file = next_filename($CFG['export_dir']);
        if ($DRY) {
            log_line("PRÓBA: {$file} készülne (#{$id}, " . count($items) . ' tétel, fizetés: ' . fizmod((string) $order['payment_method'], $pickup) . ')');
            continue;
        }
        $path = $CFG['export_dir'] . '/' . $file;
        if (!file_put_contents($path . '.tmp', $c, LOCK_EX) || !rename($path . '.tmp', $path)) {
            @unlink($path . '.tmp');
            throw new RuntimeException('A fájl nem írható: ' . $path);
        }
        set_order_meta($db, $p, $hp, $id, $metaKey, $file);
        $state = array_merge($state, ['last_exported_order_id' => $id, 'last_exported_filename' => $file, 'last_exported_at' => date('Y-m-d H:i:s')]);
        save_state($stateFile, $state);
        $count++;
        log_line("ELKÉSZÜLT {$file} (#{$id})");
    } catch (Throwable $e) {
        log_line("#{$id} sikertelen: " . $e->getMessage(), 'ERROR');
    }
}
log_line('KÉSZ – ' . ($DRY ? count($orders) . ' rendelés menne' : $count . ' rendelés beküldve') . ($hp ? ' (HPOS)' : ' (posts tábla)'));
flock($lock, LOCK_UN);
