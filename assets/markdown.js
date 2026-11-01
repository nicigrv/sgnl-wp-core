/*
  Mini-Markdown für Wettkampf-Nachrichten – genutzt von index.html und admin/.
  Sicher by design: Der Text wird zuerst komplett escaped, erst danach werden
  Formatierungen erzeugt. Links nur mit http(s):// oder mailto:.

  Unterstützt:
    # / ## / ###     Überschriften
    **fett**  __fett__   *kursiv*  _kursiv_   ~~durchgestrichen~~   `code`
    [Text](https://…)   nackte https://-Links
    - / * / + Liste    1. nummerierte Liste    > Zitat    --- Trennlinie
    Zeilenumbruch = Umbruch, Leerzeile = neuer Absatz
*/
(function (global) {
  'use strict';

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function safeHref(u) {
    u = String(u || '').trim();
    return /^(https?:\/\/|mailto:)/i.test(u) ? u : null;
  }

  function link(href, labelHtml) {
    return '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer">' + labelHtml + '</a>';
  }

  /* Hervorhebungen auf bereits escaptem Text (enthält keine Tags) */
  function emph(h) {
    return h
      .replace(/\*\*(?=\S)([^\n]*?\S)\*\*/g, '<strong>$1</strong>')
      .replace(/__(?=\S)([^\n]*?\S)__/g, '<strong>$1</strong>')
      .replace(/~~(?=\S)([^\n]*?\S)~~/g, '<del>$1</del>')
      .replace(/(^|[^*\w])\*(?=[^\s*])([^*\n]*?[^\s*])?\*(?![*\w])/g, (m, a, b) => b === undefined ? m : a + '<em>' + b + '</em>')
      .replace(/(^|[^_\w])_(?=[^\s_])([^_\n]*?[^\s_])?_(?![_\w])/g, (m, a, b) => b === undefined ? m : a + '<em>' + b + '</em>');
  }

  /* Links + Hervorhebungen (ohne Code-Spans) */
  function textPart(t) {
    const re = /\[([^\]\n]+)\]\(([^)\s]+)\)|(https?:\/\/[^\s<>()]*[^\s<>().,;:!?'"])/g;
    let out = '', last = 0, m;
    while ((m = re.exec(t))) {
      out += emph(esc(t.slice(last, m.index)));
      if (m[1] !== undefined) {
        const href = safeHref(m[2]);
        out += href ? link(href, emph(esc(m[1]))) : emph(esc(m[0]));
      } else {
        out += link(m[3], esc(m[3]));
      }
      last = re.lastIndex;
    }
    return out + emph(esc(t.slice(last)));
  }

  /* Inline: Code-Spans bleiben unformatiert */
  function inline(src) {
    return String(src).split(/(`[^`\n]+`)/g)
      .map((p, i) => i % 2 ? '<code>' + esc(p.slice(1, -1)) + '</code>' : textPart(p))
      .join('');
  }

  function render(src) {
    const lines = String(src ?? '').replace(/\r\n?/g, '\n').split('\n');
    let html = '', para = [], list = null, quote = [];

    const flushPara  = () => { if (para.length) { html += '<p>' + para.map(inline).join('<br>') + '</p>'; para = []; } };
    const flushList  = () => { if (list) { html += '<' + list.type + '>' + list.items.map(x => '<li>' + inline(x) + '</li>').join('') + '</' + list.type + '>'; list = null; } };
    const flushQuote = () => { if (quote.length) { html += '<blockquote>' + quote.map(inline).join('<br>') + '</blockquote>'; quote = []; } };
    const flushAll   = () => { flushPara(); flushList(); flushQuote(); };

    for (const raw of lines) {
      const line = raw.replace(/\s+$/, '');
      let m;
      if (!line.trim()) { flushAll(); continue; }

      if (/^\s{0,3}([-*_])(\s*\1){2,}$/.test(line)) { flushAll(); html += '<hr>'; continue; }

      if ((m = line.match(/^\s{0,3}(#{1,3})\s+(.+?)\s*#*$/))) {
        flushAll();
        const tag = 'h' + (m[1].length + 3); // # → h4 (Karten-Titel ist h3)
        html += '<' + tag + '>' + inline(m[2]) + '</' + tag + '>';
        continue;
      }

      if ((m = line.match(/^\s{0,3}>\s?(.*)$/))) { flushPara(); flushList(); quote.push(m[1]); continue; }

      if ((m = line.match(/^\s{0,3}[-*+]\s+(.*)$/))) {
        flushPara(); flushQuote();
        if (!list || list.type !== 'ul') { flushList(); list = { type: 'ul', items: [] }; }
        list.items.push(m[1]);
        continue;
      }
      if ((m = line.match(/^\s{0,3}\d{1,3}[.)]\s+(.*)$/))) {
        flushPara(); flushQuote();
        if (!list || list.type !== 'ol') { flushList(); list = { type: 'ol', items: [] }; }
        list.items.push(m[1]);
        continue;
      }

      // Eingerückte Folgezeile gehört noch zum letzten Listenpunkt
      if (list && /^\s{2,}\S/.test(line)) { list.items[list.items.length - 1] += '\n' + line.trim(); continue; }

      flushList(); flushQuote();
      para.push(line.trim());
    }
    flushAll();
    // Umbrüche innerhalb von Listenpunkten
    return html.replace(/(<li>[^]*?<\/li>)/g, s => s.replace(/\n/g, '<br>'));
  }

  global.sgnlMarkdown = render;

  /* Admin: vorhandene Nachrichten rendern + Live-Vorschau unter dem Textfeld */
  function init() {
    document.querySelectorAll('[data-md]').forEach(el => {
      el.innerHTML = render(el.textContent);
      el.removeAttribute('data-md');
    });
    document.querySelectorAll('[data-md-preview]').forEach(box => {
      const input = document.getElementById(box.getAttribute('data-md-preview'));
      const out = box.querySelector('[data-md-out]');
      if (!input || !out) return;
      const update = () => {
        const v = input.value.trim();
        box.hidden = !v;
        out.innerHTML = v ? render(v) : '';
      };
      input.addEventListener('input', update);
      update();
    });
  }
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
  }
})(typeof window !== 'undefined' ? window : globalThis);
