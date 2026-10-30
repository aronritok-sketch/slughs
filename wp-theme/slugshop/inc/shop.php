<?php
/**
 * WooCommerce: klasszikus (shortcode-os) kosár, pénztár és fiók; a kinézet a téma CSS-éből.
 *
 * - A WooCommerce saját CSS-e csak a kosár / pénztár / fiók oldalon töltődik (ott a szerkezetét használjuk).
 * - Kosárba tétel AJAX-szal a kártyákról és a termékoldalról (JS nélkül is működik: ?add-to-cart= / POST).
 * - Pénztár: magyar sorrend (vezetéknév elöl), kötelező telefon, nem kötelező adószám – a Számlázz.hu bővítmény
 *   kulcsába is beírva (DONTESEK 2026-10-08, Mandala: különben a számlán nincs adószám).
 */

defined('ABSPATH') || exit;

add_filter('woocommerce_enqueue_styles', function ($styles) {
    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
        unset($styles['woocommerce-smallscreen']);
        return $styles;
    }
    return [];
});

// A WooCommerce blokkjainak stílusai nem kellenek (klasszikus oldalak).
add_action('wp_enqueue_scripts', function () {
    foreach (['wc-blocks-style', 'wc-blocks-vendors-style', 'wc-block-style'] as $h) {
        wp_dequeue_style($h);
    }
}, 100);

// A WooCommerce saját morzsamenüje és oldalsávja nem kell (a téma rajzolja).
add_action('init', function () {
    remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
});

/** A fejléc kosár-számlálója a WooCommerce AJAX-válaszában is (fragments). */
add_filter('woocommerce_add_to_cart_fragments', function (array $fragments): array {
    $n = slugshop_cart_count();
    $fragments['span.cart-count'] = '<span class="cart-count" data-cart-count data-n="' . $n . '">' . $n . '</span>';
    return $fragments;
});

/** Pénztár: magyar sorrend, kötelező telefon, adószám. */
add_filter('woocommerce_billing_fields', function (array $f): array {
    if (isset($f['billing_last_name'], $f['billing_first_name'])) {
        $f['billing_last_name']['priority'] = 10;
        $f['billing_last_name']['class'] = ['form-row-first'];
        $f['billing_first_name']['priority'] = 20;
        $f['billing_first_name']['class'] = ['form-row-last'];
    }
    if (isset($f['billing_phone'])) {
        $f['billing_phone']['required'] = true;
        $f['billing_phone']['priority'] = 25;
        $f['billing_phone']['placeholder'] = '+36 30 123 4567';
    }
    if (isset($f['billing_email'])) {
        $f['billing_email']['priority'] = 26;
    }
    $f['billing_tax_number'] = [
        'label' => 'Adószám', 'description' => 'Csak cégnek, ha a számlára kell.', 'required' => false, 'class' => ['form-row-wide'], 'priority' => 31,
        'placeholder' => '12345678-1-12', 'autocomplete' => 'off',
    ];
    return $f;
});
add_filter('woocommerce_shipping_fields', function (array $f): array {
    if (isset($f['shipping_last_name'], $f['shipping_first_name'])) {
        $f['shipping_last_name']['priority'] = 10;
        $f['shipping_last_name']['class'] = ['form-row-first'];
        $f['shipping_first_name']['priority'] = 20;
        $f['shipping_first_name']['class'] = ['form-row-last'];
    }
    return $f;
});
/** Magyarországon a megye nem kell a címhez (a WooCommerce listát adna hozzá). */
add_filter('woocommerce_get_country_locale', function (array $locale): array {
    $locale['HU']['state'] = ['required' => false, 'hidden' => true];
    return $locale;
});

/**
 * A WooCommerce 11 a telefonmezőt a saját beállítása (woocommerce_checkout_phone_field) szerint a végén újra
 * felveszi: itt, a pénztár mezőinek végső listáján tesszük kötelezővé és a név mögé.
 */
add_filter('woocommerce_checkout_fields', function (array $f): array {
    if (isset($f['billing']['billing_phone'])) {
        $f['billing']['billing_phone']['required'] = true;
        $f['billing']['billing_phone']['priority'] = 25;
        $f['billing']['billing_phone']['class'] = ['form-row-wide'];
    }
    if (isset($f['billing']['billing_email'])) {
        $f['billing']['billing_email']['priority'] = 26;
        $f['billing']['billing_email']['class'] = ['form-row-wide'];
    }
    uasort($f['billing'], fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));
    return $f;
}, 20);
add_filter('pre_option_woocommerce_checkout_phone_field', fn() => 'required');

/** A kosár / pénztár szállítási sora magyarul (a WooCommerce 11 „Shipment” felirata helyett). */
add_filter('woocommerce_shipping_package_name', fn($name, $i) => 'Szállítás', 10, 2);

add_action('woocommerce_after_checkout_validation', function ($data, WP_Error $errors) {
    $tax = trim((string) ($data['billing_tax_number'] ?? ''));
    if ($tax !== '' && !preg_match('/^\d{8}-?\d-?\d{2}$/', $tax)) {
        $errors->add('billing_tax_number', 'Az adószám formája: 12345678-1-12.');
    }
}, 10, 2);

add_action('woocommerce_checkout_create_order', function (WC_Order $order, array $data) {
    $tax = trim((string) ($data['billing_tax_number'] ?? ''));
    if ($tax === '') {
        return;
    }
    $order->update_meta_data('_billing_tax_number', $tax);
    $order->update_meta_data('_billing_wc_szamlazz_adoszam', $tax); // a Számlázz.hu bővítmény ezt olvassa
    $_POST['wc_szamlazz_adoszam'] = $tax; // a bővítmény saját mentése / NAV-ellenőrzése is lássa
    if ($order->get_customer_id()) {
        update_user_meta($order->get_customer_id(), 'wc_szamlazz_adoszam', $tax);
    }
}, 10, 2);

add_action('woocommerce_admin_order_data_after_billing_address', function (WC_Order $order) {
    $tax = (string) $order->get_meta('_billing_tax_number');
    if ($tax !== '') {
        echo '<p><strong>Adószám:</strong> ' . esc_html($tax) . '</p>';
    }
});

/** Az üres kosárból vissza a nyitóoldalra (a bolt-oldal a kategóriák listája). */
add_filter('woocommerce_return_to_shop_redirect', fn() => home_url('/'));
add_filter('woocommerce_return_to_shop_text', fn() => 'Vissza a boltba');

/** A fiók oldalon JS nélkül is a regisztráció mutatkozzon, ha onnan jönnek (?regisztracio=1). */
add_filter('body_class', function (array $c): array {
    if (!empty($_GET['regisztracio']) && function_exists('is_account_page') && is_account_page()) {
        $c[] = 'show-register';
    }
    return $c;
});

/** A termék „Kosárba” gombja után ne a WooCommerce saját „Kosár megtekintése” linkje jelenjen meg (a JS üzenetet ad). */
add_filter('woocommerce_product_add_to_cart_text', fn($t, $p) => $p && $p->is_in_stock() ? 'Kosárba' : 'Értesítést kérek!', 10, 2);

/**
 * Személyes átvétel: a pénztárban a cím és a nyitvatartás a szállítási mód alatt (a régi oldal szövegével).
 */
add_action('woocommerce_after_shipping_rate', function ($method) {
    if ($method->get_method_id() === 'local_pickup') {
        $c = (array) slugshop_site('contact', []);
        $hours = implode(' · ', array_map(fn($h) => implode(': ', (array) $h), (array) ($c['hours'] ?? [])));
        echo '<small class="method-note">' . esc_html(trim(($c['address'] ?? '') . ' · ' . $hours, ' ·')) . '</small>';
    }
});

/**
 * A WooCommerce a termék- és kategóriaoldalakhoz a saját sablonját töltené (archive-product.php, single-product.php),
 * így elmaradna az iu_theme fejléce, lábléce és a téma sablonja. Az iu_woocommerce mu-plugin nélkül ezt itt kell
 * visszairányítani a keretrendszer index.php-jára (iu-theme skill: márka-archívum buktató).
 */
add_filter('template_include', function ($template) {
    if (function_exists('is_woocommerce') && (is_woocommerce() || is_product_taxonomy())) {
        return get_template_directory() . '/index.php';
    }
    return $template;
}, 100);

/**
 * Csak az üzletben: a légfegyvert személyi igazolvánnyal, személyes átvétellel és helyszíni fizetéssel lehet megvenni
 * (az ügyfél szabálya). Ha a kosárban ilyen termék van, csak a személyes átvétel és a megadott fizetési mód(ok)
 * választhatók. Kategóriák és fizetési módok: Megjelenés → Slugshop → Adatok (alap: fx-fegyverek, ill. cod).
 */
function slugshop_shop_only_term_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', (string) slugshop_site('shopOnly.categories', 'fx-fegyverek')))) as $slug) {
            $t = get_term_by('slug', sanitize_title($slug), 'product_cat');
            if ($t) {
                $ids[] = (int) $t->term_id;
                $ids = array_merge($ids, array_map('intval', (array) get_term_children($t->term_id, 'product_cat')));
            }
        }
        $ids = array_values(array_unique($ids));
    }
    return $ids;
}

function slugshop_is_shop_only(int $product_id): bool
{
    $ids = slugshop_shop_only_term_ids();
    if (!$ids || !$product_id) {
        return false;
    }
    $parent = (int) wp_get_post_parent_id($product_id);
    $terms = wp_get_post_terms($parent ?: $product_id, 'product_cat', ['fields' => 'ids']);
    return is_array($terms) && (bool) array_intersect($ids, array_map('intval', $terms));
}

function slugshop_cart_has_shop_only(): bool
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_cart() as $item) {
        if (slugshop_is_shop_only((int) ($item['product_id'] ?? 0))) {
            return true;
        }
    }
    return false;
}

function slugshop_shop_only_notice(): string
{
    return 'Légfegyvert csak az üzletünkben, személyes átvétellel és helyszíni fizetéssel vásárolhatsz (személyi igazolvány szükséges). Jászberény, Megyeház u. 3.';
}

add_filter('woocommerce_package_rates', function ($rates) {
    if (!slugshop_cart_has_shop_only()) {
        return $rates;
    }
    $pickup = array_filter($rates, fn($r) => in_array($r->get_method_id(), ['local_pickup', 'pickup_location'], true));
    return $pickup ?: $rates; // ha nincs személyes átvétel beállítva, ne maradjon üresen a pénztár
}, 100);

add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) {
        return $gateways;
    }
    if (!is_array($gateways) || !slugshop_cart_has_shop_only()) {
        return $gateways;
    }
    $allowed = array_filter(array_map('trim', explode(',', (string) slugshop_site('shopOnly.gateways', 'cod'))));
    return array_intersect_key($gateways, array_flip($allowed));
}, 100);

foreach (['woocommerce_before_cart', 'woocommerce_before_checkout_form'] as $hook) {
    add_action($hook, function () {
        if (slugshop_cart_has_shop_only() && function_exists('wc_print_notice')) {
            wc_print_notice(slugshop_shop_only_notice(), 'notice');
        }
    }, 5);
}
