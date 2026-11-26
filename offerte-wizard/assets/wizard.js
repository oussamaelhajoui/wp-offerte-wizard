/*
 * Offerte Wizard - front-end.
 * Draait volledig in een Shadow DOM: de stijlen van het thema (Divi) komen er niet in,
 * en de stijlen van de wizard komen er niet uit. Alle tekst komt als textContent in de
 * DOM (nooit als HTML).
 */
(function () {
  'use strict';

  var CFG = window.OfferteWizardConfig;
  if (!CFG || !CFG.rest) { return; }

  var KEY = 'ow_state_v1';
  var MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
  var DAYS = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
  var SVG = 'http://www.w3.org/2000/svg';
  var ICONS = {
    tag: 'M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z M7.5 7.5h.01',
    calendar: 'M3 5h18v16H3z M3 10h18 M8 3v4 M16 3v4 M9 15l2 2 4-4',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 7v5l3 2',
    list: 'M9 6h12 M9 12h12 M9 18h12 M3.5 6l1 1 2-2 M3.5 12l1 1 2-2 M3.5 18l1 1 2-2',
    crane: 'M4 21h16 M7 21V9 M7 9h12 M7 9l5-5 7 5 M16 9v6 M15 15h2',
    tools: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z',
    check: 'M5 12l5 5 9-10',
    cross: 'M6 6l12 12 M18 6L6 18',
    ruler: 'M3 17L17 3l4 4L7 21z M7 13l2 2 M10 10l2 2 M13 7l2 2',
    plan: 'M4 4h16v16H4z M4 12h8 M12 4v8 M12 16h8 M8 12v8',
    house: 'M3 11l9-8 9 8 M5 10v10h14V10 M10 20v-6h4v6',
    flat: 'M3 8h18v12H3z M3 8l2-3h14l2 3 M10 20v-5h4v5',
    building: 'M5 21V4h14v17 M9 8h2 M13 8h2 M9 12h2 M13 12h2 M10 21v-5h4v5',
    brick: 'M3 6h18v12H3z M3 12h18 M9 6v6 M15 12v6',
    board: 'M4 5h16v14H4z M8 9h8 M8 13h5',
    block: 'M4 8l8-4 8 4v8l-8 4-8-4z M4 8l8 4 8-4 M12 12v8',
    camera: 'M4 8h4l2-3h4l2 3h4v12H4z M12 17a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z',
    file: 'M6 3h9l4 4v14H6z M14 3v5h5'
  };

  /* ---------- helpers ---------- */
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text !== undefined && text !== null) { e.textContent = text; }
    return e;
  }
  function icon(name) {
    var s = document.createElementNS(SVG, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    var p = document.createElementNS(SVG, 'path');
    p.setAttribute('d', ICONS[name] || ICONS.check);
    s.appendChild(p);
    return s;
  }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parseYmd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function fmtDate(s) { var p = s.split('-'); return p[2] + '-' + p[1] + '-' + p[0]; }
  function euro(n) { return '€' + Math.round(n).toLocaleString('nl-NL') + ',-'; }
  function fmtNum(n) { return (Math.round(n * 10) / 10).toLocaleString('nl-NL'); }

  /* Spiegel van NumberParser::parse (PHP). De server controleert opnieuw. */
  function parseNum(raw) {
    var s = String(raw === undefined || raw === null ? '' : raw).toLowerCase();
    s = s.replace(/vierkante meter|vierkant|m²|m\^2|m2/g, '').trim();
    if (!/^\d[\d.,\s]*$/.test(s)) { return null; }
    s = s.replace(/\s/g, '');
    var hc = s.indexOf(',') > -1, hd = s.indexOf('.') > -1;
    if (hc && hd && s.lastIndexOf('.') > s.lastIndexOf(',')) { return null; }
    if (hc) { s = s.replace(/\./g, '').replace(',', '.'); }
    else if (/^\d{1,3}(\.\d{3})+$/.test(s)) { s = s.replace(/\./g, ''); }
    var v = Number(s);
    return (isFinite(v) && v > 0 && v <= 100000) ? v : null;
  }

  function request(path, opts) {
    return fetch(CFG.rest + path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) {
          var err = new Error(j.message || 'Er ging iets mis. Probeer het opnieuw.');
          err.data = j.data;
          throw err;
        }
        return j;
      });
    });
  }

  function store(value) {
    try {
      if (value === undefined) { return JSON.parse(sessionStorage.getItem(KEY)); }
      if (value === null) { sessionStorage.removeItem(KEY); } else { sessionStorage.setItem(KEY, JSON.stringify(value)); }
    } catch (e) { /* opslag niet beschikbaar */ }
    return null;
  }

  function nextOf(step, answers) {
    var n = step.next;
    if (!n) { return null; }
    var w = n.when || [];
    for (var i = 0; i < w.length; i++) {
      if (String(answers[w[i].field]) === w[i].eq) { return w[i].goto; }
    }
    return n['default'] || null;
  }

  /* Afbeeldingen verkleinen voor upload (sneller op mobiel). */
  function prepare(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) { return Promise.resolve(file); }
    return createImageBitmap(file).then(function (bmp) {
      var s = Math.min(1, 2400 / Math.max(bmp.width, bmp.height));
      if (s === 1 && file.size < 2 * 1024 * 1024) { return file; }
      var c = document.createElement('canvas');
      c.width = Math.round(bmp.width * s);
      c.height = Math.round(bmp.height * s);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (res) {
        c.toBlob(function (b) { res(b ? new File([b], 'bouwplan.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85);
      });
    }).catch(function () { return file; });
  }

  /* ---------- Wizard ---------- */
  function Wizard(node) {
    this.node = node;
    this.fullpage = node.getAttribute('data-fullpage') === '1';
    this.bouwplan = node.getAttribute('data-bouwplan') !== 'uit';
    this.mode = node.getAttribute('data-mode') || '';
    this.state = this.freshState();
    var saved = store();
    if (saved && saved.step && saved.answers) { this.state = saved; }
    this.summary = null;
    this.hp = '';
    this.first = true;
  }

  Wizard.prototype.freshState = function () { return { step: 'welkom', answers: {}, history: [] }; };
  Wizard.prototype.save = function () { if (this.state.step === 'overzicht') { store(null); } else { store(this.state); } };

  Wizard.prototype.mount = function () {
    // Eigen tag-naam: generieke thema-regels voor div/section raken de wizard zo niet.
    var host = document.createElement('ow-host');
    host.setAttribute('data-ow-host', '');
    var base = 'all:initial!important;display:block!important;';
    if (this.fullpage) {
      host.style.cssText = base + 'position:fixed!important;top:0!important;right:0!important;bottom:0!important;left:0!important;z-index:2147483000!important;overflow-y:auto!important;background:#fff!important;-webkit-overflow-scrolling:touch!important;overscroll-behavior:contain!important;';
      document.body.appendChild(host);
      this.prevOverflow = document.documentElement.style.overflow;
      document.documentElement.style.overflow = 'hidden';
    } else {
      host.style.cssText = base + 'width:100%!important;max-width:100%!important;position:relative!important;';
      this.node.appendChild(host);
    }
    this.host = host;
    this.sr = host.attachShadow({ mode: 'open' });
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = CFG.css;
    this.sr.appendChild(link);
    this.root = el('div', 'ow' + (this.fullpage ? ' ow-full' : ''));
    this.root.style.visibility = 'hidden';
    this.sr.appendChild(this.root);
    var self = this;
    return new Promise(function (res) {
      link.onload = res; link.onerror = res; setTimeout(res, 2500);
    }).then(function () { self.root.style.visibility = ''; });
  };

  Wizard.prototype.boot = function () {
    var self = this;
    Promise.all([this.mount(), request('config')]).then(function (r) {
      var cfg = r[1];
      self.cfg = cfg;
      self.byId = {};
      cfg.steps.forEach(function (s) { self.byId[s.id] = s; });
      var b = cfg.brand;
      self.root.style.setProperty('--ow-accent', b.accent);
      self.root.style.setProperty('--ow-dark', b.dark);
      self.root.style.setProperty('--ow-soft', b.soft);
      var st = self.state;
      if (!self.byId[st.step] || st.step === 'overzicht') { self.state = self.freshState(); }
      self.show(self.state.step);
    }).catch(function () {
      if (self.root) {
        self.root.textContent = 'De offerteflow kan nu niet laden. Probeer het later opnieuw.';
        self.root.style.visibility = '';
      }
    });
  };

  Wizard.prototype.show = function (id) {
    var step = this.byId[id];
    this.state.step = id;
    this.save();
    var root = this.root;
    root.textContent = '';
    var wrap = el('div', 'wrap');
    root.appendChild(wrap);
    wrap.appendChild(this.header(step));
    var main = el('main', 'step');
    wrap.appendChild(main);
    var render = this['r_' + step.type];
    render.call(this, step, main);
    if (step.type !== 'summary') { wrap.appendChild(this.nav(step)); }
    if (!this.first) {
      var h = main.querySelector('h1');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
      if (this.fullpage) { this.host.scrollTop = 0; }
      else if (this.host.getBoundingClientRect().top < 0) { this.host.scrollIntoView({ block: 'start' }); }
    }
    this.first = false;
  };

  Wizard.prototype.header = function (step) {
    var head = el('header', 'head');
    var b = this.cfg.brand;
    if (b.logo) {
      var img = el('img', 'logo');
      img.alt = b.name;
      img.src = b.logo;
      img.onerror = function () { var t = el('div', 'logo-text', b.name); if (img.parentNode) { img.parentNode.replaceChild(t, img); } };
      head.appendChild(img);
    } else {
      head.appendChild(el('div', 'logo-text', b.name));
    }
    if (step.phase > 0) {
      var bar = el('div', 'bar');
      bar.setAttribute('role', 'progressbar');
      bar.setAttribute('aria-valuemin', '1');
      bar.setAttribute('aria-valuemax', '3');
      bar.setAttribute('aria-valuenow', String(step.phase));
      for (var i = 1; i <= 3; i++) {
        bar.appendChild(el('span', i < step.phase ? 'seg done' : (i === step.phase ? 'seg cur' : 'seg')));
      }
      head.appendChild(bar);
    }
    return head;
  };

  Wizard.prototype.titles = function (step, main) {
    main.appendChild(el('h1', 'h1', step.title));
    if (step.subtitle) { main.appendChild(el('p', 'sub', step.subtitle)); }
  };

  Wizard.prototype.nav = function (step) {
    var self = this;
    var nav = el('div', 'nav');
    if (this.state.history.length) {
      var back = el('button', 'btn', 'Terug');
      back.type = 'button';
      back.addEventListener('click', function () { self.back(); });
      nav.appendChild(back);
    }
    var next = el('button', 'btn primary', step.cta || 'Doorgaan');
    next.type = 'button';
    next.addEventListener('click', function () { self.next(); });
    nav.appendChild(next);
    return nav;
  };

  Wizard.prototype.toast = function (msg) {
    var old = this.root.querySelector('.toast');
    if (old) { old.remove(); }
    var t = el('div', 'toast', msg);
    t.setAttribute('role', 'alert');
    this.root.appendChild(t);
    setTimeout(function () { if (t.parentNode) { t.remove(); } }, 4000);
  };

  Wizard.prototype.next = function () {
    var step = this.byId[this.state.step];
    var err = this.validate(step);
    if (err) { this.toast(err); return; }
    if (step.submit) { this.submit(); return; }
    var n = nextOf(step, this.state.answers);
    if (!n) { return; }
    this.state.history.push(step.id);
    this.show(n);
  };

  Wizard.prototype.back = function () {
    var id = this.state.history.pop();
    if (id) { this.show(id); }
  };

  Wizard.prototype.validate = function (step) {
    var a = this.state.answers, t = this.cfg.texts;
    switch (step.type) {
      case 'choice':
        return step.options.some(function (o) { return a[step.field] === o.value; }) ? null : t.choice;
      case 'multi':
        return (a[step.field] && a[step.field].length) ? null : t.choice;
      case 'number':
        return parseNum(a[step.field]) !== null ? null : t.number;
      case 'upload':
        return a.plan_token ? null : t.upload;
      case 'review':
        if (!a.rooms || !a.rooms.length) { return null; }
        return (parseNum(a.m2_wand) !== null && parseNum(a.m2_plafond) !== null) ? null : t.number;
      case 'daterange':
        return (a.periode_start && a.periode_eind) ? null : t.period;
      case 'contact':
        return this.validateContact(step);
    }
    return null;
  };

  Wizard.prototype.validateContact = function (step) {
    var a = this.state.answers, t = this.cfg.texts, names = [];
    step.fields.forEach(function (f) { if (f.kind === 'field') { names.push(f.name); } });
    for (var i = 0; i < names.length; i++) {
      if (!String(a[names[i]] || '').trim()) { return t.contact; }
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(a.email).trim())) { return t.email; }
    var phone = String(a.phone_number).replace(/[\s\-().]/g, '');
    if (!/^(?:0|\+31|0031)[1-9]\d{8}$/.test(phone)) { return t.phone; }
    if (!/^[1-9]\d{3}\s?[a-zA-Z]{2}$/.test(String(a.post_code).trim())) { return t.postcode; }
    return null;
  };

  /* ---------- stap-renderers ---------- */
  Wizard.prototype.r_welcome = function (step, main) {
    this.titles(step, main);
    if (step.lead) { main.appendChild(el('p', 'lead', step.lead)); }
    var box = el('ul', 'items');
    step.items.forEach(function (it) {
      var li = el('li', 'item');
      var ic = el('span', 'ic'); ic.appendChild(icon(it.icon));
      var tx = el('span', 'tx');
      tx.appendChild(el('strong', null, it.title));
      tx.appendChild(el('span', 'muted', it.text));
      li.appendChild(ic); li.appendChild(tx);
      box.appendChild(li);
    });
    main.appendChild(box);
  };

  Wizard.prototype.r_choice = function (step, main) {
    var self = this, a = this.state.answers, multi = step.type === 'multi';
    this.titles(step, main);
    if (step.note) { main.appendChild(el('div', 'note', step.note)); }
    var list = el('div', 'cards');
    list.setAttribute('role', multi ? 'group' : 'radiogroup');
    step.options.forEach(function (o) {
      if (o.requires === 'bouwplan' && !self.bouwplan) { return; }
      var b = el('button', 'card');
      b.type = 'button';
      b.setAttribute('role', multi ? 'checkbox' : 'radio');
      var ic = el('span', 'ic big'); ic.appendChild(icon(o.icon));
      b.appendChild(ic);
      b.appendChild(el('span', 't', o.label));
      if (o.sub) { b.appendChild(el('span', 's', o.sub)); }
      var on = function () { return multi ? (a[step.field] || []).indexOf(o.value) > -1 : a[step.field] === o.value; };
      var sync = function () { b.setAttribute('aria-checked', on() ? 'true' : 'false'); };
      sync();
      b.addEventListener('click', function () {
        if (multi) {
          var cur = a[step.field] || [], i = cur.indexOf(o.value);
          if (i > -1) { cur.splice(i, 1); } else { cur.push(o.value); }
          a[step.field] = cur;
          sync();
        } else {
          a[step.field] = o.value;
          [].forEach.call(list.children, function (c) { c.setAttribute('aria-checked', 'false'); });
          sync();
        }
        self.save();
      });
      list.appendChild(b);
    });
    main.appendChild(list);
  };
  Wizard.prototype.r_multi = Wizard.prototype.r_choice;

  Wizard.prototype.r_number = function (step, main) {
    var self = this, a = this.state.answers;
    this.titles(step, main);
    var input = el('input', 'input');
    input.type = 'text';
    input.setAttribute('inputmode', 'decimal');
    input.setAttribute('enterkeyhint', 'next');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-label', step.title);
    input.placeholder = step.placeholder || '';
    input.value = a[step.field] || '';
    input.addEventListener('input', function () { a[step.field] = input.value; self.save(); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { self.next(); } });
    main.appendChild(input);
    this.hint(step, main);
  };

  Wizard.prototype.hint = function (step, main) {
    if (!step.hint) { return; }
    var h = el('div', 'hintbox');
    h.appendChild(el('strong', null, step.hint.title));
    h.appendChild(el('span', 'muted', step.hint.text));
    main.appendChild(h);
  };

  Wizard.prototype.r_text = function (step, main) {
    var self = this, a = this.state.answers;
    this.titles(step, main);
    var ta = el('textarea', 'input area');
    ta.rows = 7;
    ta.maxLength = 2000;
    ta.placeholder = step.placeholder || '';
    ta.setAttribute('aria-label', step.title);
    ta.value = a[step.field] || '';
    ta.addEventListener('input', function () { a[step.field] = ta.value; self.save(); });
    main.appendChild(ta);
  };

  Wizard.prototype.r_contact = function (step, main) {
    var self = this, a = this.state.answers;
    this.titles(step, main);
    var form = el('div', 'form');
    step.fields.forEach(function (f) {
      if (f.kind === 'heading') { form.appendChild(el('h2', 'h2', f.text)); return; }
      if (f.kind === 'note') {
        var n = el('div', 'note');
        n.appendChild(el('strong', null, f.title));
        n.appendChild(el('span', 'muted', f.text));
        form.appendChild(n);
        return;
      }
      var wrap = el('div', 'field');
      var id = 'ow-' + f.name;
      var lab = el('label', null, f.label);
      lab.htmlFor = id;
      var input = el('input', 'input');
      input.id = id;
      input.type = f.input === 'email' ? 'email' : (f.input === 'tel' ? 'tel' : 'text');
      input.setAttribute('autocomplete', f.autocomplete || 'on');
      if (f.input === 'email') { input.setAttribute('inputmode', 'email'); }
      if (f.input === 'tel') { input.setAttribute('inputmode', 'tel'); }
      input.placeholder = f.placeholder || '';
      input.value = a[f.name] || '';
      input.addEventListener('input', function () { a[f.name] = input.value; self.save(); });
      wrap.appendChild(lab); wrap.appendChild(input);
      if (f.hint) { wrap.appendChild(el('div', 'muted small', f.hint)); }
      form.appendChild(wrap);
    });
    var trap = el('div', 'trap');
    trap.setAttribute('aria-hidden', 'true');
    var tin = el('input');
    tin.type = 'text'; tin.tabIndex = -1; tin.setAttribute('autocomplete', 'off'); tin.name = 'website';
    tin.addEventListener('input', function () { self.hp = tin.value; });
    trap.appendChild(tin);
    form.appendChild(trap);
    main.appendChild(form);
  };

  Wizard.prototype.r_daterange = function (step, main) {
    var self = this, a = this.state.answers, today = this.cfg.today, t0 = parseYmd(today);
    var view = new Date(t0.getFullYear(), t0.getMonth(), 1);
    if (a.periode_start) { var s0 = parseYmd(a.periode_start); view = new Date(s0.getFullYear(), s0.getMonth(), 1); }
    this.titles(step, main);
    var cal = el('div', 'cal');
    var label = el('p', 'calstate');
    label.setAttribute('aria-live', 'polite');
    main.appendChild(cal);
    main.appendChild(label);
    this.hint(step, main);

    function pick(day) {
      if (!a.periode_start || a.periode_eind) { a.periode_start = day; a.periode_eind = ''; }
      else if (day < a.periode_start) { a.periode_start = day; }
      else { a.periode_eind = day; }
      self.save();
      draw();
    }
    function draw() {
      cal.textContent = '';
      var head = el('div', 'calhead');
      var prev = el('button', 'calnav', '‹');
      prev.type = 'button'; prev.setAttribute('aria-label', 'Vorige maand');
      var atStart = view.getFullYear() === t0.getFullYear() && view.getMonth() === t0.getMonth();
      prev.disabled = atStart;
      prev.addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); draw(); });
      var nxt = el('button', 'calnav', '›');
      nxt.type = 'button'; nxt.setAttribute('aria-label', 'Volgende maand');
      nxt.addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); draw(); });
      head.appendChild(prev);
      head.appendChild(el('strong', 'calmonth', MONTHS[view.getMonth()] + ' ' + view.getFullYear()));
      head.appendChild(nxt);
      cal.appendChild(head);
      var grid = el('div', 'calgrid');
      DAYS.forEach(function (d) { grid.appendChild(el('span', 'dow', d)); });
      var offset = (new Date(view.getFullYear(), view.getMonth(), 1).getDay() + 6) % 7;
      for (var i = 0; i < offset; i++) { grid.appendChild(el('span', 'blank')); }
      var count = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
      for (var d = 1; d <= count; d++) {
        var day = ymd(new Date(view.getFullYear(), view.getMonth(), d));
        var b = el('button', 'day', String(d));
        b.type = 'button';
        b.setAttribute('aria-label', d + ' ' + MONTHS[view.getMonth()] + ' ' + view.getFullYear());
        if (day < today) { b.disabled = true; }
        var st = a.periode_start, en = a.periode_eind;
        if (day === st || day === en) { b.className += ' sel'; b.setAttribute('aria-pressed', 'true'); }
        else if (st && en && day > st && day < en) { b.className += ' range'; }
        (function (dd) { b.addEventListener('click', function () { pick(dd); }); })(day);
        grid.appendChild(b);
      }
      cal.appendChild(grid);
      if (a.periode_start && a.periode_eind) {
        label.textContent = 'Je hebt de periode van ' + fmtDate(a.periode_start) + ' t/m ' + fmtDate(a.periode_eind) + ' geselecteerd.';
      } else if (a.periode_start) {
        label.textContent = 'Kies nu de laatste dag van je periode (of tik dezelfde dag nogmaals).';
      } else {
        label.textContent = 'Tik eerst de eerste dag en daarna de laatste dag van je periode.';
      }
    }
    draw();
  };

  Wizard.prototype.effectiveMode = function () { return this.mode || this.cfg.default_mode; };

  Wizard.prototype.r_upload = function (step, main) {
    var self = this, a = this.state.answers;
    this.titles(step, main);
    var mode = this.effectiveMode();
    var wantsAi = this.cfg.ai_possible && (mode === 'auto' || mode === 'ai');
    var consent = null;
    if (wantsAi) {
      var lab = el('label', 'check');
      consent = el('input');
      consent.type = 'checkbox';
      lab.appendChild(consent);
      lab.appendChild(el('span', null, 'Ik geef toestemming om dit bouwplan door een externe AI-dienst te laten uitlezen (sneller en nauwkeuriger).'));
      main.appendChild(lab);
    }
    var status = el('div', 'status');
    status.setAttribute('aria-live', 'polite');
    var file = el('input'); file.type = 'file'; file.hidden = true;
    file.accept = 'application/pdf,image/jpeg,image/png,image/webp';
    var cam = el('input'); cam.type = 'file'; cam.hidden = true;
    cam.accept = 'image/*'; cam.setAttribute('capture', 'environment');
    var pick = el('button', 'btn wide');
    pick.type = 'button';
    pick.appendChild(icon('file'));
    pick.appendChild(el('span', null, 'Kies bestand'));
    pick.addEventListener('click', function () { file.click(); });
    var box = el('div', 'uploadbox');
    box.appendChild(pick);
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
      var shot = el('button', 'btn wide');
      shot.type = 'button';
      shot.appendChild(icon('camera'));
      shot.appendChild(el('span', null, 'Foto maken'));
      shot.addEventListener('click', function () { cam.click(); });
      box.appendChild(shot);
    }
    box.appendChild(file); box.appendChild(cam);
    main.appendChild(box);
    main.appendChild(status);
    if (a.plan_token) { status.textContent = 'Bouwplan ontvangen. Kies een ander bestand om het te vervangen.'; }

    function upload(f) {
      if (!f) { return; }
      if (f.size > self.cfg.max_mb * 1048576) { self.toast('Het bestand is te groot (maximaal ' + self.cfg.max_mb + ' MB).'); return; }
      status.className = 'status busy';
      status.textContent = 'Bezig met uitlezen…';
      prepare(f).then(function (p) {
        var fd = new FormData();
        fd.append('plan', p, p.name || 'bouwplan');
        fd.append('mode', self.mode);
        fd.append('consent', consent && consent.checked ? '1' : '0');
        fd.append('session', self.cfg.session);
        return request('upload', { method: 'POST', body: fd });
      }).then(function (res) {
        a.plan_token = res.token;
        a.rooms = res.rooms || [];
        if (a.rooms.length) {
          a.m2_wand = String(res.totals.wall_m2);
          a.m2_plafond = String(res.totals.ceiling_m2);
          status.textContent = 'We vonden ' + a.rooms.length + ' ruimte' + (a.rooms.length === 1 ? '' : 's') + ' (plafond ' + fmtNum(res.totals.ceiling_m2) + ' m², wanden ' + fmtNum(res.totals.wall_m2) + ' m²). Controleer ze in de volgende stap.';
        } else {
          delete a.m2_wand; delete a.m2_plafond;
          status.textContent = 'Bouwplan ontvangen. We konden de m² niet automatisch uitlezen; je kunt ze in de volgende stap zelf invullen of leeg laten, dan bekijken wij de tekening.';
        }
        status.className = 'status ok';
        self.save();
      }).catch(function (err) {
        status.className = 'status bad';
        status.textContent = err.message;
      });
    }
    file.addEventListener('change', function () { upload(file.files[0]); });
    cam.addEventListener('change', function () { upload(cam.files[0]); });
  };

  Wizard.prototype.r_review = function (step, main) {
    var self = this, a = this.state.answers;
    this.titles(step, main);
    var totals = el('div', 'totals');
    function sum() {
      var f = 0, w = 0;
      (a.rooms || []).forEach(function (r) { f += parseNum(r.floor_m2) || 0; w += parseNum(r.wall_m2) || 0; });
      a.m2_plafond = String(Math.round(f * 10) / 10);
      a.m2_wand = String(Math.round(w * 10) / 10);
      totals.textContent = 'Totaal: ' + fmtNum(f) + ' m² plafond, ' + fmtNum(w) + ' m² wanden';
      self.save();
    }
    function numberField(labelText, get, set) {
      var wrap = el('div', 'field');
      var lab = el('label', null, labelText);
      var input = el('input', 'input');
      input.type = 'text';
      input.setAttribute('inputmode', 'decimal');
      input.setAttribute('autocomplete', 'off');
      input.value = get() === undefined ? '' : String(get()).replace('.', ',');
      input.addEventListener('input', function () { set(input.value); });
      wrap.appendChild(lab); wrap.appendChild(input);
      return wrap;
    }
    if (a.rooms && a.rooms.length) {
      a.rooms.forEach(function (r, i) {
        var card = el('div', 'room');
        card.appendChild(el('strong', null, r.name || 'Ruimte ' + (i + 1)));
        var row = el('div', 'tworow');
        row.appendChild(numberField('Plafond (m²)', function () { return r.floor_m2; }, function (v) { r.floor_m2 = v; sum(); }));
        row.appendChild(numberField('Wanden (m²)', function () { return r.wall_m2; }, function (v) { r.wall_m2 = v; sum(); }));
        card.appendChild(row);
        main.appendChild(card);
      });
      main.appendChild(totals);
      sum();
    } else {
      main.appendChild(el('div', 'note', 'We konden geen maten uitlezen. Vul de m² zelf in als je ze weet, of laat leeg: dan bekijken wij je bouwplan.'));
      main.appendChild(numberField('Wanden (m²)', function () { return a.m2_wand; }, function (v) { a.m2_wand = v; self.save(); }));
      main.appendChild(numberField('Plafonds (m²)', function () { return a.m2_plafond; }, function (v) { a.m2_plafond = v; self.save(); }));
    }
  };

  Wizard.prototype.renderLoading = function () {
    var root = this.root;
    root.textContent = '';
    var wrap = el('div', 'wrap');
    wrap.appendChild(this.header({ phase: 3 }));
    var main = el('main', 'step center');
    main.appendChild(el('h1', 'h1', 'We zetten je klus even op een rijtje…'));
    main.appendChild(el('p', 'sub', 'Nog héél even geduld - we maken je overzicht klaar!'));
    var sp = el('div', 'spinner');
    sp.setAttribute('role', 'status');
    sp.setAttribute('aria-label', 'Bezig');
    main.appendChild(sp);
    wrap.appendChild(main);
    root.appendChild(wrap);
  };

  Wizard.prototype.submit = function () {
    var self = this, started = Date.now();
    this.renderLoading();
    request('submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ answers: this.state.answers, session: this.cfg.session, hp: this.hp })
    }).then(function (res) {
      setTimeout(function () {
        self.summary = res.summary || {};
        store(null);
        self.state = { step: 'overzicht', answers: {}, history: [] };
        self.show('overzicht');
      }, Math.max(0, 1500 - (Date.now() - started)));
    }).catch(function (err) {
      var errs = err.data && err.data.errors;
      var id = errs ? Object.keys(errs)[0] : null;
      if (id && self.byId[id]) { self.show(id); self.toast(errs[id]); }
      else { self.show('beschrijving'); self.toast(err.message); }
    });
  };

  Wizard.prototype.r_summary = function (step, main) {
    var s = this.summary || {};
    main.appendChild(el('h1', 'h1', 'We hebben je aanvraag ontvangen.'));
    main.appendChild(el('p', 'sub', 'We nemen zo snel mogelijk contact met je op om jouw klus samen door te nemen en een offerte op te maken.'));
    if (s.periode) {
      var tiles = el('div', 'tiles');
      [['Periode', s.periode], ['Type', s.type], ['Werkzaamheden', s.werk]].forEach(function (t) {
        var d = el('div', 'tile');
        d.appendChild(el('span', 'tlabel', t[0]));
        d.appendChild(el('strong', null, t[1]));
        tiles.appendChild(d);
      });
      main.appendChild(tiles);
    }
    var box = el('div', 'sumbox');
    if (s.totaal) {
      var m = el('div', 'm2row');
      [['Wanden', s.wand], ['Plafonds', s.plafond], ['Totaal', s.totaal]].forEach(function (x) {
        if (x[1] === null || x[1] === undefined) { return; }
        var c = el('div', 'm2');
        c.appendChild(el('span', 'tlabel', x[0]));
        c.appendChild(el('strong', null, fmtNum(x[1]) + ' m²'));
        m.appendChild(c);
      });
      box.appendChild(m);
      [['Stucwerk', s.stucwerk], ['Schilderwerk', s.schilderwerk]].forEach(function (x) {
        if (!x[1]) { return; }
        var p = el('div', 'price');
        p.appendChild(el('span', 'tlabel', x[0]));
        p.appendChild(el('strong', null, 'Prijsindicatie is tussen ' + euro(x[1].min) + ' en ' + euro(x[1].max) + ' (inclusief arbeid, materiaal & BTW).'));
        p.appendChild(el('span', 'muted small', 'Dit is een richtprijs op basis van de ingevulde informatie. De definitieve offerte volgt na ons gesprek.'));
        box.appendChild(p);
      });
      if (s.price_hidden) {
        box.appendChild(el('p', 'muted pricehidden', 'Je prijsindicatie ontvang je van ons zodra we je aanvraag hebben bekeken.'));
      }
    } else {
      box.appendChild(el('p', 'muted', 'We berekenen de m² en de prijs samen met je tijdens ons telefoongesprek.'));
    }
    main.appendChild(box);
    var notes = el('ul', 'items');
    var add = function (ic, text) {
      var li = el('li', 'item');
      var i = el('span', 'ic'); i.appendChild(icon(ic));
      li.appendChild(i); li.appendChild(el('span', 'tx', text));
      notes.appendChild(li);
    };
    add('calendar', 'Op basis van je periode en de locatie' + (s.plaats ? ' in ' + s.plaats : '') + ' bevestigen we de planning tijdens ons telefoongesprek.');
    if (s.dagen) { add('clock', 'Aantal klusdagen zal ongeveer ' + s.dagen.min + '-' + s.dagen.max + ' dagen zijn. Stucwerk moet drogen; houd rekening met een droogtijd van 1,5-2 weken.'); }
    add('list', 'Goede voorbereiding is het halve werk. Maak de ruimtes zoveel mogelijk leeg en dek vloeren en ramen af; wij regelen de rest.');
    main.appendChild(notes);
  };

  /* ---------- start ---------- */
  function init() {
    var nodes = document.querySelectorAll('[data-offerte-wizard]:not([data-ow-ready])');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].setAttribute('data-ow-ready', '1');
      new Wizard(nodes[i]).boot();
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
