<?php
/**
 * Oldalak: cím + morzsamenü, kapcsolat (elérhetőségek, nyitvatartás, űrlap), jogi szövegek (Fogyasztó Barát),
 * impresszum, videók, cikkek, 404.
 */

defined('ABSPATH') || exit;

function slugshop_render_page_head(): string
{
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return '';
    }
    $crumbs = [];
    foreach (array_reverse(get_post_ancestors($post)) as $anc) {
        $crumbs[] = [get_the_title($anc), get_permalink($anc)];
    }
    $crumbs[] = [get_the_title($post), null];
    return '<div class="wrap page page-head">' . slugshop_crumbs($crumbs) . '<h1 class="page-title">' . esc_html(get_the_title($post)) . '</h1></div>';
}

/** A kapcsolati űrlap (a beküldést a forms.php kezeli; JS nélkül is működik). */
function slugshop_contact_form(bool $dark = false): string
{
    $sent = isset($_GET['uzenet']) && $_GET['uzenet'] === 'ok';
    if ($sent) {
        return '<div class="form-ok" role="status">Köszönjük, az üzenetedet megkaptuk. Hamarosan válaszolunk.</div>';
    }
    return '<form class="form ' . ($dark ? 'form-dark' : '') . '" data-form="contact" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" novalidate>'
        . '<input type="hidden" name="action" value="slugshop_contact">' . slugshop_form_guard()
        . '<div class="form-grid">'
        . '<div class="field"><label for="cf-nev">Név *</label><input id="cf-nev" name="your-name" required autocomplete="name"></div>'
        . '<div class="field"><label for="cf-tel">Telefon *</label><input id="cf-tel" name="your-phone" type="tel" required autocomplete="tel" placeholder="+36 ( __ ) ___ - ____" data-phone></div>'
        . '<div class="field"><label for="cf-email">Email</label><input id="cf-email" name="your-email" type="email" autocomplete="email"></div>'
        . '<div class="field"><span class="label">Kép feltöltése</span><div class="dropzone" tabindex="0" role="button" aria-label="Kép feltöltése">Húzd és dobáld be a fájlokat ide<input type="file" name="files[]" hidden multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.heic,.webp"></div><div class="files"></div></div>'
        . '</div>'
        . '<div class="field"><label for="cf-uzenet">Üzenet</label><textarea id="cf-uzenet" name="your-message"></textarea></div>'
        . '<label class="check"><input type="checkbox" name="accept" value="1" required> <span>Elfogadom az <a href="' . esc_url(home_url('/adatvedelmi-nyilatkozat')) . '">adatvédelmi nyilatkozatot</a></span></label>'
        . '<div><button class="btn" type="submit">Küldés</button></div></form>';
}

function slugshop_render_contact(): string
{
    $c = (array) slugshop_site('contact', []);
    $phone = (string) slugshop_site('phone');
    $email = (string) slugshop_site('email');
    $hours = '';
    foreach ((array) ($c['hours'] ?? []) as [$day, $time]) {
        $hours .= '<tr><td>' . esc_html($day) . '</td><td><b>' . esc_html($time) . '</b></td></tr>';
    }
    $map = (string) ($c['map'] ?? '');
    return '<div class="iu-row contact-page" style="width:100%"><div class="iu-column iu-column-1-3"><div class="contact-info">'
        . ($map ? '<a href="' . esc_url($map) . '" target="_blank" rel="noopener">' . esc_html($c['address'] ?? '') . '</a>' : '<span>' . esc_html($c['address'] ?? '') . '</span>')
        . '<a href="tel:' . esc_attr(preg_replace('/[^+\d]/', '', $phone)) . '">' . esc_html($phone) . '</a>'
        . '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></div>'
        . '<table class="hours"><caption class="sr-only">Nyitvatartás</caption><tbody>' . $hours . '</tbody></table>'
        . ($map ? '<a class="btn" href="' . esc_url($map) . '" target="_blank" rel="noopener">Útvonaltervezés</a>' : '')
        . '</div><div class="iu-column iu-column-2-3">' . slugshop_contact_form(false) . '</div></div>';
}

/**
 * Jogi szöveg a Fogyasztó Barát rendszeréből, ugyanazzal a beágyazással, mint a régi oldalon (mindig a hatályos
 * szöveg). Típus: aszf, adatkezeles, imp. JS nélkül a tanúsítvány linkje látszik.
 */
function slugshop_render_legal(array $a): string
{
    $id = (string) slugshop_site('trackers.fogyasztobarat', '');
    $type = in_array($a['type'], ['aszf', 'adatkezeles', 'imp'], true) ? $a['type'] : 'aszf';
    if ($id === '') {
        return '<p class="note">A jogi szöveg a Fogyasztó Barát rendszeréből töltődik be: add meg a tanúsítvány azonosítóját (Megjelenés → Slugshop).</p>';
    }
    wp_enqueue_script('jquery');
    $cert = 'https://fogyasztobarat.hu/tanusitvany-ellenorzes/?csi=' . rawurlencode($id);
    return '<div class="fb-embed" data-type="' . esc_attr($type) . '">'
        . '<script id="barat_script">var st=document.createElement("script");st.src="//admin.fogyasztobarat.hu/e-api.js";st.type="text/javascript";'
        . 'st.setAttribute("data-id",' . wp_json_encode($id) . ');st.setAttribute("id","fbarat-embed");st.setAttribute("data-type",' . wp_json_encode($type) . ');'
        . 'var s=document.getElementById("barat_script");s.parentNode.insertBefore(st,s);</script>'
        . '<noscript><p><a class="btn" href="' . esc_url($cert) . '" target="_blank" rel="noopener">A dokumentum a Fogyasztó Barát oldalán</a></p></noscript></div>';
}

function slugshop_render_company(): string
{
    $co = (array) slugshop_site('company', []);
    $rows = [
        'Cégnév' => $co['name'] ?? '', 'Székhely' => $co['seat'] ?? '', 'Üzlet, személyes átvétel' => slugshop_site('contact.address', ''),
        'Adószám' => $co['tax'] ?? '', 'Cégjegyzékszám' => $co['reg'] ?? '', 'Nyilvántartó bíróság' => $co['court'] ?? '',
        'Képviselők' => $co['reps'] ?? '', 'Telefon' => slugshop_site('phone', ''), 'E-mail' => slugshop_site('email', ''),
        'Tárhelyszolgáltató' => $co['host'] ?? '',
    ];
    $out = '<table class="company"><tbody>';
    foreach ($rows as $label => $value) {
        if ((string) $value !== '') {
            $out .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
    }
    return $out . '</tbody></table>';
}

/** A médiacsomag fájljai (files/…) a médiatárban: relatív útvonal => melléklet azonosító. */
function slugshop_media_files(): array
{
    $map = wp_cache_get('slugshop_media_files');
    if (is_array($map)) {
        return $map;
    }
    global $wpdb;
    $rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'slugshop_media' AND meta_value LIKE 'files/%'", ARRAY_A);
    $map = [];
    foreach ((array) $rows as $r) {
        $map[$r['meta_value']] = (int) $r['post_id'];
    }
    wp_cache_set('slugshop_media_files', $map);
    return $map;
}

/** Egy letölthető fájl URL-je: a médiatárból, ha már ott van, különben a régi oldal címe. */
function slugshop_file_url(string $file, string $fallback = ''): string
{
    $id = slugshop_media_files()['files/' . $file] ?? 0;
    return $id ? (string) wp_get_attachment_url($id) : $fallback;
}

/** Telefon- és e-mail-gomb (pl. a Szerviz „Időpontkérés” része alatt). */
function slugshop_render_contact_buttons(array $a): string
{
    $phone = (string) slugshop_site('phone');
    $email = (string) slugshop_site('email');
    $out = '';
    if ($phone !== '') {
        $out .= '<a class="btn btn--primary" href="tel:' . esc_attr(preg_replace('/[^+\d]/', '', $phone)) . '">' . slugshop_icon('phone', 'icon') . esc_html($a['phoneLabel'] . ': ' . $phone) . '</a>';
    }
    if ($email !== '') {
        $out .= '<a class="btn" href="mailto:' . esc_attr($email) . '?subject=' . rawurlencode((string) $a['subject']) . '">' . slugshop_icon('mail', 'icon') . esc_html($a['emailLabel'] . ': ' . $email) . '</a>';
    }
    return $out !== '' ? '<p class="contact-buttons">' . $out . '</p>' : '';
}

function slugshop_render_downloads(array $a): string
{
    $items = slugshop_setup_data('downloads');
    if (!$items) {
        return '';
    }
    $out = '<h2 class="sec-title">' . esc_html($a['title']) . '</h2><ul class="downloads">';
    foreach ($items as $it) {
        if (!empty($it['dupOf'])) {
            continue; // bájtra azonos másolat („… copy 1”): a fájl megmarad, a listában egyszer szerepel
        }
        $url = slugshop_file_url($it['file'], home_url($it['src']));
        $id = slugshop_media_files()['files/' . $it['file']] ?? 0;
        $size = $id ? size_format((int) @filesize((string) get_attached_file($id)), 1) : '';
        $out .= '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener" download><span class="dl-title">' . esc_html($it['title']) . '</span><span class="dl-meta">PDF' . ($size ? ' · ' . esc_html($size) : '') . '</span></a></li>';
    }
    return $out . '</ul>';
}

function slugshop_render_video_list(): string
{
    $cards = '';
    foreach ((array) slugshop_site('videos', []) as $v) {
        $cards .= '<a class="card" href="' . esc_url(slugshop_url($v['path'])) . '"><span class="card-img">' . (!empty($v['img']) ? '<img src="' . esc_url(slugshop_img($v['img'])) . '" alt="" loading="lazy">' : '') . '</span>'
            . '<span class="card-body"><h3>' . esc_html($v['title']) . '</h3><p>' . esc_html($v['title']) . ' videók</p><span class="card-meta">Bővebben …</span></span></a>';
    }
    $cf = slugshop_coverflow();
    return ($cf ? '<div class="sec-dark videos-inline">' . $cf . '</div>' : '') . '<div class="cards">' . $cards . '</div>';
}

function slugshop_render_video_grid(array $a): string
{
    $ids = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $a['ids'])), fn($id) => preg_match('/^[\w-]{6,20}$/', $id));
    if (!$ids) {
        return '';
    }
    $out = '<div class="video-grid">';
    foreach ($ids as $id) {
        $out .= '<div><button class="yt" type="button" data-yt="' . esc_attr($id) . '" aria-label="Videó lejátszása"><img src="https://i.ytimg.com/vi/' . esc_attr($id) . '/hqdefault.jpg" alt="" loading="lazy"><span></span></button>'
            . '<a class="yt-out" href="https://www.youtube.com/watch?v=' . esc_attr($id) . '" target="_blank" rel="noopener">Megnézem a YouTube-on</a></div>';
    }
    return $out . '</div>';
}

function slugshop_render_blog(array $a): string
{
    $which = in_array($a['which'], SLUGSHOP_POST_CATS, true) ? $a['which'] : '';
    if ($which === '' && is_category(SLUGSHOP_POST_CATS)) {
        $which = get_queried_object()->slug;
    }
    $groups = $which === '' ? [['cikkek', 'Cikkek'], ['articles', 'Articles']] : [[$which, $which === 'articles' ? 'Articles' : 'Cikkek']];
    $title = $which === '' ? 'Cikkeink' : $groups[0][1];
    $crumbs = $which === '' ? [['Cikkeink', null]] : [['Cikkeink', home_url('/cikkeink')], [$title, null]];
    $intro = (string) slugshop_site('blogIntro.' . ($which === '' ? 'cikkeink' : 'cikkeink/' . $which), '');
    // Oldalba téve (Cikkeink) a címet és a morzsamenüt a sablon adja; kategória-archívumon a blokk.
    $head = is_page() ? '' : slugshop_crumbs($crumbs) . '<h1 class="page-title">' . esc_html($title) . '</h1>';
    $out = '<div class="wrap page' . (is_page() ? ' page-body' : '') . '">' . $head
        . ($intro !== '' ? '<div class="prose" style="margin-bottom:var(--space-6)">' . wp_kses_post($intro) . '</div>' : '');
    foreach ($groups as [$slug, $label]) {
        $posts = get_posts(['post_type' => 'post', 'numberposts' => -1, 'category_name' => $slug]);
        if ($which === '') {
            $out .= '<h2 class="sec-title" style="margin-top:var(--space-6)"><a href="' . esc_url(home_url('/cikkeink/' . $slug)) . '" style="color:inherit">' . esc_html($label) . '</a></h2>';
        }
        $out .= '<div class="cards">' . implode('', array_map('slugshop_article_card', $posts)) . '</div>';
    }
    return $out . '</div>';
}

function slugshop_render_article(): string
{
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return '';
    }
    $blog = ['Cikkek', home_url('/cikkeink/cikkek')];
    foreach ((array) get_the_category($post->ID) as $c) {
        if ($c->slug === 'articles') {
            $blog = ['Articles', home_url('/cikkeink/articles')];
        }
    }
    $content = apply_filters('the_content', $post->post_content);
    $img = '';
    $thumb = get_post_thumbnail_id($post);
    if ($thumb && !str_contains($content, (string) wp_get_attachment_url($thumb))) {
        $img = wp_get_attachment_image($thumb, 'large', false, ['alt' => '', 'class' => 'article-img']);
    }
    return '<div class="wrap page">' . slugshop_crumbs([['Cikkeink', home_url('/cikkeink')], $blog, [get_the_title($post), null]])
        . '<article class="prose" style="margin:0 auto"><h1 class="page-title">' . esc_html(get_the_title($post)) . '</h1>' . $img . $content . '</article></div>';
}

function slugshop_render_404(): string
{
    return '<div class="wrap page" style="text-align:center;padding-block:var(--space-9)"><h1 class="page-title">Az oldal nem található</h1>'
        . '<p>Lehet, hogy a cím elírás, vagy az oldal megszűnt. Keress rá a termékre, vagy nézd meg a kategóriákat.</p>'
        . '<p><a class="btn" href="' . esc_url(home_url('/')) . '">Vissza a kezdőlapra</a></p></div>';
}
