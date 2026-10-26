import Lang from './language.js';
import Dashboard from './dashboard.js';
import Login from './login.js';

let loginPage = document.querySelector('#login-page');
let darkEnable = localStorage.getItem('darkEnable') || 0;

Lang.lang = localStorage.getItem('lang') || 'en';

if (darkEnable == 1)
    document.body.classList.add('night');
else
    document.body.classList.remove('night');

if (loginPage) {
    new Login(document);
} else if (!document.getElementById('admin-page')) {
    new Dashboard(document);
}
for (const node of document.querySelectorAll('[data-admin-label]')) node.textContent = Lang.val[node.dataset.adminLabel] || node.textContent;
