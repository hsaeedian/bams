import Lang from './language.js';
Lang.lang = localStorage.getItem('lang') || 'en';
for (const node of document.querySelectorAll('[data-setup]')) node.textContent = Lang.val[node.dataset.setup] || node.textContent;
const language = document.getElementById('setup-language');
language.value = Lang.lang;
language.addEventListener('change', () => { Lang.lang = language.value; });
const applicationUrl = document.getElementById('app_url');
if (applicationUrl) applicationUrl.value = new URL('./', location.href).href;
document.getElementById('download-setup-key')?.addEventListener('click', () => {
    const bytes = crypto.getRandomValues(new Uint8Array(32));
    const key = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
    document.getElementById('setup_key').value = key;
    const url = URL.createObjectURL(new Blob([key + '\n'], {type:'text/plain'}));
    const link = document.createElement('a'); link.href = url; link.download = 'setup.key'; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
