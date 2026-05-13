import { Controller } from '@symfony/stimulus-bundle';

export default class extends Controller {
    connect() {
        const inputs = document.querySelectorAll('[data-admin-mask]');
        inputs.forEach(input => {
            const maskType = input.dataset.adminMask;
            if (maskType === 'phone') {
                this.setupPhoneMask(input);
            }
        });

        // On form submit, strip non-digit characters from phone fields
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

    setupPhoneMask(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length === 0) {
                e.target.value = '';
                return;
            }

            // Ensure it starts with 7
            if (value[0] !== '7' && value.length > 0) {
                value = '7' + value;
            }

            // Format: +7 (999) 999-99-99
            let formatted = '+7';
            if (value.length > 1) {
                formatted += ' (' + value.substring(1, 4);
            }
            if (value.length > 4) {
                formatted += ') ' + value.substring(4, 7);
            }
            if (value.length > 7) {
                formatted += '-' + value.substring(7, 9);
            }
            if (value.length > 9) {
                formatted += '-' + value.substring(9, 11);
            }

            e.target.value = formatted;
        });

        // Handle paste
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            const cleaned = text.replace(/\D/g, '');
            e.target.value = '';
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
            
            // Simulate input for each digit
            cleaned.split('').forEach((digit, index) => {
                e.target.value += digit;
                e.target.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }
}
