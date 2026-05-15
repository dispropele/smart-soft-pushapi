document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.ea-new-LoanTicket, .ea-edit-LoanTicket');
    if (!form) return;

    const loanAmountInput = document.getElementById('LoanTicket_loanAmount');
    if (!loanAmountInput) return;

    // ========== 2) Button "Максимально" ==========
    function ensureMaxButton() {
        // Check if button already exists
        if (loanAmountInput.nextElementSibling?.classList.contains('btn-max-loan')) {
            return;
        }

        const parent = loanAmountInput.parentNode;
        const group = parent.classList.contains('input-group') ? parent : null;
        
        if (group) {
            const maxButton = document.createElement('button');
            maxButton.type = 'button';
            maxButton.innerText = 'Максимально';
            maxButton.className = 'btn btn-outline-secondary btn-max-loan';
            group.appendChild(maxButton);

            maxButton.addEventListener('click', function (e) {
                e.preventDefault();
                let totalValue = 0;
                document.querySelectorAll('.field-collection-item').forEach(item => {
                    const valueInput = item.querySelector('[id$="_estimatedValue"]');
                    if (valueInput) totalValue += parseFloat(valueInput.value) || 0;
                });
                loanAmountInput.value = totalValue.toFixed(2);
                loanAmountInput.dispatchEvent(new Event('change', { bubbles: true }));
                updateSummary();
            });
        }
    }

    // ========== 7) Summary Panel ==========
    let summaryPanel = document.querySelector('.loan-summary-panel');
    if (!summaryPanel) {
        summaryPanel = document.createElement('div');
        summaryPanel.className = 'loan-summary-panel alert alert-info mt-4 p-3';
        summaryPanel.setAttribute('role', 'region');
        summaryPanel.setAttribute('aria-live', 'polite');
        const formActions = document.querySelector('.ea-edit-form-actions');
        if (formActions) {
            formActions.parentNode.insertBefore(summaryPanel, formActions);
        } else {
            form.appendChild(summaryPanel);
        }
    }

    function updateSummary() {
        let totalItems = 0;
        let totalWeight = 0;
        let totalValue = 0;
        const loanAmount = parseFloat(loanAmountInput.value) || 0;
        const tariffField = document.getElementById('LoanTicket_tariff');
        const tariffText = tariffField && tariffField.selectedIndex >= 0 && tariffField.value 
            ? tariffField.options[tariffField.selectedIndex].text 
            : 'Не выбран';

        document.querySelectorAll('.field-collection-item').forEach(item => {
            if (item.style.display === 'none') return;
            
            totalItems++;
            const weightInput = item.querySelector('[id$="_itemWeight"]');
            const valueInput = item.querySelector('[id$="_estimatedValue"]');
            if (weightInput) totalWeight += parseFloat(weightInput.value) || 0;
            if (valueInput) totalValue += parseFloat(valueInput.value) || 0;
        });

        // Format currency
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('ru-RU', { 
                style: 'currency', 
                currency: 'RUB',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        };

        summaryPanel.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Всего предметов:</span>
                        <strong class="fs-6">${totalItems} шт.</strong>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Общий вес:</span>
                        <strong class="fs-6">${totalWeight.toFixed(2)} г</strong>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Общая оценка:</span>
                        <strong class="fs-6">${formatCurrency(totalValue)}</strong>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Сумма к выдаче:</span>
                        <strong class="fs-6 ${loanAmount > totalValue && totalValue > 0 ? 'text-danger' : ''}">${formatCurrency(loanAmount)}</strong>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Процентная ставка:</span>
                        <strong class="fs-6">${tariffText}</strong>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Кол-во дней:</span>
                        <strong class="fs-6">${document.getElementById('LoanTicket_graceDays')?.value || 30} дн.</strong>
                    </div>
                </div>
            </div>
        `;

        // 1) Подсветка красным, если сумма больше оценки
        if (loanAmount > totalValue && totalValue > 0) {
            loanAmountInput.classList.add('is-invalid');
            loanAmountInput.style.borderColor = '#dc3545';
            loanAmountInput.style.boxShadow = '0 0 0 0.25rem rgba(220,53,69,.25)';
        } else {
            loanAmountInput.classList.remove('is-invalid');
            loanAmountInput.style.borderColor = '';
            loanAmountInput.style.boxShadow = '';
        }
    }

    form.addEventListener('change', updateSummary);
    form.addEventListener('keyup', updateSummary);
    
    const collectionContainer = document.querySelector('.field-collection');
    if (collectionContainer) {
        const observer = new MutationObserver(() => {
            updateSummary();
            ensureMaxButton();
            initAllItems();
            addCalculateButtons();
        });
        observer.observe(collectionContainer, { childList: true, subtree: true });
    }
    
    updateSummary();
    ensureMaxButton();

    // ========== 9) Calculate scrap weight ==========
    function addCalculateButtons() {
        document.querySelectorAll('.field-collection-item').forEach(item => {
            if (item.dataset.scrappyButtonAdded === 'true') return;
            item.dataset.scrappyButtonAdded = 'true';

            const scrapWeightInput = item.querySelector('[id$="_scrapWeight"]');
            const itemWeightInput = item.querySelector('[id$="_itemWeight"]');
            const insertWeightInput = item.querySelector('[id$="_insertWeight"]');

            if (!scrapWeightInput || !itemWeightInput) return;

            // Find the wrapper of scrapWeight field
            const scrapField = scrapWeightInput.closest('.field-wrapper, .form-group, [class*="field"]');
            if (!scrapField) return;

            // Add button next to input if not exists
            if (!scrapField.querySelector('.btn-calculate-scrap')) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-info btn-calculate-scrap ms-2';
                btn.innerHTML = '<i class="fa fa-calculator"></i> Рассчитать';
                btn.title = 'Вес лома = Вес изделия - Вес вставок (в гр.)';
                
                const inputGroup = scrapWeightInput.parentNode;
                if (inputGroup && inputGroup.classList.contains('input-group')) {
                    inputGroup.appendChild(btn);
                } else {
                    scrapWeightInput.parentNode.appendChild(btn);
                }

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const itemWeight = parseFloat(itemWeightInput.value) || 0;
                    const insertWeight = parseFloat(insertWeightInput?.value || 0);
                    // Вес лома = Вес изделия - Вес вставок (в граммах)
                    let scrapWeight = itemWeight - insertWeight;
                    scrapWeight = Math.max(0, scrapWeight);
                    scrapWeightInput.value = scrapWeight.toFixed(2);
                    scrapWeightInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        });
    }

    // ========== 3) Dependent Metal and Standard ==========
    function updateMetalStandards(itemRow) {
        const metalSelect = itemRow.querySelector('.metal-select');
        const standardSelect = itemRow.querySelector('.metal-standard-select');
        if (!metalSelect || !standardSelect) return;

        const selectedMetalId = metalSelect.value;
        const options = standardSelect.querySelectorAll('option');
        
        let firstValid = null;
        let hasSelectedValid = false;

        options.forEach(opt => {
            if (!opt.value) return;
            const optMetalId = opt.getAttribute('data-metal-id');
            if (selectedMetalId === '' || optMetalId === selectedMetalId) {
                opt.style.display = '';
                opt.disabled = false;
                if (!firstValid) firstValid = opt.value;
                if (opt.selected) hasSelectedValid = true;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
                if (opt.selected) opt.selected = false;
            }
        });

        if (!hasSelectedValid && selectedMetalId !== '') {
            standardSelect.value = firstValid || '';
        }
    }

    function initAllItems() {
        document.querySelectorAll('.field-collection-item').forEach(item => {
            const metalSelect = item.querySelector('.metal-select');
            const standardSelect = item.querySelector('.metal-standard-select');
            
            if (metalSelect && standardSelect) {
                // Check if already initialized
                if (item.dataset.metalInitialized === 'true') return;
                item.dataset.metalInitialized = 'true';

                // Sync metal from standard if needed
                if (!metalSelect.value && standardSelect.value) {
                    const selectedOpt = standardSelect.querySelector(`option[value="${standardSelect.value}"]`);
                    if (selectedOpt) {
                        const metalId = selectedOpt.getAttribute('data-metal-id');
                        if (metalId) metalSelect.value = metalId;
                    }
                }

                // Initial filter
                updateMetalStandards(item);

                // Add change listener
                metalSelect.addEventListener('change', () => {
                    updateMetalStandards(item);
                });
            }
        });
    }

    initAllItems();
    addCalculateButtons();
});

