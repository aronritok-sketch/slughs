<?php
/**
 * Megjelenés → Slugshop: 1) Telepítés (médiacsomag feltöltése, telepítő lépésenként, napló), 2) Adatok (elérhetőség,
 * cégadatok, mérőkódok, Fogyasztó Barát), 3) Élesítési ellenőrzés (ami a kódból ellenőrizhető).
 */

defined('ABSPATH') || exit;

/** Az Adatok fül mezői: pontozott kulcs (a site.json szerkezete) => [felirat, súgó]. */
function slugshop_setting_fields(): array
{
    return [
        'phone' => ['Telefon', 'Fejléc, lábléc, kapcsolat (pl. +36 30 677 7836).'],
        'email' => ['E-mail', 'Fejléc, lábléc; ide mennek az űrlapok, ha lent nincs külön cím.'],
        'forms.to' => ['Űrlapok címzettje', 'Ha nem az előző e-mail cím (nem kötelező).'],
        'contact.address' => ['Üzlet címe', 'Kapcsolat, személyes átvétel.'],
        'company.court' => ['Nyilvántartó bíróság', 'Impresszum (pl. Szolnoki Törvényszék Cégbírósága).'],
        'company.host' => ['Tárhelyszolgáltató', 'Impresszum: név, cím, e-mail (kötelező adat).'],
        'trackers.fogyasztobarat' => ['Fogyasztó Barát azonosító', 'Az ÁSZF, az adatkezelés és a jelvény innen töltődik (régi: MB0C642A).'],
        'trackers.gtm' => ['Google Tag Manager azonosító', 'Csak sütihozzájárulás után töltődik (régi: GTM-K2DT9KZ8).'],
        'trackers.fbPixel' => ['Meta (Facebook) Pixel', 'Csak a marketing-sütik elfogadása után.'],
        'trackers.barionPixel' => ['Barion Pixel', 'Csak a marketing-sütik elfogadása után (a régi oldalon volt).'],
        'seo.homeDescription' => ['Nyitóoldal meta-leírás', 'A Google találati listájában (kb. 150 karakter).'],
    ];
}

add_action('admin_menu', function () {
    add_theme_page('Slugshop', 'Slugshop', 'manage_options', 'slugshop', 'slugshop_admin_page');
});

function slugshop_admin_page(): void
{
    $tab = sanitize_key($_GET['tab'] ?? 'setup');
    $tabs = ['setup' => 'Telepítés', 'data' => 'Adatok', 'checks' => 'Élesítési ellenőrzés'];
    echo '<div class="wrap"><h1>Slugshop</h1><nav class="nav-tab-wrapper">';
    foreach ($tabs as $k => $l) {
        echo '<a class="nav-tab' . ($tab === $k ? ' nav-tab-active' : '') . '" href="' . esc_url(admin_url('themes.php?page=slugshop&tab=' . $k)) . '">' . esc_html($l) . '</a>';
    }
    echo '</nav>';
    if (!empty($_GET['msg'])) {
        echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['msg']))) . '</p></div>';
    }
    if ($tab === 'data') {
        slugshop_admin_data();
    } elseif ($tab === 'checks') {
        slugshop_admin_checks_page();
    } else {
        slugshop_admin_setup();
    }
    echo '</div>';
}

function slugshop_media_count(): int
{
    $dir = slugshop_media_dir();
    if (!is_dir($dir)) {
        return 0;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    return iterator_count($it);
}

function slugshop_admin_setup(): void
{
    $count = slugshop_media_count();
    $ver = get_option('slugshop_setup_version');
    echo '<h2>1. Médiacsomag</h2><p>A termékek, kategóriák és cikkek képei: <code>slugshop-media.zip</code>. Feltöltheted itt, vagy FTP-n kicsomagolva a <code>wp-content/uploads/slugshop-media/</code> mappába.</p>';
    echo '<p>Most a mappában: <strong>' . (int) $count . ' fájl</strong>.</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('slugshop_media');
    echo '<input type="hidden" name="action" value="slugshop_media"><input type="file" name="media" accept=".zip"> ';
    submit_button('Médiacsomag feltöltése', 'secondary', 'submit', false);
    echo '</form>';

    echo '<h2>2. Telepítés / frissítés</h2>';
    echo '<p>Létrehozza és beállítja: WooCommerce (Ft, bruttó ár, 27% ÁFA, szállítási zóna, fizetési módok előkészítve), 30 kategória, ~516 termék képekkel, az oldalakat (kosár, pénztár, fiók, jogi oldalak, videók …), a cikkeket és a menüket. Ismételten futtatható: ami nem változott, ahhoz nem nyúl; amit az adminban szerkesztettek, azt kihagyja (kivéve „Felülírás”). Élesítés után az árat és a készletet nem írja át (azt a JUTA kezeli).</p>';
    echo $ver ? '<p>Utolsó telepítés: <strong>' . esc_html((string) $ver) . '</strong>, ' . esc_html(wp_date('Y-m-d H:i', (int) get_option('slugshop_setup_time'))) . '.</p>' : '<p><strong>Még nem futott.</strong></p>';
    echo '<p><label><input type="checkbox" id="slug-force"> Felülírás (a szerkesztett tartalmat, az árat és a készletet is visszaírja a régi oldal szerint)</label></p>';
    echo '<p><button class="button button-primary" id="slug-run">Telepítés indítása</button> <span id="slug-status"></span></p>';
    echo '<pre id="slug-log" style="max-height:420px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;display:none"></pre>';
    $steps = array_keys(slugshop_setup_steps());
    ?>
<script>
(function () {
  const steps = <?php echo wp_json_encode($steps); ?>;
  const btn = document.getElementById('slug-run'), log = document.getElementById('slug-log'), st = document.getElementById('slug-status');
  const nonce = <?php echo wp_json_encode(wp_create_nonce('slugshop_setup')); ?>;
  async function call(step, offset, force) {
    const body = new URLSearchParams({ action: 'slugshop_setup', step, offset, force: force ? 1 : 0, _ajax_nonce: nonce });
    const r = await fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json(); if (!j.success) throw new Error(j.data || 'hiba'); return j.data;
  }
  btn.addEventListener('click', async (e) => {
    e.preventDefault(); btn.disabled = true; log.style.display = 'block'; log.textContent = '';
    const force = document.getElementById('slug-force').checked;
    try {
      for (const step of steps) {
        let offset = 0, done = false;
        while (!done) {
          st.textContent = step + (offset ? ' (' + offset + ')' : '') + ' …';
          const d = await call(step, offset, force);
          d.log.forEach((l) => { log.textContent += l + '\n'; }); log.scrollTop = log.scrollHeight;
          done = d.done; offset = d.offset;
        }
      }
      st.textContent = 'Kész.';
    } catch (err) { st.textContent = 'Hiba: ' + err.message + ' – futtasd újra (onnan folytatja, ahol abbahagyta).'; }
    btn.disabled = false;
  });
})();
</script>
    <?php
}

add_action('wp_ajax_slugshop_setup', function () {
    check_ajax_referer('slugshop_setup');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Nincs jogosultság.');
    }
    $step = sanitize_key($_POST['step'] ?? '');
    if (!isset(slugshop_setup_steps()[$step])) {
        wp_send_json_error('Ismeretlen lépés.');
    }
    wp_send_json_success(slugshop_setup_run($step, (int) ($_POST['offset'] ?? 0), !empty($_POST['force'])));
});

add_action('admin_post_slugshop_media', function () {
    check_admin_referer('slugshop_media');
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultság.');
    }
    $msg = 'Nem jött fájl.';
    if (!empty($_FILES['media']['tmp_name']) && is_uploaded_file($_FILES['media']['tmp_name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        wp_mkdir_p(slugshop_media_dir());
        $res = unzip_file($_FILES['media']['tmp_name'], slugshop_media_dir());
        $msg = is_wp_error($res) ? 'A kicsomagolás nem sikerült: ' . $res->get_error_message() : 'Médiacsomag kicsomagolva: ' . slugshop_media_count() . ' fájl. Futtasd a telepítőt.';
    }
    wp_safe_redirect(admin_url('themes.php?page=slugshop&msg=' . rawurlencode($msg)));
    exit;
});

function slugshop_admin_data(): void
{
    $saved = (array) get_option('slugshop_settings', []);
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('slugshop_data');
    echo '<input type="hidden" name="action" value="slugshop_data"><table class="form-table"><tbody>';
    foreach (slugshop_setting_fields() as $key => [$label, $help]) {
        $value = $saved[$key] ?? '';
        $default = (string) (is_scalar(slugshop_site($key)) ? slugshop_site($key) : '');
        echo '<tr><th scope="row"><label for="f-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>'
            . '<input class="regular-text" id="f-' . esc_attr($key) . '" name="s[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($default) . '">'
            . '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }
    echo '</tbody></table><p>Üresen hagyva a régi oldal adata marad (a mezőben halványan látszik).</p>';
    submit_button('Mentés');
    echo '</form>';
}

add_action('admin_post_slugshop_data', function () {
    check_admin_referer('slugshop_data');
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultság.');
    }
    $in = (array) wp_unslash($_POST['s'] ?? []);
    $out = [];
    foreach (array_keys(slugshop_setting_fields()) as $key) {
        $v = trim(sanitize_text_field((string) ($in[$key] ?? '')));
        if ($v !== '') {
            $out[$key] = $v;
        }
    }
    update_option('slugshop_settings', $out, true);
    wp_safe_redirect(admin_url('themes.php?page=slugshop&tab=data&msg=' . rawurlencode('Mentve.')));
    exit;
});

/**
 * Élesítési ellenőrzés: [állapot: ok|warn|bad, felirat, magyarázat].
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function slugshop_admin_checks(): array
{
    $c = [];
    $add = function (bool $ok, string $label, string $fix, string $level = 'bad') use (&$c) {
        $c[] = [$ok ? 'ok' : $level, $label, $ok ? '' : $fix];
    };
    $add(version_compare((string) wp_get_theme('iu_theme')->get('Version'), '0.2.0', '>='), 'Alap téma: iu_theme 0.2.0 vagy újabb', 'Telepítsd a hlprv GitHub-kiadásából (iu_theme.zip).');
    $add(defined('HPV_GITHUB_TOKEN'), 'HPV_GITHUB_TOKEN a wp-config.php-ban (az alap téma frissítése)', 'Áron adja a tokent.', 'warn');
    $add(class_exists('WooCommerce'), 'WooCommerce bekapcsolva', 'Telepítsd és kapcsold be.');
    $add((bool) get_option('slugshop_setup_version'), 'A telepítő lefutott', 'Megjelenés → Slugshop → Telepítés.');
    $add(get_option('permalink_structure') === '/%postname%', 'Permalink: /%postname% (a régi címek perjel nélkül)', 'A telepítő beállítja; Beállítások → Közvetlen hivatkozások → Mentés.');
    $add((int) get_option('blog_public') === 1, 'A keresők indexelhetik az oldalt', 'Élesítéskor: Beállítások → Olvasás → a „ne indexeljék” pipa ki. (Tesztoldalon maradjon bent.)', 'warn');
    $add(slugshop_media_count() > 0, 'Médiacsomag feltöltve', 'Megjelenés → Slugshop → Médiacsomag.');
    $no_img = (int) (new WP_Query(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']]]))->found_posts;
    $add($no_img === 0, 'Minden terméknek van képe' . ($no_img ? " ($no_img nincs)" : ''), 'Futtasd újra a telepítőt a médiacsomag után; ami a régi oldalon sem volt, annak kézzel.', 'warn');
    $gateways = function_exists('WC') ? array_keys(array_filter(WC()->payment_gateways()->payment_gateways(), fn($g) => $g->enabled === 'yes')) : [];
    $add((bool) array_filter($gateways, fn($g) => str_contains($g, 'viva')), 'Viva Wallet (bankkártya) bekapcsolva', 'Telepítsd a Viva.com Smart Checkout bővítményt, add meg a kulcsokat (kérdéssor).');
    $add((bool) $gateways, 'Van bekapcsolt fizetési mód', 'WooCommerce → Beállítások → Fizetések.');
    $zones = class_exists('WC_Shipping_Zones') ? WC_Shipping_Zones::get_zones() : [];
    $enabled = 0;
    foreach ($zones as $z) {
        foreach ($z['shipping_methods'] as $m) {
            $enabled += $m->is_enabled() && $m->id !== 'local_pickup' ? 1 : 0;
        }
    }
    $add($enabled > 0, 'Van házhozszállítás (futár / GLS)', 'WooCommerce → Szállítás → Magyarország: a GLS bővítmény módjai vagy a futár díja (kérdéssor).');
    $add((string) slugshop_site('company.host', '') !== '', 'Impresszum: tárhelyszolgáltató', 'Megjelenés → Slugshop → Adatok.');
    $add((string) slugshop_site('company.court', '') !== '', 'Impresszum: nyilvántartó bíróság', 'Megjelenés → Slugshop → Adatok.', 'warn');
    $add((string) slugshop_site('trackers.fogyasztobarat', '') !== '', 'Fogyasztó Barát azonosító (ÁSZF, adatkezelés)', 'Megjelenés → Slugshop → Adatok.');
    $smtp = defined('WPMS_ON') || class_exists('WPMailSMTP\\Core') || (bool) get_option('wp_mail_smtp') || defined('FluentMail') || class_exists('FluentMail\\App\\App') || (bool) apply_filters('slugshop_smtp_configured', false);
    $add($smtp, 'Levélküldés SMTP-n át (WP Mail SMTP vagy hasonló)', 'Telepítsd a WP Mail SMTP-t a bolt postafiókjával; küldj próbalevelet.', 'warn');
    $juta = slugshop_juta_status();
    $add($juta['last'] !== null && !$juta['stale'], 'JUTA-szinkron él' . ($juta['last'] ? ' (utoljára: ' . wp_date('Y-m-d H:i', (int) $juta['last']) . ')' : ''), 'A /juta/ mappa és a tárhely cronja (raw_sync.php, elad.php) – lásd a JUTA-fejezetet a teendőlistában.', 'warn');
    $add(!get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 's' => 'Lorem ipsum', 'fields' => 'ids']), 'Nincs helyőrző szöveg a termékekben', 'Keresd meg és javítsd.', 'warn');
    return $c;
}

function slugshop_admin_checks_page(): void
{
    $icons = ['ok' => '✅', 'warn' => '⚠️', 'bad' => '❌'];
    echo '<p>Ami a kódból ellenőrizhető. A teljes lista (pl. próbarendelés, levelek, Search Console) Laci teendőlistájában van.</p><table class="widefat striped"><tbody>';
    foreach (slugshop_admin_checks() as [$state, $label, $fix]) {
        echo '<tr><td style="width:30px">' . $icons[$state] . '</td><td><strong>' . esc_html($label) . '</strong>' . ($fix ? '<br><span class="description">' . esc_html($fix) . '</span>' : '') . '</td></tr>';
    }
    echo '</tbody></table>';
}
