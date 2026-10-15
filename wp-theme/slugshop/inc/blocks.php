<?php
/**
 * Saját blokkok. Mind szerveroldali, önzáró blokk (a markupban nincs mentett HTML, így a szerkesztőben nem lehet
 * „váratlan tartalom”). A szövegek attribútumok (a blokk oldalsávjában szerkeszthetők), a listák (nyitókép diái,
 * csempék, menü) a site.json-ból jönnek. A HTML a jóváhagyott előnézet (assets/js/app.js) szerkezete.
 *
 * Regisztráció az iu_custom_blocks mu-pluginnel (iucb_add_block), ha az nincs, a WordPress register_block_type-jával,
 * ugyanazzal a kimenettel (minta: Alpha Movers).
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/parts/chrome.php';
require_once __DIR__ . '/parts/home.php';
require_once __DIR__ . '/parts/catalog.php';
require_once __DIR__ . '/parts/pages.php';

/**
 * @param array $attrs  név => alapérték (string|bool|int)
 * @param array $fields név => [típus, címke] a szerkesztő oldalsávjához (text, textarea, toggle)
 */
function slugshop_block(string $name, string $title, array $attrs, callable $render, array $fields = []): void
{
    $attributes = [];
    foreach ($attrs as $key => $default) {
        $attributes[$key] = ['type' => is_bool($default) ? 'boolean' : (is_int($default) ? 'number' : 'string'), 'default' => $default];
    }
    $template = function ($attributes) use ($render, $attrs) {
        return (string) $render(wp_parse_args((array) $attributes, $attrs));
    };
    if (function_exists('iucb_add_block')) {
        $panel = [];
        foreach ($fields as $key => [$type, $label]) {
            $panel[$key] = ['type' => $type, 'label' => $label];
        }
        iucb_add_block('slugshop/' . $name, [
            'title' => 'Slugshop: ' . $title,
            'category' => 'widgets',
            'attributes' => $attributes,
            'fields' => $panel ? [['panel' => 'Beállítások', 'fields' => $panel]] : [],
            'editJS' => 'return ' . wp_json_encode('Slugshop: ' . $title) . ';',
            'template' => $template,
        ]);
        return;
    }
    register_block_type('slugshop/' . $name, [
        'title' => 'Slugshop: ' . $title,
        'category' => 'widgets',
        'attributes' => $attributes + ['className' => ['type' => 'string', 'default' => '']],
        'render_callback' => fn($attributes) => $template($attributes),
        'supports' => ['html' => false],
    ]);
    $editor_fields = [];
    foreach ($fields as $key => [$type, $label]) {
        $editor_fields[] = ['key' => $key, 'type' => $type, 'label' => $label];
    }
    $GLOBALS['slugshop_editor_blocks'][] = ['name' => 'slugshop/' . $name, 'title' => 'Slugshop: ' . $title, 'attributes' => $attributes, 'fields' => $editor_fields];
}

/** Szerkesztőnézet iu_custom_blocks nélkül: felcímkézett helyőrző + a szövegek az oldalsávban (assets/js/editor.js). */
add_action('enqueue_block_editor_assets', function () {
    if (empty($GLOBALS['slugshop_editor_blocks'])) {
        return;
    }
    wp_enqueue_script('slugshop-editor', SLUGSHOP_URL . '/assets/js/editor.js', ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'], SLUGSHOP_VERSION, true);
    wp_add_inline_script('slugshop-editor', 'window.SLUGSHOP_BLOCKS = ' . wp_json_encode($GLOBALS['slugshop_editor_blocks']) . ';', 'before');
    wp_add_inline_style('wp-edit-blocks', '.slug-editor-block{padding:14px 18px;border:1px dashed #EA810B;background:#222226;color:#fff;font:600 14px/1.4 system-ui,sans-serif}.slug-editor-block small{display:block;font-weight:400;color:#bbb;margin-top:2px}');
});

add_action('init', function () {
    // Keret
    slugshop_block('header', 'Fejléc', [], 'slugshop_render_header');
    slugshop_block('footer', 'Lábléc', [], 'slugshop_render_footer');

    // Nyitóoldal
    slugshop_block('hero', 'Nyitókép (diák)', [], 'slugshop_render_hero');
    slugshop_block('intro', 'Csempék és bemutatkozás', ['title' => '', 'text' => ''], 'slugshop_render_intro',
        ['title' => ['text', 'Cím (üresen: a régi oldalé)'], 'text' => ['textarea', 'Szöveg']]);
    slugshop_block('features', 'Két szöveges blokk + forgó 3D fegyverek', [], 'slugshop_render_features');
    slugshop_block('panthera', 'Szétszedett FX Panthera (3D)', [
        'eyebrow' => 'FX Panthera', 'title' => 'Darabjaira szedve',
        'text' => 'Görgess, és nézd meg, miből épül fel egy FX Panthera. Az alkatrészek cikkszám szerint rendelhetők, a robbantott ábrák a Letöltések között vannak.',
    ], 'slugshop_render_panthera', ['eyebrow' => ['text', 'Felirat'], 'title' => ['text', 'Cím'], 'text' => ['textarea', 'Szöveg']]);
    slugshop_block('new-products', 'Új termékek', ['title' => 'Új termékek', 'limit' => 12], 'slugshop_render_new_products',
        ['title' => ['text', 'Cím']]);
    slugshop_block('videos', 'Videók (lapozó)', ['title' => 'Videók', 'band' => true], 'slugshop_render_videos_band',
        ['title' => ['text', 'Cím']]);
    slugshop_block('contact-band', 'Kapcsolati sáv űrlappal', ['title' => 'Vegye fel velünk a kapcsolatot!'], 'slugshop_render_contact_band',
        ['title' => ['text', 'Cím']]);
    slugshop_block('articles', 'Legújabb cikkek', ['title' => 'Cikkeink', 'lead' => 'Érdekességek, újdonságok, innováció egy helyen'], 'slugshop_render_articles_band',
        ['title' => ['text', 'Cím'], 'lead' => ['text', 'Alcím']]);

    // Bolt
    slugshop_block('category', 'Termékkategória (lista)', [], 'slugshop_render_category');
    slugshop_block('product', 'Termékoldal', [], 'slugshop_render_product');
    slugshop_block('search', 'Keresési találatok', [], 'slugshop_render_search');

    // Oldalak
    slugshop_block('page-head', 'Oldal címe és morzsamenü', [], 'slugshop_render_page_head');
    slugshop_block('contact', 'Kapcsolat (elérhetőségek + űrlap)', [], 'slugshop_render_contact');
    slugshop_block('legal', 'Jogi szöveg (Fogyasztó Barát)', ['type' => 'aszf'], 'slugshop_render_legal', ['type' => ['text', 'Típus: aszf, adatkezeles vagy imp']]);
    slugshop_block('company', 'Cégadatok (impresszum)', [], 'slugshop_render_company');
    slugshop_block('downloads', 'Letölthető robbantott ábrák', ['title' => 'Letölthető robbantott ábrák és segédletek'], 'slugshop_render_downloads', ['title' => ['text', 'Cím']]);
    slugshop_block('video-list', 'Videókategóriák', [], 'slugshop_render_video_list');
    slugshop_block('video-grid', 'YouTube-videók', ['ids' => ''], 'slugshop_render_video_grid', ['ids' => ['textarea', 'YouTube-azonosítók (soronként)']]);
    slugshop_block('blog', 'Cikklista', ['which' => ''], 'slugshop_render_blog', ['which' => ['text', 'cikkek, articles vagy üres (mindkettő)']]);
    slugshop_block('article', 'Cikk', [], 'slugshop_render_article');
    slugshop_block('not-found', '404', [], 'slugshop_render_404');
}, 5);
