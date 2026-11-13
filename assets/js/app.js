// Driftboard front-end: form validation, character counter, drag & drop.
// (No inline scripts anywhere - the Content-Security-Policy would block them.)
document.addEventListener('DOMContentLoaded', () => {

  // ---- flash messages: dismiss button + auto-fade ----
  document.querySelectorAll('.flash').forEach(box => {
    const close = () => { box.style.opacity = '0'; setTimeout(() => box.remove(), 400); };
    const x = box.querySelector('.flash-x');
    if (x) x.addEventListener('click', close);
    if (box.classList.contains('flash-success')) setTimeout(close, 6000);
  });

  // ---- friendly client-side validation (server re-checks everything) ----
  const showError = (input, msg) => {
    clearError(input);
    input.classList.add('invalid');
    const p = document.createElement('p');
    p.className = 'field-error js-error';
    p.textContent = msg;
    input.closest('label').appendChild(p);
  };
  const clearError = input => {
    input.classList.remove('invalid');
    const label = input.closest('label');
    label.querySelectorAll('.field-error').forEach(el => el.remove());
  };

  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', ev => {
      let firstBad = null;
      form.querySelectorAll('input, textarea').forEach(input => {
        if (input.type === 'hidden') return;
        clearError(input);
        const label = input.dataset.label || 'This field';
        const value = input.value.trim();
        let msg = '';
        if (input.required && value === '') msg = label + ' is required.';
        else if (input.dataset.min && value.length < +input.dataset.min) msg = label + ' must be at least ' + input.dataset.min + ' characters.';
        else if (input.type === 'email' && value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value)) msg = 'Please enter a valid email address.';
        else if (input.hasAttribute('data-password') && (input.value.length < 8 || !/[A-Za-z]/.test(input.value) || !/\d/.test(input.value))) msg = 'Use at least 8 characters, with a letter and a number.';
        else if (input.dataset.match && input.value !== form.elements[input.dataset.match].value) msg = 'The two passwords do not match.';
        if (msg) { showError(input, msg); firstBad = firstBad || input; }
      });
      if (firstBad) { ev.preventDefault(); firstBad.focus(); }
    });
    form.addEventListener('input', ev => { if (ev.target.classList.contains('invalid')) clearError(ev.target); });
  });

  // ---- live character counter ----
  document.querySelectorAll('textarea[data-counter]').forEach(area => {
    const out = area.closest('label').querySelector('.counter');
    const max = +area.dataset.counter;
    const update = () => { if (out) out.textContent = area.value.length + ' / ' + max + ' characters'; };
    area.addEventListener('input', update); update();
  });

  // ---- drag & drop between kanban columns ----
  const board = document.querySelector('.board');
  if (board) {
    let dragged = null;
    board.addEventListener('dragstart', ev => {
      const card = ev.target.closest('.task');
      if (!card) return;
      dragged = card; card.classList.add('dragging');
      ev.dataTransfer.effectAllowed = 'move';
      ev.dataTransfer.setData('text/plain', card.dataset.id);
    });
    board.addEventListener('dragend', () => {
      if (dragged) dragged.classList.remove('dragging');
      board.querySelectorAll('.over').forEach(z => z.classList.remove('over'));
    });
    board.querySelectorAll('.dropzone').forEach(zone => {
      zone.addEventListener('dragover', ev => { ev.preventDefault(); zone.classList.add('over'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('over'));
      zone.addEventListener('drop', async ev => {
        ev.preventDefault(); zone.classList.remove('over');
        if (!dragged) return;
        const status = zone.closest('.column').dataset.status;
        if (status === dragged.dataset.status) return;
        const body = new URLSearchParams({ id: dragged.dataset.id, status: status, csrf_token: board.dataset.csrf });
        try {
          const res = await fetch('task_status.php', {
            method: 'POST', body, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin'
          });
          if (!res.ok) throw new Error('rejected');
          window.location.reload();
        } catch (e) {
          alert('Sorry, the task could not be moved. Please refresh and try again.');
        }
      });
    });
  }
});
