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
  let previouslyFocused = null;
  document.querySelectorAll('.js-confirm-delete').forEach((form) => form.addEventListener('submit', (event) => {
    event.preventDefault();
    pendingForm = form;
    if (!overlayModal) return form.submit();
    previouslyFocused = document.activeElement;
    const name = form.dataset.name ? ` "${form.dataset.name}"` : '';
    modalMessage.textContent = `Data${name} akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`;
    overlayModal.hidden = false;
    overlayModal.setAttribute('aria-hidden', 'false');
    modalConfirm?.focus();
  }));
  const closeModal = () => {
    if (overlayModal) {
      overlayModal.hidden = true;
      overlayModal.setAttribute('aria-hidden', 'true');
    }
    pendingForm = null;
    previouslyFocused?.focus?.();
    previouslyFocused = null;
  };
  modalCancel?.addEventListener('click', closeModal);
  modalConfirm?.addEventListener('click', () => { if (pendingForm) { const form = pendingForm; pendingForm = null; form.submit(); } });
  overlayModal?.addEventListener('click', (event) => { if (event.target === overlayModal) closeModal(); });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeSidebar(); closeModal(); closeProfilePhotoConfirm?.(true); }
    if (event.key !== 'Tab') return;
    const activeOverlay = overlayModal && !overlayModal.hidden
      ? overlayModal
      : profilePhotoConfirmOverlay && !profilePhotoConfirmOverlay.hidden
        ? profilePhotoConfirmOverlay
        : null;
    if (!activeOverlay) return;
    const focusable = [...activeOverlay.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  const fileInput = document.getElementById('cover_image');
  const preview = document.querySelector('.file-field__preview');
  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (!file || !preview) return;
    let previewImage = preview.tagName === 'IMG' ? preview : preview.querySelector('img');
    if (!previewImage) {
      previewImage = document.createElement('img');
      previewImage.alt = 'Pratinjau cover buku';
      previewImage.loading = 'eager';
      previewImage.decoding = 'async';
      previewImage.className = 'book-cover__preview-image';
      preview.replaceChildren(previewImage);
    }
    previewImage.src = URL.createObjectURL(file);
    previewImage.hidden = false;
    preview.hidden = false;
  });

  const profilePhotoInput = document.querySelector('[data-profile-photo-input]');
  const profilePhotoField = document.querySelector('[data-profile-photo-field]');
  const profilePhotoLabel = document.querySelector('[data-profile-photo-label]');
  const profilePhotoStatus = document.querySelector('[data-profile-photo-status]');
  const profilePhotoForm = document.querySelector('[data-profile-photo-form]');
  const profilePhotoConfirmOverlay = document.querySelector('[data-profile-photo-confirm]');
  const profilePhotoConfirmPreview = document.querySelector('[data-profile-photo-confirm-preview]');
  const profilePhotoConfirmMessage = document.querySelector('[data-profile-photo-confirm-message]');
  const profilePhotoAccept = document.querySelector('[data-profile-photo-accept]');
  const profilePhotoCancel = document.querySelector('[data-profile-photo-cancel]');
  const initialProfilePhotoLabel = profilePhotoLabel?.textContent || 'Pilih foto baru';
  const initialProfilePhotoStatus = profilePhotoStatus?.textContent || 'JPG, PNG, atau WEBP · maksimal 2 MB';
  let profilePhotoConfirmed = false;
  let profilePhotoObjectUrl = null;
  let profilePhotoPreviouslyFocused = null;

  const formatFileSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };
  const resetProfilePhotoSelection = () => {
    profilePhotoConfirmed = false;
    if (profilePhotoInput) profilePhotoInput.value = '';
    profilePhotoField?.classList.remove('is-selected', 'is-pending');
    if (profilePhotoLabel) profilePhotoLabel.textContent = initialProfilePhotoLabel;
    if (profilePhotoStatus) profilePhotoStatus.textContent = initialProfilePhotoStatus;
  };
  const revokeProfilePhotoPreview = () => {
    if (!profilePhotoObjectUrl) return;
    URL.revokeObjectURL(profilePhotoObjectUrl);
    profilePhotoObjectUrl = null;
  };
  const closeProfilePhotoConfirm = (cancel = false) => {
    if (!profilePhotoConfirmOverlay) return;
    profilePhotoConfirmOverlay.hidden = true;
    profilePhotoConfirmOverlay.setAttribute('aria-hidden', 'true');
    if (cancel) resetProfilePhotoSelection();
    revokeProfilePhotoPreview();
    profilePhotoPreviouslyFocused?.focus?.();
    profilePhotoPreviouslyFocused = null;
  };
  const acceptProfilePhoto = () => {
    const file = profilePhotoInput?.files?.[0];
    if (!file) return;
    profilePhotoConfirmed = true;
    profilePhotoField?.classList.remove('is-pending');
    profilePhotoField?.classList.add('is-selected');
    if (profilePhotoLabel) profilePhotoLabel.textContent = 'Foto siap diunggah';
    if (profilePhotoStatus) profilePhotoStatus.textContent = `${file.name} · ${formatFileSize(file.size)}`;
    closeProfilePhotoConfirm();
  };
  const openProfilePhotoConfirm = (file) => {
    if (!file) return;
    profilePhotoConfirmed = false;
    if (!profilePhotoConfirmOverlay) {
      if (window.confirm('Gunakan foto profil baru ini? Foto akan disimpan saat Anda menekan “Simpan profil”.')) acceptProfilePhoto();
      else resetProfilePhotoSelection();
      return;
    }
    revokeProfilePhotoPreview();
    profilePhotoObjectUrl = URL.createObjectURL(file);
    if (profilePhotoConfirmPreview) profilePhotoConfirmPreview.src = profilePhotoObjectUrl;
    if (profilePhotoConfirmMessage) profilePhotoConfirmMessage.textContent = `${file.name} · ${formatFileSize(file.size)}. Foto akan disimpan saat Anda menekan “Simpan profil”.`;
    profilePhotoField?.classList.remove('is-selected');
    profilePhotoField?.classList.add('is-pending');
    profilePhotoConfirmOverlay.hidden = false;
    profilePhotoConfirmOverlay.setAttribute('aria-hidden', 'false');
    profilePhotoPreviouslyFocused = document.activeElement;
    profilePhotoAccept?.focus();
  };
  profilePhotoInput?.addEventListener('change', () => openProfilePhotoConfirm(profilePhotoInput.files?.[0]));
  profilePhotoAccept?.addEventListener('click', acceptProfilePhoto);
  profilePhotoCancel?.addEventListener('click', () => closeProfilePhotoConfirm(true));
  profilePhotoConfirmOverlay?.addEventListener('click', (event) => {
    if (event.target === profilePhotoConfirmOverlay) closeProfilePhotoConfirm(true);
  });
  profilePhotoForm?.addEventListener('submit', (event) => {
    const file = profilePhotoInput?.files?.[0];
    if (!file || profilePhotoConfirmed) return;
    if (!profilePhotoConfirmOverlay) {
      if (window.confirm('Gunakan foto profil baru ini? Foto akan disimpan saat Anda menekan “Simpan profil”.')) profilePhotoConfirmed = true;
      else { event.preventDefault(); resetProfilePhotoSelection(); }
      return;
    }
    event.preventDefault();
    openProfilePhotoConfirm(file);
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
      const cover = book.cover
        ? `<img src="${escapeHtml(book.cover)}" alt="" loading="lazy" decoding="async" onerror="this.hidden=true;var cover=this.closest('.book-cover');if(cover){cover.classList.add('book-cover--error');var fallback=cover.querySelector('.book-cover__fallback');if(fallback){fallback.hidden=false;}}">`
        : '<span class="book-cover__fallback" aria-hidden="true"><strong class="book-cover__fallback-mark">LS</strong><small>Cover belum tersedia</small></span>';
      const id = Number.parseInt(book.id, 10);
      const href = Number.isInteger(id) && id > 0 ? `/books/${id}` : '#';
      const stock = Number.isFinite(Number(book.stock)) ? Number(book.stock) : 0;
      return `<article class="catalog-card"><div class="catalog-card__cover book-cover book-cover--catalog" role="img" aria-label="Cover ${title}">${cover}</div><div><p>${category}</p><h2>${title}</h2><small>${author}</small><div><span class="badge ${stock > 0 ? 'badge--success' : 'badge--danger'}">${stock > 0 ? `${stock} tersedia` : 'Stok habis'}</span><a href="${href}" aria-label="Lihat ${title}">→</a></div></div></article>`;
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
