// HTML → PDF az átadási anyagokhoz. Futtatás: node pdf.js   (Playwright: lokális vagy globális)
// Figyelmeztet, ha egy oldal tartalma túllóg az A4-en.
const path = require('path');
let playwright;
try { playwright = require('playwright'); } catch (e) {
	playwright = require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright');
}

const FILES = [
	['kerdessor.html', 'Slugshop_kerdessor.pdf'],
	['laci.html', 'Slugshop_teendok_Laci.pdf'],
];

// Splits .page.flow sections: blocks that do not fit move to a new page; a .flow-keep block (section heading)
// moves together with the block after it. Then numbers the flow pages ("3 / 9") in the footer.
function paginate() {
	const mm = 3.7795;
	const pages = [...document.querySelectorAll('.page.flow')];
	const queue = [...pages];
	while (queue.length) {
		const page = queue.shift();
		const foot = page.querySelector(':scope > .foot');
		const limit = page.getBoundingClientRect().bottom - 20 * mm;
		const blocks = [...page.children].filter(c => c !== foot);
		let cut = blocks.findIndex(b => b.getBoundingClientRect().bottom > limit);
		if (cut <= 0) continue;
		while (cut > 1 && blocks[cut - 1].classList.contains('flow-keep')) cut--;
		const next = document.createElement('section');
		next.className = page.className;
		blocks.slice(cut).forEach(b => next.appendChild(b));
		next.appendChild(foot.cloneNode(true));
		page.after(next);
		queue.unshift(next);
	}
	const all = [...document.querySelectorAll('.page.flow')];
	all.forEach((p, i) => { const r = p.querySelector('.foot span:last-child'); if (r) r.textContent = (i + 1) + ' / ' + all.length; });
}

(async () => {
	const browser = await playwright.chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
	for (const [src, out] of FILES) {
		const page = await browser.newPage({ viewport: { width: 900, height: 1200 } });
		await page.goto('file://' + path.join(__dirname, src), { waitUntil: 'networkidle' });
		await page.evaluate(() => document.fonts.ready);
		await page.emulateMedia({ media: 'print' });
		await page.evaluate(paginate);
		const overflow = await page.evaluate(() => [...document.querySelectorAll('.page')]
			.map((p, i) => {
				const limit = p.getBoundingClientRect().bottom - 14 * 3.78; // above the footer
				const last = [...p.children].filter(c => !c.classList.contains('foot')).pop();
				return last && last.getBoundingClientRect().bottom > limit ? i + 1 : 0;
			}).filter(Boolean));
		const fonts = await page.evaluate(() => [document.fonts.check('700 20px Satoshi'), document.fonts.check('400 12px Inter')]);
		await page.pdf({ path: path.join(__dirname, out), preferCSSPageSize: true, printBackground: true });
		console.log(out, 'oldalak:', await page.evaluate(() => document.querySelectorAll('.page').length),
			'betűk (Satoshi, Inter):', fonts.join(', '), overflow.length ? 'TÚLCSORDUL: ' + overflow.join(', ') : '');
		await page.close();
	}
	await browser.close();
})();
