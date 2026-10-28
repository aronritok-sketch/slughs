<?php
/**
 * SEO-alapok SEO-bővítmény nélkül: meta leírás (a régi oldal meta-leírása / a kategória vagy termék szövegéből),
 * Open Graph, strukturált adatok (Store a nyitóoldalon, Product a termékoldalon, BreadcrumbList), noindex a keresésen,
 * kosáron, pénztáron, fiókon. Ha Yoast / Rank Math fut, a meta és az OG az övé – itt csak a Product és a Store marad.
 */

defined('ABSPATH') || exit;

function slugshop_seo_plugin(): bool
{
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION');
}

function slugshop_meta_description(): string
{
    $text = '';
    if (is_front_page()) {
        $text = (string) slugshop_site('seo.homeDescription', '');
    } elseif (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $term = get_queried_object();
        $text = (string) (get_term_meta($term->term_id, 'slugshop_meta', true) ?: $term->description);
    } elseif (is_singular('product')) {
        $p = wc_get_product(get_queried_object_id());
        $text = $p ? (string) ($p->get_meta('slugshop_meta') ?: $p->get_short_description() ?: $p->get_description()) : '';
    } elseif (is_singular()) {
        $post = get_queried_object();
        $text = (string) (get_post_meta($post->ID, 'slugshop_meta', true) ?: $post->post_excerpt ?: $post->post_content);
    }
    $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks($text)))));
    return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157), " ,.;:-–") . '…' : $text;
}

add_action('wp_head', function () {
    $noindex = is_search() || (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) || !empty($_GET['rendezes']) || !empty($_GET['raktaron'])
        || !empty($_GET['oldal']) || !empty($_GET['q']) || !empty($_GET['modell']);
    if ($noindex) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
    if (slugshop_seo_plugin()) {
        return;
    }
    $desc = slugshop_meta_description();
    if ($desc !== '') {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }
    $img = slugshop_img('logo.webp');
    if (is_singular('product')) {
        $p = wc_get_product(get_queried_object_id());
        $img = $p && $p->get_image_id() ? (string) wp_get_attachment_image_url($p->get_image_id(), 'large') : $img;
    } elseif (is_singular() && has_post_thumbnail()) {
        $img = (string) get_the_post_thumbnail_url(null, 'large');
    }
    $og = [
        'og:type' => is_singular('product') ? 'product' : (is_singular('post') ? 'article' : 'website'),
        'og:site_name' => 'Slugshop', 'og:locale' => 'hu_HU',
        'og:title' => wp_get_document_title(), 'og:description' => $desc, 'og:image' => $img,
        'og:url' => is_front_page() ? home_url('/') : (is_singular() ? get_permalink() : (is_tax() ? get_term_link(get_queried_object()) : '')),
    ];
    foreach ($og as $k => $v) {
        if (is_string($v) && $v !== '') {
            echo '<meta property="' . esc_attr($k) . '" content="' . esc_attr($v) . '">' . "\n";
        }
    }
}, 3);

/** Strukturált adatok a lap végén (a morzsamenü a blokk renderelésekor gyűlik). */
add_action('wp_footer', function () {
    $graph = [];
    if (is_front_page()) {
        $co = (array) slugshop_site('company', []);
        $c = (array) slugshop_site('contact', []);
        $graph[] = array_filter([
            '@type' => 'Store', '@id' => home_url('/#store'), 'name' => 'Slugshop', 'legalName' => $co['name'] ?? '', 'url' => home_url('/'),
            'logo' => slugshop_img('logo.webp'), 'image' => slugshop_img('logo.webp'), 'telephone' => slugshop_site('phone'), 'email' => slugshop_site('email'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c['street'] ?? 'Megyeház u. 3.', 'addressLocality' => 'Jászberény', 'postalCode' => '5100', 'addressCountry' => 'HU'],
            'vatID' => $co['tax'] ?? '', 'sameAs' => array_values(array_filter((array) slugshop_site('social', []))),
        ]);
    }
    if (is_singular('product') && !slugshop_seo_plugin_has_product()) {
        $p = wc_get_product(get_queried_object_id());
        if ($p) {
            $graph[] = array_filter([
                '@type' => 'Product', 'name' => $p->get_name(), 'sku' => $p->get_sku(), 'url' => $p->get_permalink(),
                'image' => $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'large') : null,
                'description' => wp_strip_all_tags((string) ($p->get_short_description() ?: $p->get_description())),
                'offers' => $p->get_price() !== '' ? ['@type' => 'Offer', 'price' => (string) $p->get_price(), 'priceCurrency' => get_woocommerce_currency(),
                    'availability' => 'https://schema.org/' . ($p->is_in_stock() ? 'InStock' : 'OutOfStock'), 'url' => $p->get_permalink()] : null,
            ]);
        }
    }
    $crumbs = (array) ($GLOBALS['slugshop_breadcrumbs'] ?? []);
    if ($crumbs && !slugshop_seo_plugin()) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Kezdőlap', 'item' => home_url('/')]];
        foreach ($crumbs as $i => [$label, $url]) {
            $items[] = array_filter(['@type' => 'ListItem', 'position' => $i + 2, 'name' => $label, 'item' => $url ?: null]);
        }
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    if ($graph) {
        echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}, 20);

function slugshop_seo_plugin_has_product(): bool
{
    return defined('WPSEO_WOO_VERSION'); // a Yoast WooCommerce SEO saját Product-sémát ad
}

/** A régi meta-leírások (Joomla) a WordPress sitemapjában nem kellenek; a felhasználói sitemap ki. */
add_filter('wp_sitemaps_add_provider', fn($provider, $name) => $name === 'users' ? false : $provider, 10, 2);
add_filter('wp_sitemaps_post_types', function ($types) {
    unset($types['attachment'], $types[SLUGSHOP_MSG]);
    return $types;
});
