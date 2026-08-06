(function () {
  const mask = document.getElementById('modalMask');
  const title = document.getElementById('modalTitle');
  const content = document.getElementById('modalContent');
  const closeBtn = document.getElementById('modalClose');
  const okBtn = document.getElementById('modalOk');

  function openModal(t, c) {
    if (!mask || !title || !content) return;
    title.textContent = t || '提示';
    content.textContent = c || '';
    mask.classList.add('show');
    mask.setAttribute('aria-hidden', 'false');
  }
  function closeModal() {
    if (!mask) return;
    mask.classList.remove('show');
    mask.setAttribute('aria-hidden', 'true');
  }
  function trackAction(id, action) {
    const body = new URLSearchParams();
    body.set('id', id);
    body.set('action', action || 'popup');
    fetch('track.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: body.toString(),
      credentials: 'same-origin'
    }).catch(function () {});
  }

  function copyText(text) {
    text = String(text || '');
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      ta.style.top = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        ok ? resolve() : reject(new Error('copy failed'));
      } catch (e) {
        document.body.removeChild(ta);
        reject(e);
      }
    });
  }

  document.querySelectorAll('.popup-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.dataset.title, btn.dataset.content);
      trackAction(btn.dataset.id, 'popup');
    });
  });

  document.querySelectorAll('.copy-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const tag = btn.querySelector('.tag');
      const old = tag ? tag.textContent : '';
      copyText(btn.dataset.copy || '').then(function () {
        if (tag) tag.textContent = '已复制';
        trackAction(btn.dataset.id, 'copy');
        setTimeout(function () { if (tag) tag.textContent = old || '复制'; }, 1200);
      }).catch(function () {
        openModal('复制失败', '当前浏览器不支持自动复制，请手动复制内容。');
      });
    });
  });

  [closeBtn, okBtn].forEach(function (btn) { if (btn) btn.addEventListener('click', closeModal); });
  if (mask) {
    mask.addEventListener('click', function (e) { if (e.target === mask) closeModal(); });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

  const copyBtn = document.getElementById('copyPermanent');
  const permanent = document.getElementById('permanentUrl');
  if (copyBtn && permanent) {
    copyBtn.addEventListener('click', function () {
      copyText(permanent.textContent.trim()).then(function () {
        const old = copyBtn.textContent;
        copyBtn.textContent = '已复制';
        setTimeout(function () { copyBtn.textContent = old; }, 1200);
      });
    });
  }

  const searchInput = document.getElementById('buttonSearch');
  const categoryFilter = document.getElementById('categoryFilter');
  const noResults = document.getElementById('noResults');
  const navItems = Array.from(document.querySelectorAll('[data-nav-item]'));
  let activeCategory = 'all';

  function normalize(value) {
    return String(value || '').toLowerCase().trim();
  }
  function applyFrontFilters() {
    const keyword = normalize(searchInput ? searchInput.value : '');
    let visible = 0;
    navItems.forEach(function (item) {
      const text = normalize(item.dataset.search || item.textContent);
      const category = item.dataset.category || '';
      const matchesKeyword = !keyword || text.indexOf(keyword) !== -1;
      const matchesCategory = activeCategory === 'all' || category === activeCategory;
      const show = matchesKeyword && matchesCategory;
      item.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    if (noResults) noResults.hidden = visible !== 0;
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFrontFilters);
  }
  if (categoryFilter) {
    categoryFilter.querySelectorAll('button[data-category]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeCategory = btn.dataset.category || 'all';
        categoryFilter.querySelectorAll('button').forEach(function (item) { item.classList.remove('active'); });
        btn.classList.add('active');
        applyFrontFilters();
      });
    });
  }
})();
