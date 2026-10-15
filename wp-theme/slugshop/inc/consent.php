<?php
/**
 * 18+ kapu és süti-hozzájárulás (a régi oldal szövegével; a döntés a látogató böngészőjében, sütiben marad).
 *
 * A mérőkódok csak hozzájárulás után töltődnek (DONTESEK: GA4 / mérés csak hozzájárulással):
 * - Google Tag Manager (a régi oldalé: GTM-K2DT9KZ8) – „Teljesítmény mérése” vagy „marketing” elfogadása után,
 *   Consent Mode v2-vel (alapból minden „denied”);
 * - Facebook (Meta) Pixel és Barion Pixel – csak a marketing-sütik elfogadása után.
 * Az azonosítók: Megjelenés → Slugshop → Adatok (alapból a régi oldaléi, a site.json-ból).
 */

defined('ABSPATH') || exit;

function slugshop_trackers(): array
{
    return [
        'gtm' => preg_replace('/[^A-Z0-9-]/', '', (string) slugshop_site('trackers.gtm', '')),
        'fbPixel' => preg_replace('/\D/', '', (string) slugshop_site('trackers.fbPixel', '')),
        'barionPixel' => preg_replace('/[^A-Za-z0-9-]/', '', (string) slugshop_site('trackers.barionPixel', '')),
    ];
}

/** Consent Mode v2 alapállapot: minden tiltva, amíg a látogató nem dönt (a GTM csak döntés után töltődik). */
add_action('wp_head', function () {
    if (!slugshop_trackers()['gtm']) {
        return;
    }
    echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});</script>\n";
}, 1);

/** A Fogyasztó Barát tanúsítvány-jelvénye (a régi oldalon minden oldalon ott volt). */
add_action('wp_footer', function () {
    $id = (string) slugshop_site('trackers.fogyasztobarat', '');
    if ($id === '' || !slugshop_site('trackers.fogyasztobaratBadge', true)) {
        return;
    }
    echo '<script id="barat_hud_sr_script">if(document.getElementById("fbarat")===null){var hst=document.createElement("script");hst.src="//admin.fogyasztobarat.hu/h-api.js";hst.type="text/javascript";hst.setAttribute("data-id",' . wp_json_encode($id) . ');hst.setAttribute("id","fbarat");var hs=document.getElementById("barat_hud_sr_script");hs.parentNode.insertBefore(hst,hs);}</script>' . "\n";
}, 50);
