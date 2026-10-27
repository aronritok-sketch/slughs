<?php
/**
 * Telepítő: a régi slugshop.hu tartalma és a webshop beállításai a WordPressbe, lépésenként.
 *
 *   wp slugshop setup [--force] [--media=<mappa|zip>]     vagy     Megjelenés → Slugshop → Telepítés
 *
 * Lépések: beállítások → kategóriák → termékek (25-ös kötegekben) → oldalak → cikkek → menük → befejezés.
 * Idempotens: a második futás nem ír át semmit. Amit az adminban azóta szerkesztettek (más a tartalma, mint amit a
 * telepítő írt – lenyomat a „slugshop_hash” metában), azt kihagyja, kivéve --force / „Felülírás” pipával.
 *
 * Adatok: setup/data/*.json (a tools/wp/build_data.py generálja az előnézet adataiból), blokk-tartalom:
 * setup/content/**.html. Képek: a médiacsomag (dist/slugshop-media.zip) kicsomagolva a
 * wp-content/uploads/slugshop-media/ mappába – a telepítő onnan veszi fel őket a médiatárba (egyszer).
 */

defined('ABSPATH') || exit;

const SLUGSHOP_SETUP_VERSION = '1.0.0';
const SLUGSHOP_BATCH = 25;

function slugshop_setup_data(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = SLUGSHOP_DIR . '/setup/data/' . $name . '.json';
        $cache[$name] = is_readable($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }
    return $cache[$name];
}

/** Blokk-tartalom a setup/content-ből; a {{media:mappa/fájl}} jelölők helyére a médiatárbeli kép URL-je kerül. */
function slugshop_setup_content(string $rel): string
{
    $file = SLUGSHOP_DIR . '/setup/content/' . $rel;
    $html = is_readable($file) ? trim((string) file_get_contents($file)) : '';
    return (string) preg_replace_callback('/\{\{media:([\w\/.\-]+)\}\}/', function ($m) {
        $id = slugshop_attach($m[1]);
        return $id ? (string) wp_get_attachment_url($id) : '';
    }, $html);
}

function slugshop_media_dir(): string
{
    return wp_upload_dir()['basedir'] . '/slugshop-media';
}

/** A lépések sorrendje: [név => [felirat, kötegelt?]]. */
function slugshop_setup_steps(): array
{
    return [
        'settings' => ['Beállítások (WordPress, WooCommerce, ÁFA, szállítás, oldalak)', false],
        'categories' => ['Termékkategóriák', false],
        'products' => ['Termékek képekkel', true],
        'pages' => ['Oldalak', false],
        'posts' => ['Cikkek', false],
        'menus' => ['Menük', false],
        'finish' => ['Befejezés (nyitóoldal, permalinkek)', false],
    ];
}

/**
 * Egy lépés (vagy köteg) futtatása.
 *
 * @return array{done:bool, offset:int, total:int, log:string[]}
 */
/**
 * Zárolt katalógus: az első teljes telepítés után a termékeket és a kategóriákat az admin (az ügyfél, CSV-import,
 * csoportos szerkesztés) kezeli – a telepítő ezekhez a lépésekhez csak feloldás után nyúl (Telepítés fül).
 */
function slugshop_catalog_locked(): bool
{
    return get_option('slugshop_catalog_lock') === '1';
}

// Meglévő telepítésnél (a zárolás előtti verzióról frissítve) a katalógus magától zárolódik.
add_action('admin_init', function () {
    if (get_option('slugshop_catalog_lock') === false && get_option('slugshop_setup_version')) {
        update_option('slugshop_catalog_lock', '1');
    }
});

function slugshop_setup_run(string $step, int $offset = 0, bool $force = false): array
{
    if (in_array($step, ['categories', 'products'], true) && slugshop_catalog_locked()) {
        $msg = $step === 'products' ? '🔒 Termékek: zárolva, kihagyva (az adminban szerkesztik; feloldás: Megjelenés → Slugshop → Telepítés)' : '🔒 Kategóriák: zárolva, kihagyva';
        return ['done' => true, 'offset' => 0, 'total' => 0, 'log' => [$msg]];
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    wp_suspend_cache_invalidation(false);
    $log = [];
    $fn = 'slugshop_setup_' . $step;
    if (!function_exists($fn)) {
        return ['done' => true, 'offset' => 0, 'total' => 0, 'log' => ['Ismeretlen lépés: ' . $step]];
    }
    $res = $fn($offset, $force, $log);
    return ['done' => (bool) ($res['done'] ?? true), 'offset' => (int) ($res['offset'] ?? 0), 'total' => (int) ($res['total'] ?? 0), 'log' => $log];
}

// --------------------------------------------------------------------------------------------------- segédek

/** Lenyomat a „kézzel szerkesztették-e” kérdéshez (blokkszinten normalizálva: a szerkesztő mentése mást ír ki). */
function slugshop_content_hash(string $content): string
{
    $norm = function (array $blocks) use (&$norm) {
        $out = [];
        foreach ($blocks as $b) {
            if ($b['blockName'] === null && trim((string) $b['innerHTML']) === '') {
                continue;
            }
            $attrs = array_filter((array) $b['attrs'], fn($v) => $v !== '' && $v !== null && $v !== []);
            unset($attrs['style'], $attrs['iu_style']);
            ksort($attrs);
            $out[] = [$b['blockName'], $attrs, $b['blockName'] && !$b['innerBlocks'] ? preg_replace('/\s+/', ' ', trim(wp_strip_all_tags((string) $b['innerHTML']))) : '', $norm((array) $b['innerBlocks'])];
        }
        return $out;
    };
    return md5((string) wp_json_encode($norm(parse_blocks($content))));
}

/**
 * Kép a médiatárba (egyszer): a médiacsomag relatív útvonala alapján. A régi URL-t a „slugshop_source” metába írja
 * (a legacy.php a régi képcímeket így irányítja). Visszaad: melléklet azonosító vagy 0.
 */
function slugshop_attach(string $rel, int $parent = 0, string $alt = '', string $source = ''): int
{
    if ($rel === '') {
        return 0;
    }
    $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'slugshop_media', 'meta_value' => $rel]);
    if ($found) {
        return (int) $found[0];
    }
    $file = slugshop_media_dir() . '/' . $rel;
    if (!is_readable($file)) {
        return 0;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $up = wp_upload_bits(basename($file), null, (string) file_get_contents($file));
    if (!empty($up['error'])) {
        return 0;
    }
    $type = wp_check_filetype($up['file']);
    $id = wp_insert_attachment(['post_mime_type' => $type['type'] ?: 'image/webp', 'post_title' => $alt !== '' ? $alt : pathinfo($file, PATHINFO_FILENAME), 'post_status' => 'inherit'], $up['file'], $parent);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $up['file']));
    update_post_meta($id, 'slugshop_media', $rel);
    if ($alt !== '') {
        update_post_meta($id, '_wp_attachment_image_alt', $alt);
    }
    if ($source !== '') {
        update_post_meta($id, 'slugshop_source', $source);
    }
    return (int) $id;
}

/** Oldal vagy cikk létrehozása / frissítése a lenyomat szerint. */
function slugshop_upsert_post(array $args, string $key, bool $force, array &$log): int
{
    $existing = get_posts(['post_type' => $args['post_type'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'slugshop_key', 'meta_value' => $key]);
    if (!$existing) {
        // Típusra szűrve (a get_page_by_path a mellékleteket is visszaadná – Bébiszitter Akadémia tanulsága).
        $existing = get_posts(['post_type' => $args['post_type'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'name' => $args['post_name'], 'post_parent' => $args['post_parent'] ?? 0]);
    }
    $hash = slugshop_content_hash((string) $args['post_content']);
    if ($existing) {
        $id = (int) $existing[0];
        $stored = (string) get_post_meta($id, 'slugshop_hash', true);
        $current = slugshop_content_hash((string) get_post_field('post_content', $id));
        if ($stored === $hash && $current === $hash && get_post_field('post_title', $id) === $args['post_title']) {
            return $id; // változatlan
        }
        if (!$force && $stored !== '' && $current !== $stored) {
            $log[] = '⚠ kihagyva (az adminban szerkesztették): ' . $args['post_title'];
            return $id;
        }
        $args['ID'] = $id;
        wp_update_post(wp_slash($args));
        $log[] = '↻ frissítve: ' . $args['post_title'];
    } else {
        $id = (int) wp_insert_post(wp_slash($args + ['post_status' => 'publish']), true);
        if (!$id || is_wp_error($id)) {
            $log[] = '✗ nem sikerült: ' . $args['post_title'];
            return 0;
        }
        $log[] = '+ létrehozva: ' . $args['post_title'];
    }
    update_post_meta($id, 'slugshop_key', $key);
    update_post_meta($id, 'slugshop_hash', $hash);
    return $id;
}

// --------------------------------------------------------------------------------------------------- lépések

function slugshop_setup_settings(int $offset, bool $force, array &$log): array
{
    $site = slugshop_setup_data('site');
    $opts = [
        'blogname' => 'Slugshop', 'blogdescription' => 'Légfegyverek és ZAN lövedékek', 'timezone_string' => 'Europe/Budapest',
        'date_format' => 'Y. F j.', 'time_format' => 'H:i', 'start_of_week' => 1, 'permalink_structure' => '/%postname%',
        'default_comment_status' => 'closed', 'default_ping_status' => 'closed', 'show_avatars' => 0, 'uploads_use_yearmonth_folders' => 1,
        // WooCommerce: forint, bruttó ár, 27% ÁFA, Magyarország
        'woocommerce_default_country' => 'HU', 'woocommerce_store_address' => 'Megyeház u. 3.', 'woocommerce_store_city' => 'Jászberény',
        'woocommerce_store_postcode' => '5100', 'woocommerce_currency' => 'HUF', 'woocommerce_currency_pos' => 'right_space',
        'woocommerce_price_thousand_sep' => '.', 'woocommerce_price_decimal_sep' => ',', 'woocommerce_price_num_decimals' => 0,
        'woocommerce_calc_taxes' => 'yes', 'woocommerce_prices_include_tax' => 'yes', 'woocommerce_tax_display_shop' => 'incl',
        'woocommerce_tax_display_cart' => 'incl', 'woocommerce_tax_based_on' => 'base', 'woocommerce_tax_total_display' => 'single',
        'woocommerce_allowed_countries' => 'specific', 'woocommerce_specific_allowed_countries' => ['HU'],
        'woocommerce_ship_to_countries' => '', 'woocommerce_ship_to_destination' => 'billing', 'woocommerce_enable_guest_checkout' => 'yes',
        'woocommerce_enable_checkout_login_reminder' => 'yes', 'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
        'woocommerce_enable_myaccount_registration' => 'yes', 'woocommerce_registration_generate_password' => 'yes',
        'woocommerce_manage_stock' => 'yes', 'woocommerce_notify_low_stock' => 'no', 'woocommerce_notify_no_stock' => 'no',
        'woocommerce_hide_out_of_stock_items' => 'no', 'woocommerce_enable_reviews' => 'no', 'woocommerce_enable_ajax_add_to_cart' => 'yes',
        'woocommerce_cart_redirect_after_add' => 'no', 'woocommerce_email_from_name' => 'Slugshop', 'woocommerce_email_from_address' => (string) ($site['email'] ?? ''),
        'woocommerce_checkout_order_received_endpoint' => 'rendeles-fogadva', 'woocommerce_coming_soon' => 'no', 'woocommerce_store_pages_only' => 'no',
        'woocommerce_feature_order_attribution_enabled' => 'no',
    ];
    foreach ($opts as $k => $v) {
        update_option($k, $v);
    }
    // ÁFA: 27% normál kulcs Magyarországra (egyszer)
    if (class_exists('WC_Tax')) {
        global $wpdb;
        $has = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'HU' AND tax_rate_class = ''");
        if (!$has) {
            WC_Tax::_insert_tax_rate(['tax_rate_country' => 'HU', 'tax_rate' => '27.0000', 'tax_rate_name' => 'ÁFA', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '']);
            $log[] = '+ ÁFA 27%';
        }
    }
    slugshop_setup_shipping($log);
    slugshop_setup_payments($log);
    // A WooCommerce oldalai (shortcode-os, klasszikus) és a jogi oldalak hozzárendelése a pages lépésben.
    $log[] = '✓ WordPress- és WooCommerce-beállítások';
    return ['done' => true];
}

/**
 * Szállítás: „Magyarország” zóna. A futár (fix díj) KIKAPCSOLVA jön létre, amíg a díját meg nem adják (a régi bolt
 * díjtáblája – kérdéssor); a GLS bővítmény módjai ide kerülnek (Laci). A személyes átvétel (Jászberény) ingyenes, bekapcsolva.
 */
function slugshop_setup_shipping(array &$log): void
{
    if (!class_exists('WC_Shipping_Zones')) {
        return;
    }
    foreach (WC_Shipping_Zones::get_zones() as $z) {
        if ($z['zone_name'] === 'Magyarország') {
            return;
        }
    }
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name('Magyarország');
    $zone->set_zone_order(1);
    $zone->add_location('HU', 'country');
    $zone->save();
    $courier = $zone->add_shipping_method('flat_rate');
    $pickup = $zone->add_shipping_method('local_pickup');
    global $wpdb;
    $wpdb->update($wpdb->prefix . 'woocommerce_shipping_zone_methods', ['is_enabled' => 0], ['instance_id' => $courier]);
    update_option('woocommerce_flat_rate_' . $courier . '_settings', ['title' => 'Házhozszállítás futárszolgálattal', 'tax_status' => 'taxable', 'cost' => '']);
    update_option('woocommerce_local_pickup_' . $pickup . '_settings', ['title' => 'Személyes átvétel (Jászberény, Megyeház u. 3.)', 'tax_status' => 'none', 'cost' => '']);
    $log[] = '+ szállítási zóna: Magyarország (személyes átvétel be; a futár díja kérdéssor szerint, addig kikapcsolva)';
}

/** Fizetés: előre utalás és utánvét kikapcsolva jön létre (bankszámlaszám / díj kell); a Viva Wallet bővítmény külön. */
function slugshop_setup_payments(array &$log): void
{
    if (!get_option('woocommerce_bacs_settings')) {
        update_option('woocommerce_bacs_settings', ['enabled' => 'no', 'title' => 'Előre utalás', 'description' => 'A csomagot a jóváírás után adjuk fel. A bankszámlaszámot a visszaigazoló levélben küldjük.', 'instructions' => 'Kérjük, a közleménybe írd be a rendelésszámot.']);
        update_option('woocommerce_cod_settings', ['enabled' => 'no', 'title' => 'Utánvét', 'description' => 'Fizetés a futárnak készpénzzel vagy kártyával.', 'instructions' => '', 'enable_for_methods' => [], 'enable_for_virtual' => 'no']);
        update_option('woocommerce_cheque_settings', ['enabled' => 'no']);
        $log[] = '+ fizetési módok előkészítve (előre utalás, utánvét – kikapcsolva, kérdéssor szerint)';
    }
}

/**
 * Kategória a régi útvonala szerint – közvetlen lekérdezéssel: a WooCommerce a termékkategóriák get_terms()-ébe a saját
 * „order” meta-rendezését teszi, ami a meta_key szűrővel együtt üres eredményt ad (duplikált kategóriák lettek volna).
 */
function slugshop_term_by_path(string $path): array
{
    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT tm.term_id FROM {$wpdb->termmeta} tm JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id AND tt.taxonomy = 'product_cat'
         WHERE tm.meta_key = 'slugshop_path' AND tm.meta_value = %s ORDER BY tm.term_id LIMIT 1",
        $path
    ));
    return $id ? [$id] : [];
}

function slugshop_setup_categories(int $offset, bool $force, array &$log): array
{
    $cats = slugshop_setup_data('categories');
    $ids = [];
    foreach ($cats as $i => $c) {
        $parent = $c['parent'] ? ($ids[$c['parent']] ?? 0) : 0;
        // Ha a kategória címe változott (oldPath), a régi címen tárolt kategóriát viszi tovább.
        $found = slugshop_term_by_path($c['path']) ?: (!empty($c['oldPath']) ? slugshop_term_by_path($c['oldPath']) : []);
        $args = ['description' => $c['desc'], 'parent' => $parent, 'slug' => $c['slug']];
        if ($found) {
            $id = (int) $found[0];
            $term = get_term($id, 'product_cat');
            $stored = (string) get_term_meta($id, 'slugshop_hash', true);
            $hash = md5($c['name'] . '|' . $c['desc']);
            if (!empty($c['oldPath']) && $term->slug !== $c['slug'] && (string) get_term_meta($id, 'slugshop_path', true) === $c['oldPath']) {
                wp_update_term($id, 'product_cat', ['slug' => $c['slug']]);
            }
            if ($stored !== $hash && ($force || $stored === '' || md5($term->name . '|' . $term->description) === $stored)) {
                wp_update_term($id, 'product_cat', $args + ['name' => $c['name']]);
                update_term_meta($id, 'slugshop_hash', $hash);
                $log[] = '↻ ' . $c['name'];
            }
        } else {
            $res = wp_insert_term($c['name'], 'product_cat', $args);
            if (is_wp_error($res)) {
                $log[] = '✗ ' . $c['name'] . ': ' . $res->get_error_message();
                continue;
            }
            $id = (int) $res['term_id'];
            update_term_meta($id, 'slugshop_hash', md5($c['name'] . '|' . $c['desc']));
            $log[] = '+ ' . $c['name'];
        }
        $ids[$c['path']] = $id;
        update_term_meta($id, 'slugshop_path', $c['path']);
        update_term_meta($id, 'slugshop_menu', $c['menu']);
        update_term_meta($id, 'order', $i + 1);
        if (!empty($c['img']) && !get_term_meta($id, 'thumbnail_id', true)) {
            $att = slugshop_attach($c['img'], 0, $c['name']);
            if ($att) {
                update_term_meta($id, 'thumbnail_id', $att);
            }
        }
    }
    slugshop_flush_cat_paths();
    update_option('slugshop_setup_cat_ids', $ids, false);
    $log[] = '✓ ' . count($ids) . ' kategória';
    return ['done' => true];
}

function slugshop_setup_products(int $offset, bool $force, array &$log): array
{
    $all = slugshop_setup_data('products');
    $total = count($all);
    $cat_ids = (array) get_option('slugshop_setup_cat_ids', []);
    if (!$cat_ids) {
        $cat_ids = slugshop_cat_paths();
    }
    foreach (array_slice($all, $offset, SLUGSHOP_BATCH, true) as $i => $d) {
        slugshop_setup_product($d, (int) $i, $cat_ids, $force, $log);
    }
    $next = $offset + SLUGSHOP_BATCH;
    if ($next >= $total) {
        $log[] = '✓ ' . $total . ' termék';
    }
    return ['done' => $next >= $total, 'offset' => $next, 'total' => $total];
}

function slugshop_setup_product(array $d, int $order, array $cat_ids, bool $force, array &$log): void
{
    $found = get_posts(['post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'slugshop_key', 'meta_value' => $d['key']]);
    $text = fn($name, $short, $desc) => md5($name . '|' . $short . '|' . $desc);
    $new_text = $text($d['name'], $d['short'], $d['desc']);
    $terms = array_values(array_filter(array_map(fn($p) => (int) ($cat_ids[$p] ?? 0), $d['cats'])));
    $create = !$found;
    $product = $create ? new WC_Product_Simple() : wc_get_product((int) $found[0]);
    if (!$product) {
        return;
    }
    $changed = false;
    if (!$create) {
        // Élesítés után az árat és a készletet a JUTA írja, a szöveget a bolt: újrafuttatáskor csak a változatlan
        // (a telepítő által írt) szöveget frissítjük; árhoz, készlethez, cikkszámhoz csak --force-szal nyúlunk.
        $now = $text($product->get_name(), $product->get_short_description(), $product->get_description());
        $stored = (string) $product->get_meta('slugshop_text_hash');
        if ($now !== $new_text) {
            if ($force || $now === $stored) {
                $product->set_name($d['name']);
                $product->set_short_description($d['short']);
                $product->set_description($d['desc']);
                $changed = true;
            } else {
                $log[] = '⚠ szöveg kihagyva (az adminban szerkesztették): ' . $d['name'];
            }
        }
        if ($force) {
            $create = true; // az alábbi „új termék” mezőket is újraírja
        } else {
            // Csak az adatfájl ÚJ hozzárendeléseit adja hozzá (pl. az ELR kategória); amit az adminban kivettek vagy
            // hozzáadtak, az marad. Az előző futás adat-hozzárendeléseit a slugshop_cats_data meta őrzi.
            $prev = $product->get_meta('slugshop_cats_data');
            $new = $prev === '' ? [] : array_diff($d['cats'], (array) json_decode((string) $prev, true));
            $add = array_values(array_filter(array_map(fn($p) => (int) ($cat_ids[$p] ?? 0), $new)));
            if (array_diff($add, $product->get_category_ids())) {
                $product->set_category_ids(array_values(array_unique(array_merge($product->get_category_ids(), $add))));
                $changed = true;
            }
        }
    }
    if ((string) $product->get_meta('slugshop_cats_data') !== wp_json_encode($d['cats'])) {
        $product->update_meta_data('slugshop_cats_data', wp_json_encode($d['cats']));
        $changed = true;
    }
    // Gyári cikkszám (a névből; a termékoldal és a modellszűrő használja) és az ismétlődő tétel (rejtve, a címe az elsőre visz).
    if ((string) $product->get_meta('slugshop_mpn') !== (string) ($d['mpn'] ?? '')) {
        $product->update_meta_data('slugshop_mpn', (string) ($d['mpn'] ?? ''));
        $changed = true;
    }
    if ((string) $product->get_meta('slugshop_dup_of') !== (string) ($d['dupOf'] ?? '')) {
        $product->update_meta_data('slugshop_dup_of', (string) ($d['dupOf'] ?? ''));
        $product->set_catalog_visibility(!empty($d['dupOf']) ? 'hidden' : 'visible');
        $changed = true;
        if (!empty($d['dupOf'])) {
            $log[] = '⚠ ismétlődő tétel elrejtve (a címe a megmaradóra visz): ' . $d['name'];
        }
    }
    if ($create) {
        $product->set_name($d['name']);
        $product->set_short_description($d['short']);
        $product->set_description($d['desc']);
        $product->set_slug($d['slug']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_menu_order($order);
        $product->set_regular_price($d['price'] !== null ? (string) $d['price'] : '');
        $product->set_manage_stock(false);
        $product->set_stock_status($d['stock'] === 'in' ? 'instock' : 'outofstock');
        $product->set_tax_status('taxable');
        $product->set_category_ids($terms);
        if ($d['sku'] !== '' && $d['sku'] !== $product->get_sku()) {
            try {
                $product->set_sku($d['sku']);
            } catch (WC_Data_Exception $e) {
                $product->update_meta_data('slugshop_old_sku', $d['sku']); // ismétlődő cikkszám a régi boltban
                $log[] = '⚠ ismétlődő cikkszám (' . $d['sku'] . '): ' . $d['name'];
            }
        }
        $product->update_meta_data('slugshop_key', $d['key']);
        $product->update_meta_data('slugshop_old_path', $d['path']);
        if (!empty($d['stockText']) && $d['stockText'] !== 'Raktáron') {
            $product->update_meta_data('slugshop_stock_text', $d['stockText']);
        }
        if (!empty($d['meta'])) {
            $product->update_meta_data('slugshop_meta', $d['meta']);
        }
        $changed = true;
    }
    if (!$changed && $product->get_image_id()) {
        return; // változatlan
    }
    $product->update_meta_data('slugshop_text_hash', $new_text);
    $id = $product->save();
    if ($create || !get_post_meta($id, 'slugshop_primary_cat', true)) {
        update_post_meta($id, 'slugshop_primary_cat', (int) ($cat_ids[$d['primary']] ?? ($terms[0] ?? 0)));
    }
    // Képek (egyszer; ha a médiacsomag még nincs fent, a következő futás pótolja)
    if (!$product->get_image_id() && !empty($d['images'])) {
        $atts = [];
        foreach ($d['images'] as $n => [$rel, $src]) {
            $att = slugshop_attach($rel, $id, $d['name'] . ($n ? ' – ' . ($n + 1) . '. kép' : ''), (string) $src);
            if ($att) {
                $atts[] = $att;
            }
        }
        if ($atts) {
            $product->set_image_id(array_shift($atts));
            $product->set_gallery_image_ids($atts);
            $product->save();
        }
    }
    if ($changed) {
        $log[] = ($found && !$force ? '↻ ' : '+ ') . $d['name'];
    }
}

function slugshop_setup_pages(int $offset, bool $force, array &$log): array
{
    // Letölthető PDF-ek (robbantott ábrák) a médiatárba, a régi címükkel (a legacy.php átirányítja őket).
    $n = 0;
    foreach (slugshop_setup_data('downloads') as $it) {
        $n += slugshop_attach('files/' . $it['file'], 0, $it['title'], $it['src']) ? 1 : 0;
    }
    wp_cache_delete('slugshop_media_files');
    $log[] = '✓ ' . $n . ' letölthető PDF';
    $ids = [];
    foreach (slugshop_setup_data('pages') as $p) {
        $parent = $p['parent'] ? (int) ($ids[$p['parent']] ?? 0) : 0;
        $id = slugshop_upsert_post([
            'post_type' => 'page', 'post_title' => $p['title'], 'post_name' => $p['slug'], 'post_parent' => $parent,
            'post_content' => slugshop_setup_content('pages/' . $p['slug'] . '.html'), 'menu_order' => (int) ($p['order'] ?? 0),
            'post_excerpt' => (string) ($p['excerpt'] ?? ''),
        ], 'page:' . ($p['parent'] ? $p['parent'] . '/' : '') . $p['slug'], $force, $log);
        if (!$id) {
            continue;
        }
        $ids[$p['slug']] = $id;
        if (!empty($p['meta'])) {
            update_post_meta($id, 'slugshop_meta', $p['meta']);
        }
        if (!empty($p['img']) && !has_post_thumbnail($id)) {
            $att = slugshop_attach($p['img'], $id, $p['title']);
            if ($att) {
                set_post_thumbnail($id, $att);
            }
        }
    }
    $wc = ['woocommerce_cart_page_id' => 'kosar', 'woocommerce_checkout_page_id' => 'penztar', 'woocommerce_myaccount_page_id' => 'fiokom',
        'woocommerce_shop_page_id' => 'minden-termek', 'woocommerce_terms_page_id' => 'aszf', 'wp_page_for_privacy_policy' => 'adatvedelmi-nyilatkozat',
        'page_on_front' => 'kezdolap'];
    foreach ($wc as $opt => $slug) {
        if (!empty($ids[$slug])) {
            update_option($opt, $ids[$slug]);
        }
    }
    update_option('show_on_front', 'page');
    $log[] = '✓ ' . count($ids) . ' oldal';
    return ['done' => true];
}

function slugshop_setup_posts(int $offset, bool $force, array &$log): array
{
    $cats = [];
    foreach (['cikkek' => 'Cikkek', 'articles' => 'Articles'] as $slug => $name) {
        $t = get_term_by('slug', $slug, 'category');
        $cats[$slug] = $t ? (int) $t->term_id : (int) (wp_insert_term($name, 'category', ['slug' => $slug])['term_id'] ?? 0);
    }
    $posts = slugshop_setup_data('posts');
    $base = (int) get_option('slugshop_posts_base', 0) ?: time();
    update_option('slugshop_posts_base', $base, false);
    foreach ($posts as $i => $p) {
        // A régi oldal nem írta ki a dátumot: a sorrend marad (a legújabb elöl), az időpont a telepítésé.
        $id = slugshop_upsert_post([
            'post_type' => 'post', 'post_title' => $p['title'], 'post_name' => $p['slug'], 'post_excerpt' => $p['excerpt'],
            'post_content' => slugshop_setup_content('posts/' . $p['slug'] . '.html'), 'post_category' => [$cats[$p['cat']]],
            'post_date' => wp_date('Y-m-d H:i:s', $base - $i * 60),
        ], 'post:' . $p['slug'], $force, $log);
        if ($id && !empty($p['img']) && !has_post_thumbnail($id)) {
            $att = slugshop_attach($p['img'], $id, $p['title']);
            if ($att) {
                set_post_thumbnail($id, $att);
            }
        }
    }
    $log[] = '✓ ' . count($posts) . ' cikk';
    return ['done' => true];
}

function slugshop_setup_menus(int $offset, bool $force, array &$log): array
{
    $locations = get_nav_menu_locations();
    $site = slugshop_setup_data('site');
    $cats = slugshop_cat_paths();
    foreach (['slugshop-primary' => ['Fő menü', 'menu'], 'slugshop-footer' => ['Lábléc', 'footerMenu']] as $loc => [$name, $key]) {
        if (!empty($locations[$loc]) && wp_get_nav_menu_object($locations[$loc])) {
            continue; // már van menü – nem írjuk át
        }
        $menu = wp_get_nav_menu_object($name);
        $menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu($name);
        if (!$menu) {
            foreach ((array) ($site[$key] ?? []) as $pos => [$title, $path]) {
                $parent = slugshop_menu_item($menu_id, $title, $path, 0, $pos, $cats);
                if (isset($cats[$path])) {
                    foreach (slugshop_child_cats($cats[$path]) as $j => $child) {
                        slugshop_menu_item($menu_id, slugshop_cat_label($child), slugshop_term_path($child), $parent, $j, $cats);
                    }
                }
            }
        }
        $locations[$loc] = $menu_id;
        $log[] = '+ menü: ' . $name;
    }
    set_theme_mod('nav_menu_locations', $locations);
    return ['done' => true];
}

function slugshop_menu_item(int $menu, string $title, string $path, int $parent, int $pos, array $cats): int
{
    $args = ['menu-item-title' => $title, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent, 'menu-item-position' => $pos + 1];
    if (isset($cats[$path])) {
        $args += ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $cats[$path]];
    } else {
        $page = get_posts(['post_type' => 'page', 'name' => basename($path), 'numberposts' => 1, 'fields' => 'ids']);
        $args += $page ? ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $page[0]] : ['menu-item-type' => 'custom', 'menu-item-url' => home_url($path)];
    }
    return (int) wp_update_nav_menu_item($menu, 0, $args);
}

function slugshop_setup_finish(int $offset, bool $force, array &$log): array
{
    // Takarítás: a WordPress és a WooCommerce mintatartalma (Hello world!, Sample Page, a WooCommerce „Shop” oldala,
    // a minta adatvédelmi és visszaküldési vázlat) a lomtárba – csak ha érintetlen (sosem szerkesztették) és nem a miénk.
    $shop = (int) get_option('woocommerce_shop_page_id');
    foreach (['hello-world' => 'post', 'sample-page' => 'page', 'privacy-policy' => 'page', 'uzlet' => 'page', 'shop' => 'page', 'elallas_visszakuldes' => 'page', 'refund_returns' => 'page'] as $slug => $type) {
        foreach (get_posts(['post_type' => $type, 'name' => $slug, 'post_status' => ['publish', 'draft'], 'numberposts' => 1]) as $p) {
            if ($p->ID === $shop || get_post_meta($p->ID, 'slugshop_key', true) || $p->post_modified_gmt !== $p->post_date_gmt) {
                continue;
            }
            wp_trash_post($p->ID);
            $log[] = '🗑 mintatartalom a lomtárba: ' . $p->post_title;
        }
    }
    slugshop_flush_cat_paths();
    flush_rewrite_rules(false);
    update_option('slugshop_setup_version', SLUGSHOP_SETUP_VERSION);
    update_option('slugshop_setup_time', time());
    $missing = 0;
    foreach (slugshop_setup_data('products') as $d) {
        if (!empty($d['images']) && !is_readable(slugshop_media_dir() . '/' . $d['images'][0][0])) {
            $missing++;
        }
    }
    $log[] = $missing ? '⚠ ' . $missing . ' termék képe hiányzik: töltsd fel a médiacsomagot, és futtasd újra a telepítőt.' : '✓ minden termékkép a helyén';
    $log[] = '✓ kész';
    return ['done' => true];
}
