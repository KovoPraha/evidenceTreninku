(() => {
  'use strict';
  const status = document.getElementById('profile-access-status');
  const csrf = document.getElementById('profile-access-csrf');
  const fragment = new URLSearchParams(window.location.hash.replace(/^#/, ''));
  const token = fragment.get('token') || '';
  history.replaceState(null, '', window.location.pathname);
  if (!/^[a-f0-9]{64}$/.test(token) || !csrf) {
    if (status) status.textContent = 'Odkaz není platný. Požádejte klub o nový.';
    return;
  }
  const body = new FormData();
  body.set('csrf_token', csrf.value);
  body.set('token', token);
  fetch(window.location.pathname, {method: 'POST', body, credentials: 'same-origin'})
    .then(async response => {
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Odkaz nelze otevřít.');
      window.location.replace(data.redirect);
    })
    .catch(error => {
      if (status) status.textContent = error instanceof Error ? error.message : 'Odkaz nelze otevřít.';
    });
})();
