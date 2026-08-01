document.addEventListener('DOMContentLoaded', () => {
  const targets = document.querySelectorAll('.page .table-card,.page .form-card,.page .detail-card,.page .recent-panel,.page .insight-card,.page .student-hero,.page .hero-card');
  const show = (item) => item.classList.add('is-visible');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => { if (entry.isIntersecting) { show(entry.target); observer.unobserve(entry.target); } }), { threshold: .08 });
    targets.forEach((item, index) => { item.style.transitionDelay = String(Math.min(index * 45, 180)) + 'ms'; observer.observe(item); });
  } else targets.forEach(show);
  document.querySelectorAll('.hero-card,.student-hero').forEach((hero) => {
    if (hero.querySelector('.book-orbit')) return;
    const shelf = document.createElement('span'); shelf.className = 'book-orbit'; shelf.setAttribute('aria-hidden', 'true');
    shelf.innerHTML = '<i></i><i></i><i></i><i></i>'; hero.appendChild(shelf);
  });
});
