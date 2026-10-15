/**
 * A generált blokk-markup kanonikus alakra hozása a valódi blokkszerkesztőben (teszt-WordPressen, a téma bekapcsolva).
 *
 * Minden fájlt (setup/content/**.html, templates/*.html) a szerkesztő parse-ol; az érvénytelen blokkokat
 * (a mentett HTML nem egyezik a block.js save() kimenetével) wp.blocks.createBlock-kal újraépíti ugyanazokkal az
 * attribútumokkal és belső blokkokkal, majd wp.blocks.serialize-zal visszaírja. Második futásra minden blokk érvényes.
 *
 *   BASE=http://localhost:8890 WPUSER=admin WPPASS=admin node tools/wp/canon.mjs [fájl…]
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

import { createRequire } from 'module';
import { execSync } from 'child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PWPATH || execSync('npm root -g').toString().trim() + '/playwright');
const BASE = process.env.BASE || 'http://localhost:8890';
const { WPUSER = 'admin', WPPASS = 'admin' } = process.env;
const theme = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'wp-theme', 'slugshop');

const walk = (d) => readdirSync(d).flatMap((f) => { const p = join(d, f); return statSync(p).isDirectory() ? walk(p) : p.endsWith('.html') ? [p] : []; });
const files = process.argv.slice(2).length ? process.argv.slice(2) : [...walk(join(theme, 'setup', 'content')), ...walk(join(theme, 'templates'))];

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' });
const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
await page.goto(BASE + '/wp-login.php');
await page.fill('#user_login', WPUSER); await page.fill('#user_pass', WPPASS); await page.click('#wp-submit');
await page.waitForLoadState('networkidle');
await page.goto(BASE + '/wp-admin/post-new.php?post_type=page', { waitUntil: 'domcontentloaded' });
await page.waitForFunction(() => window.wp?.blocks?.getBlockTypes?.().some((b) => b.name === 'iu/section') && window.wp.blocks.getBlockType('slugshop/header'), null, { timeout: 120000 });
await page.waitForTimeout(1500);

let changed = 0, bad = 0;
for (const file of files) {
  const src = readFileSync(file, 'utf8');
  const res = await page.evaluate((content) => {
    const { parse, serialize, createBlock } = wp.blocks;
    const fixed = [];
    const fix = (blocks) => blocks.map((b) => {
      const inner = fix(b.innerBlocks || []);
      if (!b.isValid && b.name !== 'core/freeform') { fixed.push(b.name); return createBlock(b.name, b.attributes, inner); }
      b.innerBlocks = inner; return b;
    });
    const out = serialize(fix(parse(content)));
    const invalid = [];
    const missing = [];
    const check = (bs) => bs.forEach((b) => { if (!b.isValid) invalid.push(b.name); if (b.name === 'core/missing') missing.push(b.attributes.originalName); check(b.innerBlocks || []); });
    check(parse(out));
    return { out, fixed, invalid, missing };
  }, src);
  const rel = file.replace(theme + '/', '');
  if (res.invalid.length || res.missing.length) {
    bad++;
    console.log(`  ✗ ${rel}: érvénytelen: ${res.invalid.join(', ')} ${res.missing.length ? 'hiányzó: ' + res.missing.join(', ') : ''}`);
  }
  if (res.out.trim() !== src.trim()) {
    writeFileSync(file, res.out.trim() + '\n');
    changed++;
    console.log(`  ↻ ${rel}` + (res.fixed.length ? ` (újraépítve: ${[...new Set(res.fixed)].join(', ')})` : ''));
  }
}
await browser.close();
console.log(`\n${files.length} fájl, ${changed} módosítva, ${bad} hibás`);
process.exit(bad ? 1 : 0);
