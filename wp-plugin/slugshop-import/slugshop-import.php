<?php
/**
 * Plugin Name: Slugshop – régi bolt átvétele
 * Description: A régi Joomla + VirtueMart oldalról áthozza a vásárlókat, a rendeléseket és a hiányzó termékeket. Eszközök → Régi bolt átvétele.
 * Version: 1.0.0
 * Author: HelloProVision
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Text Domain: slugshop-import
 *
 * Hogyan: a bővítmény oldaláról letöltött adatátadó fájlt (saját tokennel) a régi oldal gyökerébe kell tölteni, utána
 * innen kötegenként húzzuk át az adatokat. Semmit nem ír felül: ami már megvan (régi azonosító, e-mail, cikkszám vagy
 * cím szerint), azt csak kiegészíti az üres mezőiben. Levelet nem küld, készletet nem von le, a JUTA-nak nem küldi be a
 * régi rendeléseket (_slugshop_imported + _elad_exported_file = regi-bolt).
 */

defined('ABSPATH') || exit;

const SLUGIMP_OPT = 'slugshop_import';
const SLUGIMP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
const SLUGIMP_STEPS = ['products' => 'Termékek (hiányzók, üres mezők)', 'customers' => 'Vásárlók', 'orders' => 'Rendelések'];
const SLUGIMP_BATCH = ['products' => 40, 'customers' => 100, 'orders' => 40];

function slugimp_opt(): array
{
    $o = (array) get_option(SLUGIMP_OPT, []);
    if (empty($o['token'])) {
        $o['token'] = bin2hex(random_bytes(24));
        $o['file'] = 'slugshop-export-' . bin2hex(random_bytes(4)) . '.php';
        update_option(SLUGIMP_OPT, $o, false);
    }
    return $o + ['url' => '', 'cursor' => [], 'stats' => []];
}

function slugimp_save(array $o): void
{
    update_option(SLUGIMP_OPT, $o, false);
}

// ------------------------------------------------------------------ régi rendelésszám a WooCommerce-ben

add_filter('woocommerce_order_number', function ($number, $order) {
    $old = $order instanceof WC_Order ? (string) $order->get_meta('_slugshop_old_number') : '';
    return $old !== '' ? $old : $number;
}, 10, 2);
add_filter('woocommerce_shop_order_search_fields', fn($f) => array_merge((array) $f, ['_slugshop_old_number']));
add_filter('woocommerce_order_table_search_query_meta_keys', fn($k) => array_merge((array) $k, ['_slugshop_old_number']));

// ------------------------------------------------------------------ admin

add_action('admin_menu', function () {
    add_management_page('Régi bolt átvétele', 'Régi bolt átvétele', 'manage_woocommerce', 'slugshop-import', 'slugimp_page');
});

/** Az adatátadó fájl letöltése a saját tokennel. */
add_action('admin_post_slugimp_agent', function () {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('slugimp_agent')) {
        wp_die('Nincs jogosultság.');
    }
    $o = slugimp_opt();
    $php = str_replace('__TOKEN__', $o['token'], (string) file_get_contents(__DIR__ . '/agent-template.php'));
    nocache_headers();
    header('Content-Type: application/x-php');
    header('Content-Disposition: attachment; filename="' . $o['file'] . '"');
    echo $php;
    exit;
});

add_action('admin_post_slugimp_settings', function () {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('slugimp_settings')) {
        wp_die('Nincs jogosultság.');
    }
    $o = slugimp_opt();
    if (isset($_POST['url'])) {
        $o['url'] = esc_url_raw(trim(wp_unslash($_POST['url'])));
    }
    if (!empty($_POST['reset'])) {
        $step = sanitize_key(wp_unslash($_POST['reset']));
        unset($o['cursor'][$step], $o['stats'][$step]);
    }
    if (!empty($_POST['newtoken'])) {
        unset($o['token']);
        slugimp_save($o);
        $o = slugimp_opt();
    }
    slugimp_save($o);
    wp_safe_redirect(admin_url('tools.php?page=slugshop-import'));
    exit;
});

function slugimp_page(): void
{
    $o = slugimp_opt();
    $base = $o['url'] ? preg_replace('#/[^/]*\.php$#', '', $o['url']) : 'https://slugshop.hu';
    echo '<div class="wrap"><h1>Régi bolt átvétele (Joomla + VirtueMart → WooCommerce)</h1>';
    echo '<ol style="max-width:860px">'
        . '<li><b>Mentés</b> erről az oldalról (adatbázis + uploads), mielőtt elindítod.</li>'
        . '<li><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=slugimp_agent'), 'slugimp_agent')) . '">Adatátadó fájl letöltése</a> '
        . '→ töltsd fel a régi oldal gyökerébe (ahol a <code>configuration.php</code> van), FTP-n vagy a tárhely fájlkezelőjével. A neve: <code>' . esc_html($o['file']) . '</code>.</li>'
        . '<li>Add meg a címét, mentsd, és nyomd meg a <b>Kapcsolat</b> gombot.</li>'
        . '<li>Előbb <b>Próba</b> (nem ír semmit, az első köteget mutatja), utána <b>Indítás</b>. Sorrend: termékek → vásárlók → rendelések (vagy „Minden”). Megszakítás után onnan folytatja.</li>'
        . '<li>A végén <b>töröld a fájlt a régi oldalról</b> (személyes adatokat ad ki). Élesítés előtt újra futtatva csak az újakat hozza.</li></ol>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:16px 0">';
    wp_nonce_field('slugimp_settings');
    echo '<input type="hidden" name="action" value="slugimp_settings"><label><b>Az adatátadó fájl címe</b><br><input type="url" name="url" class="large-text" placeholder="' . esc_attr($base . '/' . $o['file']) . '" value="' . esc_attr($o['url']) . '"></label> '
        . '<p><button class="button button-primary">Mentés</button> <button class="button" name="newtoken" value="1" onclick="return confirm(\'Új token: a régi fájl nem működik tovább, újat kell feltölteni. Mehet?\')">Új token</button></p></form>';

    echo '<p><button class="button" data-slugimp="info">Kapcsolat</button></p><div id="slugimp-info"></div>';
    echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>Lépés</th><th>Állás</th><th></th></tr></thead><tbody>';
    foreach (SLUGIMP_STEPS as $step => $label) {
        $st = $o['stats'][$step] ?? [];
        $state = $st ? sprintf('új: %d · meglévő: %d · kihagyva: %d · hiba: %d%s', $st['new'] ?? 0, $st['found'] ?? 0, $st['skip'] ?? 0, $st['error'] ?? 0, !empty($st['done']) ? ' · <b>kész</b>' : ' · utolsó régi azonosító: ' . (int) ($o['cursor'][$step] ?? 0)) : '–';
        echo '<tr><td><b>' . esc_html($label) . '</b></td><td>' . wp_kses_post($state) . '</td><td style="white-space:nowrap">'
            . '<button class="button" data-slugimp="' . esc_attr($step) . '" data-dry="1">Próba</button> '
            . '<button class="button button-primary" data-slugimp="' . esc_attr($step) . '">Indítás</button> '
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">' . wp_nonce_field('slugimp_settings', '_wpnonce', true, false)
            . '<input type="hidden" name="action" value="slugimp_settings"><button class="button-link" name="reset" value="' . esc_attr($step) . '" onclick="return confirm(\'Elölről? (A már áthozottakat nem duplikálja.)\')">elölről</button></form></td></tr>';
    }
    echo '</tbody></table><p><button class="button button-primary" data-slugimp="all">Minden (termékek → vásárlók → rendelések)</button></p>';
    echo '<textarea id="slugimp-log" readonly rows="18" class="large-text code" style="max-width:860px"></textarea></div>';
    $js = <<<'JS'
(function () {
  const log = document.getElementById('slugimp-log');
  const say = (t) => { log.value += t + '\n'; log.scrollTop = log.scrollHeight; };
  let busy = false;
  async function call(step, dry) {
    const body = new URLSearchParams({ action: 'slugimp_run', step, dry: dry ? '1' : '', _ajax_nonce: SLUGIMP.nonce });
    const r = await fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
    const t = await r.text();
    try { return JSON.parse(t); } catch (e) { return { success: false, data: { message: 'Hibás válasz (' + r.status + '): ' + t.slice(0, 300) } }; }
  }
  async function run(step, dry) {
    for (let guard = 0; guard < 2000; guard++) {
      const res = await call(step, dry);
      if (!res.success) { say('✗ ' + (res.data && res.data.message || 'hiba')); return false; }
      (res.data.log || []).forEach(say);
      if (step === 'info') { document.getElementById('slugimp-info').innerHTML = res.data.html || ''; return true; }
      if (dry || res.data.done) { say(dry ? '— próba vége (nem írt semmit)' : '✓ ' + step + ' kész'); return true; }
    }
    return false;
  }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-slugimp]'); if (!b || busy) return;
    e.preventDefault(); busy = true; document.querySelectorAll('[data-slugimp]').forEach((x) => x.disabled = true);
    const step = b.dataset.slugimp, dry = !!b.dataset.dry;
    try {
      if (step === 'all') { for (const s of ['products', 'customers', 'orders']) { say('== ' + s); if (!(await run(s, false))) break; } }
      else { say('== ' + step + (dry ? ' (próba)' : '')); await run(step, dry); }
    } finally { busy = false; document.querySelectorAll('[data-slugimp]').forEach((x) => x.disabled = false); }
    if (!dry && step !== 'info') say('(az állás a lap újratöltése után frissül)');
  });
})();
JS;
    echo '<script>window.SLUGIMP=' . wp_json_encode(['nonce' => wp_create_nonce('slugimp_run')]) . ';' . $js . '</script>';
}

// ------------------------------------------------------------------ kapcsolat

function slugimp_fetch(string $action, array $args = [])
{
    $o = slugimp_opt();
    if (!$o['url']) {
        return new WP_Error('slugimp', 'Add meg az adatátadó fájl címét.');
    }
    $url = add_query_arg(['t' => $o['token'], 'a' => $action] + $args, $o['url']);
    $r = wp_remote_get($url, ['timeout' => 90, 'user-agent' => SLUGIMP_UA, 'headers' => ['Accept' => 'application/json']]);
    if (is_wp_error($r)) {
        return $r;
    }
    $code = (int) wp_remote_retrieve_response_code($r);
    $data = json_decode((string) wp_remote_retrieve_body($r), true);
    if ($code !== 200 || !is_array($data)) {
        $msg = is_array($data) && !empty($data['error']) ? $data['error'] : 'HTTP ' . $code . ': ' . mb_substr(wp_strip_all_tags((string) wp_remote_retrieve_body($r)), 0, 200);
        return new WP_Error('slugimp', $code === 403 ? 'A régi oldal elutasította (rossz token, vagy tűzfal). ' . $msg : $msg);
    }
    return $data;
}

/** A régi oldal kódtáblái (országok, állapotok, fizetési és szállítási módok, ÁFA) – egy kötegnyi ideig. */
function slugimp_info()
{
    $info = get_transient('slugimp_info');
    if (!$info) {
        $info = slugimp_fetch('info');
        if (is_wp_error($info)) {
            return $info;
        }
        set_transient('slugimp_info', $info, HOUR_IN_SECONDS);
    }
    return $info;
}

add_action('wp_ajax_slugimp_run', function () {
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('slugimp_run', false, false)) {
        wp_send_json_error(['message' => 'Nincs jogosultság (lépj be újra).']);
    }
    if (!function_exists('WC')) {
        wp_send_json_error(['message' => 'A WooCommerce nincs bekapcsolva.']);
    }
    @set_time_limit(300);
    wp_raise_memory_limit('admin');
    // Az átvétel alatt semmilyen levél nem megy ki (rendelés-visszaigazolás, fiók, admin-értesítés).
    add_filter('pre_wp_mail', '__return_false', 9999);
    $step = sanitize_key(wp_unslash($_POST['step'] ?? ''));
    $dry = !empty($_POST['dry']);
    if ($step === 'info') {
        delete_transient('slugimp_info');
        $info = slugimp_info();
        if (is_wp_error($info)) {
            wp_send_json_error(['message' => $info->get_error_message()]);
        }
        $c = (array) ($info['counts'] ?? []);
        wp_send_json_success([
            'log' => ['✓ Kapcsolat rendben: VirtueMart ' . ($info['virtuemart'] ?? '?') . ', nyelv ' . ($info['lang'] ?? '?') . ', PHP ' . ($info['php'] ?? '?')],
            'html' => '<p>A régi oldalon: <b>' . (int) ($c['products'] ?? 0) . '</b> termék, <b>' . (int) ($c['categories'] ?? 0) . '</b> kategória, <b>'
                . (int) ($c['customers'] ?? 0) . '</b> felhasználó, <b>' . (int) ($c['orders'] ?? 0) . '</b> rendelés.</p>',
        ]);
    }
    if (!isset(SLUGIMP_STEPS[$step])) {
        wp_send_json_error(['message' => 'Ismeretlen lépés.']);
    }
    $info = slugimp_info();
    if (is_wp_error($info)) {
        wp_send_json_error(['message' => $info->get_error_message()]);
    }
    $o = slugimp_opt();
    $after = (int) ($o['cursor'][$step] ?? 0);
    $data = slugimp_fetch($step, ['after' => $after, 'limit' => SLUGIMP_BATCH[$step]]);
    if (is_wp_error($data)) {
        wp_send_json_error(['message' => $data->get_error_message()]);
    }
    $items = (array) ($data['items'] ?? []);
    $log = [];
    $stats = ['new' => 0, 'found' => 0, 'skip' => 0, 'error' => 0];
    foreach ($items as $it) {
        try {
            [$kind, $line] = call_user_func('slugimp_' . $step, $it, $info, $dry);
        } catch (Throwable $e) {
            [$kind, $line] = ['error', '✗ ' . $e->getMessage()];
        }
        $stats[$kind]++;
        if ($line !== '') {
            $log[] = $line;
        }
    }
    $done = !$items || count($items) < SLUGIMP_BATCH[$step];
    if (!$dry) {
        $o = slugimp_opt(); // közben más kérés is írhatta
        if ($items) {
            $o['cursor'][$step] = (int) $data['next'];
        }
        foreach ($stats as $k => $n) {
            $o['stats'][$step][$k] = ($o['stats'][$step][$k] ?? 0) + $n;
        }
        $o['stats'][$step]['done'] = $done;
        slugimp_save($o);
        if ($step === 'orders' && $stats['new']) {
            do_action('slugshop_orders_imported'); // a téma JUTA-része „regi-bolt” jelölőt tesz rájuk (biztonsági háló)
        }
    }
    $log[] = sprintf('köteg: %d tétel – új: %d, meglévő: %d, kihagyva: %d, hiba: %d', count($items), $stats['new'], $stats['found'], $stats['skip'], $stats['error']);
    wp_send_json_success(['log' => $log, 'done' => $done]);
});

// ------------------------------------------------------------------ közös

function slugimp_val(array $row, string $key): string
{
    return trim((string) ($row[$key] ?? ''));
}

/** Régi azonosító → WordPress-objektum (felhasználó, termék, rendelés) a _slugshop_old_id meta szerint. */
function slugimp_find_old(string $type, int $old): int
{
    global $wpdb;
    if ($type === 'user') {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_slugshop_old_id' AND meta_value = %s LIMIT 1", (string) $old));
    }
    if ($type === 'order') {
        if (slugimp_hpos()) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_slugshop_old_id' AND meta_value = %s LIMIT 1", (string) $old));
        }
        return (int) $wpdb->get_var($wpdb->prepare("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'shop_order' WHERE pm.meta_key = '_slugshop_old_id' AND pm.meta_value = %s LIMIT 1", (string) $old));
    }
    return (int) $wpdb->get_var($wpdb->prepare("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product' WHERE pm.meta_key = '_slugshop_old_id' AND pm.meta_value = %s LIMIT 1", (string) $old));
}

function slugimp_hpos(): bool
{
    return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/** Cím a VirtueMart-sorból WooCommerce-mezőkbe (billing_ / shipping_ előtag nélkül). */
function slugimp_address(array $row, array $info): array
{
    $countries = (array) ($info['countries'] ?? []);
    $a = [
        'first_name' => slugimp_val($row, 'first_name'), 'last_name' => slugimp_val($row, 'last_name'), 'company' => slugimp_val($row, 'company'),
        'address_1' => slugimp_val($row, 'address_1'), 'address_2' => slugimp_val($row, 'address_2'), 'city' => slugimp_val($row, 'city'),
        'postcode' => slugimp_val($row, 'zip'), 'country' => (string) ($countries[$row['virtuemart_country_id'] ?? ''] ?? 'HU'),
        'phone' => slugimp_val($row, 'phone_1') ?: slugimp_val($row, 'phone_2'), 'email' => slugimp_val($row, 'email'),
    ];
    return array_filter($a, fn($v) => $v !== '');
}

/** Adószám a cím egyedi mezőiből (pl. „adoszam”, „vat_number”). */
function slugimp_tax_number(array $row): string
{
    foreach ($row as $k => $v) {
        if (preg_match('/adoszam|ado_szam|vatnum|vat_number|tax_number|taxid/i', (string) $k) && trim((string) $v) !== '') {
            return trim((string) $v);
        }
    }
    return '';
}

function slugimp_by_type(array $rows, string $type): array
{
    foreach ($rows as $r) {
        if (($r['address_type'] ?? '') === $type) {
            return $r;
        }
    }
    return [];
}

/** VirtueMart dátum (UTC) → időbélyeg. */
function slugimp_time(string $v): ?int
{
    if ($v === '' || str_starts_with($v, '0000')) {
        return null;
    }
    $t = strtotime($v . ' UTC');
    return $t ?: null;
}

// ------------------------------------------------------------------ vásárlók

function slugimp_customers(array $u, array $info, bool $dry): array
{
    $old = (int) $u['id'];
    $email = sanitize_email(slugimp_val($u, 'email'));
    $label = slugimp_val($u, 'name') . ' <' . $email . '>';
    if (array_intersect((array) ($u['groups'] ?? []), ['Super Users', 'Administrator', 'Manager', 'Editor', 'Publisher', 'Author'])) {
        return ['skip', '– ' . $label . ': a régi oldal munkatársi fiókja, nem vásárló'];
    }
    if (!is_email($email)) {
        return ['skip', '– #' . $old . ': nincs érvényes e-mail címe'];
    }
    if (!empty($u['block'])) {
        return ['skip', '– ' . $label . ': a régi oldalon tiltott fiók'];
    }
    $bt = slugimp_by_type((array) ($u['addresses'] ?? []), 'BT');
    $st = slugimp_by_type((array) ($u['addresses'] ?? []), 'ST');
    $billing = $bt ? slugimp_address($bt, $info) : [];
    $shipping = $st ? slugimp_address($st, $info) : [];
    unset($shipping['email'], $billing['email']);
    if (empty($billing['last_name']) && empty($billing['first_name'])) {
        // „Kiss Péter” → vezetéknév, keresztnév
        $parts = preg_split('/\s+/', slugimp_val($u, 'name'), 2);
        $billing['last_name'] = $parts[0] ?? '';
        $billing['first_name'] = $parts[1] ?? '';
    }
    $id = slugimp_find_old('user', $old) ?: (int) (get_user_by('email', $email)->ID ?? 0);
    if ($id) {
        if (!$dry) {
            update_user_meta($id, '_slugshop_old_id', $old);
            slugimp_fill_user($id, $billing, $shipping, slugimp_tax_number($bt));
        }
        return ['found', ''];
    }
    if ($dry) {
        return ['new', '+ ' . $label . ($billing ? ' (' . ($billing['city'] ?? '') . ')' : '')];
    }
    $login = sanitize_user(slugimp_val($u, 'username'), true);
    if ($login === '' || username_exists($login)) {
        $login = $email;
    }
    $reg = slugimp_time(slugimp_val($u, 'registerDate'));
    $id = wp_insert_user([
        'user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password(32, true, true),
        'display_name' => slugimp_val($u, 'name') ?: $login, 'first_name' => $billing['first_name'] ?? '', 'last_name' => $billing['last_name'] ?? '',
        'role' => 'customer', 'user_registered' => $reg ? gmdate('Y-m-d H:i:s', $reg) : current_time('mysql', true),
    ]);
    if (is_wp_error($id)) {
        return ['error', '✗ ' . $label . ': ' . $id->get_error_message()];
    }
    update_user_meta($id, '_slugshop_old_id', $old);
    update_user_meta($id, '_slugshop_imported', 1);
    slugimp_fill_user($id, $billing + ['email' => $email], $shipping, slugimp_tax_number($bt));
    return ['new', '+ ' . $label];
}

/** Csak az üres mezőket tölti (amit a vásárló vagy a bolt már beírt, az marad). */
function slugimp_fill_user(int $id, array $billing, array $shipping, string $tax): void
{
    foreach (['billing' => $billing, 'shipping' => $shipping] as $prefix => $fields) {
        foreach ($fields as $k => $v) {
            if ($prefix === 'shipping' && in_array($k, ['email', 'phone'], true)) {
                continue;
            }
            if ((string) get_user_meta($id, $prefix . '_' . $k, true) === '') {
                update_user_meta($id, $prefix . '_' . $k, $v);
            }
        }
    }
    if ($tax !== '' && (string) get_user_meta($id, 'billing_tax_number', true) === '') {
        update_user_meta($id, 'billing_tax_number', $tax); // a pénztár „Adószám” mezője ezzel töltődik elő
        update_user_meta($id, 'wc_szamlazz_adoszam', $tax);
    }
}

// ------------------------------------------------------------------ termékek

/** A régi termék bruttó ára (a VirtueMart nettót tárol, ÁFA-szabállyal; a felülírt ár már a végső). */
function slugimp_gross(array $p, array $info): ?float
{
    $prices = (array) ($p['prices'] ?? []);
    if (!$prices) {
        return null;
    }
    $pr = $prices[0];
    foreach ($prices as $x) {
        if ((int) ($x['virtuemart_shoppergroup_id'] ?? 0) === 0 && (int) ($x['price_quantity_start'] ?? 0) <= 1) {
            $pr = $x;
            break;
        }
    }
    if ((int) ($pr['override'] ?? 0) === 1 && (float) ($pr['product_override_price'] ?? 0) > 0) {
        return round((float) $pr['product_override_price']);
    }
    $rate = 27.0;
    $calcs = (array) ($info['calcs'] ?? []);
    foreach ($calcs as $c) {
        if ((int) ($pr['product_tax_id'] ?? 0) > 0 ? (int) $c['id'] === (int) $pr['product_tax_id'] : (in_array($c['kind'], ['VatTax', 'Tax'], true) && (int) $c['published'] === 1)) {
            $rate = (float) $c['value'];
            break;
        }
    }
    return round((float) ($pr['product_price'] ?? 0) * (1 + $rate / 100));
}

/** Mértékegység-átváltás a bolt egységeire (kg / g, cm / mm / m). */
function slugimp_unit(float $v, string $from, string $kind): float
{
    $from = strtolower($from);
    $to = strtolower((string) get_option($kind === 'weight' ? 'woocommerce_weight_unit' : 'woocommerce_dimension_unit', $kind === 'weight' ? 'kg' : 'cm'));
    $base = ['kg' => 1000, 'g' => 1, 'lb' => 453.592, 'lbs' => 453.592, 'oz' => 28.3495, 'm' => 1000, 'cm' => 10, 'mm' => 1, 'in' => 25.4, 'yd' => 914.4];
    if (!isset($base[$from], $base[$to])) {
        return $v;
    }
    return round($v * $base[$from] / $base[$to], 4);
}

function slugimp_products(array $p, array $info, bool $dry): array
{
    $old = (int) $p['virtuemart_product_id'];
    $name = slugimp_val($p, 'product_name') ?: 'Termék #' . $old;
    $slug = slugimp_val($p, 'slug');
    $sku = slugimp_val($p, 'product_sku');
    $id = slugimp_find_old('product', $old);
    if (!$id && $slug !== '') {
        $hit = get_posts(['post_type' => 'product', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids']);
        $id = $hit ? (int) $hit[0] : 0;
    }
    if (!$id && $sku !== '') {
        $id = (int) wc_get_product_id_by_sku($sku);
    }
    if ($id) {
        if (!$dry) {
            slugimp_fill_product($id, $p);
        }
        return ['found', ''];
    }
    $hidden = (int) ($p['published'] ?? 1) !== 1;
    if ($dry) {
        return ['new', '+ ' . $name . ($sku ? ' [' . $sku . ']' : '') . ($hidden ? ' (a régi boltban rejtett)' : '') . ' → piszkozat'];
    }
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('draft'); // új (a régi oldalon nem látszott, vagy kimaradt): átnézés után közzétehető
    $product->set_description((string) ($p['product_desc'] ?? ''));
    $product->set_short_description((string) ($p['product_s_desc'] ?? ''));
    if ($slug !== '') {
        $product->set_slug($slug);
    }
    $price = slugimp_gross($p, $info);
    if ($price !== null && $price > 0) {
        $product->set_regular_price((string) $price);
    }
    $product->set_stock_status((int) ($p['product_in_stock'] ?? 0) > 0 ? 'instock' : 'outofstock');
    $terms = [];
    foreach ((array) ($p['categories'] ?? []) as $c) {
        $t = !empty($c['slug']) ? get_term_by('slug', $c['slug'], 'product_cat') : false;
        if ($t) {
            $terms[] = (int) $t->term_id;
        }
    }
    if ($terms) {
        $product->set_category_ids(array_values(array_unique($terms)));
    }
    if ($sku !== '') {
        try {
            $product->set_sku($sku);
        } catch (WC_Data_Exception $e) {
            $product->update_meta_data('slugshop_old_sku', $sku);
        }
    }
    $product->update_meta_data('_slugshop_old_id', $old);
    $product->update_meta_data('_slugshop_imported', 1);
    if ($hidden) {
        $product->update_meta_data('_slugshop_old_hidden', 1);
    }
    $id = $product->save();
    slugimp_fill_product($id, $p);
    $imgs = slugimp_images($id, (array) ($p['media'] ?? []), $name);
    return ['new', '+ ' . $name . ($sku ? ' [' . $sku . ']' : '') . ' → piszkozat' . ($hidden ? ' (a régi boltban rejtett volt)' : '') . ($imgs ? ', ' . $imgs . ' kép' : '')];
}

/** Meglévő terméknél csak az üres mezők: súly, méretek, GTIN; és a régi azonosító. Név, leírás, ár, készlet: nem. */
function slugimp_fill_product(int $id, array $p): void
{
    $product = wc_get_product($id);
    if (!$product) {
        return;
    }
    $changed = false;
    if (!$product->get_meta('_slugshop_old_id')) {
        $product->update_meta_data('_slugshop_old_id', (int) $p['virtuemart_product_id']);
        $changed = true;
    }
    $w = (float) ($p['product_weight'] ?? 0);
    if ($w > 0 && $product->get_weight() === '') {
        $product->set_weight((string) slugimp_unit($w, (string) ($p['product_weight_uom'] ?? 'KG'), 'weight'));
        $changed = true;
    }
    foreach (['length' => 'product_length', 'width' => 'product_width', 'height' => 'product_height'] as $k => $col) {
        $v = (float) ($p[$col] ?? 0);
        if ($v > 0 && call_user_func([$product, 'get_' . $k]) === '') {
            call_user_func([$product, 'set_' . $k], (string) slugimp_unit($v, (string) ($p['product_lwh_uom'] ?? 'MM'), 'dimension'));
            $changed = true;
        }
    }
    $gtin = preg_replace('/\D/', '', (string) ($p['product_gtin'] ?? ''));
    if ($gtin !== '' && in_array(strlen($gtin), [8, 12, 13, 14], true) && method_exists($product, 'get_global_unique_id') && $product->get_global_unique_id() === '') {
        try {
            $product->set_global_unique_id($gtin);
            $changed = true;
        } catch (Throwable $e) {
            // másik terméknél már szerepel – kihagyjuk
        }
    }
    if ($changed) {
        $product->save();
    }
}

/** Képek a régi oldalról (böngésző-azonosítóval: a régi tárhely tűzfala a „WordPress” letöltőt tiltja). */
function slugimp_images(int $id, array $media, string $name): int
{
    $o = slugimp_opt();
    $base = preg_replace('#/[^/]*$#', '', $o['url']);
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $ids = [];
    foreach (array_slice($media, 0, 8) as $m) {
        $rel = ltrim((string) ($m['file_url'] ?? ''), '/');
        if ($rel === '' || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $rel)) {
            continue;
        }
        $url = preg_match('#^https?://#', $rel) ? $rel : $base . '/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
        $tmp = wp_tempnam(basename($rel));
        $r = wp_remote_get($url, ['timeout' => 60, 'user-agent' => SLUGIMP_UA, 'stream' => true, 'filename' => $tmp]);
        if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
            @unlink($tmp);
            continue;
        }
        $att = media_handle_sideload(['name' => sanitize_file_name(basename($rel)), 'tmp_name' => $tmp], $id, $name);
        if (is_wp_error($att)) {
            @unlink($tmp);
            continue;
        }
        update_post_meta($att, '_wp_attachment_image_alt', $name);
        update_post_meta($att, 'slugshop_source', '/' . $rel);
        $ids[] = (int) $att;
    }
    if ($ids) {
        $product = wc_get_product($id);
        $product->set_image_id(array_shift($ids));
        $product->set_gallery_image_ids($ids);
        $product->save();
    }
    return $ids ? count($ids) + 1 : 0;
}

// ------------------------------------------------------------------ rendelések

/** VirtueMart-állapot → WooCommerce. */
function slugimp_status(string $code, array $info): string
{
    $map = ['P' => 'pending', 'U' => 'on-hold', 'C' => 'processing', 'S' => 'completed', 'F' => 'completed', 'X' => 'cancelled', 'R' => 'refunded', 'D' => 'failed'];
    if (isset($map[$code])) {
        return $map[$code];
    }
    $name = strtoupper((string) (($info['orderstates'] ?? [])[$code] ?? ''));
    foreach (['SHIPPED' => 'completed', 'COMPLETE' => 'completed', 'DELIVER' => 'completed', 'CANCEL' => 'cancelled', 'REFUND' => 'refunded', 'DENIED' => 'failed', 'CONFIRMED' => 'processing'] as $k => $s) {
        if (str_contains($name, $k)) {
            return $s;
        }
    }
    return 'on-hold';
}

/** A bolt magyar 27%-os ÁFA-kulcsának azonosítója (a tételek adójához). */
function slugimp_tax_rate_id(): int
{
    static $id = null;
    if ($id === null) {
        global $wpdb;
        $id = (int) $wpdb->get_var("SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'HU' AND tax_rate_class = '' ORDER BY tax_rate_order LIMIT 1");
    }
    return $id;
}

function slugimp_orders(array $r, array $info, bool $dry): array
{
    $old = (int) $r['virtuemart_order_id'];
    $number = slugimp_val($r, 'order_number') ?: (string) $old;
    $status = slugimp_status(slugimp_val($r, 'order_status'), $info);
    $bt = slugimp_by_type((array) ($r['addresses'] ?? []), 'BT');
    $st = slugimp_by_type((array) ($r['addresses'] ?? []), 'ST');
    $email = sanitize_email(slugimp_val($bt, 'email'));
    $total = (float) ($r['order_total'] ?? 0);
    $label = $number . ' (' . gmdate('Y-m-d', slugimp_time(slugimp_val($r, 'created_on')) ?: time()) . ', ' . wc_format_decimal($total, 0) . ' Ft, ' . $status . ')';
    $existing = slugimp_find_old('order', $old);
    if ($existing) {
        // a régi boltban azóta változhatott az állapota (pl. kiküldték): azt frissítjük, csendben
        $o = wc_get_order($existing);
        if ($o && $o->get_status() !== $status && !$dry) {
            // az adattárolón át: a WC_Order::save() állapotváltást (levél, készlet, számla) futtatna
            $o->set_status($status);
            $o->get_data_store()->update($o);
            $o->apply_changes();
        }
        return ['found', ''];
    }
    if ($dry) {
        return ['new', '+ ' . $label . ', ' . count((array) ($r['lines'] ?? [])) . ' tétel, ' . ($email ?: 'e-mail nélkül')];
    }
    $rate = slugimp_tax_rate_id();
    $order = new WC_Order();
    // Az állapotot „csendben” állítjuk: így nem fut le az állapotváltás (levél, készletlevonás, számla, JUTA).
    $order->set_object_read(false);
    $order->set_status($status);
    $order->set_object_read(true);
    $created = slugimp_time(slugimp_val($r, 'created_on')) ?: time();
    $order->set_date_created($created);
    $codes = (array) ($info['currencies'] ?? []);
    $order->set_currency((string) ($codes[$r['order_currency'] ?? ''] ?? '') ?: get_woocommerce_currency());
    $order->set_prices_include_tax(true);
    $order->set_created_via('slugshop-import');
    $billing = $bt ? slugimp_address($bt, $info) : [];
    $shipping = $st ? slugimp_address($st, $info) : $billing;
    unset($shipping['email']);
    foreach ($billing as $k => $v) {
        call_user_func([$order, 'set_billing_' . $k], $v);
    }
    foreach ($shipping as $k => $v) {
        if (is_callable([$order, 'set_shipping_' . $k])) {
            call_user_func([$order, 'set_shipping_' . $k], $v);
        }
    }
    $uid = (int) ($r['virtuemart_user_id'] ?? 0) ? slugimp_find_old('user', (int) $r['virtuemart_user_id']) : 0;
    if (!$uid && $email) {
        $uid = (int) (get_user_by('email', $email)->ID ?? 0);
    }
    if ($uid) {
        $order->set_customer_id($uid);
    }
    $note = slugimp_val($r, 'customer_note') ?: slugimp_val($bt, 'customer_note');
    if ($note !== '') {
        $order->set_customer_note($note);
    }

    $taxes = fn(float $tax) => $rate && $tax != 0.0 ? ['total' => [$rate => $tax], 'subtotal' => [$rate => $tax]] : ['total' => [], 'subtotal' => []];
    foreach ((array) ($r['lines'] ?? []) as $l) {
        $qty = max(1, (int) ($l['product_quantity'] ?? 1));
        $gross = (float) ($l['product_subtotal_with_tax'] ?? 0) ?: (float) ($l['product_final_price'] ?? 0) * $qty;
        $tax = $rate ? (float) ($l['product_tax'] ?? 0) * $qty : 0.0;
        if ($tax > $gross) {
            $tax = 0.0;
        }
        $item = new WC_Order_Item_Product();
        $item->set_name(slugimp_val($l, 'order_item_name') ?: 'Tétel');
        $item->set_quantity($qty);
        $sku = slugimp_val($l, 'order_item_sku');
        $pid = (int) ($l['virtuemart_product_id'] ?? 0) ? slugimp_find_old('product', (int) $l['virtuemart_product_id']) : 0;
        if (!$pid && $sku !== '') {
            $pid = (int) wc_get_product_id_by_sku($sku);
        }
        if ($pid && get_post_type($pid) === 'product') {
            $item->set_product_id($pid);
        }
        if ($sku !== '') {
            $item->add_meta_data('Cikkszám (régi bolt)', $sku, true);
        }
        $item->set_subtotal((string) ($gross - $tax));
        $item->set_total((string) ($gross - $tax));
        $item->set_taxes($taxes($tax));
        $order->add_item($item);
    }
    $ship_name = (string) (($info['shipments'] ?? [])[$r['virtuemart_shipmentmethod_id'] ?? ''] ?? '');
    $ship = (float) ($r['order_shipment'] ?? 0);
    $ship_tax = $rate ? (float) ($r['order_shipment_tax'] ?? 0) : 0.0;
    if ($ship_name !== '' || $ship > 0) {
        $item = new WC_Order_Item_Shipping();
        $item->set_method_title($ship_name ?: 'Szállítás');
        $item->set_method_id('slugshop_import');
        $item->set_total((string) ($rate ? $ship : $ship + (float) ($r['order_shipment_tax'] ?? 0)));
        $item->set_taxes(['total' => $ship_tax ? [$rate => $ship_tax] : []]);
        $order->add_item($item);
    }
    $pay_name = (string) (($info['payments'] ?? [])[$r['virtuemart_paymentmethod_id'] ?? ''] ?? '');
    $order->set_payment_method('slugshop_import');
    $order->set_payment_method_title($pay_name ?: 'Régi bolt');
    $pay = (float) ($r['order_payment'] ?? 0);
    if ($pay > 0) {
        $pay_tax = $rate ? (float) ($r['order_payment_tax'] ?? 0) : 0.0;
        $fee = new WC_Order_Item_Fee();
        $fee->set_name(($pay_name ?: 'Fizetési mód') . ' díja');
        $fee->set_total((string) ($rate ? $pay : $pay + (float) ($r['order_payment_tax'] ?? 0)));
        $fee->set_taxes(['total' => $pay_tax ? [$rate => $pay_tax] : []]);
        $order->add_item($fee);
    }
    foreach (['coupon_discount' => 'Kupon' . (slugimp_val($r, 'coupon_code') !== '' ? ' (' . slugimp_val($r, 'coupon_code') . ')' : ''), 'order_discount' => 'Kedvezmény'] as $col => $title) {
        $d = abs((float) ($r[$col] ?? 0));
        if ($d > 0) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name($title);
            $fee->set_total((string) -$d);
            $fee->set_tax_status('none');
            $order->add_item($fee);
        }
    }
    if ($rate) {
        $order->update_taxes();
    }
    $order->set_shipping_total((string) ($rate ? $ship : $ship + (float) ($r['order_shipment_tax'] ?? 0)));
    $order->set_total((string) $total); // a régi bolt végösszege, pontosan
    if (in_array($status, ['processing', 'completed'], true)) {
        $order->set_date_paid($created);
    }
    if ($status === 'completed') {
        $order->set_date_completed(slugimp_time(slugimp_val($r, 'modified_on')) ?: $created);
    }
    // Ne vonjon le készletet, ne számolja eladásnak, és a JUTA-nak ne küldje be (a régi bolt már beküldte).
    if (method_exists($order, 'set_order_stock_reduced')) {
        $order->set_order_stock_reduced(true);
    } else {
        $order->update_meta_data('_order_stock_reduced', 'yes');
    }
    if (method_exists($order, 'set_recorded_sales')) {
        $order->set_recorded_sales(true);
    }
    $order->update_meta_data('_slugshop_old_id', $old);
    $order->update_meta_data('_slugshop_old_number', $number);
    $order->update_meta_data('_slugshop_imported', 1);
    $order->update_meta_data('_elad_exported_file', 'regi-bolt');
    $tax_no = slugimp_tax_number($bt);
    if ($tax_no !== '') {
        $order->update_meta_data('_billing_tax_number', $tax_no);
    }
    $order->save();
    $hist = [];
    foreach ((array) ($r['history'] ?? []) as $h) {
        $t = slugimp_time(slugimp_val($h, 'created_on'));
        $hist[] = ($t ? wp_date('Y-m-d H:i', $t) : '') . ' ' . slugimp_status(slugimp_val($h, 'order_status_code'), $info) . (slugimp_val($h, 'comments') !== '' ? ' – ' . wp_strip_all_tags(slugimp_val($h, 'comments')) : '');
    }
    $order->add_order_note('Átvéve a régi boltból (VirtueMart #' . $old . ', ' . $number . ').' . ($hist ? "\nElőzmények:\n" . implode("\n", $hist) : ''));
    return ['new', '+ ' . $label];
}
