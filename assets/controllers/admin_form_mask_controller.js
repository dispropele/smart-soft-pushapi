import { Controller } from '@symfony/stimulus-bundle';

export default class extends Controller {
    connect() {
        // Initial pass for non-Turbo pages
        this.initMasks();

        // Re-run on every Turbo page load (EasyAdmin navigates via Turbo)
        this._onTurboLoad = () => this.initMasks();
        document.addEventListener('turbo:load', this._onTurboLoad);

        // Strip non-digits from phone fields before any form submits.
        // Registered on document so it stays active across Turbo navigations.
        // Guard flag prevents registering twice if controller re-connects.
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
        document.removeEventListener('turbo:load', this._onTurboLoad);
    }

    // -------------------------------------------------------
    // Find all un-initialised masked inputs in the current DOM
    // -------------------------------------------------------
    initMasks() {
        document.querySelectorAll('[data-admin-mask]').forEach(input => {
            // Skip if already set up to avoid double-binding
            if (input.dataset.maskReady) return;
            input.dataset.maskReady = '1';

            const maskType = input.dataset.adminMask;
            if (maskType === 'phone') {
                this.setupPhoneMask(input);
            }
        });
    }

    // -------------------------------------------------------
    // Phone mask: formats while typing, strips on submit
    // -------------------------------------------------------
    setupPhoneMask(input) {
        input.addEventListener('input', (e) => {
            const raw = e.target.value.replace(/\D/g, '');

            if (raw.length === 0) {
                e.target.value = '';
                return;
            }

            // Normalise to 11 digits starting with 7
            let digits = raw;
            if (digits[0] !== '7') {
                digits = '7' + digits;
            }
            digits = digits.slice(0, 11);

            // Build formatted string progressively so the caret feels natural
            let formatted = '+7';
            if (digits.length > 1)  formatted += ' (' + digits.slice(1, 4);
            if (digits.length > 4)  formatted += ') ' + digits.slice(4, 7);
            if (digits.length > 7)  formatted += '-' + digits.slice(7, 9);
            if (digits.length > 9)  formatted += '-' + digits.slice(9, 11);

            e.target.value = formatted;
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            // Put raw digits into the field and fire input event to reformat
            e.target.value = text.replace(/\D/g, '');
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // On blur, if the value is incomplete/invalid, clear it
        input.addEventListener('blur', (e) => {
            const digits = e.target.value.replace(/\D/g, '');
            if (digits.length > 0 && digits.length < 11) {
                e.target.setCustomValidity('Введите полный номер телефона');
                e.target.reportValidity();
            } else {
                e.target.setCustomValidity('');
            }
        });

        input.addEventListener('focus', (e) => {
            e.target.setCustomValidity('');
        });
    }
}