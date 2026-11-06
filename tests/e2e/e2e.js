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

  // --- Team-Karten: mittig bzw. bündig mit der Überschrift ---
  console.log('== Mannschaften');
  {
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.goto(BASE + '/ueber-uns/unsere-mannschaften/', { waitUntil: 'networkidle' });
    const tiles = await page.$$eval('.sgnl-albums.is-teams .sgnl-album', els => els.map(e => e.textContent.trim()));
    tiles.length === 2 ? ok('Übersicht zeigt 2 Mannschafts-Kacheln') : fail('Mannschafts-Kacheln: ' + tiles.length);
    await page.goto(BASE + '/ueber-uns/unsere-mannschaften/1-mannschaft/', { waitUntil: 'networkidle' });
    const m = await page.evaluate(() => {
      const grids = [...document.querySelectorAll('.sgnl-team')];
      const res = grids.map(g => {
        const head = g.previousElementSibling;
        const cards = [...g.children].map(c => c.getBoundingClientRect());
        const gr = g.getBoundingClientRect();
        const left = Math.min(...cards.map(c => c.left)), right = Math.max(...cards.map(c => c.right));
        return { left: g.classList.contains('is-team-left'), headLeft: head.getBoundingClientRect().left, cardLeft: left,
          gap: Math.abs((left - gr.left) - (gr.right - right)), sameRow: cards.every(c => Math.abs(c.top - cards[0].top) < 2) };
      });
      return res;
    });
    const c = m.find(x => !x.left), l = m.find(x => x.left);
    c && c.gap < 3 && c.sameRow ? ok('Team-Karten mittig') : fail('Team-Karten nicht mittig: ' + JSON.stringify(c));
    l && Math.abs(l.cardLeft - l.headLeft) < 3 ? ok('Team-Karten linksbündig auf einer Linie mit der Überschrift') : fail('Linksbündig verschoben: ' + JSON.stringify(l));
    await page.screenshot({ path: path.join(SHOTS, 'mannschaft-team-karten.png'), fullPage: true });
    await page.close();
  }

  // --- Trainingszeiten: Filter und Ansicht ---
  console.log('== Trainingszeiten');
  {
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.goto(BASE + '/ueber-uns/trainingszeiten/', { waitUntil: 'networkidle' });
    const vis = sel => page.$$eval(sel, els => els.filter(e => e.offsetParent !== null).length);
    const allDays = await vis('.sgnl-tday');
    allDays === 6 ? ok('Wochenplan: 6 Trainingstage') : fail('Wochenplan zeigt ' + allDays + ' Tage');
    await page.click('.sgnl-training__chip:has-text("1. Mannschaft")');
    const s1 = await vis('.sgnl-tr'), d1 = await vis('.sgnl-tday');
    s1 === 3 && d1 === 3 ? ok('Filter „1. Mannschaft“: 3 Einheiten an 3 Tagen') : fail(`Filter: ${s1} Einheiten, ${d1} Tage`);
    await page.screenshot({ path: path.join(SHOTS, 'training-filter.png'), fullPage: true });
    await page.click('.sgnl-training__views button[data-view="groups"]');
    const g1 = await vis('.sgnl-tgroup');
    g1 === 1 ? ok('Ansicht „Nach Gruppen“ mit Filter: 1 Gruppe') : fail('Nach Gruppen: ' + g1);
    await page.click('.sgnl-training__chip[data-group=""]');
    const gAll = await vis('.sgnl-tgroup');
    gAll >= 9 ? ok('Alle Gruppen: ' + gAll + ' Karten') : fail('Alle Gruppen: ' + gAll);
    await page.screenshot({ path: path.join(SHOTS, 'training-gruppen.png'), fullPage: true });
    await page.reload({ waitUntil: 'networkidle' });
    const kept = await page.getAttribute('.sgnl-training', 'data-view');
    kept === 'groups' ? ok('Gewählte Ansicht wird gemerkt') : fail('Ansicht nicht gemerkt: ' + kept);
    await page.close();
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
    const loaded = await page.waitForFunction(() => {
      const b = document.querySelector('.sgnl-db__panel[data-tab="ergebnisse"] .sgnl-db__body');
      const f = b && b.querySelector('iframe');
      const d = f && f.contentDocument;
      return !!(b && b.hasAttribute('data-auto') && d && d.querySelector('table tbody tr'));
    }, null, { timeout: 45000 }).then(() => true).catch(() => false);
    const measureFit = () => page.evaluate(() => {
      const f = document.querySelector('.sgnl-db__panel[data-tab="ergebnisse"] .sgnl-db__body iframe');
      return { frame: f.getBoundingClientRect().height, content: f.contentDocument.body.scrollHeight };
    });
    let fitted = null;
    if (loaded) {
      // Inhalt wird nachgeladen → der Rahmen wächst mit (kurz warten, bis er nachgezogen hat)
      await page.waitForFunction(() => {
        const f = document.querySelector('.sgnl-db__panel[data-tab="ergebnisse"] .sgnl-db__body iframe');
        return f.getBoundingClientRect().height >= f.contentDocument.body.scrollHeight - 4;
      }, null, { timeout: 10000 }).catch(() => {});
      fitted = await measureFit();
    }
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
    // Trainingszeiten bearbeiten
    {
      await page.goto(`${BASE}/wp-admin/edit.php?post_type=sgnl_event&page=sgnl-training`, { waitUntil: 'networkidle' });
      const rows = await page.$$eval('#sgnl-ta-rows tr', r => r.length);
      rows === 19 ? ok('Trainingszeiten-Tabelle: 19 Zeilen') : fail('Trainingszeiten-Tabelle: ' + rows);
      await page.click('#sgnl-ta-add');
      const last = page.locator('#sgnl-ta-rows tr').last();
      await last.locator('select').selectOption('7');
      await last.locator('.sgnl-ta__hm').first().fill('10:00');
      await last.locator('.sgnl-ta__hm').nth(1).fill('11');
      await last.locator('input[name$="[group]"]').fill('Testgruppe');
      await last.locator('textarea').fill('Erika Muster');
      await page.screenshot({ path: path.join(SHOTS, 'admin-trainingszeiten.png'), fullPage: true });
      await Promise.all([page.waitForNavigation(), page.click('#sgnl-ta-form button[type="submit"]')]);
      const saved = await page.$$eval('#sgnl-ta-rows tr', r => r.length);
      saved === 20 ? ok('Neue Trainingszeit gespeichert') : fail('Nach dem Speichern: ' + saved + ' Zeilen');
      await page.goto(BASE + '/ueber-uns/trainingszeiten/', { waitUntil: 'networkidle' });
      (await page.content()).includes('Testgruppe') && (await page.content()).includes('Sonntag') ? ok('… und auf der Website sichtbar') : fail('Neue Zeit fehlt auf der Website');
    }
    // Mannschaften-Übersicht: Bildauswahl im Editor
    {
      const tid = ids[ids.length - 1];
      await page.goto(`${BASE}/wp-admin/post.php?post=${tid}&action=edit`, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor').getBlocks().length, null, { timeout: 60000 });
      const cid = await page.evaluate(() => { const b = wp.data.select('core/block-editor').getBlocks().find(x => x.name === 'sgnl/albums'); if (b) { wp.data.dispatch('core/block-editor').selectBlock(b.clientId); } return b ? b.clientId : null; });
      if (!cid) { fail('Editor: Mannschaften-Übersicht nicht gefunden'); }
      else {
        const ed = page.frameLocator('iframe[name="editor-canvas"]');
        const sidebar = page.locator('.interface-complementary-area, .editor-sidebar').first();
        const okPanel = await page.waitForFunction(() => /Bilder der Mannschaften/.test(document.body.innerText) && /1\. Mannschaft/.test(document.body.innerText), null, { timeout: 30000 }).then(() => true).catch(() => false);
        okPanel ? ok('Editor: Bildauswahl je Mannschaft sichtbar') : fail('Editor: Bildauswahl fehlt');
        await page.screenshot({ path: path.join(SHOTS, 'admin-mannschaften-bilder.png'), fullPage: false });
      }
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
