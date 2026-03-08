(function () {
  'use strict';

  /* ── DOM refs ──────────────────────────────────────── */
  const sidebar      = document.getElementById('sidebar');
  const overlay      = document.getElementById('sidebarOverlay');
  const breadcrumb   = document.getElementById('breadcrumb');
  const themeToggle  = document.getElementById('themeToggle');
  const menuToggle   = document.querySelector('.menu-toggle');
  const navLinks     = document.querySelectorAll('.nav-link[data-panel]');
  const panels       = document.querySelectorAll('.panel');

  /* ── Panel content loading ─────────────────────────── */
  const loadedPanels = new Set();
  const docsVersion = '12';

  function sanitizePanelHtml(html) {
    return html.replace(/<!-- Code injected by live-server -->[\s\S]*?<\/script>/gi, '');
  }

  async function loadPanel(id) {
    if (loadedPanels.has(id)) return;
    const panel = document.getElementById(id);
    if (!panel) return;
    try {
      const res = await fetch(`docs/${id}.fragment?v=${docsVersion}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const html = sanitizePanelHtml(await res.text());
      panel.innerHTML = html;
      loadedPanels.add(id);
    } catch (err) {
      console.error(`Failed to load panel "${id}":`, err);
      panel.innerHTML = `<p style="color:var(--color-danger,red);padding:2rem">Failed to load content for <strong>${id}</strong>.</p>`;
    }
  }

  /* ── Panel label map ───────────────────────────────── */
  const panelLabels = {
    overview:      'Overview',
    installation:  'Installation',
    architecture:  'System Architecture',
    whyddd:        'Why DDD?',
    cqrs:          'CQRS Pattern',
    eventflow:     'Event Flow',
    caching:       'Caching Strategy',
    'performance-optimization': 'Performance & Optimization',
    api:           'API Reference',
    countries:     'Countries',
    countryresolver: 'Country Resolver Lifecycle',
    seeddata:      'Seed Data',
    testing:       'Testing',
    observability: 'Observability',
    logging:       'Logging',
    grafana:       'Grafana Dashboards',
    hubui:         'Hub Service UI',
    deviations:    'Deviations & ADRs'
  };

  /* ── Mermaid — source preservation ─────────────────── */
  const mermaidSources = new Map();
  const renderedPanels = new Set();

  function hasMermaid() {
    return typeof window.mermaid !== 'undefined';
  }

  function applyMermaidFallback(panel) {
    if (!panel) return;
    panel.querySelectorAll('pre.mermaid').forEach(block => {
      if (!mermaidSources.has(block)) {
        mermaidSources.set(block, block.textContent.trim());
      }
      block.textContent = mermaidSources.get(block);
      block.classList.add('mermaid-fallback');
      block.removeAttribute('data-processed');
    });
  }

  function initMermaid(theme) {
    if (!hasMermaid()) return false;
    mermaid.initialize({
      startOnLoad: false,
      theme: theme === 'dark' ? 'dark' : 'default',
      maxTextSize: 100000,
      flowchart: { useMaxWidth: true, htmlLabels: true },
      sequence:  { useMaxWidth: true }
    });
    return true;
  }

  /* ── Diagram zoom modal ────────────────────────────── */
  let diagramModal = null;
  let diagramFitFrame = 0;

  function fitDiagramModalContent() {
    if (!diagramModal || !diagramModal.classList.contains('diagram-view')) return;

    const content = diagramModal.querySelector('.diagram-modal-content');
    const pre = content ? content.querySelector('pre.mermaid') : null;
    const svg = pre ? pre.querySelector('svg') : null;
    if (!content || !pre || !svg) return;

    const viewBox = svg.viewBox && svg.viewBox.baseVal;
    const naturalWidth = (viewBox && viewBox.width)
      || parseFloat(svg.getAttribute('width'))
      || svg.getBoundingClientRect().width;
    const naturalHeight = (viewBox && viewBox.height)
      || parseFloat(svg.getAttribute('height'))
      || svg.getBoundingClientRect().height;
    const availableWidth = pre.clientWidth;
    const availableHeight = pre.clientHeight;

    if (!(naturalWidth > 0 && naturalHeight > 0 && availableWidth > 0 && availableHeight > 0)) return;

    const scale = Math.min(availableWidth / naturalWidth, availableHeight / naturalHeight);
    const fittedWidth = `${Math.floor(naturalWidth * scale)}px`;
    const fittedHeight = `${Math.floor(naturalHeight * scale)}px`;

    svg.setAttribute('width', fittedWidth);
    svg.setAttribute('height', fittedHeight);
    svg.style.setProperty('width', fittedWidth, 'important');
    svg.style.setProperty('height', fittedHeight, 'important');
    svg.style.setProperty('max-width', 'none', 'important');
    svg.style.setProperty('max-height', 'none', 'important');
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
  }

  function scheduleDiagramFit() {
    window.cancelAnimationFrame(diagramFitFrame);
    diagramFitFrame = window.requestAnimationFrame(fitDiagramModalContent);
  }

  function ensureDiagramModal() {
    if (diagramModal) return;
    diagramModal = document.createElement('div');
    diagramModal.id = 'diagram-modal';
    diagramModal.setAttribute('role', 'dialog');
    diagramModal.setAttribute('aria-modal', 'true');
    diagramModal.setAttribute('aria-label', 'Diagram zoom view');
    diagramModal.innerHTML =
      '<div class="diagram-modal-inner">'
      + '<button class="diagram-modal-close" aria-label="Close diagram">×</button>'
      + '<div class="diagram-modal-content"></div>'
      + '</div>';
    document.body.appendChild(diagramModal);

    diagramModal.addEventListener('click', e => {
      if (e.target === diagramModal) closeDiagramModal();
    });
    diagramModal.querySelector('.diagram-modal-close')
      .addEventListener('click', closeDiagramModal);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && diagramModal.classList.contains('open'))
        closeDiagramModal();
    });

    window.addEventListener('resize', scheduleDiagramFit);
  }

  function openDiagramModal(pre) {
    ensureDiagramModal();
    diagramModal.classList.remove('image-view');
    diagramModal.classList.add('diagram-view');
    const content = diagramModal.querySelector('.diagram-modal-content');
    content.innerHTML = '';

    const source = mermaidSources.get(pre);
    if (source) {
      const freshPre = document.createElement('pre');
      freshPre.className = 'mermaid';
      freshPre.textContent = source;
      content.appendChild(freshPre);

      diagramModal.classList.add('open');
      document.body.style.overflow = 'hidden';

      const theme = document.documentElement.getAttribute('data-theme');
      if (!initMermaid(theme)) {
        freshPre.classList.add('mermaid-fallback');
      } else {
        Promise.resolve(mermaid.run({ nodes: [freshPre] }))
          .then(() => scheduleDiagramFit())
          .catch(err => {
            console.error('Failed to render Mermaid diagram in modal:', err);
            freshPre.classList.add('mermaid-fallback');
          });
      }
    } else {
      diagramModal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    diagramModal.querySelector('.diagram-modal-close').focus();
  }

  function closeDiagramModal() {
    if (!diagramModal) return;
    diagramModal.classList.remove('open', 'image-view', 'diagram-view');
    document.body.style.overflow = '';
  }

  /* ── Image zoom ────────────────────────────────────────── */
  function openImageModal(img) {
    ensureDiagramModal();
    diagramModal.classList.remove('diagram-view');
    diagramModal.classList.add('image-view');
    const content = diagramModal.querySelector('.diagram-modal-content');
    content.innerHTML = '';
    const bigImg = document.createElement('img');
    bigImg.src = img.src;
    bigImg.alt = img.alt || '';
    content.appendChild(bigImg);
    diagramModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    diagramModal.querySelector('.diagram-modal-close').focus();
  }

  function injectImageZoomButtons(panel) {
    panel.querySelectorAll('.screenshot-item, .screenshot-figure').forEach(container => {
      if (container.classList.contains('has-img-zoom')) return;
      const img = container.querySelector('img');
      if (!img) return;
      container.classList.add('has-img-zoom');
      const btn = document.createElement('button');
      btn.className = 'img-zoom-btn';
      btn.setAttribute('aria-label', 'View full size');
      btn.title = 'View full size';
      btn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3'
        + 'm0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>'
        + '</svg>';
      btn.addEventListener('click', () => openImageModal(img));
      container.appendChild(btn);
    });
  }

  /* ── Copy buttons (code blocks) ─────────────────────────── */
  function injectCopyButtons(panel) {
    panel.querySelectorAll('pre:not(.mermaid)').forEach(pre => {
      if (pre.classList.contains('has-copy-btn')) return;
      pre.classList.add('has-copy-btn');
      const btn = document.createElement('button');
      btn.className = 'copy-btn';
      btn.setAttribute('aria-label', 'Copy code');
      btn.title = 'Copy to clipboard';
      btn.textContent = 'Copy';
      btn.addEventListener('click', () => {
        const source = (pre.querySelector('code') || pre).textContent.trim();
        const reset = () => {
          setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
        };
        if (navigator.clipboard) {
          navigator.clipboard.writeText(source).then(() => {
            btn.textContent = 'Copied!'; btn.classList.add('copied'); reset();
          }).catch(() => {
            btn.textContent = 'Failed'; reset();
          });
        } else {
          /* fallback */
          const ta = document.createElement('textarea');
          ta.value = source; ta.style.cssText = 'position:fixed;opacity:0';
          document.body.appendChild(ta); ta.select();
          document.execCommand('copy'); document.body.removeChild(ta);
          btn.textContent = 'Copied!'; btn.classList.add('copied'); reset();
        }
      });
      pre.appendChild(btn);
    });
  }

  /* ── JSON Syntax Highlighting ────────────────────── */
  function highlightJSON(panel) {
    panel.querySelectorAll('pre:not(.mermaid) code').forEach(code => {
      if (code.dataset.highlighted) return;
      const text = code.textContent.trim();
      if (!/^[\[{]/.test(text)) return; /* only JSON-looking blocks */
      const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      const highlighted = text.replace(
        /("(\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?|[{}\.\[\],:])/g,
        match => {
          let cls = 'json-punct';
          if (/^"|/.test(match) && match.startsWith('"')) {
            cls = /:$/.test(match) ? 'json-key' : 'json-string';
          } else if (match === 'true' || match === 'false') {
            cls = 'json-bool';
          } else if (match === 'null') {
            cls = 'json-null';
          } else if (/^-?\d/.test(match)) {
            cls = 'json-number';
          }
          return '<span class="' + cls + '">' + esc(match) + '</span>';
        }
      );
      code.innerHTML = highlighted;
      code.dataset.highlighted = 'true';
    });
  }

  function injectDiagramZoomButtons(panel) {
    panel.querySelectorAll('pre.mermaid').forEach(pre => {
      /* skip if wrapper already added */
      if (pre.parentElement && pre.parentElement.classList.contains('diagram-wrapper')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'diagram-wrapper';
      pre.parentNode.insertBefore(wrapper, pre);
      wrapper.appendChild(pre);

      const btn = document.createElement('button');
      btn.className = 'diagram-zoom-btn';
      btn.setAttribute('aria-label', 'Zoom diagram');
      btn.title = 'Open full screen';
      btn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3'
        + 'm0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>'
        + '</svg>';
      btn.addEventListener('click', () => openDiagramModal(pre));
      wrapper.appendChild(btn);
    });
  }

  function renderMermaidInPanel(panelId) {
    const panel = document.getElementById(panelId);
    if (!panel) return;

    const blocks = panel.querySelectorAll('pre.mermaid');
    if (!blocks.length) return;

    if (!hasMermaid()) {
      applyMermaidFallback(panel);
      return;
    }

    blocks.forEach(block => {
      /* Save original source on first encounter */
      if (!mermaidSources.has(block)) {
        mermaidSources.set(block, block.textContent.trim());
      }
      /* Restore source for re-render */
      block.textContent = mermaidSources.get(block);
      block.classList.remove('mermaid-fallback');
      block.removeAttribute('data-processed');
    });

    /* Let Mermaid re-render, then inject zoom buttons */
    const p = mermaid.run({ nodes: blocks });
    Promise.resolve(p)
      .then(() => injectDiagramZoomButtons(panel))
      .catch(err => {
        console.error(`Failed to render Mermaid diagrams for panel "${panelId}":`, err);
        applyMermaidFallback(panel);
      });
    renderedPanels.add(panelId);
  }

  /* ── Panel switching ───────────────────────────────── */
  async function switchPanel(id) {
    panels.forEach(p => p.classList.remove('active'));
    navLinks.forEach(l => l.classList.remove('active'));

    const target = document.getElementById(id);
    if (target) target.classList.add('active');

    navLinks.forEach(l => {
      if (l.dataset.panel === id) l.classList.add('active');
    });

    breadcrumb.textContent = panelLabels[id] || id;
    history.replaceState(null, '', '#' + id);

    /* Close mobile sidebar */
    sidebar.classList.remove('open');
    overlay.classList.remove('active');

    /* Load content from docs/{id}.fragment then render Mermaid */
    await loadPanel(id);
    const panelEl = document.getElementById(id);
    if (panelEl) {
      highlightJSON(panelEl);
      injectImageZoomButtons(panelEl);
      injectCopyButtons(panelEl);
    }
    renderMermaidInPanel(id);
  }

  /* ── Wire up nav links ─────────────────────────────── */
  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      switchPanel(link.dataset.panel);
    });
  });

  /* ── Mobile menu ───────────────────────────────────── */
  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }

  /* ── Dark mode ─────────────────────────────────────── */
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    /* Re-init Mermaid with new theme and clear rendered cache */
    initMermaid(theme);
    renderedPanels.clear();
    /* Re-render Mermaid in the currently active panel */
    const active = document.querySelector('.panel.active');
    if (active) renderMermaidInPanel(active.id);
  }

  const savedTheme = localStorage.getItem('theme') || 'light';
  applyTheme(savedTheme);

  themeToggle.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });

  /* ── Initial panel from hash ───────────────────────── */
  const hash = location.hash.replace('#', '');
  if (hash && panelLabels[hash]) {
    switchPanel(hash);
  } else {
    switchPanel('overview');
  }
})();
