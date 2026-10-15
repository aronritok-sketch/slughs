<?php
/**
 * JUTA-Soft kapcsolat – a Mandala mintájára (aronritok-sketch/mdn: docs/JUTA.md, inc/features/juta.php).
 *
 * A kapcsolat NEM a téma része: a tárhely web gyökerében egy /juta/ mappa két szkripttel, amelyeket a tárhely cronja hív:
 *   /juta/raw_sync.php – a JUTA készlet-exportja alapján cikkszám (SKU) szerint frissíti az árat, a készletet és a
 *                        készletállapotot; ismeretlen cikkszámból piszkozat termék lesz;
 *   /juta/elad.php     – a teljesített webshop-rendeléseket fájlba írja a JUTA-nak, és megjelöli őket.
 * A szkriptek a sebesség miatt WordPress nélkül, közvetlenül az adatbázisba írnak. Ezért a téma 10 percenként összeveti a
 * termékek ár- és készletadatainak lenyomatát, és a megváltozottaknál:
 *   - törli az objektum-gyorsítótárat és a WooCommerce átmeneti adatait (különben a régi ár / készlet látszhat),
 *   - ha egy termék újra raktárra került, kiküldi az „Értesítést kérek” leveleket,
 *   - feljegyzi a szinkron idejét (az admin figyelmeztet, ha 26 óránál régebben volt változás),
 *   - az új (a JUTA-ból jött, piszkozat) termékeket az adminban jelzi.
 * Ha a szkriptek valaha WordPress-betöltéssel futnak, azonnal is jelezhetnek (nem kötelező):
 *   do_action('slugshop_juta_sync_done', $stats);       // raw_sync.php végén
 *   $ids = slugshop_juta_orders_to_send(['completed']); // elad.php: beküldendő rendelések
 *   do_action('slugshop_juta_orders_sent', $ids);       // elad.php végén
 */

defined('ABSPATH') || exit;

const SLUGSHOP_JUTA_SENT = '_elad_exported_file'; // ugyanaz a jelölő, amit az elad.php ír
const SLUGSHOP_JUTA_KEYS = ['_price', '_regular_price', '_sale_price', '_stock', '_stock_status', '_sku', '_manage_stock'];

/** Termékenkénti lenyomat [azonosító => [crc32, készletállapot]] – egy lekérdezés, a gyorsítótárat megkerülve. */
function slugshop_juta_fingerprints(): array
{
    global $wpdb;
    $keys = "'" . implode("','", array_map('esc_sql', SLUGSHOP_JUTA_KEYS)) . "'";
    $wpdb->query('SET SESSION group_concat_max_len = 65535');
    $rows = $wpdb->get_results(
        "SELECT p.ID AS id, CRC32(CONCAT_WS('|', p.post_status, p.post_title,
            (SELECT GROUP_CONCAT(m.meta_key, '=', m.meta_value ORDER BY m.meta_key SEPARATOR ';') FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key IN ($keys)))) AS h,
            (SELECT s.meta_value FROM {$wpdb->postmeta} s WHERE s.post_id = p.ID AND s.meta_key = '_stock_status' LIMIT 1) AS st
         FROM {$wpdb->posts} p WHERE p.post_type IN ('product', 'product_variation') AND p.post_status NOT IN ('auto-draft', 'trash')",
        ARRAY_A
    ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a kulcsok konstansok
    $out = [];
    foreach ((array) $rows as $r) {
        $out[(int) $r['id']] = [(int) $r['h'], $r['st'] === 'instock' ? 1 : 0];
    }
    return $out;
}

function slugshop_juta_watch(): array
{
    global $wpdb;
    $now = slugshop_juta_fingerprints();
    if (!$now && $wpdb->last_error) {
        return ['error' => 1];
    }
    $was = get_option('slugshop_juta_fingerprints', null);
    update_option('slugshop_juta_fingerprints', $now, false);
    if (!is_array($was)) {
        return ['changed' => 0, 'new' => 0, 'gone' => 0, 'first' => 1];
    }
    $changed = [];
    $back = [];
    foreach ($now as $id => [$h, $in]) {
        if (isset($was[$id]) && $was[$id][0] !== $h) {
            $changed[] = $id;
            if ($in && !$was[$id][1]) {
                $back[] = $id;
            }
        }
    }
    $new = array_keys(array_diff_key($now, $was));
    $gone = array_keys(array_diff_key($was, $now));
    $ids = array_merge($changed, $new, $gone);
    foreach ($ids as $id) {
        clean_post_cache($id);
        wp_cache_delete($id, 'post_meta');
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($id);
        }
        $parent = (int) wp_get_post_parent_id($id);
        if ($parent) {
            clean_post_cache($parent);
            wp_cache_delete($parent, 'post_meta');
        }
    }
    foreach ($back as $id) {
        slugshop_notify_back_in_stock((int) ($parent = wp_get_post_parent_id($id)) ?: $id);
    }
    if ($ids) {
        update_option('slugshop_juta_sync', ['time' => time(), 'stats' => ['changed' => count($changed), 'new' => count($new), 'gone' => count($gone), 'back' => count($back)], 'source' => 'watch'], false);
        do_action('slugshop_juta_changes', $ids);
    }
    return ['changed' => count($changed), 'new' => count($new), 'gone' => count($gone), 'back' => count($back)];
}

add_filter('cron_schedules', function ($s) {
    $s['slugshop_10min'] = ['interval' => 10 * MINUTE_IN_SECONDS, 'display' => 'Slugshop: 10 percenként'];
    return $s;
});
add_action('init', function () {
    if (!wp_next_scheduled('slugshop_juta_watch')) {
        wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'slugshop_10min', 'slugshop_juta_watch');
    }
});
add_action('slugshop_juta_watch', function () {
    if (slugshop_lock('juta_watch', 300)) {
        slugshop_juta_watch();
    }
});

add_action('slugshop_juta_sync_done', function ($stats = []) {
    update_option('slugshop_juta_sync', ['time' => time(), 'stats' => array_map('intval', array_filter((array) $stats, 'is_scalar')), 'source' => 'script'], false);
    slugshop_juta_watch();
});

/**
 * Beküldendő rendelések (HPOS-tól és a régi adattárolótól függetlenül – a meta_query csak HPOS-on működne, ezért a
 * jelölőt itt szűrjük): a megadott állapotúak, jelölő nélkül, nem átköltöztetettek.
 */
function slugshop_juta_orders_to_send(array $statuses = ['completed'], int $limit = 200): array
{
    if (!function_exists('wc_get_orders')) {
        return [];
    }
    $out = [];
    $page = 1;
    do {
        $orders = wc_get_orders(['type' => 'shop_order', 'status' => $statuses, 'limit' => 100, 'paged' => $page++, 'orderby' => 'date', 'order' => 'ASC']);
        foreach ($orders as $o) {
            if (!$o->get_meta(SLUGSHOP_JUTA_SENT) && !$o->get_meta('_slugshop_imported')) {
                $out[] = (int) $o->get_id();
            }
        }
    } while ($orders && count($out) < $limit);
    return array_slice($out, 0, $limit);
}

add_action('slugshop_juta_orders_sent', function ($ids = []) {
    foreach ((array) $ids as $id) {
        $order = wc_get_order((int) $id);
        if ($order) {
            $order->update_meta_data(SLUGSHOP_JUTA_SENT, 'webshop-' . gmdate('Ymd'));
            $order->save_meta_data();
        }
    }
});

/**
 * Átköltöztetett (régi VirtueMart) rendelések: ha valaha átvesszük őket, kapjanak „regi-bolt” jelölőt, hogy az elad.php
 * ne küldje be újra (a régi bolt már beküldte). Az átvevő eszköz a _slugshop_imported metát írja.
 */
add_action('init', function () {
    if (get_option('slugshop_juta_marked') === 'done' || get_transient('slugshop_juta_marking') || !function_exists('wc_get_orders')) {
        return;
    }
    set_transient('slugshop_juta_marking', 1, 2 * MINUTE_IN_SECONDS);
    $page = (int) get_option('slugshop_juta_mark_page', 1);
    $orders = wc_get_orders(['type' => 'shop_order', 'status' => 'any', 'limit' => 200, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC']);
    foreach ($orders as $o) {
        if ($o->get_meta('_slugshop_imported') && !$o->get_meta(SLUGSHOP_JUTA_SENT)) {
            $o->update_meta_data(SLUGSHOP_JUTA_SENT, 'regi-bolt');
            $o->save_meta_data();
        }
    }
    if (count($orders) < 200) {
        update_option('slugshop_juta_marked', 'done', false);
        delete_option('slugshop_juta_mark_page');
    } else {
        update_option('slugshop_juta_mark_page', $page + 1, false);
    }
}, 30);

/** Ha később átvett régi rendelések érkeznek (az átvevő eszköz _slugshop_imported metát ír), a jelölés újraindul. */
add_action('slugshop_orders_imported', function () {
    delete_option('slugshop_juta_marked');
});

/** Állapot az Élesítési ellenőrzéshez és az admin értesítéshez. */
function slugshop_juta_status(): array
{
    $sync = (array) get_option('slugshop_juta_sync', []);
    $drafts = (int) (new WP_Query(['post_type' => 'product', 'post_status' => 'draft', 'posts_per_page' => 1, 'fields' => 'ids']))->found_posts;
    $hours = !empty($sync['time']) ? (int) floor((time() - (int) $sync['time']) / HOUR_IN_SECONDS) : null;
    return ['last' => $sync['time'] ?? null, 'hours' => $hours, 'stale' => $hours !== null && $hours > 26, 'drafts' => $drafts];
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $s = slugshop_juta_status();
    if ($s['stale']) {
        echo '<div class="notice notice-warning"><p><strong>JUTA:</strong> ' . (int) $s['hours'] . ' órája nem változott ár vagy készlet. Fut a tárhely cronja (/juta/raw_sync.php)?</p></div>';
    }
    if ($s['drafts'] && get_current_screen() && in_array(get_current_screen()->id, ['dashboard', 'edit-product'], true)) {
        echo '<div class="notice notice-info"><p><strong>JUTA:</strong> ' . (int) $s['drafts'] . ' új termék vár piszkozatként (kategória, kép, leírás kell, aztán közzététel). <a href="' . esc_url(admin_url('edit.php?post_type=product&post_status=draft')) . '">Megnyitás</a></p></div>';
    }
});
