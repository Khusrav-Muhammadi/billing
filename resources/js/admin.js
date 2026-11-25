import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Копирование: <button data-copy="текст">
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const text = button.getAttribute('data-copy') || '';
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        const area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    const label = button.querySelector('[data-copy-label]');
    if (label) {
        const original = label.textContent;
        label.textContent = button.getAttribute('data-copied') || 'OK';
        setTimeout(() => { label.textContent = original; }, 1600);
    }
});
