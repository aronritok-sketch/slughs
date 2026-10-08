// Füstteszt: az egyfájlos előnézet minden útvonala asztalon és mobilon.
// Ellenőrzi: JS-hiba, vízszintes túlcsordulás, pontosan egy H1, nem üres tartalom.
// Futtatás: node tests/smoke.mjs   (Playwright kell: npm i -D playwright, vagy globális telepítés)
import { createRequire } from 'module';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';
const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require(execSync('npm root -g').toString().trim() + '/playwright'); }
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const FILE = 'file://' + path.join(root, 'dist', 'slugshop-elonezet.html');

const browser = await pw.chromium.launch();
const problems = [];
for (const width of [1440, 390]) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  await ctx.addInitScript(() => { localStorage.setItem('slugshop_age', 'true'); localStorage.setItem('slugshop_cookie', '{}'); });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => problems.push(`${width}px JS-hiba: ${e.message}`));
  await page.route(/^https?:/, (r) => r.abort());
  await page.goto(FILE);
  const routes = await page.evaluate(() => {
    const S = window.SLUG;
    return ['/', ...Object.keys(S.cats), ...Object.values(S.products).map((p) => p.path), ...S.articles.map((a) => a.path),
      '/videok', ...S.videos.map((v) => v.path), '/cikkeink', '/cikkeink/cikkek', '/cikkeink/articles', '/szerviz', '/rolunk',
      '/letoltesek', '/kapcsolat', '/aszf', '/impresszum', '/adatvedelmi-nyilatkozat', '/kosar', '/bejelentkezes',
      '/regisztracio', '/elonezet', '/kereses/zan', '/nincs-ilyen-oldal'];
  });
  for (const r of routes) {
    await page.evaluate((h) => { location.hash = '#' + h; }, r);
    await page.waitForTimeout(25);
    const res = await page.evaluate(() => ({
      ow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      h1: document.querySelectorAll('#main h1').length,
      len: document.querySelector('#main').innerText.trim().length,
    }));
    if (res.ow > 0) problems.push(`${width}px ${r}: vízszintes túlcsordulás ${res.ow}px`);
    if (res.h1 !== 1) problems.push(`${width}px ${r}: ${res.h1} db H1`);
    if (res.len < 40) problems.push(`${width}px ${r}: üres tartalom`);
  }
  console.log(`${width}px: ${routes.length} útvonal`);
  await ctx.close();
}
await browser.close();
console.log(problems.length ? problems.join('\n') : 'Minden rendben.');
process.exit(problems.length ? 1 : 0);
