<?php
/**
 * Bolt: termékkártya, kategóriaoldal (oldalsó kategóriafa, alkategóriák, rendezés, „csak raktáron”, „Továbbiak
 * betöltése”), termékoldal (galéria, kosárba, kérdés, értesítés), keresés és az élő kereső REST-végpontja.
 * A HTML a WooCommerce klasszikus osztályait használja (li.product, .price, .single_add_to_cart_button …), mint az előnézet.
 */

defined('ABSPATH') || exit;

const SLUGSHOP_PER_PAGE = 24;

/** Egy kategória gyerekei a régi sorrendben (term meta „order”). */
function slugshop_child_cats(int $parent): array
{
    $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => $parent, 'hide_empty' => false]);
    $terms = is_array($terms) ? $terms : [];
    // A WooCommerce alapértelmezett („Egyéb”) kategóriája csak akkor látszik, ha van benne termék.
    $default = (int) get_option('default_product_cat');
    $terms = array_values(array_filter($terms, fn($t) => $t->term_id !== $default || $t->count > 0));
    usort($terms, fn($a, $b) => [(int) (get_term_meta($a->term_id, 'order', true) ?: 999), $a->name] <=> [(int) (get_term_meta($b->term_id, 'order', true) ?: 999), $b->name]);
    return $terms;
}

function slugshop_cat_label(WP_Term $t): string
{
    return (string) (get_term_meta($t->term_id, 'slugshop_menu', true) ?: $t->name);
}

/** A kategória ősei a gyökértől (a kategóriával együtt). */
function slugshop_cat_chain(?WP_Term $term): array
{
    $chain = [];
    while ($term instanceof WP_Term) {
        array_unshift($chain, $term);
        $term = $term->parent ? get_term($term->parent, 'product_cat') : null;
    }
    return $chain;
}

function slugshop_stock_text(WC_Product $p): string
{
    if (!$p->is_in_stock()) {
        return 'Elfogyott';
    }
    $t = (string) $p->get_meta('slugshop_stock_text');
    return $t !== '' ? $t : 'Raktáron';
}

function slugshop_product_card($id): string
{
    $p = function_exists('wc_get_product') ? wc_get_product($id) : null;
    if (!$p) {
        return '';
    }
    $in = $p->is_in_stock() && $p->is_purchasable();
    $img = $p->get_image_id() ? wp_get_attachment_image($p->get_image_id(), 'slugshop-card', false, ['loading' => 'lazy', 'alt' => $p->get_name()]) : slugshop_icon('box');
    $action = $in
        ? '<a href="' . esc_url($p->add_to_cart_url()) . '" rel="nofollow" class="button add_to_cart_button" data-add="' . (int) $p->get_id() . '">' . slugshop_icon('cart', 'icon') . 'Kosárba</a>'
        : '<button type="button" class="button" data-notify="' . (int) $p->get_id() . '" data-name="' . esc_attr($p->get_name()) . '">Értesítést kérek!</button>';
    return '<li class="product ' . ($in ? 'instock' : 'outofstock') . '">' . ($in ? '' : '<span class="badge-out">Elfogyott</span>')
        . '<a href="' . esc_url($p->get_permalink()) . '" class="woocommerce-LoopProduct-link"><span class="img">' . $img . '</span>'
        . '<h2 class="woocommerce-loop-product__title">' . esc_html($p->get_name()) . '</h2>'
        . '<span class="price">' . esc_html(slugshop_price($p->get_price())) . '</span></a>'
        . '<div class="actions">' . $action . '</div></li>';
}

function slugshop_sidebar_tree(?WP_Term $current): string
{
    $open = array_map(fn($t) => $t->term_id, slugshop_cat_chain($current));
    $node = function (WP_Term $t) use (&$node, $open, $current) {
        $kids = slugshop_child_cats($t->term_id);
        $is_open = in_array($t->term_id, $open, true);
        $label = slugshop_cat_label($t);
        $out = '<li><div class="row"><a href="' . esc_url(get_term_link($t)) . '"' . ($current && $current->term_id === $t->term_id ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        if ($kids) {
            $out .= '<button class="tog" type="button" aria-expanded="' . ($is_open ? 'true' : 'false') . '" aria-label="' . esc_attr($label . ' alkategóriái') . '">' . slugshop_icon('down') . '</button>';
        }
        $out .= '</div>';
        if ($kids) {
            $out .= '<ul' . ($is_open ? '' : ' hidden') . '>' . implode('', array_map($node, $kids)) . '</ul>';
        }
        return $out . '</li>';
    };
    return '<ul class="cat-tree">' . implode('', array_map($node, slugshop_child_cats(0))) . '</ul>';
}

/** A lista lekérdezése: [azonosítók, összes]. */
function slugshop_list_query(?WP_Term $term, string $sort, bool $only_stock, int $page): array
{
    $args = [
        'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => SLUGSHOP_PER_PAGE, 'paged' => max(1, $page),
        'fields' => 'ids', 'no_found_rows' => false,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ];
    if ($term) {
        $args['tax_query'] = [['taxonomy' => 'product_cat', 'terms' => $term->term_id, 'include_children' => false]];
    }
    if ($only_stock) {
        $args['meta_query'] = [['key' => '_stock_status', 'value' => 'instock']];
    }
    if ($sort === 'asc' || $sort === 'desc') {
        $args['meta_key'] = '_price';
        $args['orderby'] = ['meta_value_num' => strtoupper($sort), 'title' => 'ASC'];
    } elseif ($sort === 'name') {
        $args['orderby'] = ['title' => 'ASC'];
    }
    $q = new WP_Query($args);
    return [array_map('intval', $q->posts), (int) $q->found_posts];
}

function slugshop_render_category(): string
{
    $term = is_tax('product_cat') ? get_queried_object() : null;
    $term = $term instanceof WP_Term ? $term : null;
    $sort = isset($_GET['rendezes']) ? sanitize_key(wp_unslash($_GET['rendezes'])) : 'default';
    $sort = in_array($sort, ['default', 'asc', 'desc', 'name'], true) ? $sort : 'default';
    $stock = !empty($_GET['raktaron']);
    $page = max(1, (int) ($_GET['oldal'] ?? 1));

    $chain = slugshop_cat_chain($term);
    $crumbs = [];
    foreach ($chain as $i => $t) {
        $crumbs[] = [slugshop_cat_label($t), $i < count($chain) - 1 ? get_term_link($t) : null];
    }
    if (!$term) {
        $crumbs = [['Minden termék', null]];
    }
    $title = $term ? $term->name : 'Minden termék';
    $desc = $term ? (string) $term->description : '';
    $subs = '';
    foreach ($term ? slugshop_child_cats($term->term_id) : slugshop_child_cats(0) as $ch) {
        $thumb = (int) get_term_meta($ch->term_id, 'thumbnail_id', true);
        $img = $thumb ? wp_get_attachment_image($thumb, 'slugshop-card', false, ['loading' => 'lazy', 'alt' => '']) : '<span class="noimg">' . slugshop_icon('box') . '</span>';
        $subs .= '<a class="subcat" href="' . esc_url(get_term_link($ch)) . '"><span class="img">' . $img . '</span><h3>' . esc_html(slugshop_cat_label($ch)) . '</h3></a>';
    }
    [$ids, $total] = $term || !$subs ? slugshop_list_query($term, $sort, $stock, $page) : [[], 0];
    $shown = min($total, $page * SLUGSHOP_PER_PAGE);
    $base = $term ? get_term_link($term) : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'));

    $list = '';
    if ($total || $stock) {
        $opts = ['default' => 'Alapértelmezett sorrend', 'asc' => 'Ár szerint növekvő', 'desc' => 'Ár szerint csökkenő', 'name' => 'Név szerint'];
        $select = '';
        foreach ($opts as $k => $l) {
            $select .= '<option value="' . esc_attr($k) . '"' . selected($sort, $k, false) . '>' . esc_html($l) . '</option>';
        }
        $next = $shown < $total ? add_query_arg(array_filter(['rendezes' => $sort !== 'default' ? $sort : null, 'raktaron' => $stock ? 1 : null, 'oldal' => $page + 1]), $base) : '';
        $list = '<form class="toolbar" method="get" action="' . esc_url($base) . '" data-autosubmit>'
            . '<span class="count" id="count">Találatok: ' . (int) $shown . ' / ' . (int) $total . '</span>'
            . '<label class="check"><input type="checkbox" id="only-stock" name="raktaron" value="1"' . checked($stock, true, false) . '> Csak raktáron lévő</label>'
            . '<label><span class="sr-only">Rendezés</span><select id="sort" name="rendezes">' . $select . '</select></label>'
            . '<noscript><button class="btn btn--sm" type="submit">Szűrés</button></noscript></form>'
            . '<ul class="products" id="grid">' . (implode('', array_map('slugshop_product_card', $ids)) ?: '<li class="empty" style="grid-column:1/-1">Nincs raktáron lévő termék ebben a kategóriában.</li>') . '</ul>'
            . '<div class="more-wrap">' . ($next ? '<a class="btn" id="more" href="' . esc_url($next) . '" data-more>Továbbiak betöltése</a>' : '') . '</div>';
    } elseif (!$subs) {
        $list = '<p class="empty">Ebben a kategóriában jelenleg nincs termék.</p>';
    }

    return '<div class="wrap page">' . slugshop_crumbs($crumbs) . (function_exists('wc_print_notices') ? wc_print_notices(true) : '')
        . '<div class="shop-layout"><aside class="sidebar" id="sidebar" aria-label="Kategóriák"><h2>Kategóriák</h2>' . slugshop_sidebar_tree($term) . '</aside><div>'
        . '<h1 class="cat-title">' . esc_html($title) . '</h1>'
        . ($desc !== '' && $page === 1 ? '<div class="cat-desc prose">' . wp_kses_post(wpautop($desc)) . '</div>' : '')
        . '<button class="btn btn--sm filter-btn" type="button" data-toggle-sidebar aria-controls="sidebar" style="margin-bottom:var(--space-4)">Kategóriák</button>'
        . ($subs && $page === 1 ? '<div class="subcats">' . $subs . '</div>' : '')
        . $list . '</div></div></div>';
}

function slugshop_render_product(): string
{
    $p = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
    if (!$p) {
        return '';
    }
    $GLOBALS['product'] = $p;
    $cat = slugshop_primary_cat($p->get_id());
    $crumbs = [];
    foreach (slugshop_cat_chain($cat) as $t) {
        $crumbs[] = [slugshop_cat_label($t), get_term_link($t)];
    }
    $crumbs[] = [$p->get_name(), null];
    $imgs = array_filter(array_merge([$p->get_image_id()], $p->get_gallery_image_ids()));
    $main = $imgs ? wp_get_attachment_image((int) reset($imgs), 'large', false, ['id' => 'main-img', 'alt' => $p->get_name(), 'fetchpriority' => 'high']) : slugshop_icon('box');
    $thumbs = '';
    if (count($imgs) > 1) {
        $thumbs = '<div class="thumbs">';
        $i = 0;
        foreach ($imgs as $img) {
            $large = wp_get_attachment_image_url((int) $img, 'large');
            $thumbs .= '<button type="button" data-thumb="' . esc_url((string) $large) . '" aria-current="' . ($i === 0 ? 'true' : 'false') . '" aria-label="' . ($i + 1) . '. kép">' . wp_get_attachment_image((int) $img, 'thumbnail', false, ['loading' => 'lazy', 'alt' => '']) . '</button>';
            $i++;
        }
        $thumbs .= '</div>';
    }
    $in = $p->is_in_stock() && $p->is_purchasable();
    $ask = '<button class="ask-btn" type="button" data-ask="' . (int) $p->get_id() . '" data-name="' . esc_attr($p->get_name()) . '" aria-label="Tegye fel kérdését a termékről">' . slugshop_icon('mail') . '</button>';
    if ($in) {
        $form = '<form class="cart-form cart" method="post" action="' . esc_url($p->get_permalink()) . '" data-cart-form enctype="multipart/form-data">'
            . '<div class="quantity"><button type="button" data-q="-1" aria-label="Kevesebb">−</button><input id="qty" name="quantity" type="number" min="1" max="' . esc_attr((string) ($p->get_max_purchase_quantity() > 0 ? $p->get_max_purchase_quantity() : 99)) . '" value="1" aria-label="Mennyiség" inputmode="numeric"><button type="button" data-q="1" aria-label="Több">+</button></div>'
            . '<button class="button single_add_to_cart_button" type="submit" name="add-to-cart" value="' . (int) $p->get_id() . '">' . slugshop_icon('cart', 'icon') . 'Kosárba</button>' . $ask . '</form>';
    } else {
        $form = '<div class="cart-form"><button class="button" type="button" data-notify="' . (int) $p->get_id() . '" data-name="' . esc_attr($p->get_name()) . '">Értesítést kérek!</button>' . $ask . '</div>';
    }
    $short = (string) $p->get_short_description();
    $desc = (string) $p->get_description();
    $related = '';
    if ($cat) {
        $rel = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 4, 'fields' => 'ids', 'post__not_in' => [$p->get_id()],
            'orderby' => 'menu_order', 'order' => 'ASC', 'tax_query' => [['taxonomy' => 'product_cat', 'terms' => $cat->term_id, 'include_children' => false]]]);
        if ($rel) {
            $related = '<section class="related"><h2>Ebben a kategóriában még</h2><ul class="products">' . implode('', array_map('slugshop_product_card', $rel)) . '</ul></section>';
        }
    }
    return '<div class="wrap page">' . slugshop_crumbs($crumbs) . (function_exists('wc_print_notices') ? wc_print_notices(true) : '')
        . '<div class="product-single product">'
        . '<div class="woocommerce-product-gallery"><div class="woocommerce-product-gallery__image">' . $main . '</div>' . $thumbs . '</div>'
        . '<div class="summary"><h1 class="product_title">' . esc_html($p->get_name()) . '</h1>'
        . '<span class="price">' . esc_html(slugshop_price($p->get_price())) . '</span>'
        . '<div class="stock-row"><span class="stock ' . ($p->is_in_stock() ? 'in-stock' : 'out-of-stock') . '">' . esc_html(slugshop_stock_text($p)) . '</span>' . ($p->get_sku() ? '<span>Cikkszám: ' . esc_html($p->get_sku()) . '</span>' : '') . '</div>'
        . ($short !== '' ? '<div class="woocommerce-product-details__short-description prose">' . wp_kses_post(wpautop($short)) . '</div>' : '')
        . $form
        . ($cat ? '<a class="back-link" href="' . esc_url(get_term_link($cat)) . '"><b>Vissza:</b> ' . esc_html(slugshop_cat_label($cat)) . '</a>' : '')
        . '</div></div>'
        . '<div class="wc-tabs"><h2 class="tab">Leírás</h2><div class="panel"><div class="prose">' . wp_kses_post(wpautop($desc !== '' ? $desc : ($short !== '' ? $short : '<p>A termékhez nincs részletes leírás.</p>'))) . '</div></div></div>'
        . $related . '</div>';
}

/** Termékkeresés névre és cikkszámra (ékezet- és kisbetű-független: az adatbázis rendezése miatt). */
function slugshop_search_products(string $q, int $limit = 60): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $by_text = get_posts(['post_type' => 'product', 'post_status' => 'publish', 's' => $q, 'numberposts' => $limit, 'fields' => 'ids', 'orderby' => 'relevance']);
    $by_sku = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => $limit, 'fields' => 'ids',
        'meta_query' => [['key' => '_sku', 'value' => $q, 'compare' => 'LIKE']]]);
    return array_slice(array_values(array_unique(array_map('intval', array_merge($by_sku, $by_text)))), 0, $limit);
}

function slugshop_render_search(): string
{
    $q = get_search_query(false);
    $ids = slugshop_search_products($q);
    $posts = $q !== '' ? get_posts(['post_type' => 'post', 's' => $q, 'numberposts' => 12]) : [];
    return '<div class="wrap page">' . slugshop_crumbs([['Keresés', null]]) . '<h1 class="page-title">Keresés: „' . esc_html($q) . '”</h1>'
        . '<p>' . count($ids) . ' termék' . ($posts ? ' és ' . count($posts) . ' cikk' : '') . '.</p>'
        . ($ids ? '<ul class="products search-grid">' . implode('', array_map('slugshop_product_card', $ids)) . '</ul>' : '<div class="empty">Nincs találat. Próbáld kevesebb szóval vagy cikkszámmal.</div>')
        . ($posts ? '<h2 class="sec-title" style="margin-top:var(--space-7)">Cikkek</h2><div class="cards">' . implode('', array_map('slugshop_article_card', $posts)) . '</div>' : '')
        . '</div>';
}

/** Élő kereső a fejlécben: GET slugshop/v1/search?q= → az első 12 találat. */
add_action('rest_api_init', function () {
    register_rest_route('slugshop/v1', '/search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => ['q' => ['type' => 'string', 'required' => true]],
        'callback' => function (WP_REST_Request $r) {
            $q = sanitize_text_field((string) $r->get_param('q'));
            $ids = mb_strlen($q) >= 2 ? slugshop_search_products($q, 60) : [];
            $items = [];
            foreach (array_slice($ids, 0, 12) as $id) {
                $p = wc_get_product($id);
                if ($p) {
                    $items[] = ['name' => $p->get_name(), 'url' => $p->get_permalink(), 'img' => (string) wp_get_attachment_image_url($p->get_image_id(), 'thumbnail'),
                        'price' => slugshop_price($p->get_price()), 'stock' => slugshop_stock_text($p)];
                }
            }
            return ['total' => count($ids), 'items' => $items, 'all' => add_query_arg(['s' => $q, 'post_type' => 'product'], home_url('/'))];
        },
    ]);
});
