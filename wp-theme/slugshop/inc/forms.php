<?php
/**
 * Űrlapok: kapcsolat (képfeltöltéssel), „Értesítést kérek!” (elfogyott termék), kérdés a termékről.
 *
 * - Beküldés JS-sel a REST-en (slugshop/v1/form/<fajta>), JS nélkül az admin-post.php-n át – ugyanaz a feldolgozó.
 * - Védelem: rejtett csapdamező, legalább 3 mp kitöltési idő, IP-nkénti korlát (10 / óra), fájltípus és -méret.
 * - Minden beküldés megmarad az adminban (Beküldések), a levél a bolt címére megy, Reply-To a kitöltőé.
 * - Az „Értesítést kérek” feliratkozás: amikor a termék újra raktárra kerül (kézi mentés vagy JUTA-szinkron), a téma
 *   levelet küld, és a feliratkozást lezárja.
 */

defined('ABSPATH') || exit;

const SLUGSHOP_MSG = 'slugshop_message';

add_action('init', function () {
    register_post_type(SLUGSHOP_MSG, [
        'labels' => ['name' => 'Beküldések', 'singular_name' => 'Beküldés', 'menu_name' => 'Beküldések', 'edit_item' => 'Beküldés', 'search_items' => 'Keresés a beküldésekben', 'not_found' => 'Nincs beküldés.'],
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-email-alt', 'menu_position' => 58,
        'supports' => ['title'], 'capability_type' => 'post', 'capabilities' => ['create_posts' => 'do_not_allow'], 'map_meta_cap' => true,
    ]);
});

/** Rejtett csapdamező és időbélyeg (az űrlapba). */
function slugshop_form_guard(): string
{
    return '<div class="hp" aria-hidden="true"><label>Weboldal<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="ts" value="' . esc_attr((string) time()) . '">';
}

function slugshop_form_ip(): string
{
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Egy beküldés feldolgozása. $p: mezők (unslashed), $files: a $_FILES['files'] tömbje.
 *
 * @return array{ok:bool, message?:string, errors?:array}
 */
function slugshop_handle_form(string $kind, array $p, array $files = []): array
{
    if (!in_array($kind, ['contact', 'notify', 'ask'], true)) {
        return ['ok' => false, 'errors' => ['form' => 'Ismeretlen űrlap.']];
    }
    // Csapda: a robot kitölti a rejtett mezőt, vagy azonnal küld. Ilyenkor „sikert” mondunk, de nem mentünk.
    if (!empty($p['website']) || (time() - (int) ($p['ts'] ?? 0)) < 3) {
        return ['ok' => true, 'message' => 'Köszönjük!'];
    }
    $key = 'slugshop_form_' . md5(slugshop_form_ip());
    $n = (int) get_transient($key);
    if ($n >= (int) apply_filters('slugshop_form_rate_limit', 10)) {
        return ['ok' => false, 'errors' => ['form' => 'Túl sok beküldés. Próbáld újra egy óra múlva, vagy hívj minket.']];
    }

    $name = sanitize_text_field($p['your-name'] ?? '');
    $email = sanitize_email($p['your-email'] ?? '');
    $phone = sanitize_text_field($p['your-phone'] ?? '');
    $message = sanitize_textarea_field($p['your-message'] ?? '');
    $product_id = (int) ($p['product'] ?? 0);
    $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    $errors = [];
    if ($kind === 'contact') {
        if ($name === '') {
            $errors['your-name'] = 'Kötelező mező.';
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 9) {
            $errors['your-phone'] = 'Teljes telefonszámot adj meg.';
        }
        if (!empty($p['your-email']) && !is_email($email)) {
            $errors['your-email'] = 'Érvényes e-mail címet adj meg.';
        }
        if (empty($p['accept'])) {
            $errors['accept'] = 'Az adatvédelmi nyilatkozat elfogadása kötelező.';
        }
    } else {
        if (!is_email($email)) {
            $errors['your-email'] = 'Érvényes e-mail címet adj meg.';
        }
        if (!$product) {
            $errors['form'] = 'A termék nem található.';
        }
        if ($kind === 'ask') {
            if ($name === '') {
                $errors['your-name'] = 'Kötelező mező.';
            }
            if ($message === '') {
                $errors['your-message'] = 'Kötelező mező.';
            }
        }
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    // Fájlok (csak a kapcsolati űrlapon): legfeljebb 5 db, egyenként 10 MB, képek és dokumentumok.
    $saved = [];
    if ($kind === 'contact' && !empty($files['name']) && is_array($files['name'])) {
        $allowed = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $up = wp_upload_dir();
        $dir = $up['basedir'] . '/slugshop-uzenetek/' . gmdate('Y-m');
        wp_mkdir_p($dir);
        if (!file_exists($up['basedir'] . '/slugshop-uzenetek/index.html')) {
            file_put_contents($up['basedir'] . '/slugshop-uzenetek/index.html', '');
        }
        foreach ($files['name'] as $i => $orig) {
            if (count($saved) >= 5 || (int) ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK || (int) $files['size'][$i] > 10 * MB_IN_BYTES) {
                continue;
            }
            $check = wp_check_filetype_and_ext($files['tmp_name'][$i], (string) $orig, $allowed);
            if (!$check['ext']) {
                continue;
            }
            $target = $dir . '/' . wp_generate_password(12, false) . '-' . sanitize_file_name((string) $orig);
            if (is_uploaded_file($files['tmp_name'][$i]) ? move_uploaded_file($files['tmp_name'][$i], $target) : @rename($files['tmp_name'][$i], $target)) {
                $saved[] = $target;
            }
        }
    }

    $titles = ['contact' => 'Üzenet', 'notify' => 'Értesítést kér', 'ask' => 'Kérdés a termékről'];
    $title = $titles[$kind] . ': ' . ($name !== '' ? $name : $email) . ($product ? ' – ' . $product->get_name() : '');
    $id = wp_insert_post(['post_type' => SLUGSHOP_MSG, 'post_status' => 'publish', 'post_title' => $title], true);
    if (is_wp_error($id)) {
        return ['ok' => false, 'errors' => ['form' => 'A mentés nem sikerült. Kérjük, hívj minket.']];
    }
    $fields = array_filter(['Név' => $name, 'Telefon' => $phone, 'E-mail' => $email, 'Üzenet' => $message, 'Termék' => $product ? $product->get_name() . ' (' . $product->get_sku() . ')' : '']);
    update_post_meta($id, 'kind', $kind);
    update_post_meta($id, 'fields', $fields);
    update_post_meta($id, 'files', $saved);
    update_post_meta($id, 'ip', slugshop_form_ip());
    if ($product) {
        update_post_meta($id, 'product', $product->get_id());
    }
    if ($kind === 'notify') {
        update_post_meta($id, 'notify_status', 'waiting');
        update_post_meta($id, 'notify_email', $email);
    }
    set_transient($key, $n + 1, HOUR_IN_SECONDS);

    // Levél a boltnak (az értesítés-kérésről nem – az az adminban és a termék visszatérésekor számít).
    if ($kind !== 'notify') {
        $body = '';
        foreach ($fields as $label => $value) {
            $body .= $label . ': ' . $value . "\n";
        }
        if ($product) {
            $body .= "\n" . $product->get_permalink() . "\n";
        }
        $body .= "\nBeküldés az adminban: " . admin_url('post.php?post=' . $id . '&action=edit') . "\n";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if ($email) {
            $headers[] = 'Reply-To: ' . ($name !== '' ? str_replace(["\r", "\n", '"'], '', $name) . ' <' . $email . '>' : $email);
        }
        wp_mail(slugshop_form_recipient(), '[Slugshop] ' . $title, $body, $headers, $saved);
    }

    $ok = ['contact' => 'Köszönjük, az üzenetedet megkaptuk. Hamarosan válaszolunk.',
        'notify' => 'Rendben, e-mailben szólunk, amint a termék újra raktárra kerül.',
        'ask' => 'Köszönjük a kérdést, hamarosan válaszolunk e-mailben.'];
    return ['ok' => true, 'message' => $ok[$kind]];
}

function slugshop_form_recipient(): string
{
    $to = (string) slugshop_site('forms.to', '');
    return is_email($to) ? $to : (string) (slugshop_site('email') ?: get_option('admin_email'));
}

add_action('rest_api_init', function () {
    register_rest_route('slugshop/v1', '/form/(?P<kind>contact|notify|ask)', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            $files = $r->get_file_params();
            $res = slugshop_handle_form((string) $r['kind'], (array) $r->get_body_params(), (array) ($files['files'] ?? []));
            return new WP_REST_Response($res, $res['ok'] ? 200 : 422);
        },
    ]);
});

/** JS nélküli beküldés (csak a kapcsolati űrlap): vissza az oldalra ?uzenet=ok / hiba. */
foreach (['admin_post_nopriv_slugshop_contact', 'admin_post_slugshop_contact'] as $hook) {
    add_action($hook, function () {
        $res = slugshop_handle_form('contact', wp_unslash($_POST), $_FILES['files'] ?? []);
        $back = wp_get_referer() ?: home_url('/kapcsolat');
        wp_safe_redirect(add_query_arg('uzenet', $res['ok'] ? 'ok' : 'hiba', $back) . '#kapcsolat-urlap');
        exit;
    });
}

/** Admin: a beküldés adatai, mellékletek letöltéssel. */
add_action('add_meta_boxes_' . SLUGSHOP_MSG, function () {
    add_meta_box('slugshop-msg', 'Adatok', function (WP_Post $post) {
        echo '<table class="widefat striped"><tbody>';
        foreach ((array) get_post_meta($post->ID, 'fields', true) as $label => $value) {
            echo '<tr><th style="width:140px">' . esc_html($label) . '</th><td>' . nl2br(esc_html((string) $value)) . '</td></tr>';
        }
        $status = get_post_meta($post->ID, 'notify_status', true);
        if ($status) {
            echo '<tr><th>Értesítés</th><td>' . esc_html($status === 'sent' ? 'elküldve' : 'várakozik (a termék visszatérésekor megy)') . '</td></tr>';
        }
        echo '<tr><th>Beküldve</th><td>' . esc_html(get_the_date('Y-m-d H:i', $post)) . ' · IP: ' . esc_html((string) get_post_meta($post->ID, 'ip', true)) . '</td></tr>';
        foreach ((array) get_post_meta($post->ID, 'files', true) as $i => $file) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=slugshop_file&msg=' . $post->ID . '&i=' . $i), 'slugshop_file');
            echo '<tr><th>Fájl ' . ($i + 1) . '</th><td><a href="' . esc_url($url) . '">' . esc_html(preg_replace('/^[A-Za-z0-9]{12}-/', '', basename((string) $file))) . '</a></td></tr>';
        }
        echo '</tbody></table>';
    }, SLUGSHOP_MSG, 'normal', 'high');
});

add_action('admin_post_slugshop_file', function () {
    check_admin_referer('slugshop_file');
    $id = (int) ($_GET['msg'] ?? 0);
    if (!current_user_can('edit_post', $id)) {
        wp_die('Nincs jogosultság.');
    }
    $files = (array) get_post_meta($id, 'files', true);
    $file = (string) ($files[(int) ($_GET['i'] ?? -1)] ?? '');
    if ($file === '' || !is_readable($file)) {
        wp_die('A fájl nem található.');
    }
    nocache_headers();
    header('Content-Type: ' . (wp_check_filetype($file)['type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . rawurlencode(preg_replace('/^[A-Za-z0-9]{12}-/', '', basename($file))) . '"');
    readfile($file);
    exit;
});

add_filter('manage_' . SLUGSHOP_MSG . '_posts_columns', fn($c) => ['cb' => $c['cb'], 'title' => 'Beküldés', 'kind' => 'Fajta', 'date' => 'Dátum']);
add_action('manage_' . SLUGSHOP_MSG . '_posts_custom_column', function ($col, $id) {
    if ($col === 'kind') {
        $k = (string) get_post_meta($id, 'kind', true);
        echo esc_html(['contact' => 'Kapcsolat', 'notify' => 'Értesítést kér', 'ask' => 'Kérdés'][$k] ?? $k);
        if ($k === 'notify') {
            echo ' – ' . esc_html(get_post_meta($id, 'notify_status', true) === 'sent' ? 'elküldve' : 'várakozik');
        }
    }
}, 10, 2);

/**
 * „Értesítést kérek”: a termék újra raktáron → levél minden várakozónak. Kézi mentésnél a WooCommerce eseménye,
 * JUTA-szinkronnál a juta.php figyelője hívja (slugshop_notify_back_in_stock).
 */
function slugshop_notify_back_in_stock(int $product_id): int
{
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_in_stock()) {
        return 0;
    }
    $waiting = get_posts(['post_type' => SLUGSHOP_MSG, 'numberposts' => 200, 'fields' => 'ids',
        'meta_query' => [['key' => 'product', 'value' => $product_id], ['key' => 'notify_status', 'value' => 'waiting']]]);
    $n = 0;
    foreach ($waiting as $id) {
        $email = (string) get_post_meta($id, 'notify_email', true);
        if (!is_email($email)) {
            continue;
        }
        $body = "Kedves Vásárlónk!\n\nA(z) „" . $product->get_name() . "” újra raktáron van a Slugshopban:\n" . $product->get_permalink()
            . "\n\nA termék a raktárkészlet erejéig rendelhető.\n\nÜdvözlettel:\nSlugshop\n" . slugshop_site('phone') . ' · ' . slugshop_site('email') . "\n";
        if (wp_mail($email, 'Újra raktáron: ' . $product->get_name(), $body, ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . slugshop_form_recipient()])) {
            update_post_meta($id, 'notify_status', 'sent');
            update_post_meta($id, 'notify_sent', time());
            $n++;
        }
    }
    return $n;
}

add_action('woocommerce_product_set_stock_status', function ($id, $status) {
    if ($status === 'instock') {
        slugshop_notify_back_in_stock((int) $id);
    }
}, 10, 2);

/**
 * Tesztmód (Adatok fül, „test.mailTo”): a tesztoldalon minden levél csak a megadott címre megy, a tárgyban az eredeti
 * címzettel – így a próbarendelés, űrlap, „Értesítést kérek” nem jut el vásárlóhoz. Élesítéskor üresen kell hagyni.
 */
add_filter('wp_mail', function (array $args): array {
    $to = (string) slugshop_site('test.mailTo', '');
    if (!is_email($to)) {
        return $args;
    }
    $orig = implode(', ', array_map('trim', is_array($args['to']) ? $args['to'] : explode(',', (string) $args['to'])));
    $args['subject'] = '[TESZT → ' . $orig . '] ' . $args['subject'];
    $args['to'] = $to;
    $headers = is_array($args['headers'] ?? null) ? $args['headers'] : preg_split('/\r?\n/', (string) ($args['headers'] ?? ''));
    $args['headers'] = array_values(array_filter($headers, fn($h) => $h !== '' && !preg_match('/^\s*(cc|bcc)\s*:/i', (string) $h)));
    return $args;
}, 999);
