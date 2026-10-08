// Vásárlási folyamat az egyfájlos előnézetben: 18+ kapu, süti, kereső, kosár, pénztár, köszönő oldal.
import { createRequire } from 'module';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';
const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require(execSync('npm root -g').toString().trim() + '/playwright'); }
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const FILE = 'file://' + path.join(root, 'dist', 'slugshop-elonezet.html');
const fails = []; const ok = (c, m) => { if (!c) fails.push(m); else console.log('✓ ' + m); };

// szoftveres WebGL (SwiftShader), hogy a three.js jelenetek fej nélkül is fussanak
const b = await pw.chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
p.on('pageerror', (e) => fails.push('JS-hiba: ' + e.message));
await p.route(/^https?:/, (r) => r.abort());
await p.goto(FILE);
ok(await p.isVisible('.modal.age'), '18+ ablak megjelenik');
await p.click('[data-age=yes]');
ok(await p.isVisible('.cookie'), 'sütiablak megjelenik az elfogadás után');
await p.click('[data-ck-act=all]');
await p.keyboard.press('/'); await p.waitForTimeout(100);
await p.keyboard.type('zan 25,5');
ok((await p.locator('.search-hit').count()) > 0, 'kereső talál („zan 25,5”)');
await p.keyboard.press('Enter'); await p.waitForTimeout(100);
ok((await p.locator('#main li.product').count()) > 0, 'keresési találati oldal');
await p.goto(FILE + '#/'); await p.waitForTimeout(300);
ok(await p.evaluate(() => !!window.SlugFX3D && !!window.PANTHERA.model), '3D modul és Panthera-modelladat betöltve');
await p.evaluate(() => document.querySelector('.panthera').scrollIntoView()); await p.waitForTimeout(1500);
ok(await p.evaluate(() => document.querySelector('.panthera-stage').classList.contains('is-3d') && !!document.querySelector('.panthera-stage canvas')), 'Panthera: WebGL-jelenet fut');
ok((await p.locator('.panthera .p3d-label').count()) >= 10, 'Panthera: alkatrész-címkék');
await p.evaluate(() => { const el = document.querySelector('.panthera'); window.scrollTo(0, el.getBoundingClientRect().top + scrollY + el.offsetHeight - innerHeight); });
// a sima görgetés szoftveres WebGL mellett lassú, ezért kivárjuk
ok(await p.waitForFunction(() => document.querySelector('.panthera').classList.contains('is-done'), null, { timeout: 10000 }).then(() => true, () => false), 'Panthera: görgetésre szétszedi a fegyvert');
ok((await p.locator('.coverflow .cf-item').count()) === 7, 'videókarusszel: 7 videó');
await p.click('.coverflow .cf-next'); await p.waitForTimeout(700);
ok((await p.textContent('[data-cf-label]')).startsWith('2 /'), 'videókarusszel: lapozás');
await p.evaluate(() => document.querySelector('.rifles').scrollIntoView()); await p.waitForTimeout(1500);
ok(await p.evaluate(() => document.querySelector('.rifles-stage').classList.contains('is-3d') && !!document.querySelector('.rifles-stage canvas')), 'forgó fegyverek: WebGL-jelenet fut');
await p.goto(FILE + '#/zan-slugok/22-5-5mm-slugs'); await p.waitForTimeout(100);
await p.click('#main li.product.instock [data-add] >> nth=0');
ok((await p.textContent('[data-cart-count]')).trim() === '1', 'kosárba tétel a listából');
await p.click('#main li.product.instock a.woocommerce-LoopProduct-link >> nth=1'); await p.waitForTimeout(100);
await p.click('[data-q="1"]'); await p.click('.single_add_to_cart_button');
ok((await p.textContent('[data-cart-count]')).trim() === '3', 'kosárba tétel az adatlapról (2 db)');
await p.goto(FILE + '#/kosar'); await p.waitForTimeout(100);
ok((await p.locator('.shop_table tbody tr').count()) === 2, 'kosár: 2 tétel');
await p.click('a[href="#/penztar"]'); await p.waitForTimeout(100);
await p.click('[data-form=checkout] button[type=submit]');
ok((await p.locator('.field-error').count()) > 0, 'pénztár: hiányzó mezők jelölve');
for (const [id, v] of [['#b-last', 'Teszt'], ['#b-first', 'Elek'], ['#b-email', 'teszt@pelda.hu'], ['#b-zip', '5100'], ['#b-city', 'Jászberény'], ['#b-street', 'Fő utca 1.']]) await p.fill(id, v);
await p.type('#b-tel', '06301234567');
await p.check('[data-form=checkout] input[type=checkbox][required]');
await p.click('[data-form=checkout] button[type=submit]'); await p.waitForTimeout(150);
ok((await p.locator('.order-done').count()) === 1 && (await p.textContent('[data-cart-count]')).trim() === '0', 'rendelés leadva, kosár ürítve');
await b.close();
console.log(fails.length ? '\nHIBA:\n' + fails.join('\n') : '\nMinden rendben.');
process.exit(fails.length ? 1 : 0);
