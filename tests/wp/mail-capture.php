<?php
/**
 * Teszt mu-plugin (csak helyi teszt-WordPressbe): a kimenő leveleket fájlba írja (wp-content/mail-log/*.json) küldés
 * helyett, és SQLite / helyi gépen a WooCommerce-t nem engedi külső kérésekre.
 */
add_filter('pre_wp_mail', function ($null, $atts) {
    $dir = WP_CONTENT_DIR . '/mail-log';
    wp_mkdir_p($dir);
    file_put_contents($dir . '/' . microtime(true) . '.json', wp_json_encode($atts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}, 10, 2);
add_filter('woocommerce_admin_disabled', '__return_true');
add_filter('pre_http_request', function ($pre, $args, $url) {
    return str_contains($url, 'localhost') ? $pre : new WP_Error('offline', 'Helyi teszt: nincs külső kérés.');
}, 10, 3);
