/**
 * Browser test of the pop-up against a running WordPress (for example a
 * WordPress Playground site) with the plugin active and an "admin"/"password"
 * administrator, the Playground default. It changes the plugin's settings.
 *
 *   NODE_PATH="$(npm root -g)" node tests/e2e/popup.cjs http://127.0.0.1:9410
 *
 * Needs Playwright with Chromium (npm i -g playwright; npx playwright install chromium).
 */
'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('playwright');

const BASE = (process.argv[2] || 'http://127.0.0.1:9400').replace(/\/$/, '');
const IMAGE = BASE + '/wp-admin/images/wordpress-logo.png';
const DIALOG = '.wppu-overlay [role="dialog"]';
let failures = 0;

function check(label, ok) {
	console.log((ok ? 'PASS ' : 'FAIL ') + label);
	if (!ok) failures++;
}

async function visible(page, timeout = 4000) {
	try {
		await page.waitForSelector(DIALOG, { state: 'visible', timeout });
		return true;
	} catch (e) {
		return false;
	}
}

async function save(admin, values) {
	await admin.goto(BASE + '/wp-admin/options-general.php?page=wp-pop-up');
	for (const [name, value] of Object.entries(values)) {
		const field = admin.locator(`[name="wppu_settings[${name}]"]`);
		const type = await field.first().getAttribute('type');
		if (type === 'hidden') {
			await field.evaluate((input, v) => { input.value = v; }, String(value));
		} else if (type === 'checkbox') {
			await field.setChecked(!!value);
		} else if (type === 'radio') {
			await admin.check(`[name="wppu_settings[${name}]"][value="${value}"]`);
		} else {
			await field.fill(String(value));
		}
	}
	await Promise.all([admin.waitForNavigation(), admin.click('#submit')]);
	return admin.locator('.notice, .updated, .error').allInnerTexts();
}

async function setPages(admin, titles) {
	await admin.goto(BASE + '/wp-admin/options-general.php?page=wp-pop-up');
	await admin.check('[name="wppu_settings[display]"][value="pages"]');
	for (const box of await admin.locator('[name="wppu_settings[page_ids][]"]').all()) {
		const label = (await box.locator('xpath=..').innerText()).trim();
		await box.setChecked(titles.includes(label));
	}
	await Promise.all([admin.waitForNavigation(), admin.click('#submit')]);
}

(async () => {
	const browser = await chromium.launch();
	const errors = [];
	const watch = (page) => page.on('pageerror', (err) => errors.push(err.message));

	const adminContext = await browser.newContext();
	const admin = await adminContext.newPage();
	watch(admin);
	await admin.goto(BASE + '/wp-login.php');
	await admin.fill('#user_login', 'admin');
	await admin.fill('#user_pass', 'password');
	await Promise.all([admin.waitForNavigation(), admin.click('#wp-submit')]);

	// 0. Media library: upload, pick, save.
	const file = path.join(os.tmpdir(), 'wppu-e2e.png');
	fs.writeFileSync(file, await (await admin.request.get(IMAGE)).body());
	await admin.goto(BASE + '/wp-admin/options-general.php?page=wp-pop-up');
	await admin.click('#wppu-image-select');
	await admin.setInputFiles('.media-modal input[type="file"]', file);
	await admin.waitForSelector('.media-modal .attachment.selected:not(.uploading)', { timeout: 30000 });
	await admin.click('.media-modal .media-button-select');
	const pickedId = await admin.inputValue('#wppu-image-id');
	check('media library pick fills the image', Number(pickedId) > 0 && (await admin.inputValue('#wppu-image-url')).includes('/uploads/'));
	check('media library pick updates the preview', ((await admin.getAttribute('#wppu-image-preview img', 'src')) || '').includes('/uploads/'));
	await Promise.all([admin.waitForNavigation(), admin.click('#submit')]);
	check('picked image is saved', (await admin.inputValue('#wppu-image-id')) === pickedId);
	await admin.fill('#wppu-image-url', 'javascript:alert(1)');
	await admin.dispatchEvent('#wppu-image-url', 'change');
	check('typed non-http URL shows no preview', (await admin.locator('#wppu-image-preview img').count()) === 0);
	await admin.fill('#wppu-image-url', IMAGE);
	await admin.dispatchEvent('#wppu-image-url', 'change');
	check('typed URL shows a preview and clears the media choice', (await admin.getAttribute('#wppu-image-preview img', 'src')) === IMAGE
		&& (await admin.inputValue('#wppu-image-id')) === '0');

	await save(admin, {
		enabled: 1, image_id: 0, image_url: IMAGE, image_alt: 'Kvietimas paremti', link_url: 'https://example.org/parama/',
		link_new_tab: 1, display: 'home', frequency: 'session', delay: 0, start_date: '', end_date: '', devices: 'all',
		close_overlay: 1, overlay_opacity: 70, max_width: 0,
	});
	check('settings page saves', (await admin.locator('#setting-error-settings_updated').count()) === 1);

	// 1. Front page, once per session.
	let visitor = await browser.newContext({ viewport: { width: 1280, height: 800 } });
	let page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/');
	check('shows on the front page', await visible(page));
	check('dialog has an accessible name', (await page.getAttribute(DIALOG, 'aria-label')) === 'Kvietimas paremti');
	check('image is the configured one', (await page.getAttribute('.wppu-image', 'src')) === IMAGE);
	check('link opens in a new tab with rel=noopener', (await page.getAttribute('.wppu-link', 'target')) === '_blank'
		&& (await page.getAttribute('.wppu-link', 'rel')) === 'noopener');
	check('close button has focus', await page.evaluate(() => document.activeElement.classList.contains('wppu-close')));
	await page.keyboard.press('Tab');
	check('Tab moves to the link', await page.evaluate(() => document.activeElement.classList.contains('wppu-link')));
	await page.keyboard.press('Tab');
	check('Tab wraps back to the close button', await page.evaluate(() => document.activeElement.classList.contains('wppu-close')));
	check('page scrolling is locked', await page.evaluate(() => document.documentElement.classList.contains('wppu-open')));
	await page.keyboard.press('Escape');
	await page.waitForSelector('.wppu-overlay', { state: 'detached', timeout: 2000 }).catch(() => {});
	check('Esc closes it', (await page.locator('.wppu-overlay').count()) === 0);
	await page.reload();
	check('not shown again in the same session', !(await visible(page, 1500)));
	await visitor.close();

	// 2. Close button and backdrop.
	visitor = await browser.newContext();
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/');
	await visible(page);
	await page.click('.wppu-close');
	await page.waitForSelector('.wppu-overlay', { state: 'detached', timeout: 2000 }).catch(() => {});
	check('× closes it', (await page.locator('.wppu-overlay').count()) === 0);
	await visitor.close();

	visitor = await browser.newContext();
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/');
	await visible(page);
	await page.mouse.click(5, 5);
	await page.waitForSelector('.wppu-overlay', { state: 'detached', timeout: 2000 }).catch(() => {});
	check('backdrop click closes it', (await page.locator('.wppu-overlay').count()) === 0);
	// A new link is a new campaign: shown again in the same session.
	await save(admin, { link_url: 'https://example.org/parama-2/' });
	await page.reload();
	check('new link shows it again to a visitor who saw the old one', await visible(page));
	await visitor.close();

	// 3. Other pages, no link, no backdrop close, delay.
	await save(admin, { link_url: '', close_overlay: 0, delay: 2, frequency: 'always' });
	visitor = await browser.newContext();
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/?pagename=apie-mus');
	check('home only: not loaded on another page', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await page.goto(BASE + '/');
	check('delay: not shown before 2 s', !(await visible(page, 1200)));
	check('delay: shown after 2 s', await visible(page, 3000));
	check('no link: image is not wrapped in a link', (await page.locator('.wppu-link').count()) === 0);
	await page.mouse.click(5, 5);
	await page.waitForTimeout(400);
	check('backdrop click off: stays open', (await page.locator(DIALOG).count()) === 1);
	await page.reload();
	check('"every time": shown again after reload', await visible(page, 3500));
	await visitor.close();

	// 4. Selected pages and devices.
	await save(admin, { delay: 0, devices: 'mobile' });
	await setPages(admin, ['Kontaktai']);
	visitor = await browser.newContext({ viewport: { width: 1280, height: 800 } });
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/?pagename=kontaktai');
	check('mobile only: not shown on a wide screen', !(await visible(page, 1500)));
	await visitor.close();
	visitor = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/?pagename=kontaktai');
	check('mobile only: shown on a phone, on a selected page', await visible(page));
	const box = await page.locator('.wppu-image').boundingBox();
	check('image fits the phone screen', box && box.x >= 0 && box.x + box.width <= 390);
	await page.goto(BASE + '/');
	check('selected pages: not loaded on the front page', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await visitor.close();

	// 5. Dates, disabled, preview.
	const day = (offset) => new Date(Date.now() + offset * 86400000).toISOString().slice(0, 10);
	let notices = await save(admin, { display: 'all', devices: 'all', start_date: day(3), end_date: day(1) });
	check('end before start: warned and cleared', notices.join(' ').includes('Pabaigos data') && (await admin.inputValue('[name="wppu_settings[end_date]"]')) === '');
	visitor = await browser.newContext();
	page = await visitor.newPage();
	watch(page);
	await page.goto(BASE + '/');
	check('future start date: not loaded', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await save(admin, { start_date: day(-3), end_date: day(-1) });
	await page.goto(BASE + '/');
	check('past end date: not loaded', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await save(admin, { start_date: day(-1), end_date: day(1) });
	await page.goto(BASE + '/');
	check('inside the date window: shown', await visible(page));

	await save(admin, { enabled: 0 });
	await page.goto(BASE + '/');
	check('disabled: not loaded', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await page.goto(BASE + '/?wppu_preview=1');
	check('preview needs an administrator', (await page.evaluate(() => typeof window.wppuConfig)) === 'undefined');
	await admin.goto(BASE + '/?pagename=apie-mus&wppu_preview=1');
	check('preview: an administrator sees it even when disabled', await visible(admin));
	await visitor.close();

	check('no JavaScript errors', errors.length === 0);
	errors.forEach((e) => console.log('  page error: ' + e));
	await browser.close();
	console.log(failures ? failures + ' check(s) failed' : 'All checks passed');
	process.exit(failures ? 1 : 0);
})().catch((err) => {
	console.error(err);
	process.exit(1);
});
