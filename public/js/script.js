document.addEventListener('DOMContentLoaded', () => {
  const themeToggle = document.getElementById('themeToggle');
  const savedTheme = localStorage.getItem('library-theme');
  const initialTheme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.dataset.theme = initialTheme;
  const updateThemeButton = () => themeToggle?.setAttribute('aria-label', document.documentElement.dataset.theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
  updateThemeButton();
  themeToggle?.addEventListener('click', () => {
    document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('library-theme', document.documentElement.dataset.theme);
    updateThemeButton();
  });

  const sidebar = document.getElementById('appSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const menu = document.getElementById('menuToggle');
  const close = document.getElementById('sidebarClose');
  const openSidebar = () => { sidebar?.classList.add('is-open'); overlay?.classList.add('is-open'); menu?.setAttribute('aria-expanded', 'true'); };
  const closeSidebar = () => { sidebar?.classList.remove('is-open'); overlay?.classList.remove('is-open'); menu?.setAttribute('aria-expanded', 'false'); };
  menu?.addEventListener('click', openSidebar);
  close?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);

  const profileToggle = document.getElementById('profileToggle');
  const profileMenu = document.getElementById('profileDropdown');
  profileToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const open = profileMenu?.classList.toggle('is-open');
    profileToggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', () => { profileMenu?.classList.remove('is-open'); profileToggle?.setAttribute('aria-expanded', 'false'); });

  const overlayModal = document.getElementById('modalOverlay');
  const modalMessage = document.getElementById('modalMessage');
  const modalConfirm = document.getElementById('modalConfirm');
  const modalCancel = document.getElementById('modalCancel');
  let pendingForm = null;
  document.querySelectorAll('.js-confirm-delete').forEach((form) => form.addEventListener('submit', (event) => {
    event.preventDefault();
    pendingForm = form;
    if (!overlayModal) return form.submit();
    const name = form.dataset.name ? ` "${form.dataset.name}"` : '';
    modalMessage.textContent = `Data${name} akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`;
    overlayModal.hidden = false;
    modalConfirm?.focus();
  }));
  const closeModal = () => { if (overlayModal) overlayModal.hidden = true; pendingForm = null; };
  modalCancel?.addEventListener('click', closeModal);
  modalConfirm?.addEventListener('click', () => { if (pendingForm) { const form = pendingForm; pendingForm = null; form.submit(); } });
  overlayModal?.addEventListener('click', (event) => { if (event.target === overlayModal) closeModal(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeSidebar(); closeModal(); } });

  const fileInput = document.getElementById('cover_image');
  const preview = document.querySelector('.file-field__preview');
  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (!file || !preview) return;
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
  });

  const profilePhotoInput = document.querySelector('[data-profile-photo-input]');
  const profilePhotoField = document.querySelector('[data-profile-photo-field]');
  const profilePhotoLabel = document.querySelector('[data-profile-photo-label]');
  const profilePhotoStatus = document.querySelector('[data-profile-photo-status]');
  profilePhotoInput?.addEventListener('change', () => {
    const file = profilePhotoInput.files?.[0];
    if (!file) return;
    profilePhotoField?.classList.add('is-selected');
    if (profilePhotoLabel) profilePhotoLabel.textContent = 'Foto siap diunggah';
    if (profilePhotoStatus) profilePhotoStatus.textContent = file.name;
  });

  const viewToggle = document.getElementById('viewToggle');
  const tableView = document.getElementById('tableView');
  const catalogView = document.getElementById('catalogView');
  const catalogGrid = document.getElementById('bookCatalogGrid');
  const books = window.realBookCatalogData || [];
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
  const renderCatalog = () => {
    if (!catalogGrid || catalogGrid.children.length) return;
    if (!books.length) {
      catalogGrid.innerHTML = '<div class="empty-state"><span aria-hidden="true">B</span><h2>Belum ada buku</h2><p>Koleksi akan muncul di sini saat tersedia.</p></div>';
      return;
    }
    catalogGrid.innerHTML = books.map((book) => {
      const title = escapeHtml(book.title);
      const author = escapeHtml(book.author);
      const category = escapeHtml(book.category);
      const cover = book.cover ? `<img src="${escapeHtml(book.cover)}" alt="Cover ${title}">` : '<span aria-hidden="true">Lib<br>Sync</span>';
      const id = Number.parseInt(book.id, 10);
      const href = Number.isInteger(id) && id > 0 ? `/books/${id}` : '#';
      const stock = Number.isFinite(Number(book.stock)) ? Number(book.stock) : 0;
      return `<article class="catalog-card"><div class="catalog-card__cover">${cover}</div><div><p>${category}</p><h2>${title}</h2><small>${author}</small><div><span class="badge ${stock > 0 ? 'badge--success' : 'badge--danger'}">${stock > 0 ? `${stock} tersedia` : 'Stok habis'}</span><a href="${href}" aria-label="Lihat ${title}">→</a></div></div></article>`;
    }).join('');
  };
  viewToggle?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-view]');
    if (!button) return;
    const catalog = button.dataset.view === 'catalog';
    tableView.hidden = catalog;
    catalogView.hidden = !catalog;
    viewToggle.querySelectorAll('button').forEach((item) => item.classList.toggle('view-toggle__btn--active', item === button));
    if (catalog) renderCatalog();
  });
});
