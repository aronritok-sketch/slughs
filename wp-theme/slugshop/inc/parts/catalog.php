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

/** A kategória felirata: mindig a WooCommerce-ben mentett név (az ügyfél átnevezése mindenhol látszik). */
function slugshop_cat_label(WP_Term $t): string
{
    return (string) $t->name;
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

/** A rejtett termékek (pl. az ismétlődő tétel) kizárása a saját listákból: 'catalog' vagy 'search'. */
function slugshop_visible_tax(string $where = 'catalog'): array
{
    return ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-' . $where], 'operator' => 'NOT IN'];
}

/** Gyári FX-cikkszámok a gyári robbantott ábrák szerint (setup/data/fxparts.json, tools/wp/fxparts.py). */
function slugshop_fxparts(): array
{
    $d = slugshop_setup_data('fxparts');
    return ['models' => (array) ($d['models'] ?? []), 'parts' => (array) ($d['parts'] ?? [])];
}

/** A termék gyári cikkszáma (pl. „16092”) – üres, ha nincs. */
function slugshop_mpn(int $id): string
{
    return (string) get_post_meta($id, 'slugshop_mpn', true);
}

/** Azok a modellek (slug => név), amelyek gyári ábráján a kategória (vagy a lista) valamelyik terméke szerepel. */
function slugshop_models_for(array $ids): array
{
    $fx = slugshop_fxparts();
    $out = [];
    foreach ($ids as $id) {
        foreach ((array) ($fx['parts'][slugshop_mpn((int) $id)]['models'] ?? []) as $m) {
            $out[$m] = $fx['models'][$m] ?? $m;
        }
    }
    asort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/** Egy modell gyári cikkszámai (a meta_query IN-jéhez). */
function slugshop_model_mpns(string $model): array
{
    $out = [];
    foreach (slugshop_fxparts()['parts'] as $num => $part) {
        if (in_array($model, (array) $part['models'], true)) {
            $out[] = (string) $num;
        }
    }
    return $out;
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

/**
 * A lista lekérdezése: [azonosítók, összes]. $filter: 'model' (gyári ábra szerinti modell), 'q' (név, cikkszám).
 * $only_page: csak a kért oldal (a „Továbbiak betöltése” kérése); különben az 1.–$page. oldal együtt, hogy a ?oldal=3
 * újratöltve vagy megosztva is a teljes, addig betöltött listát mutassa.
 */
function slugshop_list_query(?WP_Term $term, string $sort, bool $only_stock, int $page, array $filter = [], bool $only_page = false): array
{
    $page = max(1, $page);
    $args = [
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => $only_page ? SLUGSHOP_PER_PAGE : SLUGSHOP_PER_PAGE * $page, 'paged' => $only_page ? $page : 1,
        'fields' => 'ids', 'no_found_rows' => false,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'tax_query' => [slugshop_visible_tax()],
        'meta_query' => [],
    ];
    if ($term) {
        $args['tax_query'][] = ['taxonomy' => $term->taxonomy, 'terms' => $term->term_id, 'include_children' => false];
    }
    if ($only_stock) {
        $args['meta_query'][] = ['key' => '_stock_status', 'value' => 'instock'];
    }
    if (!empty($filter['model'])) {
        $args['meta_query'][] = ['key' => 'slugshop_mpn', 'value' => slugshop_model_mpns($filter['model']) ?: ['-'], 'compare' => 'IN'];
    }
    if (isset($filter['q']) && $filter['q'] !== '') {
        $args['post__in'] = slugshop_search_products($filter['q'], 500) ?: [0];
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
    // Termékkategória, márka (WooCommerce márkák: /marka/…) vagy címke: ugyanaz a lista, a márkánál a kategóriafa nélkül.
    $term = function_exists('is_product_taxonomy') && is_product_taxonomy() ? get_queried_object() : null;
    $term = $term instanceof WP_Term ? $term : null;
    $is_cat = $term && $term->taxonomy === 'product_cat';
    $sort = isset($_GET['rendezes']) ? sanitize_key(wp_unslash($_GET['rendezes'])) : 'default';
    $sort = in_array($sort, ['default', 'asc', 'desc', 'name'], true) ? $sort : 'default';
    $stock = !empty($_GET['raktaron']);
    $page = max(1, (int) ($_GET['oldal'] ?? 1));
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    // Modellválasztó: ha a kategória termékei között van gyári FX-alkatrész (a gyári robbantott ábrák szerint)
    $models = [];
    if ($term) {
        $mpn_ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
            'tax_query' => [['taxonomy' => $term->taxonomy, 'terms' => $term->term_id, 'include_children' => false]],
            'meta_query' => [['key' => 'slugshop_mpn', 'value' => '', 'compare' => '!=']]]);
        $models = slugshop_models_for($mpn_ids);
    }
    $model = isset($_GET['modell']) ? sanitize_key(wp_unslash($_GET['modell'])) : '';
    $model = isset($models[$model]) ? $model : '';
    $filtered = $model !== '' || $q !== '';

    $chain = $is_cat ? slugshop_cat_chain($term) : [];
    $crumbs = [];
    foreach ($chain as $i => $t) {
        $crumbs[] = [slugshop_cat_label($t), $i < count($chain) - 1 ? get_term_link($t) : null];
    }
    if ($term && !$is_cat) {
        $tax = get_taxonomy($term->taxonomy);
        $crumbs = [[$tax ? (string) $tax->labels->name : 'Termékek', null], [$term->name, null]];
    }
    if (!$term) {
        $crumbs = [['Minden termék', null]];
    }
    $title = $term ? $term->name : 'Minden termék';
    $desc = $term ? (string) $term->description : '';
    $subs = '';
    foreach ($is_cat ? slugshop_child_cats($term->term_id) : ($term ? [] : slugshop_child_cats(0)) as $ch) {
        $thumb = (int) get_term_meta($ch->term_id, 'thumbnail_id', true);
        $img = $thumb ? wp_get_attachment_image($thumb, 'slugshop-card', false, ['loading' => 'lazy', 'alt' => '']) : '<span class="noimg">' . slugshop_icon('box') . '</span>';
        $subs .= '<a class="subcat" href="' . esc_url(get_term_link($ch)) . '"><span class="img">' . $img . '</span><h3>' . esc_html(slugshop_cat_label($ch)) . '</h3></a>';
    }
    [$ids, $total] = $term || !$subs ? slugshop_list_query($term, $sort, $stock, $page, ['model' => $model, 'q' => $q], !empty($_GET['resz'])) : [[], 0];
    $shown = min($total, $page * SLUGSHOP_PER_PAGE);
    $base = $term ? get_term_link($term) : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'));

    $list = '';
    if ($total || $stock || $filtered) {
        $opts = ['default' => 'Alapértelmezett sorrend', 'asc' => 'Ár szerint növekvő', 'desc' => 'Ár szerint csökkenő', 'name' => 'Név szerint'];
        $select = '';
        foreach ($opts as $k => $l) {
            $select .= '<option value="' . esc_attr($k) . '"' . selected($sort, $k, false) . '>' . esc_html($l) . '</option>';
        }
        $next = $shown < $total ? add_query_arg(array_map('rawurlencode', array_filter(['modell' => $model ?: null, 'q' => $q !== '' ? $q : null, 'rendezes' => $sort !== 'default' ? $sort : null, 'raktaron' => $stock ? 1 : null, 'oldal' => $page + 1])), $base) : '';
        $model_select = '';
        if ($models) {
            $model_select = '<label class="model-pick"><span>Modell</span><select name="modell" id="model"><option value="">Minden modell</option>';
            foreach ($models as $k => $l) {
                $model_select .= '<option value="' . esc_attr($k) . '"' . selected($model, $k, false) . '>' . esc_html($l) . '</option>';
            }
            $model_select .= '</select></label>';
        }
        $finder = '<div class="list-finder">' . $model_select
            . '<label class="list-search"><span class="sr-only">Keresés ebben a kategóriában</span><input type="search" name="q" value="' . esc_attr($q) . '" placeholder="' . esc_attr($models ? 'Gyári cikkszám (pl. 16092) vagy név' : 'Név vagy cikkszám') . '"></label>'
            . '<button class="btn btn--sm" type="submit">Keresés</button>'
            . ($filtered ? '<a class="list-reset" href="' . esc_url($base) . '">Szűrés törlése</a>' : '') . '</div>';
        $list = '<form class="toolbar" method="get" action="' . esc_url($base) . '" data-autosubmit>' . $finder
            . '<span class="count" id="count">Találatok: ' . (int) $shown . ' / ' . (int) $total . '</span>'
            . '<label class="check"><input type="checkbox" id="only-stock" name="raktaron" value="1"' . checked($stock, true, false) . '> Csak raktáron lévő</label>'
            . '<label><span class="sr-only">Rendezés</span><select id="sort" name="rendezes">' . $select . '</select></label>'
            . '</form>'
            . ($model !== '' ? '<p class="list-note">A gyári robbantott ábrák szerint a(z) ' . esc_html($models[$model]) . ' alkatrészei. Az ábrák a <a href="' . esc_url(slugshop_url('/letoltesek')) . '">Letöltések</a> között vannak.</p>' : '')
            . '<ul class="products" id="grid">' . (implode('', array_map('slugshop_product_card', $ids)) ?: '<li class="empty" style="grid-column:1/-1">' . ($filtered ? 'Nincs a szűrésnek megfelelő termék ebben a kategóriában.' : 'Nincs raktáron lévő termék ebben a kategóriában.') . '</li>') . '</ul>'
            . '<div class="more-wrap">' . ($next ? '<a class="btn" id="more" href="' . esc_url($next) . '" data-more>Továbbiak betöltése</a>' : '') . '</div>';
    } elseif (!$subs) {
        $list = '<p class="empty">Ebben a kategóriában jelenleg nincs termék.</p>';
    }

    return '<div class="wrap page">' . slugshop_crumbs($crumbs) . (function_exists('wc_print_notices') ? wc_print_notices(true) : '')
        . '<div class="shop-layout"><aside class="sidebar" id="sidebar" aria-label="Kategóriák"><h2>Kategóriák</h2>' . slugshop_sidebar_tree($is_cat ? $term : null) . '</aside><div>'
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
    $ask = '<button class="ask-btn" type="button" data-ask="' . (int) $p->get_id() . '" data-name="' . esc_attr($p->get_name()) . '" aria-label="Kérdezz a termékről">' . slugshop_icon('mail') . '</button>';
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
            'orderby' => 'menu_order', 'order' => 'ASC', 'tax_query' => [slugshop_visible_tax(), ['taxonomy' => 'product_cat', 'terms' => $cat->term_id, 'include_children' => false]]]);
        if ($rel) {
            $related = '<section class="related"><h2>Ebben a kategóriában még</h2><ul class="products">' . implode('', array_map('slugshop_product_card', $rel)) . '</ul></section>';
        }
    }
    return '<div class="wrap page">' . slugshop_crumbs($crumbs) . (function_exists('wc_print_notices') ? wc_print_notices(true) : '')
        . '<div class="product-single product">'
        . '<div class="woocommerce-product-gallery"><div class="woocommerce-product-gallery__image">' . $main . '</div>' . $thumbs . '</div>'
        . '<div class="summary"><h1 class="product_title">' . esc_html($p->get_name()) . '</h1>'
        . '<span class="price">' . esc_html(slugshop_price($p->get_price())) . '</span>'
        . '<div class="stock-row"><span class="stock ' . ($p->is_in_stock() ? 'in-stock' : 'out-of-stock') . '">' . esc_html(slugshop_stock_text($p)) . '</span></div>'
        . slugshop_product_ids($p)
        . ($short !== '' ? '<div class="woocommerce-product-details__short-description prose">' . wp_kses_post(wpautop($short)) . '</div>' : '')
        . slugshop_product_compat($p)
        . $form . (function_exists('slugshop_is_shop_only') && slugshop_is_shop_only($p->get_id()) ? '<div class="ship-info shop-only"><b>Csak az üzletben</b><p>' . esc_html(slugshop_shop_only_notice()) . '</p></div>' : slugshop_shipping_box())
        . ($cat ? '<a class="back-link" href="' . esc_url(get_term_link($cat)) . '"><b>Vissza:</b> ' . esc_html(slugshop_cat_label($cat)) . '</a>' : '')
        . '</div></div>'
        . '<div class="wc-tabs"><h2 class="tab">Leírás</h2><div class="panel"><div class="prose">' . wp_kses_post(wpautop($desc !== '' ? $desc : ($short !== '' ? $short : '<p>A termékhez nincs részletes leírás.</p>'))) . '</div></div></div>'
        . $related . '</div>';
}

/** Gyári és belső cikkszám külön sorban (a gyári FX-szám a névből, a belső a JUTA vonalkódja). */
function slugshop_product_ids(WC_Product $p): string
{
    $rows = '';
    $mpn = slugshop_mpn($p->get_id());
    if ($mpn !== '') {
        $rows .= '<div><dt>Gyári cikkszám</dt><dd>FX' . esc_html($mpn) . '</dd></div>';
    }
    $sku = $p->get_sku() ?: (string) $p->get_meta('slugshop_old_sku');
    if ($sku !== '') {
        $rows .= '<div><dt>' . ($mpn !== '' ? 'Belső cikkszám' : 'Cikkszám') . '</dt><dd>' . esc_html($sku) . '</dd></div>';
    }
    return $rows !== '' ? '<dl class="product-ids">' . $rows . '</dl>' : '';
}

/** Melyik FX modellekhez: csak ha a gyári cikkszám szerepel a modell gyári robbantott ábráján. */
function slugshop_product_compat(WC_Product $p): string
{
    $fx = slugshop_fxparts();
    $part = $fx['parts'][slugshop_mpn($p->get_id())] ?? null;
    if (!$part || empty($part['models'])) {
        return '';
    }
    $base = slugshop_url('/fx-alkatreszek');
    $links = array_map(fn($m) => '<a href="' . esc_url(add_query_arg('modell', $m, $base)) . '">' . esc_html($fx['models'][$m] ?? $m) . '</a>', (array) $part['models']);
    return '<div class="compat"><b>A gyári robbantott ábrák szerint ezekben a modellekben szerepel:</b> ' . implode(', ', $links)
        . '<small>Gyári megnevezés: ' . esc_html((string) $part['name']) . ' · <a href="' . esc_url(slugshop_url('/letoltesek')) . '">Robbantott ábrák</a></small></div>';
}

/** Rövid szállítási tájékoztató a kosár gomb alatt (díjak a kérdéssorból; a feladási idő az Adatok fülön). */
function slugshop_shipping_box(): string
{
    $lines = (array) slugshop_site('shipping.lines', []);
    $dispatch = trim((string) slugshop_site('shipping.dispatch', ''));
    if (!$lines && $dispatch === '') {
        return '';
    }
    return '<div class="ship-info"><b>Szállítás</b>' . ($lines ? '<ul><li>' . implode('</li><li>', array_map('esc_html', $lines)) . '</li></ul>' : '')
        . ($dispatch !== '' ? '<p>' . esc_html($dispatch) . '</p>' : '') . '</div>';
}

/** Termékkeresés névre és cikkszámra (ékezet- és kisbetű-független: az adatbázis rendezése miatt). */
function slugshop_search_products(string $q, int $limit = 60): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $hidden = [slugshop_visible_tax('search')];
    $by_text = get_posts(['post_type' => 'product', 'post_status' => 'publish', 's' => $q, 'numberposts' => $limit, 'fields' => 'ids', 'orderby' => 'relevance', 'tax_query' => $hidden]);
    // cikkszám: a belső (JUTA) vagy a gyári FX-szám, „FX” előtaggal vagy anélkül
    $num = preg_replace('/^fx\s*-?/i', '', $q);
    $meta = ['relation' => 'OR', ['key' => '_sku', 'value' => $q, 'compare' => 'LIKE']];
    if (strlen($num) >= 3) {
        $meta[] = ['key' => 'slugshop_mpn', 'value' => $num, 'compare' => 'LIKE'];
    }
    $by_sku = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => $limit, 'fields' => 'ids', 'tax_query' => $hidden, 'meta_query' => $meta]);
    return array_slice(array_values(array_unique(array_map('intval', array_merge($by_sku, $by_text)))), 0, $limit);
}

function slugshop_render_search(): string
{
    $q = get_search_query(false);
    $ids = slugshop_search_products($q, 200);
    $posts = $q !== '' ? get_posts(['post_type' => 'post', 's' => $q, 'numberposts' => 12]) : [];

    // Szűrők a találatokon: készlet, kategória (a fő kategória), ár, rendezés
    $stock = !empty($_GET['raktaron']);
    $cat = (int) ($_GET['kategoria'] ?? 0);
    $min = isset($_GET['ar_min']) && $_GET['ar_min'] !== '' ? max(0, (int) $_GET['ar_min']) : null;
    $max = isset($_GET['ar_max']) && $_GET['ar_max'] !== '' ? max(0, (int) $_GET['ar_max']) : null;
    $sort = isset($_GET['rendezes']) ? sanitize_key(wp_unslash($_GET['rendezes'])) : '';
    $cats = [];
    $rows = [];
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p) {
            continue;
        }
        // minden kategória, amelyben a termék szerepel (nem csak a fő kategória)
        $terms = get_the_terms($id, 'product_cat');
        $term_ids = [];
        foreach (is_array($terms) ? $terms : [] as $t) {
            $term_ids[] = (int) $t->term_id;
            $cats[$t->term_id] = [slugshop_cat_label($t), ($cats[$t->term_id][1] ?? 0) + 1];
        }
        $price = (float) $p->get_price();
        if (($stock && !$p->is_in_stock()) || ($cat && !in_array($cat, $term_ids, true)) || ($min !== null && $price < $min) || ($max !== null && $price > $max)) {
            continue;
        }
        $rows[] = [$id, $price];
    }
    if ($sort === 'asc' || $sort === 'desc') {
        usort($rows, fn($a, $b) => $sort === 'asc' ? $a[1] <=> $b[1] : $b[1] <=> $a[1]);
    }
    $shown = array_column($rows, 0);
    $filtered = $stock || $cat || $min !== null || $max !== null;
    uasort($cats, fn($a, $b) => strnatcasecmp($a[0], $b[0]));

    $toolbar = '';
    if ($ids) {
        $opts = '<option value="0">Minden kategória</option>';
        foreach ($cats as $id => [$label, $n]) {
            $opts .= '<option value="' . (int) $id . '"' . selected($cat, $id, false) . '>' . esc_html($label) . ' (' . (int) $n . ')</option>';
        }
        $sorts = '';
        foreach (['' => 'Relevancia', 'asc' => 'Ár szerint növekvő', 'desc' => 'Ár szerint csökkenő'] as $k => $l) {
            $sorts .= '<option value="' . esc_attr($k) . '"' . selected($sort, $k, false) . '>' . esc_html($l) . '</option>';
        }
        $toolbar = '<form class="toolbar search-filters" method="get" action="' . esc_url(home_url('/')) . '" data-autosubmit>'
            . '<input type="hidden" name="s" value="' . esc_attr($q) . '"><input type="hidden" name="post_type" value="product">'
            . '<label><span class="sr-only">Kategória</span><select name="kategoria">' . $opts . '</select></label>'
            . '<label class="check"><input type="checkbox" name="raktaron" value="1"' . checked($stock, true, false) . '> Csak raktáron lévő</label>'
            . '<span class="price-range"><label><span class="sr-only">Legalacsonyabb ár</span><input type="number" name="ar_min" min="0" step="100" inputmode="numeric" placeholder="Ár -tól" value="' . esc_attr($min === null ? '' : (string) $min) . '"></label>'
            . '<label><span class="sr-only">Legmagasabb ár</span><input type="number" name="ar_max" min="0" step="100" inputmode="numeric" placeholder="-ig (Ft)" value="' . esc_attr($max === null ? '' : (string) $max) . '"></label></span>'
            . '<label><span class="sr-only">Rendezés</span><select name="rendezes">' . $sorts . '</select></label>'
            . '<button class="btn btn--sm" type="submit">Szűrés</button>'
            . ($filtered || $sort !== '' ? '<a class="list-reset" href="' . esc_url(add_query_arg(['s' => rawurlencode($q), 'post_type' => 'product'], home_url('/'))) . '">Szűrés törlése</a>' : '')
            . '</form>';
    }
    $count = count($shown) . ($filtered ? ' / ' . count($ids) : '') . ' termék' . ($posts ? ' és ' . count($posts) . ' cikk' : '') . '.';
    $again = '<form class="search-again" role="search" method="get" action="' . esc_url(home_url('/')) . '">'
        . '<label class="sr-only" for="search-again">Keresés a termékek között</label>'
        . '<input id="search-again" type="search" name="s" value="' . esc_attr($q) . '" placeholder="Termék neve, kaliber vagy cikkszám">'
        . '<input type="hidden" name="post_type" value="product"><button class="btn btn--sm" type="submit">Keresés</button>'
        . ($q !== '' ? '<a class="list-reset" href="' . esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')) . '">Keresés törlése</a>' : '')
        . '</form>';
    return '<div class="wrap page">' . slugshop_crumbs([['Keresés', null]]) . '<h1 class="page-title">Keresés: „' . esc_html($q) . '”</h1>'
        . $again . '<p>' . esc_html($count) . '</p>' . $toolbar
        . ($shown ? '<ul class="products search-grid">' . implode('', array_map('slugshop_product_card', $shown)) . '</ul>'
            : '<div class="empty">' . ($filtered ? 'A szűrésnek egy termék sem felel meg.' : 'Nincs találat. Próbáld kevesebb szóval vagy cikkszámmal.') . '</div>')
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
