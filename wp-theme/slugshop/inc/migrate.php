<?php
/**
 * Egyszeri frissítések a már telepített (és az ügyfél által szerkesztett) oldalon – a telepítő nélkül, mert a
 * katalógus zárolva van. Mindegyik csak egyszer fut (slugshop_migrations opció), és csak azt írja, amit az ügyfél
 * még nem szerkesztett.
 */

defined('ABSPATH') || exit;

const SLUGSHOP_EXAM_PATH = 'jelentkezes-fegyvervizsgara';

add_action('admin_init', function () {
    if (!get_option('slugshop_setup_version') || wp_doing_ajax()) {
        return;
    }
    $done = (array) get_option('slugshop_migrations', []);
    foreach (['exam_page' => 'slugshop_migrate_exam_page', 'fx_parts_intro' => 'slugshop_migrate_fx_parts_intro'] as $key => $fn) {
        if (empty($done[$key])) {
            $done[$key] = $fn() ?: 'skip';
            update_option('slugshop_migrations', $done, false);
        }
    }
});

/**
 * „Jelentkezés fegyvervizsgára” (a régi oldalon a /jelentkezes-fegyvervizsgara címen): oldal a régi szöveggel és a
 * kitölthető jelentkezési lappal (PDF a médiatárba, a régi /images/… címe 301-gyel ide), és menüpont a főmenü végén.
 */
function slugshop_migrate_exam_page(): string
{
    $page = get_page_by_path(SLUGSHOP_EXAM_PATH, OBJECT, 'page');
    if (!$page) {
        $pdf = slugshop_exam_pdf();
        $content = str_replace('{{exam_pdf}}', esc_url($pdf ? (string) wp_get_attachment_url($pdf) : ''), slugshop_setup_content('pages/' . SLUGSHOP_EXAM_PATH . '.html'));
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Jelentkezés fegyvervizsgára',
            'post_name' => SLUGSHOP_EXAM_PATH, 'post_content' => $content], true);
        if (is_wp_error($id)) {
            return 'hiba: ' . $id->get_error_message();
        }
        update_post_meta($id, 'slugshop_hash', slugshop_content_hash($content));
        $page = get_post($id);
    }
    $locations = get_nav_menu_locations();
    $menu = (int) ($locations['slugshop-primary'] ?? 0);
    if ($menu) {
        foreach ((array) wp_get_nav_menu_items($menu) as $item) {
            if ((int) $item->object_id === (int) $page->ID && $item->object === 'page') {
                return 'ok';
            }
        }
        wp_update_nav_menu_item($menu, 0, [
            'menu-item-title' => 'Fegyvervizsga', 'menu-item-attr-title' => 'Jelentkezés fegyvervizsgára',
            'menu-item-object' => 'page', 'menu-item-object-id' => (int) $page->ID, 'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish',
        ]);
    }
    return 'ok';
}

function slugshop_exam_pdf(): int
{
    $source = '/images/Vizsgajelentkezesi_lap_kitoltheto.pdf';
    $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'slugshop_source', 'meta_value' => $source]);
    if ($found) {
        return (int) $found[0];
    }
    $file = SLUGSHOP_DIR . '/assets/files/' . basename($source);
    if (!is_readable($file)) {
        return 0;
    }
    $up = wp_upload_bits(basename($file), null, (string) file_get_contents($file));
    if (!empty($up['error'])) {
        return 0;
    }
    $id = wp_insert_attachment(['post_mime_type' => 'application/pdf', 'post_title' => 'Jelentkezési lap – fegyvervizsga (kitölthető)', 'post_status' => 'inherit'], $up['file']);
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    update_post_meta($id, 'slugshop_source', $source); // a régi cím (legacy.php) erre a fájlra visz
    return (int) $id;
}

/** Az FX alkatrészek bevezetője ne ígérjen olyan modellt, amelyhez a gyári ábrák szerint nincs igazolt alkatrész. */
function slugshop_migrate_fx_parts_intro(): string
{
    $ids = slugshop_term_by_path('/fx-alkatreszek');
    $term = $ids ? get_term((int) $ids[0], 'product_cat') : null;
    if (!$term instanceof WP_Term) {
        return 'nincs';
    }
    $old = 'Válassz modell szerint (DRS / Panthera / Leopard / King / Dreamline / Impact), és mindig figyelj a kompatibilitásra (évjárat, kivitel, cikkszám).';
    if (strpos($term->description, $old) === false) {
        return 'szerkesztve'; // az ügyfél átírta: nem nyúlunk hozzá
    }
    $new = 'Fent a modellválasztóban kiválaszthatod a fegyvered típusát: a lista a gyári robbantott ábrák alapján csak azokat a modelleket és alkatrészeket mutatja, amelyeknél a gyári cikkszám igazoltan egyezik. Ha a modelled nincs a listában, vagy bizonytalan vagy (évjárat, kivitel), keress cikkszámra, vagy írj nekünk.';
    wp_update_term($term->term_id, 'product_cat', ['description' => str_replace($old, $new, $term->description)]);
    return 'ok';
}
