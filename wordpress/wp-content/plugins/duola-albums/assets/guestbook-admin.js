(() => {
  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-wall-confirm]');
    if (form && !window.confirm(form.dataset.wallConfirm)) {
      event.preventDefault();
    }
  });
})();
