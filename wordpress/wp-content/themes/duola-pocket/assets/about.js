(() => {
  const page = document.querySelector('[data-man-page]');
  const content = document.querySelector('[data-man-content]');
  const search = document.querySelector('[data-man-search]');
  const searchInput = document.querySelector('[data-man-search-input]');
  const searchStatus = document.querySelector('[data-man-search-status]');
  if (!page || !content || !search || !searchInput || !searchStatus) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let lastQuery = '';
  let nextIndex = 0;

  const scrollPage = (direction) => {
    window.scrollBy({ top: direction * Math.max(72, window.innerHeight * 0.22), behavior: reducedMotion ? 'auto' : 'smooth' });
  };

  const clearSelection = () => window.getSelection()?.removeAllRanges();

  const closeSearch = () => {
    search.hidden = true;
    searchStatus.textContent = '';
    clearSelection();
    content.focus({ preventScroll: true });
  };

  const openSearch = () => {
    search.hidden = false;
    searchInput.focus();
    searchInput.select();
  };

  const findNext = () => {
    const query = searchInput.value.trim();
    if (!query) {
      searchStatus.textContent = 'empty pattern';
      return;
    }

    const text = content.textContent || '';
    const normalizedText = text.toLocaleLowerCase();
    const normalizedQuery = query.toLocaleLowerCase();
    if (query !== lastQuery) nextIndex = 0;

    let index = normalizedText.indexOf(normalizedQuery, nextIndex);
    let wrapped = false;
    if (index < 0 && nextIndex > 0) {
      index = normalizedText.indexOf(normalizedQuery);
      wrapped = true;
    }

    if (index < 0 || !content.firstChild) {
      searchStatus.textContent = 'pattern not found';
      clearSelection();
      return;
    }

    const range = document.createRange();
    range.setStart(content.firstChild, index);
    range.setEnd(content.firstChild, index + query.length);
    clearSelection();
    window.getSelection()?.addRange(range);
    const bounds = range.getBoundingClientRect();
    window.scrollTo({ top: Math.max(0, window.scrollY + bounds.top - window.innerHeight * 0.32), behavior: reducedMotion ? 'auto' : 'smooth' });
    nextIndex = index + query.length;
    lastQuery = query;
    searchStatus.textContent = wrapped ? 'search wrapped' : `${index + 1}/${text.length}`;
  };

  const quit = () => {
    window.location.href = document.body.dataset.homeUrl || '/';
  };

  const runCommand = (command) => {
    if (command === 'down') scrollPage(1);
    if (command === 'up') scrollPage(-1);
    if (command === 'search') openSearch();
    if (command === 'quit') quit();
  };

  document.querySelectorAll('[data-man-command]').forEach((button) => {
    button.addEventListener('click', () => runCommand(button.dataset.manCommand));
  });
  document.querySelector('[data-man-search-close]')?.addEventListener('click', closeSearch);
  search.addEventListener('submit', (event) => {
    event.preventDefault();
    findNext();
  });

  document.addEventListener('keydown', (event) => {
    if (event.target === searchInput) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSearch();
      }
      return;
    }

    if (event.key === 'j') runCommand('down');
    if (event.key === 'k') runCommand('up');
    if (event.key === 'q') runCommand('quit');
    if (event.key === '/') {
      event.preventDefault();
      runCommand('search');
    }
    if (event.key === 'g') window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
    if (event.key === 'G') window.scrollTo({ top: document.documentElement.scrollHeight, behavior: reducedMotion ? 'auto' : 'smooth' });
  });

  content.focus({ preventScroll: true });
})();
