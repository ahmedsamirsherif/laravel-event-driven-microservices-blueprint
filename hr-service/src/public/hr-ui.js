(function () {
    const configNode = document.getElementById('page-config');
    const pageConfig = configNode ? JSON.parse(configNode.textContent || '{}') : {};
    const page = document.body.dataset.page;
    const flashKey = 'hr-ui-flash';

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        if (response.status === 204) {
            return null;
        }

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(payload.error?.message || payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            error.details = payload.error?.details || payload.errors || null;
            throw error;
        }

        return payload;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatCurrency = (value) => {
        const amount = Number(value || 0);
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const setFlash = (message) => {
        sessionStorage.setItem(flashKey, message);
    };

    const consumeFlash = (element) => {
        const message = sessionStorage.getItem(flashKey);
        if (!message || !element) {
            return;
        }

        element.textContent = message;
        element.classList.remove('hidden');
        sessionStorage.removeItem(flashKey);
    };

    if (page === 'employees-index') {
        initIndex();
    }

    if (page === 'employee-form') {
        initForm();
    }

    // ─── Index page ──────────────────────────────────────────────────────────

    function initIndex() {
        const rows = document.getElementById('employee-rows');
        const summary = document.getElementById('employee-summary');
        const serverError = document.getElementById('employee-server-error');
        const flash = document.getElementById('employee-flash');
        const paginationCopy = document.getElementById('employee-pagination-copy');
        const pageLabel = document.getElementById('employees-page-label');
        const prevButton = document.getElementById('employees-prev');
        const nextButton = document.getElementById('employees-next');
        const toggleContainer = document.getElementById('country-toggle-container');
        const deleteModal = document.getElementById('delete-modal');
        const deleteName = document.getElementById('delete-employee-name');
        const deleteCancel = document.getElementById('delete-cancel');
        const deleteConfirm = document.getElementById('delete-confirm');

        const state = {
            country: localStorage.getItem('hr_selected_country') || 'USA',
            page: 1,
            lastPage: 1,
            perPage: pageConfig.perPage || 15,
            pendingEmployee: null,
        };

        consumeFlash(flash);

        deleteCancel.addEventListener('click', closeDeleteModal);
        deleteModal.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });

        deleteConfirm.addEventListener('click', async () => {
            if (!state.pendingEmployee) {
                return;
            }

            deleteConfirm.disabled = true;

            try {
                await request(`/api/v1/employees/${state.pendingEmployee.id}`, { method: 'DELETE' });
                setFlash(`Employee ${state.pendingEmployee.name} ${state.pendingEmployee.last_name} deleted.`);
                closeDeleteModal();
                consumeFlash(flash);
                await loadEmployees();
            } catch (error) {
                serverError.textContent = error.message;
                serverError.classList.remove('hidden');
            } finally {
                deleteConfirm.disabled = false;
            }
        });

        // Load countries from /api/v1/countries, render toggle buttons, then load employees
        request('/api/v1/countries')
            .then((response) => renderCountryToggles(response.data || []))
            .catch(() => renderCountryToggles([{ code: 'USA', label: 'USA' }, { code: 'DEU', label: 'Germany' }]))
            .finally(() => loadEmployees());

        function renderCountryToggles(countries) {
            toggleContainer.innerHTML = countries.map((c) =>
                `<button type="button" class="toggle-button" data-country="${escapeHtml(c.code)}" aria-pressed="${c.code === state.country ? 'true' : 'false'}">${escapeHtml(c.label)}</button>`
            ).join('');

            Array.from(toggleContainer.querySelectorAll('[data-country]')).forEach((button) => {
                button.addEventListener('click', () => {
                    state.country = button.dataset.country || state.country;
                    state.page = 1;
                    localStorage.setItem('hr_selected_country', state.country);
                    syncCountryButtons();
                    loadEmployees();
                });
            });
        }

        function syncCountryButtons() {
            Array.from(toggleContainer.querySelectorAll('[data-country]')).forEach((button) => {
                button.setAttribute('aria-pressed', button.dataset.country === state.country ? 'true' : 'false');
            });
        }

        function getCountryLabel(code) {
            const btn = toggleContainer.querySelector(`[data-country="${code}"]`);
            return btn ? btn.textContent.trim() : code;
        }

        function openDeleteModal(employee) {
            state.pendingEmployee = employee;
            deleteName.textContent = `${employee.name} ${employee.last_name}`;
            deleteModal.classList.remove('hidden');
            deleteModal.setAttribute('aria-hidden', 'false');
        }

        function closeDeleteModal() {
            state.pendingEmployee = null;
            deleteModal.classList.add('hidden');
            deleteModal.setAttribute('aria-hidden', 'true');
        }

        async function loadEmployees() {
            rows.innerHTML = '<tr><td colspan="6" class="inline-message">Loading employees...</td></tr>';
            summary.textContent = 'Loading employees...';
            serverError.classList.add('hidden');

            try {
                const response = await request(`/api/v1/employees?country=${state.country}&page=${state.page}&per_page=${state.perPage}`);
                const employees = response.data || [];
                const meta = response.meta || {};

                state.lastPage = Math.max(meta.last_page || 1, 1);
                renderRows(employees);

                summary.textContent = `${meta.total || employees.length} employees in ${getCountryLabel(state.country)}.`;
                paginationCopy.textContent = `Page ${meta.current_page || 1} of ${meta.last_page || 1}`;
                pageLabel.textContent = `${meta.current_page || 1} / ${meta.last_page || 1}`;
                prevButton.disabled = (meta.current_page || 1) <= 1;
                nextButton.disabled = (meta.current_page || 1) >= (meta.last_page || 1);
            } catch (error) {
                rows.innerHTML = `<tr><td colspan="6" class="inline-message">${escapeHtml(error.message)}</td></tr>`;
                summary.textContent = 'Unable to load employees.';
                paginationCopy.textContent = 'Page 1 of 1';
                pageLabel.textContent = '1 / 1';
                prevButton.disabled = true;
                nextButton.disabled = true;
                serverError.textContent = error.message;
                serverError.classList.remove('hidden');
            }
        }

        function renderRows(employees) {
            if (!employees.length) {
                rows.innerHTML = '<tr><td colspan="6" class="empty-state">No employees found.</td></tr>';
                return;
            }

            rows.innerHTML = employees.map((employee) => {
                const detailParts = employee.country === 'DEU'
                    ? [employee.tax_id || 'Pending tax ID', employee.goal || 'Pending goal']
                    : [employee.ssn || 'Pending SSN', employee.address || 'Pending address'];

                return `
                    <tr>
                        <td>${employee.id}</td>
                        <td>
                            <div class="name-stack">
                                <strong>${escapeHtml(employee.name)} ${escapeHtml(employee.last_name)}</strong>
                                <span>${escapeHtml(detailParts.filter(Boolean).join(' · '))}</span>
                            </div>
                        </td>
                        <td>${formatCurrency(employee.salary)}</td>
                        <td><span class="country-chip">${escapeHtml(getCountryLabel(employee.country))}</span></td>
                        <td class="muted">${escapeHtml(detailParts.filter(Boolean).join(' · '))}</td>
                        <td>
                            <div class="toolbar">
                                <a href="/employees/${employee.id}/edit" class="button button-secondary">Edit</a>
                                <button type="button" class="button button-danger" data-delete-id="${employee.id}">Delete</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            Array.from(rows.querySelectorAll('[data-delete-id]')).forEach((button) => {
                button.addEventListener('click', () => {
                    const employeeId = Number(button.dataset.deleteId);
                    const employee = employees.find((item) => item.id === employeeId);
                    if (employee) {
                        openDeleteModal(employee);
                    }
                });
            });
        }
    }

    // ─── Form page ───────────────────────────────────────────────────────────

    function initForm() {
        const form = document.getElementById('employee-form');
        const submitButton = document.getElementById('submit-button');
        const loadError = document.getElementById('load-error');
        const serverError = document.getElementById('form-server-error');
        const countrySelect = document.getElementById('country');
        const countryHidden = document.getElementById('country_hidden');
        const countryReadonly = document.getElementById('country-readonly-value');
        const fieldsContainer = document.getElementById('country-fields-container');
        const mode = pageConfig.mode || 'create';
        const employeeId = pageConfig.employeeId;

        let lockedCountry = 'USA';
        let maskedSsn = '';
        let currentSteps = null;

        if (countrySelect) {
            countrySelect.addEventListener('change', () => {
                lockedCountry = countrySelect.value;
                loadSteps(lockedCountry);
            });
        }

        form.addEventListener('submit', onSubmit);

        // Bootstrap: load countries for select, then either fetch employee (edit) or load steps (create)
        loadCountriesForSelect()
            .then(() => {
                if (mode === 'edit' && employeeId) {
                    return fetchEmployee();
                }
                return loadSteps(lockedCountry);
            })
            .catch((error) => {
                loadError.textContent = error.message;
                loadError.classList.remove('hidden');
                submitButton.disabled = false;
            });

        // Populate the country <select> from /api/v1/countries
        async function loadCountriesForSelect() {
            if (!countrySelect) {
                return;
            }

            const response = await request('/api/v1/countries');
            const countries = response.data || [];

            countrySelect.innerHTML = countries.map((c) =>
                `<option value="${escapeHtml(c.code)}">${escapeHtml(c.label)}</option>`
            ).join('');

            if (countries.length > 0) {
                lockedCountry = countries[0].code;
                countrySelect.value = lockedCountry;
            }
        }

        // Fetch country-specific form steps from GET /api/v1/steps/{country}
        async function loadSteps(country, values) {
            const response = await request(`/api/v1/steps/${country}`);
            currentSteps = response.data || {};
            renderCountryFields(currentSteps, values);
        }

        function renderCountryFields(stepData, values) {
            const fields = stepData.fields || [];
            const title = stepData.section_title || 'Country-Specific Fields';

            fieldsContainer.innerHTML = `
                <section class="form-section">
                    <h2>${escapeHtml(title)}</h2>
                    <div class="grid grid-two" style="margin-top: 18px;">
                        ${fields.map((field) => renderFieldHtml(field, values)).join('')}
                    </div>
                </section>
            `;
        }

        function renderFieldHtml(field, values) {
            const value = values && values[field.key] != null ? escapeHtml(String(values[field.key])) : '';
            const attrs = [
                `id="${escapeHtml(field.key)}"`,
                `name="${escapeHtml(field.key)}"`,
                `type="${escapeHtml(field.type || 'text')}"`,
                field.placeholder ? `placeholder="${escapeHtml(field.placeholder)}"` : '',
                field.required ? 'required' : '',
                field.input_mode ? `inputmode="${escapeHtml(field.input_mode)}"` : '',
                field.autocomplete ? `autocomplete="${escapeHtml(field.autocomplete)}"` : '',
                value ? `value="${value}"` : '',
            ].filter(Boolean).join(' ');

            return `
                <div>
                    <label for="${escapeHtml(field.key)}">${escapeHtml(field.label)}</label>
                    <input ${attrs}>
                    <div class="field-error" data-field-error="${escapeHtml(field.key)}"></div>
                </div>
            `;
        }

        async function fetchEmployee() {
            submitButton.disabled = true;
            loadError.classList.add('hidden');

            try {
                const response = await request(`/api/v1/employees/${employeeId}`);
                const employee = response.data || {};

                document.getElementById('name').value = employee.name || '';
                document.getElementById('last_name').value = employee.last_name || '';
                document.getElementById('salary').value = employee.salary ?? '';

                lockedCountry = employee.country || 'USA';
                maskedSsn = employee.ssn || '';

                if (countryHidden) {
                    countryHidden.value = lockedCountry;
                }

                if (countryReadonly) {
                    countryReadonly.textContent = lockedCountry;
                }

                // Load steps for this employee's country and pre-fill values
                await loadSteps(lockedCountry, employee);
            } catch (error) {
                loadError.textContent = error.message;
                loadError.classList.remove('hidden');
            } finally {
                submitButton.disabled = false;
            }
        }

        async function onSubmit(event) {
            event.preventDefault();
            clearErrors();
            loadError.classList.add('hidden');
            serverError.classList.add('hidden');
            submitButton.disabled = true;

            const activeCountry = countrySelect ? countrySelect.value : lockedCountry;
            const payload = {
                name: document.getElementById('name').value.trim(),
                last_name: document.getElementById('last_name').value.trim(),
                salary: document.getElementById('salary').value,
            };

            if (mode === 'create') {
                payload.country = activeCountry;
            }

            // Collect country-specific field values from the rendered steps
            const stepFields = currentSteps?.fields || [];
            stepFields.forEach((field) => {
                const input = document.getElementById(field.key);
                if (!input) return;
                const value = input.value.trim();

                if (field.key === 'ssn') {
                    // Only send SSN if it differs from the masked server placeholder
                    if (mode === 'create') {
                        if (value !== '') payload.ssn = value;
                    } else if (value !== '' && value !== maskedSsn) {
                        payload.ssn = value;
                    }
                } else if (value !== '') {
                    payload[field.key] = value;
                } else if (field.required) {
                    // Include empty required fields so the server returns proper validation errors
                    payload[field.key] = value;
                }
            });

            try {
                await request(mode === 'edit' ? `/api/v1/employees/${employeeId}` : '/api/v1/employees', {
                    method: mode === 'edit' ? 'PUT' : 'POST',
                    body: JSON.stringify(payload),
                });

                setFlash(mode === 'edit' ? 'Employee updated successfully.' : 'Employee created successfully.');
                window.location.href = '/employees';
            } catch (error) {
                if (error.status === 422 && error.details) {
                    renderErrors(error.details);
                } else {
                    serverError.textContent = error.message;
                    serverError.classList.remove('hidden');
                }
            } finally {
                submitButton.disabled = false;
            }
        }

        function clearErrors() {
            Array.from(document.querySelectorAll('[data-field-error]')).forEach((container) => {
                container.innerHTML = '';
                container.removeAttribute('data-has-error');
            });
        }

        function renderErrors(details) {
            Object.entries(details).forEach(([field, messages]) => {
                const container = document.querySelector(`[data-field-error="${field}"]`);
                if (!container) {
                    return;
                }

                const firstMessage = Array.isArray(messages) ? messages[0] : messages;
                container.setAttribute('data-has-error', 'true');
                container.innerHTML = `<p class="text-red-500">${escapeHtml(firstMessage)}</p>`;
            });
        }
    }
}());
