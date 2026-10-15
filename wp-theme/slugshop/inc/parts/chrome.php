<?php
/**
 * Fejléc és lábléc (az előnézet header() / footer() szerkezete). A keretrendszer a fejléc-sablont <header>-be, a
 * láblécet <footer>-be teszi, ezért itt div-ek vannak (fejlécben nem lehet újabb header).
 */

defined('ABSPATH') || exit;

/**
 * Menü fája: ha a menühelyhez van WordPress-menü rendelve, az; különben a site.json menüje (a kategóriák alkategóriáival).
 *
 * @return array<int, array{title:string,url:string,children:array}>
 */
function slugshop_menu(string $location): array
{
    $locations = get_nav_menu_locations();
    $items = !empty($locations[$location]) ? wp_get_nav_menu_items($locations[$location]) : false;
    if ($items) {
        $by = [];
        foreach ($items as $it) {
            $by[$it->ID] = ['title' => $it->title, 'url' => $it->url, 'children' => [], 'parent' => (int) $it->menu_item_parent];
        }
        $tree = [];
        foreach ($by as $id => $it) {
            if ($it['parent'] && isset($by[$it['parent']])) {
                $by[$it['parent']]['children'][] = &$by[$id];
            }
        }
        foreach ($by as $it) {
            if (!$it['parent']) {
                $tree[] = $it;
            }
        }
        return $tree;
    }
    $key = $location === 'slugshop-footer' ? 'footerMenu' : 'menu';
    $cats = slugshop_cat_paths();
    $out = [];
    foreach ((array) slugshop_site($key, []) as [$title, $path]) {
        $children = [];
        if (isset($cats[$path])) {
            foreach (slugshop_child_cats($cats[$path]) as $k) {
                $children[] = ['title' => (string) (get_term_meta($k->term_id, 'slugshop_menu', true) ?: $k->name), 'url' => get_term_link($k), 'children' => []];
            }
        }
        $out[] = ['title' => $title, 'url' => slugshop_url($path), 'children' => $children];
    }
    return $out;
}

/** Aktív menüpont: a kért útvonal a menüpont útvonalával kezdődik (a termék a kategóriája alatt is aktív). */
function slugshop_is_current(string $url): bool
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    if ($path === '' || is_search() || is_404()) {
        return false;
    }
    $now = slugshop_request_path();
    if (is_singular('product')) {
        $now = ltrim(slugshop_product_path(get_queried_object_id()), '/');
    }
    return $now === $path || str_starts_with($now, $path . '/');
}

function slugshop_account_links(): array
{
    $account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
    if (is_user_logged_in()) {
        return [['Fiókom', $account, 'login.svg'], ['Kilépés', wp_logout_url(home_url('/')), 'register.svg']];
    }
    return [['Belépés', $account, 'login.svg'], ['Regisztráció', add_query_arg('regisztracio', '1', $account), 'register.svg']];
}

function slugshop_cart_count(): int
{
    return function_exists('WC') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function slugshop_render_header(): string
{
    $phone = (string) slugshop_site('phone');
    $phone_href = 'tel:' . preg_replace('/[^+\d]/', '', $phone);
    $email = (string) slugshop_site('email');
    $social = (array) slugshop_site('social', []);
    $menu = slugshop_menu('slugshop-primary');
    $account = slugshop_account_links();
    $logo = slugshop_img('logo.webp');
    $count = slugshop_cart_count();

    $nav = '';
    foreach ($menu as $item) {
        $sub = '';
        if ($item['children']) {
            $sub = '<ul class="sub">';
            foreach ($item['children'] as $c) {
                $sub .= '<li><a href="' . esc_url($c['url']) . '"' . (slugshop_is_current($c['url']) ? ' aria-current="page"' : '') . '>' . esc_html($c['title']) . '</a></li>';
            }
            $sub .= '</ul>';
        }
        $nav .= '<li><a href="' . esc_url($item['url']) . '"' . (slugshop_is_current($item['url']) ? ' aria-current="page"' : '') . '>' . esc_html($item['title']) . '</a>' . $sub . '</li>';
    }
    $drawer = '';
    foreach ($menu as $item) {
        $drawer .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
        foreach ($item['children'] as $c) {
            $drawer .= '<a class="sub" href="' . esc_url($c['url']) . '">' . esc_html($c['title']) . '</a>';
        }
    }
    foreach (slugshop_menu('slugshop-footer') as $item) {
        $drawer .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
    }
    $contact = '<a href="' . esc_attr($phone_href) . '"><img src="' . esc_url(slugshop_img('phone.svg')) . '" alt="">' . esc_html($phone) . '</a>';
    $mail = '<a href="mailto:' . esc_attr($email) . '"><img src="' . esc_url(slugshop_img('email.svg')) . '" alt="">' . esc_html($email) . '</a>';
    $acc = '';
    foreach ($account as $i => [$label, $url, $icon]) {
        $acc .= '<a' . ($i ? ' class="t-hide"' : '') . ' href="' . esc_url($url) . '"><img src="' . esc_url(slugshop_img($icon)) . '" alt="">' . esc_html($label) . '</a>';
    }
    $soc = '';
    foreach (['facebook' => ['fb.svg', 'Facebook'], 'instagram' => ['insta.svg', 'Instagram'], 'youtube' => ['yt.svg', 'YouTube']] as $k => [$icon, $label]) {
        if (!empty($social[$k])) {
            $soc .= '<a href="' . esc_url($social[$k]) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($label) . '"><img src="' . esc_url(slugshop_img($icon)) . '" alt=""></a>';
        }
    }

    ob_start(); ?>
<a class="skip-link" href="#main">Ugrás a tartalomra</a>
<div class="topbar"><div class="wrap">
  <div class="topbar-group"><?php echo $contact; ?><span class="t-hide"><?php echo $mail; ?></span></div>
  <div class="topbar-group"><span class="social t-hide"><?php echo $soc; ?></span><?php echo $acc; ?></div>
</div></div>
<div class="site-header"><div class="wrap">
  <button class="close-btn burger" type="button" data-open-drawer aria-label="Menü megnyitása" aria-controls="drawer" aria-expanded="false"><?php echo str_replace('<svg', '<svg width="30" height="30"', slugshop_icon('menu')); ?></button>
  <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Slugshop – kezdőlap"><img src="<?php echo esc_url($logo); ?>" alt="Slugshop logó" width="84" height="84"></a>
  <nav class="main-nav" aria-label="Fő menü"><ul><?php echo $nav; ?></ul></nav>
  <div class="header-icons">
    <button type="button" data-open-search aria-label="Kereső" aria-controls="search"><?php echo slugshop_icon('search'); ?></button>
    <a href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/kosar')); ?>" aria-label="Kosár"><?php echo slugshop_icon('cart'); ?><span class="cart-count" data-cart-count data-n="<?php echo (int) $count; ?>"><?php echo (int) $count; ?></span></a>
  </div>
</div></div>
<div class="drawer" id="drawer" aria-label="Menü" aria-hidden="true">
  <div class="drawer-head"><img src="<?php echo esc_url($logo); ?>" alt="Slugshop"><button class="close-btn" type="button" data-close aria-label="Menü bezárása">×</button></div>
  <nav aria-label="Mobil menü"><?php echo $drawer; ?></nav>
  <div class="drawer-foot"><?php echo $contact . $mail . str_replace(' class="t-hide"', '', $acc); ?></div>
</div>
<div class="scrim" data-close></div>
<div class="search-panel" id="search" role="dialog" aria-label="Kereső" aria-hidden="true"><div class="wrap">
  <form class="search-row" id="search-form" role="search" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="sr-only" for="q">Keresés a termékek között</label>
    <input id="q" name="s" type="search" placeholder="Keresés…" autocomplete="off" value="<?php echo esc_attr(get_search_query()); ?>">
    <input type="hidden" name="post_type" value="product">
    <button class="btn" type="submit">Keresés</button>
    <button class="close-btn" type="button" data-close aria-label="Kereső bezárása">×</button>
  </form>
  <p class="search-hint" id="search-hint">Írd be a termék nevét, a kalibert vagy a cikkszámot.</p>
  <div class="search-results" id="search-results"></div>
</div></div>
<?php
    return (string) ob_get_clean();
}

function slugshop_render_footer(): string
{
    $phone = (string) slugshop_site('phone');
    $email = (string) slugshop_site('email');
    $age = (array) slugshop_site('age', []);
    $fb = (string) slugshop_site('trackers.fogyasztobarat', '');
    $menu1 = $menu2 = '';
    foreach (slugshop_menu('slugshop-primary') as $it) {
        $menu1 .= '<li><a href="' . esc_url($it['url']) . '">' . esc_html($it['title']) . '</a></li>';
    }
    foreach (slugshop_menu('slugshop-footer') as $it) {
        $menu2 .= '<li><a href="' . esc_url($it['url']) . '">' . esc_html($it['title']) . '</a></li>';
    }
    $menu2 .= '<li><a href="#" data-cookie-settings>Sütibeállítások</a></li>';
    ob_start(); ?>
<div class="site-footer"><div class="wrap">
  <div class="footer-grid">
    <a class="footer-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Slugshop – kezdőlap"><img src="<?php echo esc_url(slugshop_img('logoFooter.webp')); ?>" alt="Slugshop logó" loading="lazy"></a>
    <div class="footer-contact">
      <a href="tel:<?php echo esc_attr(preg_replace('/[^+\d]/', '', $phone)); ?>"><img src="<?php echo esc_url(slugshop_img('phone.svg')); ?>" alt=""><?php echo esc_html($phone); ?></a>
      <a href="mailto:<?php echo esc_attr($email); ?>"><img src="<?php echo esc_url(slugshop_img('email.svg')); ?>" alt=""><?php echo esc_html($email); ?></a>
    </div>
    <ul class="footer-menu"><?php echo $menu1; ?></ul>
    <ul class="footer-menu"><?php echo $menu2; ?></ul>
  </div>
  <?php if (!empty($age['noteTitle'])) : ?>
  <div class="footer-notice"><strong><?php echo esc_html($age['noteTitle']); ?></strong> <?php echo esc_html(implode(' ', (array) ($age['note'] ?? []))); ?></div>
  <?php endif; ?>
  <div class="footer-bottom">
    <span>© <?php echo esc_html(wp_date('Y')); ?> Slugshop Kft.<?php if ($fb) : ?> · <a href="<?php echo esc_url('https://fogyasztobarat.hu/tanusitvany-ellenorzes/?csi=' . rawurlencode($fb)); ?>" target="_blank" rel="noopener">Fogyasztó Barát tanúsítvány</a><?php endif; ?></span>
    <span>Készítette: HelloProVision</span>
  </div>
</div></div>
<button class="a11y-btn" type="button" data-a11y aria-label="Kisegítő eszközök" aria-expanded="false"><?php echo slugshop_icon('a11y'); ?></button>
<?php
    return (string) ob_get_clean();
}
