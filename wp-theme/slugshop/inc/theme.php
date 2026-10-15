<?php
/**
 * Téma-alapok: támogatások, menühelyek, stíluslapok és szkriptek, a fő tartalom horgonya, szövegkezelés.
 */

defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('woocommerce');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus([
        'slugshop-primary' => 'Fő menü (fejléc)',
        'slugshop-footer' => 'Lábléc menü',
    ]);
    add_image_size('slugshop-card', 480, 480, false);
}, 20);

// A szöveg betűre azonos maradjon a régi oldaléval (kötőjel, idézőjel).
add_filter('run_wptexturize', '__return_false');

/** Stíluslapok és szkriptek. A 3D-csomag (three.js, ~520 kB) csak ott töltődik, ahol 3D-s blokk van. */
add_action('wp_enqueue_scripts', function () {
    $css = SLUGSHOP_URL . '/assets/css/';
    $v = SLUGSHOP_VERSION;
    wp_enqueue_style('slugshop-fonts', $css . 'fonts.css', [], $v);
    wp_enqueue_style('slugshop-vars', $css . 'vars.css', ['slugshop-fonts'], $v);
    wp_enqueue_style('slugshop-iu', $css . 'iu-compat.css', ['slugshop-vars', 'style'], $v);
    wp_enqueue_style('slugshop-site', $css . 'site.css', ['slugshop-iu'], $v);
    wp_enqueue_style('slugshop-shop', $css . 'shop.css', ['slugshop-site'], $v);
    wp_enqueue_style('slugshop-wp', $css . 'wp.css', ['slugshop-shop'], $v);

    $js = SLUGSHOP_URL . '/assets/js/';
    wp_enqueue_script('slugshop-site', $js . 'site.js', [], $v, ['in_footer' => true, 'strategy' => 'defer']);
    wp_localize_script('slugshop-site', 'SLUGSHOP', [
        'home' => home_url('/'),
        'rest' => esc_url_raw(rest_url('slugshop/v1/')),
        'cart' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/kosar'),
        'ajax' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('%%endpoint%%') : '',
        'privacy' => home_url('/adatvedelmi-nyilatkozat'),
        'age' => (array) slugshop_site('age', []),
        'cookie' => (array) slugshop_site('cookie', []),
        'trackers' => slugshop_trackers(),
    ]);
    wp_register_script('slugshop-fx', $js . 'fx.js', [], $v, ['in_footer' => true, 'strategy' => 'defer']);
    wp_register_script('slugshop-fx3d', $js . 'fx3d.js', [], $v, ['in_footer' => true, 'strategy' => 'defer']);
}, 20);

/** A blokkok kérik a szkriptjeiket (a 3D csak a nyitóoldalon van, ott töltődik). */
function slugshop_need_script(string $handle): void
{
    if ($handle === 'slugshop-fx3d' && !wp_script_is('slugshop-panthera-data', 'registered')) {
        $file = SLUGSHOP_DIR . '/setup/data/panthera.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        if ($data) {
            $data['render'] = slugshop_img('panthera-render.webp');
            wp_register_script('slugshop-panthera-data', false, [], SLUGSHOP_VERSION, ['in_footer' => true]);
            wp_add_inline_script('slugshop-panthera-data', 'window.PANTHERA=' . wp_json_encode($data) . ';');
            wp_enqueue_script('slugshop-panthera-data');
        }
    }
    wp_enqueue_script($handle);
}

/** A „Ugrás a tartalomra” link célja: a fejléc után, a <main> előtt. */
add_action('iu_main_template', function () {
    echo '<span id="main" tabindex="-1"></span>';
}, 1);

/** A <body> osztályai: a sötét/világos oldal és a nyitóoldal (a CSS ezekre épít). */
add_filter('body_class', function (array $classes): array {
    if (is_front_page()) {
        $classes[] = 'is-home';
    }
    return $classes;
});

/** A betűk előtöltése (a fejléc betűi azonnal kellenek). */
add_action('wp_head', function () {
    foreach (['barlow-400-latin', 'barlow-600-latin'] as $f) {
        printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url(SLUGSHOP_URL . '/assets/fonts/' . $f . '.woff2'));
    }
    printf('<link rel="icon" href="%s">' . "\n", esc_url(slugshop_img('favicon.png')));
}, 2);

/** A dokumentum címe: „Oldal – Slugshop”, a nyitóoldalon a régi cím. */
add_filter('document_title_separator', fn() => '–');
add_filter('document_title_parts', function (array $parts): array {
    if (is_front_page()) {
        $parts['title'] = (string) slugshop_site('seo.homeTitle', 'Légfegyverek és ZAN lövedékek');
        $parts['site'] = 'Slugshop';
        unset($parts['tagline']);
    } else {
        $parts['site'] = 'Slugshop';
    }
    return $parts;
});

/** A keresés a terméket is találja (az iu_theme a bejegyzésekre szűkíthet). */
add_action('pre_get_posts', function (WP_Query $q) {
    if (!is_admin() && $q->is_main_query() && $q->is_search()) {
        $q->set('post_type', ['product', 'post']);
        $q->set('posts_per_page', 60);
    }
}, 20);

/**
 * Az iu_theme alap-stíluslapja (style.css → block-styles.css) elemszintű szabályokkal ad színt a p-nek és a címsoroknak
 * (h1:not(…) – erősebb, mint a téma osztályai), ezért a sötét sávokban sötét szöveg lenne. Ahogy a Mandalánál: a szülő
 * style.css helyett csak a variables.css töltődik, a szerkezetet (sor, oszlop, csoport, gombok) a téma iu-compat.css-e
 * adja – a jóváhagyott előnézet ugyanerre épült. Kikapcsolható: add_filter('slugshop_iu_base_styles', '__return_false').
 */
add_filter('style_loader_src', function ($src) {
    static $on = null;
    $on ??= apply_filters('slugshop_iu_base_styles', true) && is_readable(get_template_directory() . '/variables.css');
    if ($on && is_string($src) && strtok($src, '?') === trailingslashit(get_template_directory_uri()) . 'style.css') {
        return trailingslashit(get_template_directory_uri()) . 'variables.css';
    }
    return $src;
}, 5);

/** Az iu blokkok szerkezeti CSS-e helyett a téma rétege (iu-compat.css); a működéshez kellők maradnak. */
function slugshop_iu_dequeue_block_styles(): void
{
    if (!apply_filters('slugshop_iu_base_styles', true)) {
        return;
    }
    foreach (['row', 'column', 'section', 'group', 'button', 'button-group', 'card', 'query', 'breadcrumbs', 'search', 'menu'] as $block) {
        wp_dequeue_style('iu-' . $block . '-style');
    }
}
add_action('wp_print_styles', 'slugshop_iu_dequeue_block_styles', 1);
add_action('wp_print_footer_scripts', 'slugshop_iu_dequeue_block_styles', 1);
