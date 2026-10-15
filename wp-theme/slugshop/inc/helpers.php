<?php
/**
 * Közös segédfüggvények: az oldal adatai (setup/data/site.json + az adminban megadott értékek), ikonok, képek.
 */

defined('ABSPATH') || exit;

/**
 * Az oldal adatai pontozott kulccsal (pl. „company.tax”). Az alap a setup/data/site.json (a régi oldalról); amit az
 * adminban (Megjelenés → Slugshop → Adatok) megadtak, az felülírja.
 */
function slugshop_site(string $key = '', $default = null)
{
    static $data = null;
    if ($data === null) {
        $file = SLUGSHOP_DIR . '/setup/data/site.json';
        $data = is_readable($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        foreach ((array) get_option('slugshop_settings', []) as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $ref = &$data;
            foreach (explode('.', $k) as $part) {
                if (!isset($ref[$part]) || !is_array($ref[$part])) {
                    $ref[$part] = $ref[$part] ?? [];
                }
                $ref = &$ref[$part];
            }
            $ref = $v;
            unset($ref);
        }
    }
    if ($key === '') {
        return $data;
    }
    $ref = $data;
    foreach (explode('.', $key) as $part) {
        if (!is_array($ref) || !array_key_exists($part, $ref)) {
            return $default;
        }
        $ref = $ref[$part];
    }
    return $ref;
}

/** A téma képe (assets/img). */
function slugshop_img(string $file): string
{
    return SLUGSHOP_URL . '/assets/img/' . ltrim($file, '/');
}

/** Belső útvonal (pl. „/zan-slugok”) → teljes URL; a külső linket változatlanul adja vissza. */
function slugshop_url(string $path): string
{
    if ($path === '' || preg_match('#^(https?:|mailto:|tel:|\#)#', $path)) {
        return $path;
    }
    return home_url('/' . ltrim($path, '/'));
}

/** Ár a régi oldal formájában: „8.500 Ft” (a WooCommerce beállításai szerint). */
function slugshop_price($amount): string
{
    if ($amount === '' || $amount === null) {
        return '';
    }
    $text = function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $amount)) : number_format((float) $amount, 0, ',', '.') . ' Ft';
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // a wc_price entitásai (&nbsp;, &#70;) helyett karakterek
}

/** Egyszerű SVG ikonok (az előnézetből). */
function slugshop_icon(string $name, string $class = ''): string
{
    $icons = [
        'search' => '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="13" cy="13" r="10"/><path d="m21 21 9 9"/></svg>',
        'cart' => '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h5l3.5 17h17l3-12H8"/><circle cx="12" cy="27" r="2"/><circle cx="24" cy="27" r="2"/></svg>',
        'menu' => '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 8h24M4 16h24M4 24h24"/></svg>',
        'down' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>',
        'left' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m10 3-5 5 5 5"/></svg>',
        'right' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 5h20v14H2z" opacity=".15"/><path d="M2 5h20v14H2V5zm2 2v.5l8 5.5 8-5.5V7H4zm16 2.9-8 5.5-8-5.5V17h16V9.9z"/></svg>',
        'a11y' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="4" r="2"/><path d="M4 8.5 12 10l8-1.5.4 1.8-6 1.7V15l2.4 6.5-1.8.7L12 16.6l-3 5.6-1.8-.7L9.6 15v-3l-6-1.7z"/></svg>',
        'box' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M24 4 42 13v22L24 44 6 35V13z"/><path d="m6 13 18 9 18-9M24 22v22"/></svg>',
    ];
    $svg = $icons[$name] ?? '';
    return $class !== '' ? str_replace('<svg', '<svg class="' . esc_attr($class) . '"', $svg) : $svg;
}

/** Morzsamenü: [[felirat, url|null], …] – a Kezdőlap elöl; mellé BreadcrumbList a seo.php-ban. */
function slugshop_crumbs(array $items): string
{
    $GLOBALS['slugshop_breadcrumbs'] = $items;
    $out = '<ol class="crumbs" aria-label="Morzsamenü"><li><a href="' . esc_url(home_url('/')) . '">Kezdőlap</a></li>';
    foreach ($items as [$label, $url]) {
        $out .= $url ? '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>' : '<li aria-current="page">' . esc_html($label) . '</li>';
    }
    return $out . '</ol>';
}

/** Rövid, egyszerű zárolás (pl. a JUTA-figyelőhöz): igaz, ha megkaptuk. */
function slugshop_lock(string $name, int $ttl = 300): bool
{
    $key = 'slugshop_lock_' . $name;
    if (get_transient($key)) {
        return false;
    }
    set_transient($key, 1, $ttl);
    return true;
}

/** Engedélyezett HTML a leírásokban (a régi oldalról jött tisztított HTML: bekezdés, lista, táblázat, kép, link). */
function slugshop_kses(string $html): string
{
    return wp_kses_post($html);
}
