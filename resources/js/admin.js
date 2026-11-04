const app = document.getElementById('app');
const toggle = document.getElementById('menu-toggle');
const sidebar = document.getElementById('sidebar');
const mobile = window.matchMedia('(max-width: 1023px)');

function setMenu(open) {
    app.classList.toggle('is-menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    sidebar.inert = mobile.matches && !open;
}

toggle.addEventListener('click', () => setMenu(!app.classList.contains('is-menu-open')));
document.getElementById('overlay').addEventListener('click', () => setMenu(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && app.classList.contains('is-menu-open')) {
        setMenu(false);
        toggle.focus();
    }
});
mobile.addEventListener('change', () => setMenu(false));
setMenu(false);

document.querySelector('[data-impor]')?.addEventListener('submit', (event) => {
    event.currentTarget.querySelector('button[type="submit"]').disabled = true;
    event.currentTarget.querySelector('[data-proses]').hidden = false;
});
