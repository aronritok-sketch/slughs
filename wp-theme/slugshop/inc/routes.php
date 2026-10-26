<?php
/**
 * A régi slugshop.hu URL-jei változatlanul (Joomla/VirtueMart SEF-címek, záró perjel nélkül):
 *
 *   /zan-slugok                                    termékkategória (a teljes útvonal, alap nélkül)
 *   /fx-termekek/fx-kiegeszitok/bipodok            alkategória
 *   /zan-slugok/zan-457-11-2c6-mm                  termék = a fő kategória útvonala + a termék slugja
 *   /cikkeink/cikkek/<cikk>, /cikkeink/articles/<cikk>, /cikkeink/cikkek    cikkek és a két cikk-kategória
 *   /szerviz, /videok/fx-king, /kapcsolat …        oldalak (a WordPress saját oldal-hierarchiája)
 *
 * A kategória útvonala a termékkategória „slugshop_path” metájában van (a telepítő írja a régi oldal szerint; új
 * kategóriánál a szülő útvonala + a slug). A termék fő kategóriája a „slugshop_primary_cat” meta (ha nincs: az első).
 * Ha egy terméket nem a fő kategóriája alól nyitnak meg (több kategóriás termék), 301-gyel a saját címére megy.
 */

defined('ABSPATH') || exit;

const SLUGSHOP_POST_CATS = ['cikkek', 'articles'];

/** A termékkategória útvonala, pl. „/zan-slugok/22-5-5mm-slugs”. */
function slugshop_term_path($term): string
{
    $term = get_term($term, 'product_cat');
    if (!$term instanceof WP_Term) {
        return '';
    }
    $path = (string) get_term_meta($term->term_id, 'slugshop_path', true);
    if ($path !== '') {
        return $path;
    }
    $parent = $term->parent ? slugshop_term_path($term->parent) : '';
    return $parent . '/' . $term->slug;
}

/** Útvonal → kategória azonosító (gyorsítótárazva; minden kategória-változáskor újraépül). */
function slugshop_cat_paths(): array
{
    $map = get_option('slugshop_cat_paths');
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach (is_array($terms) ? $terms : [] as $t) {
        $map[slugshop_term_path($t)] = (int) $t->term_id;
    }
    update_option('slugshop_cat_paths', $map, true);
    return $map;
}

function slugshop_flush_cat_paths(): void
{
    delete_option('slugshop_cat_paths');
}
add_action('created_product_cat', 'slugshop_flush_cat_paths');
add_action('edited_product_cat', 'slugshop_flush_cat_paths');
add_action('delete_product_cat', 'slugshop_flush_cat_paths');

/** A termék fő kategóriája (WP_Term vagy null). */
function slugshop_primary_cat(int $product_id): ?WP_Term
{
    $id = (int) get_post_meta($product_id, 'slugshop_primary_cat', true);
    $terms = get_the_terms($product_id, 'product_cat');
    if (!is_array($terms) || !$terms) {
        return null;
    }
    foreach ($terms as $t) {
        if ($t->term_id === $id) {
            return $t;
        }
    }
    usort($terms, fn($a, $b) => $a->term_id <=> $b->term_id);
    return $terms[0];
}

/** A termék útvonala: a fő kategória útvonala + slug. */
function slugshop_product_path(int $product_id): string
{
    $post = get_post($product_id);
    if (!$post) {
        return '';
    }
    $cat = slugshop_primary_cat($product_id);
    $name = $post->post_name !== '' ? $post->post_name : sanitize_title($post->post_title);
    return ($cat ? slugshop_term_path($cat) : '/termek') . '/' . $name;
}

/** Linkek a régi formában. */
add_filter('term_link', function ($url, $term, $taxonomy) {
    if ($taxonomy === 'product_cat') {
        return home_url(slugshop_term_path($term));
    }
    if ($taxonomy === 'category' && in_array($term->slug, SLUGSHOP_POST_CATS, true)) {
        return home_url('/cikkeink/' . $term->slug);
    }
    return $url;
}, 10, 3);

add_filter('post_type_link', function ($url, $post) {
    if ($post->post_type === 'product' && in_array($post->post_status, ['publish', 'private'], true)) {
        return home_url(slugshop_product_path($post->ID));
    }
    return $url;
}, 10, 2);

add_filter('post_link', function ($url, $post) {
    if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
        return $url;
    }
    foreach ((array) get_the_category($post->ID) as $cat) {
        if (in_array($cat->slug, SLUGSHOP_POST_CATS, true)) {
            return home_url('/cikkeink/' . $cat->slug . '/' . $post->post_name);
        }
    }
    return $url;
}, 10, 2);

/** A kért útvonal (a WordPress telepítési alkönyvtára nélkül), perjelek nélkül. */
function slugshop_request_path(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $base = (string) parse_url(home_url('/'), PHP_URL_PATH);
    if ($base !== '/' && str_starts_with($path, rtrim($base, '/'))) {
        $path = substr($path, strlen(rtrim($base, '/')));
    }
    return trim(rawurldecode($path), '/');
}

/** A régi címek feloldása a WordPress saját szabályai után: ha kategória, termék vagy cikk, az nyer. */
add_filter('request', function (array $vars): array {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return $vars;
    }
    $path = slugshop_request_path();
    if ($path === '' || str_starts_with($path, 'wp-') || isset($vars['rest_route'])) {
        return $vars;
    }
    $cats = slugshop_cat_paths();
    if (isset($cats['/' . $path])) {
        $term = get_term($cats['/' . $path], 'product_cat');
        // Ha egy termék címe pontosan ugyanez (a régi boltban az ELR kategória és termék így ütközött), a termék nyer, mint régen.
        $slash = strrpos($path, '/');
        $same = $slash !== false ? get_posts(['post_type' => 'product', 'name' => substr($path, $slash + 1), 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']) : [];
        if ($same && slugshop_product_path((int) $same[0]) === '/' . $path) {
            $slug = substr($path, $slash + 1);
            return ['product' => $slug, 'post_type' => 'product', 'name' => $slug];
        }
        if ($term instanceof WP_Term) {
            return ['product_cat' => $term->slug];
        }
    }
    if (preg_match('#^cikkeink/(' . implode('|', SLUGSHOP_POST_CATS) . ')(?:/([^/]+))?$#', $path, $m)) {
        return empty($m[2]) ? ['category_name' => $m[1]] : ['name' => $m[2], 'post_type' => 'post'];
    }
    $slash = strrpos($path, '/');
    if ($slash !== false && isset($cats['/' . substr($path, 0, $slash)])) {
        $slug = substr($path, $slash + 1);
        $ids = get_posts(['post_type' => 'product', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']);
        if ($ids) {
            return ['product' => $slug, 'post_type' => 'product', 'name' => $slug];
        }
    }
    return $vars;
}, 20);

/** Ismétlődő tétel (a telepítő rejtette el) → a megmaradó; több kategóriás termék más kategória alól → a saját címére. */
add_action('template_redirect', function () {
    if (!is_singular('product')) {
        return;
    }
    $dup = (string) get_post_meta(get_queried_object_id(), 'slugshop_dup_of', true);
    if ($dup !== '') {
        $keep = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'slugshop_key', 'meta_value' => $dup]);
        if ($keep) {
            wp_safe_redirect(get_permalink($keep[0]), 301);
            exit;
        }
    }
    $want = slugshop_product_path(get_queried_object_id());
    if ($want !== '' && '/' . slugshop_request_path() !== $want) {
        $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        wp_safe_redirect(home_url($want) . ($query !== '' ? '?' . $query : ''), 301);
        exit;
    }
}, 5);

/** A WordPress ne „javítsa” a régi címeket (pl. perjel, hasonló nevű oldal keresése). */
add_filter('redirect_canonical', function ($redirect, $requested) {
    if (is_tax('product_cat') || is_singular('product') || is_category(SLUGSHOP_POST_CATS) || (is_singular('post'))) {
        return false;
    }
    return $redirect;
}, 10, 2);
