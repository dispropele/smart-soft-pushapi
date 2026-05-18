// Phone mask handler for forms
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('[data-phone-mask="true"]');
    
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // If empty, just clear it
            if (value.length === 0) {
                e.target.value = '';
                return;
            }
            
            // Handle leading 8 or 7 (only for Russian numbers)
            if (value.startsWith('8')) {
                value = '7' + value.slice(1);
            } else if (!value.startsWith('7') && value.length > 0) {
                value = '7' + value;
            }
            
            // Limit to 11 digits (7 + 10)
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            // Format with spaces and parentheses
            if (value.length >= 1) {
                if (value.length <= 1) {
                    e.target.value = '+' + value;
                } else if (value.length <= 4) {
                    e.target.value = '+' + value.slice(0, 1) + ' (' + value.slice(1);
                } else if (value.length <= 7) {
                    e.target.value = '+' + value.slice(0, 1) + ' (' + value.slice(1, 4) + ') ' + value.slice(4);
                } else if (value.length <= 9) {
                    e.target.value = '+' + value.slice(0, 1) + ' (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7);
                } else {
                    e.target.value = '+' + value.slice(0, 1) + ' (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
                }
            }
        });
        
        input.addEventListener('keydown', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Block non-digit characters
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
        
        // Handle paste events
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pastedText.replace(/\D/g, '');
            e.target.value = digits;
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
});

