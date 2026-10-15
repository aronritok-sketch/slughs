<?php
/**
 * A nyitóoldal szekciói (az előnézet pageHome() szerkezete): nyitókép, csempék + bemutatkozás, két szöveges blokk a
 * forgó 3D fegyverekkel, szétszedett Panthera, új termékek, videók, kapcsolati sáv, cikkek.
 */

defined('ABSPATH') || exit;

function slugshop_render_hero(): string
{
    $slides = (array) slugshop_site('slides', []);
    if (!$slides) {
        return '';
    }
    $out = '<div class="hero" aria-roledescription="körhinta" aria-label="Kiemelt ajánlatok"><div class="hero-track">';
    foreach ($slides as $i => $sl) {
        $first = $i === 0;
        $tag = $first ? 'h1' : 'h2';
        $out .= '<div class="hero-slide' . ($first ? ' is-active' : '') . '" aria-hidden="' . ($first ? 'false' : 'true') . '">'
            . '<img src="' . esc_url(slugshop_img($sl['img'])) . '" alt="" ' . ($first ? 'fetchpriority="high"' : 'loading="lazy"') . '>'
            . '<div class="hero-caption"><div class="wrap"><div>'
            . '<img class="hero-stars" src="' . esc_url(slugshop_img('stars.svg')) . '" alt="">'
            . '<' . $tag . ' class="hero-title">' . esc_html($sl['title']) . '</' . $tag . '><span class="bar"></span>'
            . '<p class="hero-text">' . esc_html($sl['text']) . '</p>'
            . '<a class="btn" href="' . esc_url(slugshop_url($sl['href'])) . '"' . ($first ? '' : ' tabindex="-1"') . '>' . esc_html($sl['button']) . '</a>'
            . '</div></div></div></div>';
    }
    $out .= '</div><button class="hero-arrow prev" type="button" aria-label="Előző dia">' . slugshop_icon('left') . '</button>'
        . '<button class="hero-arrow next" type="button" aria-label="Következő dia">' . slugshop_icon('right') . '</button><div class="hero-dots">';
    foreach ($slides as $i => $sl) {
        $out .= '<button type="button" aria-label="' . esc_attr(($i + 1) . '. dia: ' . $sl['title']) . '" aria-current="' . ($i === 0 ? 'true' : 'false') . '"></button>';
    }
    return $out . '</div></div>';
}

function slugshop_render_intro(array $a): string
{
    $intro = (array) slugshop_site('intro', []);
    $title = $a['title'] !== '' ? $a['title'] : ($intro['title'] ?? '');
    $text = $a['text'] !== '' ? $a['text'] : ($intro['text'] ?? '');
    $tiles = '';
    foreach ((array) slugshop_site('tiles', []) as $t) {
        $tiles .= '<a class="tile" href="' . esc_url(slugshop_url($t['href'])) . '">' . (!empty($t['img']) ? '<img src="' . esc_url(slugshop_img($t['img'])) . '" alt="" loading="lazy">' : '') . '<span>' . esc_html($t['title']) . '</span></a>';
    }
    return '<div class="intro"><div class="iu-row iu-row-vertical-center">'
        . '<div class="iu-column iu-column-1-2"><div class="tiles">' . $tiles . '</div></div>'
        . '<div class="iu-column iu-column-1-2 intro-copy sec-dark" style="background:none">'
        . '<img class="word" src="' . esc_url(slugshop_img('logoWord.svg')) . '" alt="Slugshop">'
        . '<h2>' . esc_html($title) . '</h2><span class="bar"></span><p>' . esc_html($text) . '</p></div></div></div>';
}

function slugshop_render_features(): string
{
    $blocks = (array) slugshop_site('blocks', []);
    if (count($blocks) < 2) {
        return '';
    }
    [$b1, $b2] = $blocks;
    $block = fn($b) => '<h2>' . esc_html($b['title']) . '</h2><span class="bar"></span><p>' . esc_html($b['text']) . '</p><a class="btn" href="' . esc_url(slugshop_url($b['href'])) . '">' . esc_html($b['button']) . '</a>';
    $rifles = '';
    if (!empty($b2['img'])) {
        slugshop_need_script('slugshop-fx3d');
        $rifles = '<div class="rifles" aria-hidden="true"><div class="rifles-stage" title="Húzd oldalra a forgatáshoz"><img class="fx3d-fallback" src="' . esc_url(slugshop_img($b2['img'])) . '" alt="" draggable="false"></div></div>';
    }
    return '<div class="features">'
        . '<div class="feature feature--light iu-section"><div class="iu-row iu-row-vertical-center"><div class="iu-column iu-column-1-2">' . $block($b1) . '</div></div></div>'
        . $rifles
        . '<div class="feature feature--dark sec-dark iu-section"><div class="iu-row"><div class="iu-column iu-column-1-2">' . $block($b2) . '</div></div></div>'
        . '</div>';
}

function slugshop_render_panthera(array $a): string
{
    slugshop_need_script('slugshop-fx3d');
    $pdf = slugshop_file_url('FX_Panthera_Exploded_PartsList.pdf', home_url('/media/acfupload/FX_Panthera_Exploded_PartsList.pdf'));
    $parts = slugshop_cat_paths()['/fx-alkatreszek'] ?? 0;
    $parts_url = $parts ? get_term_link((int) $parts, 'product_cat') : home_url('/fx-alkatreszek');
    return '<div class="panthera sec-dark" role="region" aria-label="FX Panthera robbantott ábra"><div class="panthera-sticky">'
        . '<div class="wrap panthera-head"><span class="eyebrow">' . esc_html($a['eyebrow']) . '</span><h2 class="sec-title">' . esc_html($a['title']) . '</h2><p>' . esc_html($a['text']) . '</p></div>'
        . '<div class="panthera-stage"><img class="fx3d-fallback" src="' . esc_url(slugshop_img('panthera-render.webp')) . '" alt="FX Panthera oldalnézet" draggable="false"></div>'
        . '<div class="wrap panthera-foot"><div class="panthera-count">Görgess tovább – a fegyver alkatrészeire bomlik</div><div class="panthera-progress" aria-hidden="true"><i></i></div>'
        . '<div class="panthera-actions"><a class="btn" href="' . esc_url(is_wp_error($parts_url) ? home_url('/fx-alkatreszek') : $parts_url) . '">FX alkatrészek</a><a class="btn" href="' . esc_url($pdf) . '" target="_blank" rel="noopener">Robbantott ábra (PDF)</a></div>'
        . '<small class="panthera-src">Forrás: FX Airguns gyári robbantott ábra</small></div></div></div>';
}

function slugshop_render_new_products(array $a): string
{
    if (!function_exists('wc_get_products')) {
        return '';
    }
    $slugs = array_filter((array) slugshop_site('newProducts', []));
    $ids = [];
    if ($slugs) {
        foreach ($slugs as $s) {
            $p = get_posts(['post_type' => 'product', 'name' => $s, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']);
            if ($p) {
                $ids[] = $p[0];
            }
        }
    }
    if (!$ids) {
        $ids = wc_get_products(['status' => 'publish', 'limit' => (int) $a['limit'], 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids']);
    }
    if (!$ids) {
        return '';
    }
    $cards = implode('', array_map('slugshop_product_card', array_slice($ids, 0, max(1, (int) $a['limit']))));
    return '<div class="new-products sec-dark home"><div class="iu-row"><div class="iu-column iu-column-1-1">'
        . '<h2 class="sec-title center">' . esc_html($a['title']) . '</h2><div class="carousel">'
        . '<button class="carousel-btn prev" type="button" aria-label="Előző termékek">' . slugshop_icon('left') . '</button>'
        . '<ul class="products carousel-track">' . $cards . '</ul>'
        . '<button class="carousel-btn next" type="button" aria-label="Következő termékek">' . slugshop_icon('right') . '</button>'
        . '</div></div></div></div>';
}

/** A videók: a „Videók” oldal aloldalai (cím + YouTube-azonosítók a video-grid blokkból). */
function slugshop_video_items(): array
{
    $items = [];
    foreach ((array) slugshop_site('videos', []) as $v) {
        foreach ((array) ($v['items'] ?? []) as $it) {
            $items[] = $it + ['group' => $v['title']];
        }
    }
    return $items;
}

function slugshop_coverflow(): string
{
    $items = slugshop_video_items();
    if (!$items) {
        return '';
    }
    slugshop_need_script('slugshop-fx');
    $out = '<div class="coverflow" tabindex="0" aria-roledescription="körhinta" aria-label="Videók"><div class="cf-track">';
    foreach ($items as $it) {
        $thumb = !empty($it['thumb']) ? slugshop_img($it['thumb']) : 'https://i.ytimg.com/vi/' . rawurlencode($it['id']) . '/hqdefault.jpg';
        $out .= '<div class="cf-item" data-yt="' . esc_attr($it['id']) . '" data-title="' . esc_attr($it['title']) . '" data-group="' . esc_attr($it['group']) . '">'
            . '<button type="button" class="cf-media" aria-label="' . esc_attr('Lejátszás: ' . $it['title']) . '"><img src="' . esc_url($thumb) . '" alt="" draggable="false"><span class="cf-play"></span></button></div>';
    }
    return $out . '</div><button class="cf-prev hero-arrow" type="button" aria-label="Előző videó">' . slugshop_icon('left') . '</button>'
        . '<button class="cf-next hero-arrow" type="button" aria-label="Következő videó">' . slugshop_icon('right') . '</button>'
        . '<div class="cf-caption"><span class="card-meta" data-cf-label></span><h3 data-cf-title></h3><a class="yt-out" data-cf-link href="https://www.youtube.com/watch?v=' . esc_attr($items[0]['id']) . '" target="_blank" rel="noopener">Megnézem a YouTube-on</a></div></div>';
}

function slugshop_render_videos_band(array $a): string
{
    $cf = slugshop_coverflow();
    if ($cf === '') {
        return '';
    }
    return '<div class="videos-band sec-dark"><div class="wrap"><div class="band-head"><h2 class="sec-title">' . esc_html($a['title']) . '</h2><a class="btn" href="' . esc_url(home_url('/videok')) . '">Összes videó</a></div>' . $cf . '</div></div>';
}

function slugshop_render_contact_band(array $a): string
{
    return '<div class="contact-band" id="kapcsolat-urlap"><div class="wrap" style="max-width:860px"><h2>' . esc_html($a['title']) . '</h2>' . slugshop_contact_form(true) . '</div></div>';
}

function slugshop_article_card(WP_Post $post): string
{
    $cat = '';
    foreach ((array) get_the_category($post->ID) as $c) {
        if (in_array($c->slug, SLUGSHOP_POST_CATS, true)) {
            $cat = $c->name;
        }
    }
    $img = get_the_post_thumbnail($post, 'medium_large', ['loading' => 'lazy', 'alt' => '']);
    return '<a class="card" href="' . esc_url(get_permalink($post)) . '"><span class="card-img">' . $img . '</span>'
        . '<span class="card-body"><span class="card-meta">' . esc_html($cat) . '</span><h3>' . esc_html(get_the_title($post)) . '</h3><p>' . esc_html(wp_trim_words(get_the_excerpt($post), 32, '…')) . '</p></span></a>';
}

function slugshop_render_articles_band(array $a): string
{
    $posts = get_posts(['post_type' => 'post', 'numberposts' => 3, 'category_name' => 'cikkek']);
    if (!$posts) {
        return '';
    }
    return '<div class="articles-band sec-dark"><div class="iu-row iu-row-vertical-center" style="margin-bottom:var(--space-6)">'
        . '<div class="iu-column iu-column-1-2"><h2 class="sec-title">' . esc_html($a['title']) . '</h2><p class="lead">' . esc_html($a['lead']) . '</p></div>'
        . '<div class="iu-column iu-column-1-2 iu-column-align-right"><a class="btn" href="' . esc_url(home_url('/cikkeink')) . '">' . esc_html($a['title']) . '</a></div></div>'
        . '<div class="iu-row"><div class="iu-column iu-column-1-1"><div class="cards">' . implode('', array_map('slugshop_article_card', $posts)) . '</div></div></div></div>';
}
