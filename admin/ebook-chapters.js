(function () {
  'use strict';
  var form = document.getElementById('chapter-form');
  var list = document.querySelector('.chapter-list');
  var orderStatus = document.getElementById('order-status');
  var saveStatus = document.getElementById('save-status');
  var retry = document.getElementById('retry-save');
  var timer, saving = null, orderSaving = null, saved, drag = null;

  function status(el, message, error) {
    el.textContent = message;
    el.dataset.error = error ? 'true' : 'false';
  }
  async function post(body) {
    var response = await fetch('ebook-chapters.php?product_id=' + PRODUCT_ID, {
      method: 'POST', body: body
    });
    var data;
    try { data = await response.json(); }
    catch (_) { throw new Error('Save failed. Check your connection or sign in again, then retry.'); }
    if (!response.ok || !data.success) throw new Error(data.error || 'Could not save. Please retry.');
    return data;
  }
  function snapshot() {
    if (typeof quill !== 'undefined' && quill) {
      document.getElementById('body-input').value = quill.root.innerHTML;
    }
    return new URLSearchParams(new FormData(form)).toString();
  }
  function dirty() { return form && snapshot() !== saved; }
  async function save() {
    clearTimeout(timer);
    if (!form) return true;
    if (saving) return saving;
    saving = (async function () {
      while (dirty()) {
        var current = snapshot();
        var body = new URLSearchParams(current);
        body.set('autosave', '1');
        status(saveStatus, 'Saving…');
        retry.hidden = true;
        try {
          await post(body);
          saved = current;
          var row = document.getElementById('ch-row-' + body.get('chapter_id'));
          if (row) {
            row.querySelector('.chapter-title-text').textContent = body.get('title');
            var dot = row.querySelector('.pub-dot');
            dot.classList.toggle('live', body.has('is_published'));
            dot.title = body.has('is_published') ? 'Published' : 'Draft';
          }
        } catch (error) {
          status(saveStatus, error.message, true);
          retry.hidden = false;
          return false;
        }
      }
      status(saveStatus, 'All changes saved');
      return true;
    })();
    try { return await saving; } finally { saving = null; }
  }
  if (form) {
    saved = snapshot();
    function changed() {
      clearTimeout(timer);
      document.querySelector('.editor-title span').textContent = document.getElementById('title').value;
      status(saveStatus, 'Unsaved changes');
      timer = setTimeout(save, 700);
    }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    if (typeof quill !== 'undefined' && quill) quill.on('text-change', changed);
    form.addEventListener('submit', function (event) { event.preventDefault(); save(); });
    retry.addEventListener('click', save);
    window.addEventListener('online', save);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden' && dirty()) save();
    });
  }

  function rows() { return Array.from(list.querySelectorAll('.chapter-item')); }
  function renumber() {
    rows().forEach(function (row, index) { row.querySelector('.chapter-num').textContent = index + 1; });
  }
  function restore(previous) { previous.forEach(function (row) { list.appendChild(row); }); renumber(); }
  async function persistOrder(previous) {
    if (rows().every(function (row, index) { return row === previous[index]; })) return true;
    list.classList.add('reordering');
    status(orderStatus, 'Saving order…');
    orderSaving = (async function () {
      try {
        await post(new URLSearchParams({
          csrf: CSRF_TOKEN, action: 'reorder_chapters',
          chapter_ids: JSON.stringify(rows().map(function (row) { return Number(row.dataset.id); }))
        }));
        status(orderStatus, 'Chapter order saved');
        return true;
      } catch (error) {
        restore(previous);
        status(orderStatus, error.message, true);
        return false;
      } finally { list.classList.remove('reordering'); }
    })();
    try { return await orderSaving; } finally { orderSaving = null; }
  }
  function begin(handle, pointerId) {
    if (drag || orderSaving) return false;
    drag = { row: handle.closest('.chapter-item'), handle: handle, previous: rows(), pointerId: pointerId };
    drag.row.classList.add('dragging');
    status(orderStatus, 'Move chapter, then release to save');
    return true;
  }
  function finish(cancel) {
    if (!drag) return;
    var previous = drag.previous;
    drag.row.classList.remove('dragging');
    if (drag.pointerId !== undefined && list.hasPointerCapture(drag.pointerId)) {
      list.releasePointerCapture(drag.pointerId);
    }
    drag = null;
    if (cancel) { restore(previous); status(orderStatus, 'Reorder cancelled'); }
    else { renumber(); status(orderStatus, 'Drag chapters to reorder'); persistOrder(previous); }
  }
  var panel = document.querySelector('.panel-list');
  var pointerY = 0;
  function moveAt(y) {
    var others = rows().filter(function (row) { return row !== drag.row; });
    var before = others.find(function (row) {
      var rect = row.getBoundingClientRect();
      return y < rect.top + rect.height / 2;
    });
    list.insertBefore(drag.row, before || null);
    renumber();
  }
  function scrollDrag() {
    if (!drag || drag.pointerId === undefined) return;
    var bounds = panel.getBoundingClientRect();
    var delta = pointerY < bounds.top + 50 ? -10 : pointerY > bounds.bottom - 70 ? 10 : 0;
    if (delta) { panel.scrollTop += delta; moveAt(pointerY); }
    requestAnimationFrame(scrollDrag);
  }
  list.addEventListener('pointerdown', function (event) {
    var handle = event.target.closest('.drag-handle');
    if (!handle || event.button !== 0 || !begin(handle, event.pointerId)) return;
    event.preventDefault();
    handle.focus();
    pointerY = event.clientY;
    list.setPointerCapture(event.pointerId);
    requestAnimationFrame(scrollDrag);
  });
  list.addEventListener('pointermove', function (event) {
    if (!drag || drag.pointerId !== event.pointerId) return;
    pointerY = event.clientY;
    moveAt(pointerY);
  });
  list.addEventListener('pointerup', function (event) {
    if (drag && drag.pointerId === event.pointerId) finish(false);
  });
  list.addEventListener('pointercancel', function () { finish(true); });
  list.addEventListener('lostpointercapture', function () { if (drag) finish(true); });
  list.addEventListener('keydown', function (event) {
    var handle = event.target.closest('.drag-handle');
    if (!handle) return;
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      if (drag) finish(false); else begin(handle);
    } else if (drag && event.key === 'Escape') {
      event.preventDefault(); finish(true);
    } else if (drag && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      event.preventDefault();
      var sibling = event.key === 'ArrowUp' ? drag.row.previousElementSibling : drag.row.nextElementSibling;
      if (sibling) list.insertBefore(drag.row, event.key === 'ArrowUp' ? sibling : sibling.nextElementSibling);
      renumber(); handle.focus(); drag.row.scrollIntoView({ block: 'nearest' });
    }
  });

  // Flush pending edits before navigating or adding/deleting a chapter.
  var leaving = false;
  async function beforeLeave() {
    if (drag) finish(true);
    if (orderSaving && !(await orderSaving)) return false;
    return save();
  }
  document.addEventListener('click', async function (event) {
    var link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    if (!dirty() && !saving && !orderSaving && !drag) return;
    event.preventDefault();
    if (leaving) return;
    leaving = true;
    if (await beforeLeave()) window.location.assign(link.href);
    else leaving = false;
  });
  document.addEventListener('submit', async function (event) {
    if (event.target === form || event.defaultPrevented) return;
    if (!dirty() && !saving && !orderSaving && !drag) return;
    event.preventDefault();
    if (leaving) return;
    leaving = true;
    if (await beforeLeave()) HTMLFormElement.prototype.submit.call(event.target);
    else leaving = false;
  });
  window.addEventListener('beforeunload', function (event) {
    if (dirty() || saving || orderSaving) { event.preventDefault(); event.returnValue = ''; }
  });
})();
