(() => {
  const board = document.querySelector('.board'); if (!board) return;
  const token = board.dataset.csrf; let dragged = null;
  board.querySelectorAll('.task-card').forEach(c => {
    c.addEventListener('dragstart', e => { dragged = c; e.dataTransfer.setData('text/plain', c.dataset.id); c.classList.add('dragging'); });
    c.addEventListener('dragend', () => c.classList.remove('dragging'));
  });
  board.querySelectorAll('.kcol[data-id]').forEach(col => {
    col.addEventListener('dragover', e => { if (dragged) { e.preventDefault(); col.classList.add('over'); } });
    col.addEventListener('dragleave', e => { if (!col.contains(e.relatedTarget)) col.classList.remove('over'); });
    col.addEventListener('drop', async e => {
      e.preventDefault(); col.classList.remove('over');
      if (!dragged || dragged.closest('.kcol') === col) return;
      col.querySelector('.klist').appendChild(dragged);
      const body = new URLSearchParams({ _csrf: token, column_id: col.dataset.id });
      try {
        const r = await fetch('/tasks/' + dragged.dataset.id + '/move', { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
        if (!r.ok) throw new Error();
      } catch { alert('Could not move the task.'); }
      location.reload();
    });
  });
})();
