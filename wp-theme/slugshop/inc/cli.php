<?php
/**
 * WP-CLI:
 *   wp slugshop setup [--force] [--media=<mappa|zip>] [--step=<lépés>]   telepítés / frissítés
 *   wp slugshop check                                                   élesítési ellenőrzés
 *   wp slugshop juta-watch                                              a JUTA-figyelő egy köre (teszthez)
 */

defined('ABSPATH') || exit;

WP_CLI::add_command('slugshop setup', function ($args, $assoc) {
    if (!empty($assoc['media'])) {
        $src = (string) $assoc['media'];
        wp_mkdir_p(slugshop_media_dir());
        if (is_dir($src)) {
            WP_CLI::launch('cp -R ' . escapeshellarg(rtrim($src, '/') . '/.') . ' ' . escapeshellarg(slugshop_media_dir()));
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            $res = unzip_file($src, slugshop_media_dir());
            if (is_wp_error($res)) {
                WP_CLI::error($res->get_error_message());
            }
        }
        WP_CLI::log('Médiacsomag: ' . slugshop_media_count() . ' fájl');
    }
    // A blokk-JSON-t a kses és a WP 7 custom-CSS szűrője elrontaná CLI alól (iu-theme skill).
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
    if ($admins) {
        wp_set_current_user((int) $admins[0]);
    }
    kses_remove_filters();
    $only = (string) ($assoc['step'] ?? '');
    foreach (slugshop_setup_steps() as $step => [$label, $batched]) {
        if ($only !== '' && $only !== $step) {
            continue;
        }
        WP_CLI::log('== ' . $label);
        $offset = 0;
        do {
            $r = slugshop_setup_run($step, $offset, !empty($assoc['force']));
            foreach ($r['log'] as $line) {
                if (!str_starts_with($line, '+ ') && !str_starts_with($line, '↻ ') || !empty($assoc['verbose'])) {
                    WP_CLI::log('  ' . $line);
                }
            }
            if ($batched && !$r['done']) {
                WP_CLI::log(sprintf('  … %d / %d', $r['offset'], $r['total']));
            }
            $offset = $r['offset'];
        } while (!$r['done']);
    }
    WP_CLI::success('Kész.');
});

WP_CLI::add_command('slugshop check', function () {
    foreach (slugshop_admin_checks() as [$state, $label, $fix]) {
        WP_CLI::log(['ok' => '✔', 'warn' => '!', 'bad' => '✘'][$state] . ' ' . $label . ($fix ? ' – ' . $fix : ''));
    }
});

WP_CLI::add_command('slugshop juta-watch', function () {
    WP_CLI::log(wp_json_encode(slugshop_juta_watch()));
});
