// Prevents accidental double-submission (easy to trigger on mobile with a
// double-tap) by disabling a form's submit button right after it's
// clicked. The browser still sends that click's request normally — this
// only blocks a second one from firing before the page navigates away.
(function () {
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    // A form's own onsubmit (e.g. the archive button's confirm() dialog)
    // runs before this delegated listener; if the user cancelled it,
    // e.defaultPrevented is already true and nothing is actually being
    // submitted — don't leave the button stuck disabled in that case.
    if (e.defaultPrevented) return;
    var submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn || submitBtn.disabled) return;
    // Let the click's value still get submitted before disabling.
    setTimeout(function () {
      submitBtn.disabled = true;
      submitBtn.classList.add('is-submitting');
    }, 0);
  });
})();
