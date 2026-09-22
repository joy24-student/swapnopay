// Responsive navigation shared by the developer console and documentation.
(() => {
  const sidebar = document.getElementById('workspace-sidebar');
  const toggle = document.querySelector('.workspace-toggle');
  const backdrop = document.querySelector('.workspace-backdrop');
  const mobile = window.matchMedia('(max-width: 960px)');
  let previousOverflow = '';
  function setNavigation(open, restoreFocus = false) {
    open = open && mobile.matches;
    const wasOpen = document.body.classList.contains('nav-open');
    if (open && !wasOpen) {
      previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
    } else if (!open && wasOpen) document.body.style.overflow = previousOverflow;
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    backdrop.hidden = !open;
    sidebar.inert = mobile.matches && !open;
    if (open) sidebar.querySelector('input, a, button')?.focus();
    else if (restoreFocus) toggle.focus();
  }
  toggle.addEventListener('click', () => setNavigation(!document.body.classList.contains('nav-open')));
  backdrop.addEventListener('click', () => setNavigation(false, true));
  sidebar.addEventListener('click', event => {
    if (event.target.closest('a')) setNavigation(false);
  });
  mobile.addEventListener('change', () => setNavigation(false));
  document.addEventListener('keydown', event => {
    if (!document.body.classList.contains('nav-open')) return;
    if (event.key === 'Escape') setNavigation(false, true);
    if (event.key === 'Tab') {
      const focusable = [...sidebar.querySelectorAll('a,button,input')].filter(el => el.getClientRects().length && !el.closest('[hidden]'));
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  setNavigation(false);

  // Existing preview controls keep their behavior with keyboard and focus support.
  document.querySelectorAll('.modal-overlay, #video-modal').forEach(modal => {
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    if (!modal.hasAttribute('aria-labelledby')) modal.setAttribute('aria-label', modal.querySelector('h3,h4')?.textContent.trim() || 'Preview');
    let isOpen = false, trigger, overflow;
    const syncModal = () => {
      const open = modal.classList.contains('active') || (modal.id === 'video-modal' && !modal.classList.contains('hidden'));
      if (open === isOpen) return;
      isOpen = open;
      if (open) {
        trigger = document.activeElement;
        overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.querySelector('button')?.focus();
      } else {
        document.body.style.overflow = overflow || '';
        trigger?.focus({ preventScroll: true });
      }
    };
    new MutationObserver(syncModal).observe(modal, { attributes: true, attributeFilter: ['class'] });
    document.addEventListener('keydown', event => {
      if (!isOpen) return;
      if (event.key === 'Escape') modal.querySelector('button[onclick*="close"]')?.click();
      if (event.key === 'Tab') {
        const elements = [...modal.querySelectorAll('button,a,input,iframe')].filter(el => el.getClientRects().length);
        const first = elements[0], last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }
    });
  });

  const search = document.getElementById('docs-search');
  if (search) {
    const links = [...sidebar.querySelectorAll('nav a[href^="#"]')];
    const status = document.getElementById('docs-search-status');
    search.addEventListener('input', () => {
      const query = search.value.toLowerCase().trim();
      let matches = 0;
      links.forEach(link => {
        const match = link.textContent.toLowerCase().includes(query);
        link.closest('li').hidden = !match;
        if (match) matches++;
      });
      sidebar.querySelectorAll('nav > div').forEach(group => {
        const items = [...group.querySelectorAll('li')];
        if (items.length) group.hidden = items.every(item => item.hidden);
      });
      status.hidden = matches > 0;
    });
    const sections = links.map(link => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    const observer = new IntersectionObserver(entries => {
      const entry = entries.find(item => item.isIntersecting);
      if (!entry) return;
      links.forEach(link => {
        if (link.hash === '#' + entry.target.id) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      });
    }, { rootMargin: '-90px 0px -60% 0px', threshold: 0 });
    sections.forEach(section => observer.observe(section));
    document.addEventListener('keydown', event => {
      if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        if (mobile.matches) setNavigation(true);
        search.focus();
      }
    });
  }
})();
