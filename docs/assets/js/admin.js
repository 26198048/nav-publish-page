(function () {
  'use strict';

  const select = document.getElementById('actionType');
  if (select) {
    const sync = () => {
      const isPopup = select.value === 'popup';
      const isCopy = select.value === 'copy';
      document.querySelectorAll('.action-link-fields').forEach(el => { el.style.display = (!isPopup && !isCopy) ? 'block' : 'none'; });
      document.querySelectorAll('.action-popup-fields').forEach(el => { el.style.display = isPopup ? 'block' : 'none'; });
      document.querySelectorAll('.action-copy-fields').forEach(el => { el.style.display = isCopy ? 'block' : 'none'; });
    };
    select.addEventListener('change', sync);
    sync();
  }

  const iconValue = document.getElementById('iconValue');
  const iconPreview = document.getElementById('iconPreview');
  const iconUpload = document.getElementById('iconUpload');
  const emojiChoices = document.querySelectorAll('.emoji-choice');
  const isImageIcon = (value) => /^(assets\/img\/[A-Za-z0-9._\/-]+|i\/[a-f0-9]{12}\.(?:png|jpe?g|gif|webp))$/i.test(value || '');
  const iconDisplaySrc = (value) => {
    if (!value) return '';
    // 后台位于 /admin/，数据库中仍保存 assets/... 或 i/...，预览时补 ../ 避免图片裂开。
    if (/\/admin(?:\/|$)/.test(window.location.pathname)) return '../' + value.replace(/^\/+/, '');
    return value;
  };

  const renderPreviewText = (value) => {
    if (!iconPreview) return;
    iconPreview.innerHTML = '';
    iconPreview.textContent = value || '🔗';
  };

  const renderPreviewImage = (src) => {
    if (!iconPreview) return;
    iconPreview.innerHTML = '';
    const img = document.createElement('img');
    img.src = src;
    img.alt = '';
    iconPreview.appendChild(img);
  };

  if (iconValue && iconPreview) {
    emojiChoices.forEach(btn => {
      btn.addEventListener('click', () => {
        const value = btn.dataset.icon || '🔗';
        iconValue.value = value;
        emojiChoices.forEach(item => item.classList.remove('selected'));
        btn.classList.add('selected');
        if (iconUpload) iconUpload.value = '';
        if (isImageIcon(value)) renderPreviewImage(iconDisplaySrc(value));
        else renderPreviewText(value);
      });
    });
  }

  if (iconUpload && iconPreview) {
    iconUpload.addEventListener('change', () => {
      const file = iconUpload.files && iconUpload.files[0];
      if (!file) return;
      const allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      if (!allowed.includes(file.type)) {
        window.alert('图标只支持 png、jpg、jpeg、gif、webp。');
        iconUpload.value = '';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        window.alert('图标文件不能超过 2MB。');
        iconUpload.value = '';
        return;
      }
      emojiChoices.forEach(item => item.classList.remove('selected'));
      renderPreviewImage(URL.createObjectURL(file));
    });
  }

  document.querySelectorAll('[data-bg-select]').forEach(bgSelect => {
    bgSelect.addEventListener('change', () => {
      const selected = bgSelect.options[bgSelect.selectedIndex];
      const card = bgSelect.closest('.background-card');
      const preview = card && card.querySelector('.bg-preview');
      const css = selected && selected.dataset ? selected.dataset.bg : '';
      if (preview && css) preview.style.backgroundImage = css;
      if (bgSelect.value !== 'custom') {
        const fileInput = card && card.querySelector('[data-bg-upload]');
        if (fileInput) fileInput.value = '';
      }
    });
  });

  document.querySelectorAll('[data-bg-upload]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      const allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      if (!allowed.includes(file.type)) {
        window.alert('背景图只支持 png、jpg、jpeg、gif、webp。');
        input.value = '';
        return;
      }
      if (file.size > 6 * 1024 * 1024) {
        window.alert('背景图片不能超过 6MB。');
        input.value = '';
        return;
      }
      const card = input.closest('.background-card');
      const preview = card && card.querySelector('.bg-preview');
      const bgSelect = card && card.querySelector('[data-bg-select]');
      if (preview) preview.style.backgroundImage = `url('${URL.createObjectURL(file)}')`;
      if (bgSelect) bgSelect.value = 'custom';
    });
  });


  const logoUpload = document.querySelector('[data-logo-upload]');
  if (logoUpload) {
    logoUpload.addEventListener('change', () => {
      const file = logoUpload.files && logoUpload.files[0];
      if (!file) return;
      const allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
      if (!allowed.includes(file.type)) {
        window.alert('Logo 只支持 png、jpg、jpeg、gif、webp。');
        logoUpload.value = '';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        window.alert('Logo 图片不能超过 2MB。');
        logoUpload.value = '';
        return;
      }
      const preview = document.querySelector('.logo-setting-preview img');
      if (preview) {
        preview.style.display = 'block';
        preview.src = URL.createObjectURL(file);
      }
    });
  }

  document.querySelectorAll('.js-confirm-delete').forEach(form => {
    form.addEventListener('submit', function (event) {
      if (!window.confirm('确认删除这个按钮？')) event.preventDefault();
    });
  });


  const bulkForm = document.getElementById('bulkForm');
  const bulkChecks = Array.from(document.querySelectorAll('.bulk-check'));
  const bulkSelectAll = document.querySelector('.bulk-select-all');
  const bulkCount = document.querySelector('[data-bulk-count]');
  const updateBulkState = () => {
    const selected = bulkChecks.filter(item => item.checked).length;
    if (bulkCount) bulkCount.textContent = `已选择 ${selected} 项`;
    if (bulkSelectAll) {
      bulkSelectAll.checked = selected > 0 && selected === bulkChecks.length;
      bulkSelectAll.indeterminate = selected > 0 && selected < bulkChecks.length;
    }
  };
  if (bulkSelectAll) {
    bulkSelectAll.addEventListener('change', () => {
      bulkChecks.forEach(item => { item.checked = bulkSelectAll.checked; });
      updateBulkState();
    });
  }
  bulkChecks.forEach(item => item.addEventListener('change', updateBulkState));
  updateBulkState();
  if (bulkForm) {
    bulkForm.addEventListener('submit', event => {
      bulkForm.querySelectorAll('input[name="ids[]"]').forEach(node => node.remove());
      const selected = bulkChecks.filter(item => item.checked).map(item => item.value);
      const action = bulkForm.querySelector('[name="batch_action"]');
      if (!selected.length) {
        window.alert('请先勾选要批量管理的按钮。');
        event.preventDefault();
        return;
      }
      if (!action || !action.value) {
        window.alert('请选择批量操作。');
        event.preventDefault();
        return;
      }
      if (action.value === 'delete' && !window.confirm(`确认批量删除选中的 ${selected.length} 个按钮？此操作不可恢复。`)) {
        event.preventDefault();
        return;
      }
      selected.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        bulkForm.appendChild(input);
      });
    });
  }

  const table = document.getElementById('buttonsTable');
  if (!table) return;

  const tbody = table.querySelector('tbody');
  const status = document.getElementById('sortStatus');
  let draggingRow = null;
  let moved = false;
  let lastClientY = null;
  let rafId = null;
  let saveTimer = null;
  let cleanupListeners = [];

  const setStatus = (text, type) => {
    if (!status) return;
    status.textContent = text || '';
    status.className = 'sort-status' + (type ? ' ' + type : '');
  };

  const rows = () => Array.from(tbody.querySelectorAll('tr[data-id]'));

  const refreshSortCells = () => {
    rows().forEach((row, index) => {
      const cell = row.querySelector('.sort-order-cell');
      if (cell) cell.textContent = String((index + 1) * 10);
    });
  };

  const addTempListener = (target, type, handler, options) => {
    target.addEventListener(type, handler, options);
    cleanupListeners.push(() => target.removeEventListener(type, handler, options));
  };

  const clearTempListeners = () => {
    cleanupListeners.forEach(fn => fn());
    cleanupListeners = [];
  };

  const autoScroll = (clientY) => {
    const margin = 72;
    const speed = 18;
    if (clientY < margin) window.scrollBy(0, -speed);
    if (clientY > window.innerHeight - margin) window.scrollBy(0, speed);
  };

  const rowAfterPointer = (clientY) => {
    const candidates = rows().filter(row => row !== draggingRow);
    let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    candidates.forEach(row => {
      const box = row.getBoundingClientRect();
      const offset = clientY - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset, element: row };
      }
    });
    return closest.element;
  };

  const applyDragMove = () => {
    rafId = null;
    if (!draggingRow || typeof lastClientY !== 'number') return;
    autoScroll(lastClientY);
    const beforeRow = rowAfterPointer(lastClientY);
    const oldIndex = rows().indexOf(draggingRow);
    if (beforeRow == null) {
      if (tbody.lastElementChild !== draggingRow) tbody.appendChild(draggingRow);
    } else if (beforeRow !== draggingRow && beforeRow !== draggingRow.nextElementSibling) {
      tbody.insertBefore(draggingRow, beforeRow);
    }
    const newIndex = rows().indexOf(draggingRow);
    if (oldIndex !== newIndex) moved = true;
    refreshSortCells();
  };

  const moveDraggingRow = (clientY) => {
    if (!draggingRow) return;
    lastClientY = clientY;
    if (!rafId) rafId = window.requestAnimationFrame(applyDragMove);
  };

  const saveOrder = () => {
    const ids = rows().map(row => row.dataset.id).filter(Boolean);
    if (!ids.length) return;
    setStatus('正在保存排序...', 'saving');
    fetch('buttons.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({
        op: 'reorder',
        csrf_token: table.dataset.csrf || '',
        order: ids.join(',')
      })
    })
      .then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.message || '排序保存失败');
        refreshSortCells();
        setStatus('排序已保存', 'ok');
        window.setTimeout(() => setStatus('', ''), 1800);
      })
      .catch(error => {
        setStatus(error.message || '排序保存失败，请刷新后重试', 'error');
      });
  };

  const startDrag = (row, clientY) => {
    if (!row || draggingRow) return;
    draggingRow = row;
    moved = false;
    lastClientY = clientY;
    row.classList.add('dragging');
    document.body.classList.add('is-dragging-sort');
    setStatus('拖动中，松手后自动保存...', 'saving');
  };

  const finishDrag = () => {
    if (!draggingRow) return;
    const shouldSave = moved;
    draggingRow.classList.remove('dragging');
    document.body.classList.remove('is-dragging-sort');
    draggingRow = null;
    lastClientY = null;
    if (rafId) {
      window.cancelAnimationFrame(rafId);
      rafId = null;
    }
    clearTempListeners();
    refreshSortCells();
    window.clearTimeout(saveTimer);
    if (shouldSave) saveTimer = window.setTimeout(saveOrder, 100);
    else setStatus('', '');
    moved = false;
  };

  tbody.querySelectorAll('.drag-handle').forEach(handle => {
    const row = handle.closest('tr[data-id]');
    if (!row) return;

    handle.setAttribute('title', '按住这里拖拽排序');

    // PC 端使用 mousedown/mousemove/mouseup，不依赖浏览器原生拖拽，兼容大多数电脑浏览器和虚拟主机面板。
    handle.addEventListener('mousedown', event => {
      if (event.button !== 0) return;
      startDrag(row, event.clientY);
      const onMove = e => {
        moveDraggingRow(e.clientY);
        e.preventDefault();
      };
      const onUp = e => {
        finishDrag();
        e.preventDefault();
      };
      addTempListener(document, 'mousemove', onMove, false);
      addTempListener(document, 'mouseup', onUp, false);
      addTempListener(window, 'blur', finishDrag, false);
      event.preventDefault();
    });

    // 手机和平板端使用 touch 事件；passive:false 保证可以阻止页面滚动。
    handle.addEventListener('touchstart', event => {
      const touch = event.touches && event.touches[0];
      if (!touch) return;
      startDrag(row, touch.clientY);
      const onMove = e => {
        const t = e.touches && e.touches[0];
        if (!t) return;
        moveDraggingRow(t.clientY);
        e.preventDefault();
      };
      const onEnd = e => {
        finishDrag();
        e.preventDefault();
      };
      addTempListener(document, 'touchmove', onMove, { passive: false });
      addTempListener(document, 'touchend', onEnd, { passive: false });
      addTempListener(document, 'touchcancel', onEnd, { passive: false });
      event.preventDefault();
    }, { passive: false });

    // 防止浏览器原生拖动文字/图片干扰排序。
    handle.addEventListener('dragstart', event => event.preventDefault());

    // 键盘兜底：选中“☰”后用上下键调整顺序。
    handle.addEventListener('keydown', event => {
      const currentRow = handle.closest('tr[data-id]');
      if (!currentRow) return;
      if (event.key === 'ArrowUp') {
        const prev = currentRow.previousElementSibling;
        if (prev && prev.matches('tr[data-id]')) {
          tbody.insertBefore(currentRow, prev);
          refreshSortCells();
          saveOrder();
          event.preventDefault();
        }
      }
      if (event.key === 'ArrowDown') {
        const next = currentRow.nextElementSibling;
        if (next && next.matches('tr[data-id]')) {
          tbody.insertBefore(next, currentRow);
          refreshSortCells();
          saveOrder();
          event.preventDefault();
        }
      }
    });
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) finishDrag();
  });
})();
