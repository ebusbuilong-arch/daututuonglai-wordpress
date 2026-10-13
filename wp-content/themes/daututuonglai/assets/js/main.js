document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.menu-toggle');
  var nav = document.querySelector('.main-nav');
  if (!toggle || !nav) return;
  function closeMenu() { toggle.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); }
  toggle.addEventListener('click', function () {
    var open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('is-open', !open);
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && nav.classList.contains('is-open')) { closeMenu(); toggle.focus(); } });
  nav.addEventListener('click', function (event) { if (event.target.closest('a')) closeMenu(); });
});
