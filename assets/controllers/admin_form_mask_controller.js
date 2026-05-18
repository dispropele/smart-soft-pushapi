import { Controller } from '@symfony/stimulus-bundle';

export default class extends Controller {
    connect() {
        this.initMasks();

        // Переинициализация при любой навигации через Turbo
        this._onTurboLoad   = () => this.initMasks();
        this._onTurboRender = () => this.initMasks();
        document.addEventListener('turbo:load',        this._onTurboLoad);
        document.addEventListener('turbo:render',      this._onTurboRender);
        document.addEventListener('turbo:frame-load',  this._onTurboLoad);
        document.addEventListener('turbo:frame-render',this._onTurboRender);

        // Следим за динамически добавленными полями (коллекции EasyAdmin)
        this._mutationObserver = new MutationObserver(() => this.initMasks());
        this._mutationObserver.observe(document.body, { childList: true, subtree: true });

        // Перед отправкой формы — очищаем маску, оставляем только цифры
        if (!window._adminMaskSubmitBound) {
            window._adminMaskSubmitBound = true;
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                form.querySelectorAll('[data-admin-mask="phone"]').forEach(input => {
                    if (input && input.value) {
                        input.value = input.value.replace(/\D/g, '');
                    }
                });
            }, true);
        }
    }

    disconnect() {
        document.removeEventListener('turbo:load',        this._onTurboLoad);
        document.removeEventListener('turbo:render',      this._onTurboRender);
        document.removeEventListener('turbo:frame-load',  this._onTurboLoad);
        document.removeEventListener('turbo:frame-render',this._onTurboRender);
        if (this._mutationObserver) {
            this._mutationObserver.disconnect();
        }
    }

    // ── Найти все ещё не инициализированные поля в DOM ──────────────────────
    initMasks() {
        document.querySelectorAll('[data-admin-mask]').forEach(input => {
            if (input.dataset.maskReady) return;
            input.dataset.maskReady = '1';

            if (input.dataset.adminMask === 'phone') {
                this.setupPhoneMask(input);
            }
        });
    }

    // ── Телефонная маска ─────────────────────────────────────────────────────
    setupPhoneMask(input) {
        // Форматирование при вводе
        input.addEventListener('input', (e) => {
            const raw = e.target.value.replace(/\D/g, '');
            e.target.value = this._formatPhone(raw);
        });

        // Вставка из буфера
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            const raw  = text.replace(/\D/g, '');
            e.target.value = this._formatPhone(raw);
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Автозаполнение браузера / autofill
        input.addEventListener('change', (e) => {
            const raw = e.target.value.replace(/\D/g, '');
            const formatted = this._formatPhone(raw);
            if (e.target.value !== formatted && raw.length > 0) {
                e.target.value = formatted;
            }
        });

        // Проверка при потере фокуса
        input.addEventListener('blur', (e) => {
            const digits = e.target.value.replace(/\D/g, '');
            if (digits.length > 0 && digits.length < 11) {
                e.target.setCustomValidity('Введите полный номер телефона (11 цифр)');
                e.target.reportValidity();
            } else {
                e.target.setCustomValidity('');
            }
        });

        input.addEventListener('focus', (e) => {
            e.target.setCustomValidity('');
        });

        // Если поле уже содержит только цифры (из БД) — отформатировать сразу
        const existingRaw = input.value.replace(/\D/g, '');
        if (existingRaw.length >= 10) {
            input.value = this._formatPhone(existingRaw);
        }
    }

    /**
     * Преобразует строку цифр в "+7 (XXX) XXX-XX-XX".
     * @param {string} raw  — только цифры
     * @returns {string}
     */
    _formatPhone(raw) {
        if (!raw) return '';

        // Нормализуем: убираем ведущую 7 или 8, оставляем 10 цифр без кода страны
        let digits = raw;

        // Если начинается с 8 или 7 — убираем первую цифру для нормализации
        if (digits[0] === '8' || digits[0] === '7') {
            digits = digits.slice(1);
        }

        // Обрезаем до 10 цифр (без кода страны)
        digits = digits.slice(0, 10);

        // Собираем отображаемую строку
        let out = '+7';
        if (digits.length > 0) out += ' (' + digits.slice(0, 3);
        if (digits.length >= 3) out += ')';
        if (digits.length > 3)  out += ' ' + digits.slice(3, 6);
        if (digits.length > 6)  out += '-' + digits.slice(6, 8);
        if (digits.length > 8)  out += '-' + digits.slice(8, 10);

        return out;
    }
}
