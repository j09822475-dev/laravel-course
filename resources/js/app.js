/**
 * Таймер ответа на вопрос.
 *
 * Считает время с момента открытия вопроса, показывает ориентир по времени
 * и кладёт фактические секунды в скрытое поле формы — из них собирается отчёт.
 */
function startAnswerTimer() {
    const timer = document.querySelector('[data-timer]');

    if (!timer) {
        return;
    }

    const budget = Number(timer.dataset.budget || 0);
    const label = timer.querySelector('[data-timer-value]');
    const bar = timer.querySelector('[data-timer-bar]');
    const fields = document.querySelectorAll('[data-seconds-field]');
    const startedAt = Date.now();

    const format = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;

        return `${m}:${String(s).padStart(2, '0')}`;
    };

    const tick = () => {
        const elapsed = Math.floor((Date.now() - startedAt) / 1000);

        if (label) {
            label.textContent = format(elapsed);
        }

        fields.forEach((field) => {
            field.value = elapsed;
        });

        if (bar && budget > 0) {
            const ratio = Math.min(elapsed / budget, 1);
            bar.style.width = `${ratio * 100}%`;
            bar.classList.toggle('bg-rose-500', ratio >= 1);
            bar.classList.toggle('bg-amber-500', ratio >= 0.75 && ratio < 1);
        }

        if (label && budget > 0 && elapsed > budget) {
            label.classList.add('text-rose-600', 'dark:text-rose-400');
        }
    };

    tick();
    setInterval(tick, 1000);
}

document.addEventListener('DOMContentLoaded', () => {
    startAnswerTimer();
});

/**
 * Регистрация service worker: без него Android не предлагает установку,
 * а при обрыве связи пользователь видит ошибку браузера вместо подсказки.
 */
function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Регистрация падает на http и в приватном режиме — приложение
            // продолжает работать, просто без офлайн-заглушки и установки.
        });
    });
}

/** Кнопка установки появляется только когда Android действительно готов установить приложение. */
function bindInstallPrompt() {
    const button = document.querySelector('[data-install]');

    if (!button) {
        return;
    }

    let deferred = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event;
        button.hidden = false;
    });

    button.addEventListener('click', async () => {
        if (!deferred) {
            return;
        }

        button.hidden = true;
        deferred.prompt();
        await deferred.userChoice;
        deferred = null;
    });

    window.addEventListener('appinstalled', () => {
        button.hidden = true;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    registerServiceWorker();
    bindInstallPrompt();
});
