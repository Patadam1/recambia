/* Recambia — interacción y movimiento. Sin dependencias. */
(function () {
  'use strict';

  // ---- Ajustes que puede cambiar el dueño -------------------------------
  var CONFIG = {
    plazoHoras: '[PLAZO]',       // p. ej. '24'. Se escribe en todos los [data-plazo].
    maxFotos: 3,
    maxMB: 8,
    endpoint: 'api/solicitud.php',
    // En la vista previa de GitHub Pages no hay PHP: el envío se simula.
    demo: /\.github\.io$/.test(location.hostname)
  };

  var root = document.documentElement;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('[data-plazo]').forEach(function (el) { el.textContent = CONFIG.plazoHoras; });

  // ---- Arranque de la portada: espera a las fuentes (máx. 700 ms) --------
  var started = false;
  function start() {
    if (started) return;
    started = true;
    void document.body.offsetHeight; // fija el estado inicial antes de animar
    root.classList.add('is-ready');
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(start);
  setTimeout(start, 700);

  // ---- Cabecera con sombra al bajar --------------------------------------
  var header = document.querySelector('.site-header');
  function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ---- Revelado al hacer scroll ------------------------------------------
  var revealables = document.querySelectorAll('[data-reveal], [data-reveal-group]');
  if ('IntersectionObserver' in window) {
    // Los titulares con máscara están recortados del todo (clip-path), así que
    // el observador mira a su contenedor, que sí ocupa espacio visible.
    var targets = new Map();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        (targets.get(e.target) || []).forEach(function (el) { el.classList.add('is-in'); });
        io.unobserve(e.target);
      });
    }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });
    revealables.forEach(function (el) {
      var t = el.dataset.reveal === 'mask' ? el.parentElement : el;
      if (!targets.has(t)) targets.set(t, []);
      targets.get(t).push(el);
      io.observe(t);
    });
  } else {
    revealables.forEach(function (el) { el.classList.add('is-in'); });
  }

  // ---- Matrícula de ejemplo que se escribe sola ---------------------------
  var plateInput = document.getElementById('matricula');
  (function plateDemo() {
    if (!plateInput || reduceMotion) return;
    var samples = ['1234 ABC', '4821 KLM', '0937 HJT', '7712 GZB'];
    var s = 0, i = 0, deleting = false, timer = null;
    function tick() {
      if (document.activeElement === plateInput || plateInput.value) { plateInput.placeholder = '1234 ABC'; timer = setTimeout(tick, 1200); return; }
      var word = samples[s];
      if (!deleting) {
        i++;
        plateInput.placeholder = word.slice(0, i) || ' ';
        if (i >= word.length) { deleting = true; timer = setTimeout(tick, 1800); return; }
        timer = setTimeout(tick, 110);
      } else {
        i--;
        plateInput.placeholder = word.slice(0, i) || ' ';
        if (i <= 0) { deleting = false; s = (s + 1) % samples.length; timer = setTimeout(tick, 350); return; }
        timer = setTimeout(tick, 45);
      }
    }
    setTimeout(tick, 1600);
  })();

  // ---- Barra fija en móvil ------------------------------------------------
  var mobileCta = document.querySelector('[data-mobile-cta]');
  var requestBox = document.getElementById('pedir');
  if (mobileCta && requestBox && 'IntersectionObserver' in window) {
    var formVisible = true;
    new IntersectionObserver(function (entries) {
      formVisible = entries[0].isIntersecting;
      update();
    }, { threshold: 0.05 }).observe(requestBox);
    var footer = document.querySelector('.site-footer');
    var footerVisible = false;
    new IntersectionObserver(function (entries) { footerVisible = entries[0].isIntersecting; update(); }).observe(footer);
    function update() { mobileCta.classList.toggle('is-visible', !formVisible && !footerVisible && window.scrollY > 300); }
    window.addEventListener('scroll', update, { passive: true });
  }

  // ======================================================================
  //  Formulario por pasos
  // ======================================================================
  var form = document.getElementById('request-form');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
  var bars = form.querySelectorAll('.progress span');
  var stepNow = form.querySelector('[data-step-now]');
  var manual = document.getElementById('manual');
  var toggleManual = form.querySelector('[data-toggle-manual]');
  var alertBox = form.querySelector('[data-form-alert]');
  var submitBtn = form.querySelector('[data-submit]');
  var current = 0;
  var fotos = [];

  form.elements.t.value = String(Date.now());
  steps.forEach(function (s, i) { if (i > 0) s.hidden = true; });

  function $(name) { return form.elements[name]; }
  function val(name) { var el = $(name); return el ? String(el.value || '').trim() : ''; }

  // -- Normalización
  function normPlate(v) { return v.toUpperCase().replace(/[\s\-·.]/g, ''); }
  function prettyPlate(v) {
    var p = normPlate(v);
    var m = p.match(/^(\d{4})([A-Z]{3})$/);
    return m ? m[1] + ' ' + m[2] : p;
  }
  var RE = {
    plateNew: /^\d{4}[BCDFGHJKLMNPRSTVWXYZ]{3}$/,
    plateOld: /^[A-Z]{1,2}\d{4,6}[A-Z]{0,2}$/,
    vin: /^[A-HJ-NPR-Z0-9]{17}$/,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
    cp: /^(0[1-9]|[1-4]\d|5[0-2])\d{3}$/
  };
  function normRef(v) { return v.toUpperCase().replace(/\s+/g, ' ').trim(); }

  // -- Modo: por matrícula o por referencia
  function mode() { var r = form.querySelector('input[name="modo"]:checked'); return r ? r.value : 'matricula'; }
  var panels = form.querySelectorAll('[data-mode-panel]');
  var piezaLabel = form.querySelector('[data-pieza-label]');
  var piezaOpt = form.querySelector('[data-pieza-opt]');
  var piezaHint = form.querySelector('[data-pieza-hint]');
  var fotosText = form.querySelector('[data-fotos-text]');
  function applyMode(animate) {
    var m = mode();
    panels.forEach(function (p) {
      var on = p.getAttribute('data-mode-panel') === m;
      p.hidden = !on;
      if (on && animate) { p.classList.remove('is-entering'); void p.offsetWidth; p.classList.add('is-entering'); }
    });
    form.querySelectorAll('.has-error').forEach(function (f) { f.classList.remove('has-error'); });
    var byRef = m === 'referencia';
    piezaLabel.textContent = byRef ? '¿Qué pieza es?' : '¿Qué pieza necesitas?';
    piezaOpt.hidden = !byRef;
    $('pieza').placeholder = byRef ? 'Ej.: disco de freno delantero' : 'Ej.: pastillas de freno delanteras';
    piezaHint.textContent = byRef
      ? 'Si nos dices qué es, comprobamos la referencia más rápido.'
      : 'Descríbela como puedas. Nosotros buscamos la referencia exacta.';
    fotosText.textContent = byRef ? 'Fotos de la pieza o de su etiqueta' : 'Fotos de la pieza';
  }
  form.querySelectorAll('input[name="modo"]').forEach(function (r) {
    r.addEventListener('change', function () {
      applyMode(!reduceMotion);
      var first = form.querySelector('[data-mode-panel="' + mode() + '"] input');
      if (first && window.matchMedia('(pointer: fine)').matches) first.focus({ preventScroll: true });
    });
  });
  applyMode(false);

  function phoneDigits(v) { return v.replace(/[^\d+]/g, '').replace(/^(\+34|0034)/, ''); }

  // -- Errores
  function setError(name, on, msg) {
    var f = form.querySelector('[data-field="' + name + '"]');
    if (!f) return;
    f.classList.toggle('has-error', on);
    var input = f.querySelector('input, textarea');
    if (input) input.setAttribute('aria-invalid', on ? 'true' : 'false');
    if (msg) { var m = f.querySelector('.error-msg'); if (m) m.textContent = msg; }
    if (on && !reduceMotion) { f.classList.remove('shake'); void f.offsetWidth; f.classList.add('shake'); }
  }
  form.addEventListener('input', function (e) {
    var f = e.target.closest('[data-field]');
    if (f && f.classList.contains('has-error')) { f.classList.remove('has-error'); }
  });
  form.addEventListener('animationend', function (e) { if (e.target.classList) e.target.classList.remove('shake'); });

  // -- Validación por paso
  function validate(stepIndex) {
    var bad = [];
    if (stepIndex === 0 && mode() === 'referencia') {
      var ref = normRef(val('referencia'));
      if (ref.length < 3 || !/^[A-Z0-9][A-Z0-9 .\-\/]*$/.test(ref)) bad.push(['referencia']);
      var pr = normPlate(val('matricula_ref'));
      if (pr && !(RE.plateNew.test(pr) || RE.plateOld.test(pr))) bad.push(['matricula_ref']);
    } else if (stepIndex === 0) {
      var plate = normPlate(val('matricula'));
      var manualOpen = !manual.hidden;
      var plateOk = RE.plateNew.test(plate) || RE.plateOld.test(plate);
      if (plate && !plateOk) bad.push(['matricula', 'Esa matrícula no parece válida. Revísala o elige tu coche a mano.']);
      if (!plate && !manualOpen) bad.push(['matricula', 'Escribe la matrícula (por ejemplo, 1234 ABC) o elige tu coche a mano.']);
      if (!plate && manualOpen) {
        if (!val('marca')) bad.push(['marca']);
        if (!val('modelo')) bad.push(['modelo']);
        var y = parseInt(val('anio'), 10), maxY = new Date().getFullYear() + 1;
        if (!(y >= 1950 && y <= maxY)) bad.push(['anio']);
      }
      var vin = val('bastidor').toUpperCase().replace(/\s/g, '');
      if (vin && !RE.vin.test(vin)) bad.push(['bastidor']);
    }
    if (stepIndex === 1) {
      if (mode() === 'matricula' && val('pieza').length < 3) bad.push(['pieza']);
    }
    if (stepIndex === 2) {
      if (val('nombre').length < 2) bad.push(['nombre']);
      if (!RE.email.test(val('email'))) bad.push(['email']);
      if (!/^[6789]\d{8}$/.test(phoneDigits(val('telefono')))) bad.push(['telefono']);
      if (!RE.cp.test(val('cp'))) bad.push(['cp']);
      if (!$('privacidad').checked) bad.push(['privacidad']);
    }
    bad.forEach(function (b) { setError(b[0], true, b[1]); });
    if (bad.length) {
      var first = form.querySelector('[data-field="' + bad[0][0] + '"] input, [data-field="' + bad[0][0] + '"] textarea');
      if (first) first.focus({ preventScroll: false });
    }
    return bad.length === 0;
  }

  // -- Navegación
  function go(to) {
    if (to === current || to < 0 || to >= steps.length) return;
    var back = to < current;
    steps[current].hidden = true;
    var next = steps[to];
    next.hidden = false;
    next.classList.remove('is-entering', 'is-back');
    void next.offsetWidth;
    next.classList.add('is-entering');
    if (back) next.classList.add('is-back');
    current = to;
    bars.forEach(function (b, i) { b.classList.toggle('is-on', i <= current); });
    stepNow.textContent = 'Paso ' + (current + 1);
    if (current === 1) fillCarChip();
    var focusable = next.querySelector('textarea, input:not([type=hidden]):not([tabindex="-1"])');
    if (focusable && window.matchMedia('(pointer: fine)').matches) focusable.focus({ preventScroll: true });
    var top = requestBox.getBoundingClientRect().top;
    if (top < 0) requestBox.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }

  form.addEventListener('click', function (e) {
    if (e.target.closest('[data-next]')) { if (validate(current)) go(current + 1); }
    else if (e.target.closest('[data-back]')) { go(current - 1); }
  });
  next_on_enter();
  function next_on_enter() {
    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.type === 'checkbox') return;
      if (current < steps.length - 1) { e.preventDefault(); if (validate(current)) go(current + 1); }
    });
  }

  toggleManual.addEventListener('click', function () {
    var open = manual.hidden;
    manual.hidden = !open;
    toggleManual.setAttribute('aria-expanded', String(open));
    toggleManual.textContent = open ? 'Prefiero poner la matrícula' : '¿No la sabes? Elige marca, modelo y año';
    if (open) { setError('matricula', false); plateInput.value = ''; manual.classList.remove('is-entering'); void manual.offsetWidth; manual.classList.add('is-entering'); $('marca').focus(); }
    else { plateInput.focus(); }
  });

  plateInput.addEventListener('blur', function () { if (plateInput.value) plateInput.value = prettyPlate(plateInput.value); });
  $('matricula_ref').addEventListener('blur', function (e) { if (e.target.value) e.target.value = prettyPlate(e.target.value); });

  function fillCarChip() {
    var chip = form.querySelector('[data-car-chip]');
    if (mode() === 'referencia') {
      var pr = val('matricula_ref');
      form.querySelector('[data-car-plate]').textContent = 'REF ' + normRef(val('referencia'));
      form.querySelector('[data-car-name]').textContent = [val('fabricante'), pr ? 'para ' + prettyPlate(pr) : ''].filter(Boolean).join(' · ') || 'Buscamos esta referencia exacta';
      chip.hidden = false;
      return;
    }
    var plate = val('matricula');
    var name = [val('marca'), val('modelo'), val('motor')].filter(Boolean).join(' ');
    if (val('anio')) name += (name ? ' · ' : '') + val('anio');
    form.querySelector('[data-car-plate]').textContent = plate ? prettyPlate(plate) : 'Sin matrícula';
    form.querySelector('[data-car-name]').textContent = plate ? 'Buscaremos la pieza exacta para este coche' : name;
    chip.hidden = !(plate || name);
  }

  // -- Fotos
  var fileInput = form.querySelector('input[type=file]');
  var thumbs = form.querySelector('[data-thumbs]');
  var drop = form.querySelector('[data-drop]');
  function renderThumbs() {
    thumbs.innerHTML = '';
    fotos.forEach(function (file, i) {
      var li = document.createElement('li');
      var img = document.createElement('img');
      img.alt = 'Foto ' + (i + 1);
      img.src = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(img.src); };
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Quitar foto ' + (i + 1));
      btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
      btn.addEventListener('click', function () { fotos.splice(i, 1); renderThumbs(); });
      li.appendChild(img); li.appendChild(btn); thumbs.appendChild(li);
    });
    drop.style.display = fotos.length >= CONFIG.maxFotos ? 'none' : '';
  }
  function addFiles(list) {
    var rejected = false;
    Array.prototype.forEach.call(list, function (f) {
      if (fotos.length >= CONFIG.maxFotos) { rejected = true; return; }
      if (!/^image\//.test(f.type) && !/\.(heic|heif)$/i.test(f.name)) { rejected = true; return; }
      if (f.size > CONFIG.maxMB * 1024 * 1024) { rejected = true; return; }
      fotos.push(f);
    });
    setError('fotos', rejected);
    renderThumbs();
  }
  if (window.DataTransfer) {
    fileInput.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });
  }
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
  drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer) addFiles(e.dataTransfer.files); });

  // -- Envío
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate(2)) return;
    alertBox.hidden = true;

    var data = new FormData(form);
    data.delete('fotos[]');
    fotos.forEach(function (f) { data.append('fotos[]', f, f.name); });
    // Solo se envían los datos del camino elegido
    if (mode() === 'referencia') {
      data.set('referencia', normRef(val('referencia')));
      data.set('matricula', normPlate(val('matricula_ref')));
      ['marca', 'modelo', 'anio', 'motor', 'bastidor'].forEach(function (k) { data.set(k, ''); });
    } else {
      data.set('matricula', normPlate(val('matricula')));
      data.set('referencia', '');
      data.set('fabricante', '');
    }
    data.delete('matricula_ref');

    submitBtn.setAttribute('aria-busy', 'true');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner" aria-hidden="true"></span>Enviando…';

    var request = CONFIG.demo
      ? new Promise(function (ok) { setTimeout(function () { ok({ ok: true, ref: 'SOL-DEMO' }); }, 900); })
      : fetch(CONFIG.endpoint, { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    request
      .then(function (res) {
        if (res && res.ok) return showDone(res.ref);
        if (res && res.errors) {
          var firstStep = null;
          Object.keys(res.errors).forEach(function (k) {
            setError(k, true, res.errors[k]);
            var f = form.querySelector('[data-field="' + k + '"]');
            var st = f && f.closest('.step');
            var idx = st ? steps.indexOf(st) : -1;
            if (idx > -1 && (firstStep === null || idx < firstStep)) firstStep = idx;
          });
          if (firstStep !== null && firstStep !== current) go(firstStep);
        }
        showAlert((res && res.message) || 'No hemos podido enviar tu solicitud. Inténtalo de nuevo en un momento.');
      })
      .catch(function () { showAlert('Parece que no hay conexión. Revisa tu internet e inténtalo de nuevo.'); })
      .then(resetSubmit);
  });
  function resetSubmit() {
    submitBtn.removeAttribute('aria-busy');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Pedir presupuesto';
  }
  function showAlert(msg) { alertBox.textContent = msg; alertBox.hidden = false; }
  function showDone(ref) {
    var done = document.querySelector('[data-done]');
    done.querySelector('[data-ref]').textContent = ref;
    done.querySelector('[data-done-email]').textContent = val('email');
    form.hidden = true;
    done.hidden = false;
    done.focus({ preventScroll: true });
    requestBox.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }
})();
