// Live search-as-you-type suggestions for the deed registry search box.
// Debounced fetch to /deeds/suggest, rendered as a dropdown under the
// input; arrow keys move through results, Enter/click opens the deed.
(function () {
  var input = document.getElementById('deedSearchInput');
  var box = document.getElementById('deedSearchSuggest');
  if (!input || !box) return;

  var timer = null;
  var results = [];
  var activeIndex = -1;
  var lastQuery = '';

  function close() {
    box.hidden = true;
    box.innerHTML = '';
    activeIndex = -1;
  }

  function render() {
    if (!results.length) {
      box.innerHTML = '<div class="nd-suggest-empty">No matches.</div>';
      box.hidden = false;
      return;
    }
    box.innerHTML = results.map(function (r, i) {
      return '<div class="nd-suggest-item' + (i === activeIndex ? ' is-active' : '') + '" data-id="' + r.id + '" data-index="' + i + '">'
        + '<span class="nd-suggest-main">' + escapeHtml(r.deed_number) + '</span>'
        + '<span class="nd-suggest-sub">' + escapeHtml(r.buyer_name) + ' &rarr; ' + escapeHtml(r.seller_name) + '</span>'
        + '<span class="badge badge-status status-' + r.status + '">' + escapeHtml(r.status) + '</span>'
        + '</div>';
    }).join('');
    box.hidden = false;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fetchSuggestions(q) {
    fetch(input.form.action.replace(/\/deeds$/, '/deeds/suggest') + '?q=' + encodeURIComponent(q), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) { return res.ok ? res.json() : { results: [] }; })
      .then(function (data) {
        if (input.value.trim() !== q) return; // stale response, input moved on
        results = data.results || [];
        activeIndex = -1;
        render();
      })
      .catch(function () { /* silent — suggestions are a convenience, not critical */ });
  }

  input.addEventListener('input', function () {
    var q = input.value.trim();
    lastQuery = q;
    clearTimeout(timer);
    if (q.length < 1) { close(); return; }
    timer = setTimeout(function () { fetchSuggestions(q); }, 220);
  });

  input.addEventListener('keydown', function (e) {
    if (box.hidden || !results.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % results.length;
      render();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + results.length) % results.length;
      render();
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      window.location.href = input.dataset.baseUrl + '/' + results[activeIndex].id;
    } else if (e.key === 'Escape') {
      close();
    }
  });

  box.addEventListener('click', function (e) {
    var item = e.target.closest('.nd-suggest-item');
    if (!item) return;
    window.location.href = input.dataset.baseUrl + '/' + item.dataset.id;
  });

  document.addEventListener('click', function (e) {
    if (e.target !== input && !box.contains(e.target)) close();
  });

  input.addEventListener('focus', function () {
    if (input.value.trim().length >= 1 && results.length) render();
  });
})();
