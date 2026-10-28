<?php
/**
 * Slugshop – iu_theme child téma.
 *
 * Minden projekt-specifikus kód itt van; az iu_theme keretrendszerhez nem nyúlunk. Betöltés mindig __DIR__-rel
 * (a keretrendszer régi, relatív require_once hibája miatt). Az inc/ alatt NINCS „blocks” mappa: azt az iu_theme
 * block.json-os blokkoknak nézné; a saját blokkok az inc/blocks.php-ban vannak.
 */

defined('ABSPATH') || exit;

// A stíluslap ideje is benne van: frissítés után a böngésző biztosan az új CSS/JS-t kéri.
define('SLUGSHOP_VERSION', '1.2.2.' . (int) @filemtime(__DIR__ . '/style.css'));
define('SLUGSHOP_DIR', __DIR__);
define('SLUGSHOP_URL', get_stylesheet_directory_uri());

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/routes.php';
require_once __DIR__ . '/inc/theme.php';
require_once __DIR__ . '/inc/blocks.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/forms.php';
require_once __DIR__ . '/inc/consent.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/legacy.php';
require_once __DIR__ . '/inc/juta.php';
require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/admin.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/inc/cli.php';
}
