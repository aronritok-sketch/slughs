// A WordPress-téma végponttól végpontig (futó teszt-WordPress kell: tools/wp/dev-wp.sh).
//   BASE=http://localhost:8890 WPDIR=/tmp/wp node tests/wp/e2e.mjs
// Ellenőrzi: régi URL-ek (200, egy H1, mobilon nincs vízszintes görgetés), átirányítások, 404, 18+ kapu és süti-
// hozzájárulás (a mérőkód csak utána), élő kereső, rendezés és „Továbbiak betöltése”, kosárba tétel (AJAX), rendelés
// végig (személyes átvétel, előre utalás) a levelekkel, kapcsolati űrlap, „Értesítést kérek” + JUTA-figyelő, 3D.
import { createRequire } from 'module';
import { execSync } from 'child_process';
import { readdirSync, readFileSync, rmSync, existsSync } from 'fs';
const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require(execSync('npm root -g').toString().trim() + '/playwright'); }

const BASE = process.env.BASE || 'http://localhost:8890';
const WPDIR = process.env.WPDIR || '/tmp/wp';
const wp = (cmd) => execSync(`wp --allow-root --path=${WPDIR} ${cmd} 2>/dev/null`).toString().trim();
const MAIL = `${WPDIR}/wp-content/mail-log`;
const mails = () => (existsSync(MAIL) ? readdirSync(MAIL).map((f) => JSON.parse(readFileSync(`${MAIL}/${f}`, 'utf8'))) : []);
const fails = []; const ok = (c, m) => { if (!c) { fails.push(m); console.log('✗ ' + m); } else console.log('✓ ' + m); };

// Teszt-beállítás: előre utalás bekapcsolva (élesben a kérdéssor szerint), levélnapló ürítése.
wp(`option update woocommerce_bacs_settings '{"enabled":"yes","title":"Előre utalás","description":"Teszt"}' --format=json`);
rmSync(MAIL, { recursive: true, force: true });

const b = await pw.chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
const external = [];
async function ctxFor(width, height, consent = true) {
  const ctx = await b.newContext({ viewport: { width, height } });
  if (consent) {
    await ctx.addCookies([{ name: 'slugshop_age', value: 'true', url: BASE }, { name: 'slugshop_consent', value: encodeURIComponent('{"pref":true,"stat":false,"mkt":false}'), url: BASE }]);
  }
  await ctx.route((u) => !u.href.startsWith(BASE), (r) => { external.push(r.request().url()); r.abort(); });
  return ctx;
}

// ------------------------------------------------------------- URL-ek
const urls = ['/', '/zan-slugok', '/zan-slugok/22-5-5mm-slugs', '/fx-termekek', '/fx-termekek/fx-kiegeszitok/bipodok', '/fx-alkatreszek', '/snowpeak',
  '/minden-termek/jsb-pellet', '/zan-slugok/zan-457-11-2c6-mm', '/snowpeak/pp750-nagfykapacitasu-tar', '/minden-termek', '/szerviz', '/rolunk', '/letoltesek',
  '/kapcsolat', '/aszf', '/impresszum', '/adatvedelmi-nyilatkozat', '/videok', '/videok/fx-king', '/cikkeink', '/cikkeink/cikkek', '/cikkeink/articles',
  '/cikkeink/cikkek/kalapacstomeg-hatar-pcp-fx-drs', '/kosar', '/fiokom', '/?s=zan&post_type=product'];
for (const [w, h] of [[1440, 900], [390, 844]]) {
  const ctx = await ctxFor(w, h); const p = await ctx.newPage();
  const errs = []; p.on('pageerror', (e) => errs.push(e.message));
  let bad = [];
  for (const u of urls) {
    const r = await p.goto(BASE + u, { waitUntil: 'domcontentloaded' });
    const info = await p.evaluate(() => ({ h1: document.querySelectorAll('h1').length, over: document.documentElement.scrollWidth > window.innerWidth + 1 }));
    if (r.status() !== 200 || info.h1 !== 1 || info.over) bad.push(`${u} (${r.status()}, h1=${info.h1}${info.over ? ', vízszintes görgetés' : ''})`);
  }
  ok(!bad.length, `${urls.length} oldal ${w}px: 200, egy H1, nincs vízszintes görgetés${bad.length ? ' – ' + bad.join('; ') : ''}`);
  ok(!errs.length, `${w}px: nincs JS-hiba${errs.length ? ' – ' + errs.join('; ') : ''}`);
  await ctx.close();
}

// ------------------------------------------------------------- átirányítások, 404
const head = (u) => execSync(`curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "${BASE}${u}"`).toString();
ok(head('/tarak/pp750-nagfykapacitasu-tar').startsWith('301 ' + BASE + '/snowpeak/pp750'), 'több kategóriás termék más kategória alól → 301 a fő címére');
ok(head('/bejelentkezes').includes('/fiokom'), '/bejelentkezes → /fiokom');
ok(/^301 .*\/wp-content\/uploads\/.*FX_Crown_Exploded_PartsList(-\d+)?\.pdf$/.test(head('/media/acfupload/FX_Crown_Exploded_PartsList.pdf')), 'régi PDF-cím → a médiatárbeli fájl');
ok(head('/component/virtuemart/fx-fegyverek/fx-leopard-mesterlovesz-palackkal').includes('/fx-termekek/fx-fegyverek/fx-leopard'), 'régi VirtueMart-cím → a termék');
ok(head('/nincs-ilyen-oldal').startsWith('404'), 'ismeretlen cím → 404');

// ------------------------------------------------------------- 18+ kapu, sütik, mérőkód csak hozzájárulás után
{
  const ctx = await ctxFor(1280, 900, false); const p = await ctx.newPage();
  external.length = 0;
  await p.goto(BASE + '/');
  ok(await p.isVisible('.modal.age'), '18+ ablak az első látogatáskor');
  await p.click('[data-age=yes]');
  ok(await p.isVisible('.cookie'), 'sütiablak a 18+ után');
  ok(!external.some((u) => u.includes('googletagmanager') || u.includes('facebook')), 'hozzájárulás előtt nincs mérőkód');
  await p.click('[data-ck-act=all]'); await p.waitForTimeout(500);
  ok(external.some((u) => u.includes('googletagmanager.com/gtm.js')), 'elfogadás után a GTM betölt');
  ok(external.some((u) => u.includes('connect.facebook.net')), 'elfogadás után a Meta Pixel betölt');
  await p.reload(); ok(!(await p.isVisible('.modal.age')) && !(await p.isVisible('.cookie')), 'a döntés megmarad újratöltés után');
  await ctx.close();
}

// ------------------------------------------------------------- kereső, lista, kosár, rendelés
{
  const ctx = await ctxFor(1280, 900); const p = await ctx.newPage();
  await p.goto(BASE + '/');
  await p.keyboard.press('/'); await p.waitForTimeout(300); await p.keyboard.type('zan 25');
  await p.waitForSelector('.search-hit', { timeout: 8000 }).catch(() => {});
  ok((await p.locator('.search-hit').count()) > 0, 'élő kereső talál („zan 25”)');
  await Promise.all([p.waitForURL(/[?&]s=/, { timeout: 10000 }).catch(() => {}), p.keyboard.press('Enter')]);
  ok((await p.locator('ul.products li.product').count()) > 0, 'keresési találati oldal');

  await p.goto(BASE + '/fx-alkatreszek');
  const before = await p.locator('#grid li.product').count();
  if (await p.isVisible('[data-more]')) { await p.click('[data-more]'); await p.waitForFunction((n) => document.querySelectorAll('#grid li.product').length > n, before, { timeout: 8000 }).catch(() => {}); }
  ok((await p.locator('#grid li.product').count()) > before, `„Továbbiak betöltése” (${before} → ${await p.locator('#grid li.product').count()})`);
  await Promise.all([p.waitForURL(/rendezes=asc/, { timeout: 10000 }).catch(() => {}), p.selectOption('#sort', 'asc')]);
  ok(p.url().includes('rendezes=asc'), 'rendezés ár szerint');

  await p.goto(BASE + '/zan-slugok');
  await p.click('#grid li.product.instock [data-add] >> nth=0');
  await p.waitForFunction(() => document.querySelector('[data-cart-count]').textContent.trim() === '1', null, { timeout: 8000 }).catch(() => {});
  ok((await p.textContent('[data-cart-count]')).trim() === '1', 'kosárba tétel a listából (AJAX)');
  await p.click('#grid li.product.instock a.woocommerce-LoopProduct-link >> nth=1');
  await p.click('.quantity [data-q="1"]'); await p.click('.single_add_to_cart_button');
  await p.waitForFunction(() => document.querySelector('[data-cart-count]').textContent.trim() === '3', null, { timeout: 8000 }).catch(() => {});
  ok((await p.textContent('[data-cart-count]')).trim() === '3', 'kosárba tétel az adatlapról (2 db)');

  await p.goto(BASE + '/kosar');
  ok((await p.locator('.woocommerce-cart-form .cart_item').count()) === 2, 'kosár: 2 tétel');
  await p.goto(BASE + '/penztar');
  await p.fill('#billing_last_name', 'Teszt'); await p.fill('#billing_first_name', 'Elek');
  await p.fill('#billing_phone', '+36 30 123 4567'); await p.fill('#billing_email', 'teszt@example.com');
  await p.fill('#billing_postcode', '5100'); await p.fill('#billing_city', 'Jászberény'); await p.fill('#billing_address_1', 'Fő utca 1.');
  await p.waitForTimeout(1500);
  const pickup = p.locator('input[type=radio][value^="local_pickup"]'); if (await pickup.count()) await pickup.first().check();
  await p.waitForTimeout(1500);
  await p.check('#payment_method_bacs').catch(() => {});
  const terms = p.locator('#terms'); if (await terms.count()) await terms.check();
  await p.click('#place_order');
  await p.waitForURL(/rendeles-fogadva/, { timeout: 20000 }).catch(() => {});
  ok(/rendeles-fogadva/.test(p.url()), 'rendelés leadva → köszönő oldal');
  ok((await p.textContent('[data-cart-count]')).trim() === '0', 'a kosár kiürült');
  await p.waitForTimeout(1000);
  const subj = mails().map((m) => m.subject).join(' | ');
  ok(mails().some((m) => String(m.to).includes('teszt@example.com')), 'visszaigazoló levél a vásárlónak');
  ok(mails().length >= 2, `levelek: ${subj}`);
  await ctx.close();
}

// ------------------------------------------------------------- űrlapok, értesítés + JUTA-figyelő
{
  const ctx = await ctxFor(1280, 900); const p = await ctx.newPage();
  await p.goto(BASE + '/kapcsolat'); await p.waitForTimeout(3200); // a csapda 3 mp-nél gyorsabb beküldést eldob
  await p.click('form[data-form=contact] button[type=submit]');
  ok((await p.locator('form[data-form=contact] .has-error').count()) > 0, 'kapcsolati űrlap: hiányzó mezők jelölve');
  await p.fill('#cf-nev', 'Teszt Elek'); await p.fill('#cf-tel', '06301234567'); await p.fill('#cf-email', 'elek@example.com');
  await p.fill('#cf-uzenet', 'Próbaüzenet az e2e tesztből.'); await p.check('form[data-form=contact] input[name=accept]');
  await p.click('form[data-form=contact] button[type=submit]');
  await p.waitForSelector('.form-ok', { timeout: 8000 }).catch(() => {});
  ok(await p.isVisible('.form-ok'), 'kapcsolati űrlap elküldve');
  ok(mails().some((m) => String(m.subject).includes('[Slugshop] Üzenet')), 'levél a boltnak a kapcsolati űrlapról');

  const id = wp('post list --post_type=product --meta_key=_stock_status --meta_value=outofstock --posts_per_page=1 --field=ID');
  wp('slugshop juta-watch'); // lenyomat a változás előtt
  await p.goto(BASE + '/?p=' + id);
  await p.click('.cart-form [data-notify]'); await p.fill('#n-email', 'varo@example.com');
  await p.waitForTimeout(3200); // a csapda a 3 mp-nél gyorsabb beküldést robotnak veszi
  await p.click('form[data-form=notify] button[type=submit]');
  await p.waitForSelector('.form-ok', { timeout: 8000 }).catch(() => {});
  ok(await p.isVisible('.form-ok'), '„Értesítést kérek” feliratkozás');
  // a JUTA a WordPress nélkül, közvetlenül az adatbázisba írja a készletet
  wp(`db query "UPDATE ${wp('db prefix')}postmeta SET meta_value='instock' WHERE post_id=${id} AND meta_key='_stock_status'"`);
  wp('slugshop juta-watch');
  ok(mails().some((m) => String(m.to).includes('varo@example.com') && String(m.subject).startsWith('Újra raktáron')), 'JUTA-szinkron után „Újra raktáron” levél');
  wp(`db query "UPDATE ${wp('db prefix')}postmeta SET meta_value='outofstock' WHERE post_id=${id} AND meta_key='_stock_status'"`);

  await p.goto(BASE + '/'); await p.evaluate(() => document.querySelector('.rifles').scrollIntoView()); await p.waitForTimeout(2500);
  ok(await p.evaluate(() => document.querySelector('.rifles-stage').classList.contains('is-3d')), '3D forgó fegyverek (WebGL)');
  ok(await p.evaluate(() => document.querySelector('.panthera-stage').classList.contains('is-3d')), '3D Panthera (WebGL)');
  await p.goto(BASE + '/aszf');
  ok((await p.content()).includes('admin.fogyasztobarat.hu/e-api.js'), 'ÁSZF: Fogyasztó Barát beágyazás');
  await ctx.close();
}

wp(`option update woocommerce_bacs_settings '{"enabled":"no","title":"Előre utalás"}' --format=json`);
await b.close();
console.log(fails.length ? `\nHIBA (${fails.length}):\n` + fails.join('\n') : '\nMinden rendben.');
process.exit(fails.length ? 1 : 0);
