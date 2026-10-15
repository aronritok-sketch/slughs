<?php
/**
 * Régi (Joomla / VirtueMart) címek 301-gyel – csak 404 helyett, hogy élő oldalt sose írjunk felül.
 *
 * Az SEF-címek (kategória, termék, oldal, cikk) változatlanok (routes.php), ide csak a ritkább alakok jönnek:
 * - /index.php/… → /…
 * - nyelvi előtag: /hu/… → /…
 * - VirtueMart részletező: …/<termék>-detail, …/<termék>.html → a termék
 * - egy termék vagy kategória régi helyen (pl. áthelyezték): az útvonal utolsó része szerint
 * - /component/virtuemart/… → a termék / kategória az utolsó része szerint; más /component/…, ?option=com_… → nyitóoldal; /images/virtuemart/product/<fájl> → a termék, amelyiké a kép
 * Bővíthető: add_filter('slugshop_legacy_map', fn($m) => $m + ['/regi-cim' => '/uj-cim']).
 */

defined('ABSPATH') || exit;

add_action('template_redirect', function () {
    if (!is_404()) {
        return;
    }
    $path = '/' . slugshop_request_path();
    $map = (array) apply_filters('slugshop_legacy_map', (array) slugshop_site('legacyMap', []));
    if (isset($map[$path])) {
        wp_safe_redirect(slugshop_url($map[$path]), 301);
        exit;
    }
    $target = slugshop_legacy_target($path, (string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($target !== '' && untrailingslashit($target) !== untrailingslashit(home_url($path))) {
        wp_safe_redirect($target, 301);
        exit;
    }
}, 1);

function slugshop_legacy_target(string $path, string $query): string
{
    if (str_contains($query, 'option=com_')) {
        return home_url('/');
    }
    $clean = preg_replace('#^/(index\.php|hu|component/virtuemart)(?=/|$)#', '', $path);
    $clean = preg_replace('#(-detail|\.html?)$#', '', (string) $clean);
    if ($clean === '' || $clean === '/') {
        return home_url('/');
    }
    if ($clean !== $path) {
        $cats = slugshop_cat_paths();
        if (isset($cats[$clean])) {
            return get_term_link($cats[$clean], 'product_cat');
        }
        $page = get_page_by_path(ltrim($clean, '/'));
        if ($page && $page->post_type === 'page') {
            return get_permalink($page);
        }
    }
    $last = basename($clean);
    if ($last === '') {
        return '';
    }
    if (str_starts_with($path, '/images/') || str_starts_with($path, '/media/')) {
        $att = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [['key' => 'slugshop_source', 'value' => $path]]]);
        if ($att) {
            $parent = wp_get_post_parent_id($att[0]);
            return str_starts_with($path, '/media/') || !$parent ? (string) wp_get_attachment_url($att[0]) : (string) get_permalink($parent);
        }
        return '';
    }
    // Termék a slugja szerint (pl. más kategória alól), aztán kategória az utolsó része szerint.
    $ids = get_posts(['post_type' => 'product', 'name' => sanitize_title($last), 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']);
    if ($ids) {
        return get_permalink($ids[0]);
    }
    $term = get_term_by('slug', sanitize_title($last), 'product_cat');
    if ($term) {
        return get_term_link($term);
    }
    return str_starts_with($path, '/component/') ? home_url('/') : '';
}
