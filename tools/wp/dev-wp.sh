#!/bin/sh
# Helyi teszt-WordPress a témához (MariaDB/MySQL kell): letölti a WordPresst, telepíti magyarul a WooCommerce-szel,
# bekapcsolja az iu_theme-et (a hlprv repó themes/iu_theme mappája) és a child témát (szimbolikus linkkel), lefuttatja a
# telepítőt a médiacsomaggal (build/media), és kiírja az indító parancsot (http://localhost:8890).
#   tools/wp/dev-wp.sh <wp mappa> [hlprv mappa] [db név] [db felhasználó] [db jelszó]
set -e
DIR=${1:?WordPress mappa}; HLPRV=${2:-../hlprv}; DB=${3:-slug}; USER=${4:-wp}; PASS=${5:-wp}
REPO=$(cd "$(dirname "$0")/../.." && pwd)
HLPRV=$(cd "$HLPRV" && pwd)
WP="wp --allow-root --path=$DIR"
mkdir -p "$DIR"
[ -f "$DIR/wp-load.php" ] || $WP core download --quiet
rm -f "$DIR/wp-content/mu-plugins/mail-capture.php"  # a teszt-mu-plugin tiltja a külső kéréseket (a bővítmény letöltését is)
rm -f "$DIR/wp-config.php"
$WP config create --dbname="$DB" --dbuser="$USER" --dbpass="$PASS" --dbhost=localhost --skip-check --quiet
$WP config set WP_DEBUG true --raw --quiet
$WP config set WP_DEBUG_LOG true --raw --quiet
$WP config set WP_DEBUG_DISPLAY false --raw --quiet
$WP db reset --yes --quiet 2>/dev/null || $WP db create --quiet
$WP core install --url=http://localhost:8890 --title="Slugshop" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email --quiet
$WP language core install hu_HU --activate --quiet || true
$WP plugin install woocommerce --activate --quiet
$WP language plugin install woocommerce hu_HU --quiet || true
ln -sfn "$HLPRV/themes/iu_theme" "$DIR/wp-content/themes/iu_theme"
ln -sfn "$REPO/wp-theme/slugshop" "$DIR/wp-content/themes/slugshop"
mkdir -p "$DIR/wp-content/mu-plugins" && cp "$REPO/tests/wp/mail-capture.php" "$DIR/wp-content/mu-plugins/"
$WP theme activate slugshop --quiet
$WP slugshop setup --media="$REPO/build/media"
cat > "$DIR/../slug-router.php" <<'PHP'
<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== '/' && is_file($root . $path)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
PHP
echo "Indítás: (cd $DIR && PHP_CLI_SERVER_WORKERS=6 php -S localhost:8890 -t $DIR $DIR/../slug-router.php)"
