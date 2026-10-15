/**
 * Szerkesztős ellenőrzés: minden telepített oldal és cikk megnyitása a valódi blokkszerkesztőben; kiírja az érvénytelen
 * („váratlan vagy érvénytelen tartalom”) és a hiányzó blokkokat. A sablonfájlokat a tools/wp/canon.mjs ellenőrzi.
 * (A Bébiszitter Akadémia ellenőrzőjéből.)
 *
 *   BASE=http://localhost:8890 WP="wp --allow-root --path=/tmp/wp" WPUSER=admin WPPASS=admin node tests/wp/editor-check.mjs
 */
import { execSync } from 'child_process';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PWPATH || execSync('npm root -g').toString().trim() + '/playwright');
const BASE = process.env.BASE || 'http://localhost:8890';
const WP = process.env.WP || 'wp';
const { WPUSER, WPPASS } = process.env;
if (!WPUSER || !WPPASS) { console.error('WPUSER és WPPASS kell (adminisztrátor).'); process.exit(2); }
const wp = (cmd) => execSync(`${WP} ${cmd}`, { encoding: 'utf8' }).trim();

const targets = wp('post list --post_type=page,post --post_status=publish --fields=ID,post_name --format=csv').split('\n').slice(1)
  .filter(Boolean).map((l) => { const [id, name] = l.split(','); return { id, label: name }; });

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' });
const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
await page.goto(BASE + '/wp-login.php');
await page.fill('#user_login', WPUSER); await page.fill('#user_pass', WPPASS); await page.click('#wp-submit');
await page.waitForLoadState('networkidle');
let bad = 0;
for (const t of targets) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${t.id}&action=edit`, { waitUntil: 'domcontentloaded' });
  let res;
  for (let attempt = 0; attempt < 2 && !res; attempt++) {
    try {
      await page.waitForFunction(() => { const ed = window.wp?.data?.select('core/editor'); return ed && (ed.__unstableIsEditorReady ? ed.__unstableIsEditorReady() : ed.getCurrentPostId()); }, null, { timeout: 60000 });
      await page.waitForTimeout(1000);
      res = await page.evaluate(() => {
        const out = { total: 0, invalid: [], missing: [], empty: !wp.data.select('core/editor').getEditedPostContent().trim() };
        const walk = (bs) => bs.forEach((b) => {
          out.total++;
          if (!b.isValid) out.invalid.push(b.name);
          if (b.name === 'core/missing') out.missing.push(b.attributes.originalName);
          walk(b.innerBlocks);
        });
        walk(wp.data.select('core/block-editor').getBlocks());
        return out;
      });
    } catch (e) { await page.reload({ waitUntil: 'domcontentloaded' }); }
  }
  if (!res) { bad++; console.log(`  ✗ ${t.label}: a szerkesztő nem töltött be`); continue; }
  const okBlock = !res.invalid.length && !res.missing.length;
  if (!okBlock) bad++;
  console.log(`  ${okBlock ? '✓' : '✗'} ${t.label}: ${res.empty ? 'üres (a sablon tölti ki)' : res.total + ' blokk'}` + (res.invalid.length ? `, érvénytelen: ${res.invalid.join(', ')}` : '') + (res.missing.length ? `, hiányzó: ${res.missing.join(', ')}` : ''));
}
await browser.close();
console.log(`\n${targets.length - bad} rendben, ${bad} hiba`);
process.exit(bad ? 1 : 0);
