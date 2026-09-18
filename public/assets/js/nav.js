// Mobile hamburger nav toggle — collapsed by default under 900px (see
// app.css), expanded via the .is-open class. No-op above that width since
// the toggle button itself is hidden there.
(function () {
  var toggle = document.getElementById('ndNavToggle');
  var sidebar = document.getElementById('ndSidebar');
  if (!toggle || !sidebar) return;

  toggle.addEventListener('click', function () {
    var isOpen = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  // Close the menu after choosing a nav link, so navigating away doesn't
  // leave it awkwardly open on the next page.
  sidebar.querySelectorAll('.nd-nav .nav-link').forEach(function (link) {
    link.addEventListener('click', function () {
      sidebar.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
})();
