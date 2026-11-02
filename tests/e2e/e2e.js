// Browser-Tests: Block-Editor (alle Blöcke gültig), Website ohne JS-Fehler,
// Bedienung (Ansicht, Filter, Reiter) und Screenshots (Desktop + Handy) als Artefakt.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CI = path.join(__dirname, '..', 'ci');
const SHOTS = path.join(__dirname, 'shots');
fs.mkdirSync(SHOTS, { recursive: true });

let failed = 0;
const ok = (m) => console.log('  ok   ' + m);
const fail = (m) => { console.log('  FAIL ' + m); failed++; };

(async () => {
  const browser = await chromium.launch();
  const urls = fs.readFileSync(path.join(CI, 'urls.txt'), 'utf8').split('\n').filter(u => u && !u.includes('wp-json') && !u.includes('pdf'));
  const ids = fs.readFileSync(path.join(CI, 'editor-ids.txt'), 'utf8').trim().split(/\s+/);

  // --- Website ---
  console.log('== Website');
  for (const [name, viewport] of [['desktop', { width: 1400, height: 900 }], ['handy', { width: 390, height: 844 }]]) {
    const ctx = await browser.newContext({ viewport });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
    for (const u of urls) {
      errors.length = 0;
      await page.goto(BASE + u, { waitUntil: 'networkidle' });
      // Cookie-Banner: „Nur notwendige“ (damit er die Screenshots nicht verdeckt)
      const nec = page.locator('#sgnl-consent button', { hasText: 'Nur notwendige' });
      if (await nec.count() && await nec.first().isVisible()) { await nec.first().click(); await page.waitForTimeout(200); }
      const file = (u.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'start') + '-' + name + '.png';
      await page.screenshot({ path: path.join(SHOTS, file), fullPage: true });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      if (errors.length) fail(`${u} (${name}): JS-Fehler: ${errors.join(' | ')}`); else ok(`${u} (${name}) ohne JS-Fehler`);
      if (overflow > 2) fail(`${u} (${name}): Seite ist ${overflow}px zu breit`);
    }
    await ctx.close();
  }

  // --- Bedienung ---
  console.log('== Bedienung');
  {
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.goto(BASE + '/terminplan/', { waitUntil: 'networkidle' });
    await page.click('.sgnl-events__switch button[data-view="tiles"]');
    (await page.getAttribute('.sgnl-events', 'data-view')) === 'tiles' ? ok('Umschalten auf Kacheln') : fail('Kacheln lassen sich nicht einschalten');
    await page.click('.sgnl-events__filter button[data-cat="ferien"]');
    const visible = await page.$$eval('.sgnl-event:not([hidden])', els => els.map(e => e.dataset.cat));
    visible.length && visible.every(c => c.split(' ').includes('ferien')) ? ok('Filter „Ferien“ zeigt nur Ferien') : fail('Filter funktioniert nicht: ' + visible.join(','));

    await page.goto(BASE + '/datenbank/#termine', { waitUntil: 'networkidle' });
    (await page.getAttribute('.sgnl-db__tab[data-tab="termine"]', 'aria-selected')) === 'true' ? ok('Datenbank: Reiter aus der Adresse (#termine)') : fail('Datenbank: #termine nicht ausgewählt');
    const external = [];
    page.on('request', r => { if (/webclub/i.test(r.url())) external.push(r.url()); });
    await page.click('.sgnl-db__tab[data-tab="ergebnisse"]');
    (await page.evaluate(() => location.hash)) === '#ergebnisse' ? ok('Datenbank: Reiterwechsel ändert die Adresse') : fail('Datenbank: Adresse nicht geändert');
    (await page.$('.sgnl-db [data-act="open"]')) ? fail('Datenbank: Link „Neues Fenster“ ist noch da') : ok('Datenbank: kein Link „Neues Fenster“');
    const fitted = await page.waitForFunction(() => {
      const b = document.querySelector('.sgnl-db__panel[data-tab="ergebnisse"] .sgnl-db__body');
      const f = b && b.querySelector('iframe');
      if (!b || !f || !b.hasAttribute('data-auto')) return false;
      const d = f.contentDocument; if (!d || !d.querySelector('table tbody tr')) return false;
      return { frame: f.getBoundingClientRect().height, content: d.body.scrollHeight };
    }, null, { timeout: 45000 }).then(h => h.jsonValue()).catch(() => null);
    if (fitted === null) {
      console.log('  WARN Ergebnisse nicht geladen (webclub nicht erreichbar?) – Höhenprüfung übersprungen');
    } else {
      fitted.frame >= fitted.content - 4 ? ok(`Datenbank: Rahmen passt sich an (${Math.round(fitted.frame)}px, kein Scrollen im Rahmen)`) : fail(`Datenbank: Rahmen ${fitted.frame}px < Inhalt ${fitted.content}px`);
      await page.screenshot({ path: path.join(SHOTS, 'datenbank-ergebnisse-desktop.png'), fullPage: true });
    }
    external.length ? fail('Browser lädt etwas von webclub: ' + external[0]) : ok('Browser lädt nichts von webclub');
    await page.click('.sgnl-db__tab[data-tab="live"]');
    (await page.isVisible('.sgnl-live')) ? ok('Datenbank: Live-Reiter') : fail('Datenbank: Live nicht sichtbar');
    await page.screenshot({ path: path.join(SHOTS, 'datenbank-live-desktop.png'), fullPage: true });
    await page.close();
  }

  // --- Block-Editor ---
  console.log('== Block-Editor');
  {
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.goto(BASE + '/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    for (const id of ids) {
      await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000 });
      await page.waitForTimeout(1500);
      const res = await page.evaluate(() => {
        const bad = []; let total = 0;
        const walk = bs => bs.forEach(b => { total++; if (!b.isValid) bad.push(b.name); walk(b.innerBlocks || []); });
        walk(wp.data.select('core/block-editor').getBlocks());
        return { total, bad, title: wp.data.select('core/editor').getEditedPostAttribute('title') };
      });
      res.bad.length ? fail(`Editor „${res.title}“: ungültige Blöcke ${res.bad.join(', ')}`) : ok(`Editor „${res.title}“: ${res.total} Blöcke gültig`);
    }
    // Termin-Formular
    await page.goto(`${BASE}/wp-admin/post-new.php?post_type=sgnl_event`, { waitUntil: 'networkidle' });
    (await page.isVisible('#sgnl-ev-start')) ? ok('Termin-Formular öffnet') : fail('Termin-Formular fehlt');
    await page.screenshot({ path: path.join(SHOTS, 'admin-termin-neu.png'), fullPage: true });
    await page.goto(`${BASE}/wp-admin/edit.php?post_type=sgnl_event`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(SHOTS, 'admin-termine.png'), fullPage: true });
    await page.close();
  }

  await browser.close();
  console.log(failed ? `\n${failed} Browser-Prüfungen fehlgeschlagen` : '\nAlle Browser-Prüfungen bestanden');
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
