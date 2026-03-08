(function () {
    // Parse country and step from the URL path: /{country}/{step}
    const pathSegments = window.location.pathname.replace(/^\/+|\/+$/g, '').split('/');
    const urlCountry = (pathSegments[0] || '').toUpperCase();
    const urlStep = pathSegments[1] || 'dashboard';

    const state = {
        country: urlCountry || 'USA',
        step: urlStep,
        steps: [],
        countries: [],
        events: [],
        showEventsPanel: false,
        employeesPage: 1,
        checklistPage: 1,
        checklistExpanded: new Set(),
        connected: false,
        ws: null,
        pingTimer: null,
        reconnectTimer: null,
        refreshTimer: null,
        seenEventIds: new Set(),
        modalEmployee: null,
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        const payload = response.status === 204 ? null : await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(payload?.error?.message || payload?.message || `Request failed (${response.status})`);
            error.status = response.status;
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

    const formatCurrency = (value) => new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

    /* ── Inject modal + seed button HTML ── */
    (function injectGlobalUI() {
        // Modal overlay
        const modalEl = document.createElement('div');
        modalEl.id = 'checklist-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'modal-title');
        modalEl.style.cssText = [
            'display:none;position:fixed;inset:0;z-index:1000',
            'background:rgba(0,0,0,.45);overflow-y:auto',
            'padding:40px 16px;'
        ].join(';');
        modalEl.innerHTML = `
            <div id="modal-box" style="
                max-width:540px;margin:0 auto;border-radius:10px;
                background:var(--surface);border:1px solid var(--border);
                box-shadow:0 20px 60px rgba(0,0,0,.25);
            ">
                <div id="modal-header" style="
                    display:flex;justify-content:space-between;align-items:flex-start;
                    padding:18px 20px 14px;border-bottom:1px solid var(--border);
                ">
                    <div>
                        <h2 id="modal-title" style="margin:0;font-size:1rem;font-weight:700;"></h2>
                        <p id="modal-sub" style="margin:4px 0 0;font-size:.8125rem;color:var(--muted);"></p>
                    </div>
                    <button id="modal-close" type="button" aria-label="Close" style="
                        border:none;background:transparent;
                        cursor:pointer;padding:4px;color:var(--muted);
                        font-size:1.25rem;line-height:1;
                    ">&times;</button>
                </div>
                <div id="modal-progress-bar" style="height:4px;background:var(--border-light)">
                    <div id="modal-progress-fill" style="height:100%;background:var(--accent);transition:width .3s;"></div>
                </div>
                <div id="modal-body" style="padding:16px 20px 20px;"></div>
            </div>
        `;
        document.body.appendChild(modalEl);

        modalEl.addEventListener('click', (e) => {
            if (e.target === modalEl) closeModal();
        });
        document.getElementById('modal-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    }());

    const dom = {
        nav: document.getElementById('step-nav'),
        pageTitle: document.getElementById('page-title'),
        pageCopy: document.getElementById('page-copy'),
        countrySwitcher: document.getElementById('country-switcher'),
        countryButtons: [],
        view: document.getElementById('view-container'),
        contentShell: document.getElementById('content-shell'),
        eventsToggle: document.getElementById('events-toggle'),
        eventsCount: document.getElementById('events-count'),
        eventsPanel: document.getElementById('events-panel'),
        eventsList: document.getElementById('events-list'),
        eventsClear: document.getElementById('events-clear'),
        statusDot: document.getElementById('status-dot'),
        statusLabel: document.getElementById('status-label'),
    };

    const pageDescriptions = {
        dashboard: 'Overview of employees and onboarding status.',
        employees: 'Employee records projected from the HR service.',
        checklist: 'Per-employee onboarding completion status.',
        documentation: 'Document tracking for this country.',
    };

    init();

    async function init() {
        bindEventsPanel();
        bindSeedEventButton();
        updateConnectionState(false);
        renderNavigation();
        updatePageCopy();
        renderEventsPanel();
        initWebSocket();
        await hydrateCountries();

        // If no country in URL, redirect to the first supported country
        if (!state.country || !state.countries.some((c) => c.code === state.country)) {
            const defaultCountry = state.countries[0]?.code || 'USA';
            window.location.href = `/${defaultCountry}/dashboard`;
            return;
        }

        document.title = `${state.country} · ${state.step.charAt(0).toUpperCase() + state.step.slice(1)} · Hub Service`;

        await hydrateSteps();

        try {
            await loadActiveView();
        } catch (error) {
            renderError(error.message);
        }
    }

    async function hydrateSteps() {
        try {
            const response = await request(`/api/v1/steps/${state.country}`);
            if (Array.isArray(response.data) && response.data.length > 0) {
                state.steps = response.data;
            }
        } catch (error) {
            // Steps API failed — leave steps empty so navigation shows the error state
        }

        renderNavigation();
    }

    async function hydrateCountries() {
        try {
            const response = await request('/api/v1/countries');
            if (Array.isArray(response.data) && response.data.length > 0) {
                state.countries = response.data;
            }
        } catch (error) {
            // Countries API failed — fall back to current country only
            state.countries = [{ code: state.country, label: state.country }];
        }

        renderCountryButtons();
    }

    function renderCountryButtons() {
        if (!dom.countrySwitcher) return;

        dom.countrySwitcher.innerHTML = state.countries.map((c) => {
            const active = c.code === state.country;
            return `<button type="button" class="toggle-button" data-country-toggle="${escapeHtml(c.code)}" aria-pressed="${active ? 'true' : 'false'}">${escapeHtml(c.label)}</button>`;
        }).join('');

        dom.countryButtons = Array.from(dom.countrySwitcher.querySelectorAll('[data-country-toggle]'));
        bindCountryButtons();
    }

    function bindCountryButtons() {
        dom.countryButtons.forEach((button) => {
            button.addEventListener('click', () => switchCountry(button.dataset.countryToggle || 'USA'));
        });
    }

    function bindSeedEventButton() {
        const btn = document.getElementById('seed-event-btn');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.textContent = 'Seeding...';
            try {
                const names = [
                    { name: 'Alice', last_name: 'Johnson' },
                    { name: 'Bob', last_name: 'Smith' },
                    { name: 'Carlos', last_name: 'Mendez' },
                    { name: 'Diana', last_name: 'Müller' },
                ];
                const pick = names[Math.floor(Math.random() * names.length)];
                const isUSA = state.country === 'USA';
                const body = isUSA
                    ? { ...pick, salary: Math.floor(60000 + Math.random() * 80000), country: 'USA', ssn: `${rnd(100,999)}-${rnd(10,99)}-${rnd(1000,9999)}`, address: `${rnd(1,999)} Main St` }
                    : { ...pick, salary: Math.floor(50000 + Math.random() * 60000), country: 'DEU', tax_id: `DE${rnd(100000000,999999999)}`, goal: 'Complete onboarding process' };

                await request('http://localhost:8001/api/v1/employees', {
                    method: 'POST',
                    body: JSON.stringify(body),
                });

                btn.textContent = '\u2713 Event Sent!';
                // Auto-open events panel
                if (!state.showEventsPanel) {
                    state.showEventsPanel = true;
                    renderEventsPanel();
                }
            } catch (err) {
                btn.textContent = '\u2717 Failed';
                console.error('Seed error:', err);
            } finally {
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '&#9654; Simulate Event';
                }, 3000);
            }
        });
    }

    function rnd(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function bindEventsPanel() {
        dom.eventsToggle.addEventListener('click', () => {
            state.showEventsPanel = !state.showEventsPanel;
            renderEventsPanel();
        });

        dom.eventsClear.addEventListener('click', () => {
            state.events = [];
            renderEventsPanel();
        });
    }

    async function switchCountry(nextCountry) {
        const targetCountry = nextCountry.toUpperCase();
        if (targetCountry === state.country) {
            return;
        }

        // Fetch the target country's steps from the API to determine valid navigation
        let targetSteps = [];
        try {
            const response = await request(`/api/v1/steps/${targetCountry}`);
            if (Array.isArray(response.data)) {
                targetSteps = response.data;
            }
        } catch {
            // Fall through — navigate to dashboard if steps can't be fetched
        }

        const stepExists = targetSteps.some((step) => step.key === state.step);
        const nextStep = stepExists ? state.step : (targetSteps[0]?.key || 'dashboard');

        window.location.href = `/${targetCountry}/${nextStep}`;
    }

    function renderNavigation() {
        if (!state.steps.length) {
            dom.nav.innerHTML = '<button type="button" class="nav-button" disabled><span><strong>No steps</strong><span>There is no navigation configured for this country.</span></span></button>';
            updatePageTitle();
            return;
        }

        dom.nav.innerHTML = state.steps.map((step) => {
            const active = step.key === state.step;
            return `
                <button type="button" class="nav-button" data-step="${escapeHtml(step.key)}" ${active ? 'aria-current="page"' : ''}>
                    <span>
                        <strong>${escapeHtml(step.label)}</strong>
                        <span>${escapeHtml(step.path || `/${step.key}`)}</span>
                    </span>
                    <span>${String(step.id ?? '').padStart(2, '0')}</span>
                </button>
            `;
        }).join('');

        Array.from(dom.nav.querySelectorAll('[data-step]')).forEach((button) => {
            button.addEventListener('click', () => {
                window.location.href = `/${state.country}/${button.dataset.step}`;
            });
        });

        updatePageTitle();
    }

    function updatePageTitle() {
        const activeStep = state.steps.find((step) => step.key === state.step);
        const fallback = {
            dashboard: 'Dashboard',
            employees: 'Employees',
            checklist: 'Checklist',
            documentation: 'Documentation',
        };

        dom.pageTitle.textContent = activeStep?.label || fallback[state.step] || 'Hub Service';
    }

    function updatePageCopy() {
        dom.pageCopy.textContent = pageDescriptions[state.step] || '';
    }

    function syncCountryButtons() {
        dom.countryButtons.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.countryToggle === state.country ? 'true' : 'false');
        });
    }

    async function loadActiveView() {
        updatePageTitle();
        updatePageCopy();

        if (state.step === 'employees') {
            return loadEmployeesView();
        }

        if (state.step === 'checklist') {
            return loadChecklistView();
        }

        if (state.step === 'documentation') {
            return loadDocumentationView();
        }

        return loadDashboardView();
    }

    async function loadDashboardView() {
        dom.view.innerHTML = '<div class="loading-state">Loading dashboard...</div>';

        try {
            const [schemaResponse, employeesResponse, checklistResponse] = await Promise.all([
                request(`/api/v1/schema/${state.country}`),
                request(`/api/v1/employees/${state.country}?per_page=100`),
                request(`/api/v1/checklist/${state.country}`),
            ]);

            const schema = schemaResponse.data || {};
            const employees = employeesResponse.data || [];
            const employeeMeta = employeesResponse.meta || {};
            const checklist = checklistResponse.data || {};
            const widgets = schema.widgets || [];

            const widgetMarkup = widgets.map((widget) => renderWidget(widget, employees, employeeMeta, checklist)).join('');

            const checklistSummary = checklist.has_employees
                ? `
                    <section class="card rounded-card">
                        <div class="view-header" style="margin-bottom: 12px;">
                            <div>
                                <h2>Onboarding Checklist</h2>
                                <p>${Math.round(checklist.overall_percentage || 0)}% complete</p>
                            </div>
                            <span class="mini-pill">${checklist.complete_employees || 0} complete</span>
                        </div>
                        <div class="list-stack">
                            ${(checklist.employee_checklists || []).slice(0, 5).map((employee) => {
                                const fullName = [employee.name, employee.last_name].filter(Boolean).join(' ') || `Employee #${employee.employee_id}`;
                                return `
                                <div class="mini-row" style="cursor:pointer;" data-open-modal="${employee.employee_id}">
                                    <div>
                                        <strong>${escapeHtml(fullName)}</strong>
                                        <span>#${employee.employee_id} &middot; ${employee.items.filter((item) => item.completed).length}/${employee.items.length} fields complete</span>
                                    </div>
                                    <div style="min-width: 132px; text-align: right;">
                                        <span class="mini-pill">${Math.round(employee.completion_percentage || 0)}%</span>
                                        <div class="progress-track"><div class="progress-fill" style="width: ${Math.round(employee.completion_percentage || 0)}%"></div></div>
                                    </div>
                                </div>`;
                            }).join('')}
                        </div>
                    </section>
                `
                : '<section class="card rounded-card"><h2>Onboarding Checklist</h2><p class="card-copy">No employees found for this country yet.</p></section>';

            const recentEvents = state.events.filter((event) => event.country === state.country).slice(0, 5);
            const activityMarkup = recentEvents.length
                ? `
                    <section class="card rounded-card">
                        <h2 style="margin-top: 0;">Recent Activity</h2>
                        <div class="list-stack" style="margin-top: 14px;">
                            ${recentEvents.map((event) => `
                                <div class="mini-row">
                                    <div>
                                        <strong>${escapeHtml(event.employee_data?.name || 'Employee')} ${escapeHtml(event.employee_data?.last_name || '')}</strong>
                                        <span>#${event.employee_id || '?'} · ${escapeHtml(event.event_type || 'employee.updated')}</span>
                                    </div>
                                    <span class="mini-pill">${escapeHtml(formatTime(event.timestamp))}</span>
                                </div>
                            `).join('')}
                        </div>
                    </section>
                `
                : '';

            dom.view.innerHTML = `
                <div class="view-header">
                    <div>
                        <h2>Dashboard</h2>
                    </div>
                </div>
                <div class="grid" style="margin-bottom: 18px;">${checklistSummary}${activityMarkup}</div>
                <div class="grid stats-grid">${widgetMarkup}</div>
            `;

            // Wire modal openers in dashboard checklist summary
            Array.from(dom.view.querySelectorAll('[data-open-modal]')).forEach((el) => {
                el.addEventListener('click', () => {
                    const id = Number(el.dataset.openModal);
                    const emp = (checklist.employee_checklists || []).find((e) => e.employee_id === id);
                    if (emp) openChecklistModal(emp);
                });
            });
        } catch (error) {
            renderError(error.message);
        }
    }

    async function loadEmployeesView() {
        dom.view.innerHTML = `
            <div class="view-header">
                <div>
                    <h2>Employees</h2>
                    <p>${escapeHtml(state.country)} employee records.</p>
                </div>
                <div class="actions-inline">
                    <button type="button" class="simple-button" id="employees-refresh">Refresh</button>
                </div>
            </div>
            <div class="card rounded-card">
                <table>
                    <thead>
                        <tr>
                            <th>Loading</th>
                            <th>Loading</th>
                            <th>Loading</th>
                            <th>Loading</th>
                            <th>Loading</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5" class="loading-state">Loading employees...</td></tr>
                    </tbody>
                </table>
                <div class="pagination">
                    <span class="pagination-copy">Page ${state.employeesPage} of ${state.employeesPage}</span>
                    <div class="actions-inline">
                        <button type="button" class="simple-button" id="employees-prev" disabled>Prev</button>
                        <span class="pagination-copy">${state.employeesPage} / ${state.employeesPage}</span>
                        <button type="button" class="simple-button" id="employees-next" disabled>Next</button>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('employees-refresh').addEventListener('click', () => loadEmployeesView());

        try {
            const response = await request(`/api/v1/employees/${state.country}?page=${state.employeesPage}&per_page=15`);
            const rows = response.data || [];
            const columns = response.meta?.columns || [];
            const meta = response.meta || {};

            state.employeesPage = meta.current_page || 1;

            dom.view.innerHTML = `
                <div class="view-header">
                    <div>
                        <h2>Employees</h2>
                        <p>${meta.total || rows.length} total records in ${escapeHtml(state.country)}.</p>
                    </div>
                    <div class="actions-inline">
                        <button type="button" class="simple-button" id="employees-refresh">Refresh</button>
                    </div>
                </div>
                <div class="card rounded-card">
                    <table>
                        <thead>
                            <tr>${columns.map((column) => `<th>${escapeHtml(column.label || column.key)}</th>`).join('')}</tr>
                        </thead>
                        <tbody>
                            ${rows.length ? rows.map((row) => `
                                <tr>
                                    ${columns.map((column) => `<td>${renderTableCell(row, column)}</td>`).join('')}
                                </tr>
                            `).join('') : '<tr><td colspan="5" class="empty-state">No employees found.</td></tr>'}
                        </tbody>
                    </table>
                    <div class="pagination">
                        <span class="pagination-copy">Page ${meta.current_page || 1} of ${meta.last_page || 1}</span>
                        <div class="actions-inline">
                            <button type="button" class="simple-button" id="employees-prev" ${(meta.current_page || 1) <= 1 ? 'disabled' : ''}>Prev</button>
                            <span class="pagination-copy">${meta.current_page || 1} / ${meta.last_page || 1}</span>
                            <button type="button" class="simple-button" id="employees-next" ${(meta.current_page || 1) >= (meta.last_page || 1) ? 'disabled' : ''}>Next</button>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('employees-refresh').addEventListener('click', () => loadEmployeesView());
            document.getElementById('employees-prev').addEventListener('click', () => {
                if (state.employeesPage > 1) {
                    state.employeesPage -= 1;
                    loadEmployeesView();
                }
            });
            document.getElementById('employees-next').addEventListener('click', () => {
                if (state.employeesPage < (meta.last_page || 1)) {
                    state.employeesPage += 1;
                    loadEmployeesView();
                }
            });
        } catch (error) {
            renderError(error.message);
        }
    }

    async function loadChecklistView() {
        dom.view.innerHTML = '<div class="loading-state">Loading checklist...</div>';

        try {
            const response = await request(`/api/v1/checklist/${state.country}?page=${state.checklistPage}&per_page=25`);
            const checklist = response.data || {};
            const meta = response.meta || {};

            state.checklistPage = meta.current_page || 1;

            const cards = (checklist.employee_checklists || []).map((employee) => {
                const expanded = state.checklistExpanded.has(employee.employee_id);
                const completedFields = employee.items.filter((item) => item.completed).length;
                const fullName = [employee.name, employee.last_name].filter(Boolean).join(' ');
                const initials = [employee.name, employee.last_name]
                    .filter(Boolean).map((w) => w[0].toUpperCase()).join('')
                    || `#${employee.employee_id}`;

                return `
                    <article class="checklist-card rounded-card">
                        <button type="button" class="checklist-toggle" data-checklist-toggle="${employee.employee_id}" data-open-modal="${employee.employee_id}">
                            <div class="checklist-meta">
                                <span class="avatar" title="${escapeHtml(fullName)}">${escapeHtml(initials)}</span>
                                <div>
                                    <strong>${fullName ? escapeHtml(fullName) : `Employee #${employee.employee_id}`}</strong>
                                    <span class="pagination-copy">#${employee.employee_id} &middot; ${completedFields}/${employee.items.length} fields complete</span>
                                </div>
                            </div>
                            <div style="min-width: 132px; text-align: right;">
                                <span class="mini-pill">${Math.round(employee.completion_percentage || 0)}%</span>
                                <div class="progress-track"><div class="progress-fill" style="width: ${Math.round(employee.completion_percentage || 0)}%"></div></div>
                            </div>
                        </button>
                        <div class="checklist-items ${expanded ? '' : 'hidden'}" data-checklist-items="${employee.employee_id}">
                            ${employee.items.map((item) => `
                                <div class="checklist-item">
                                    <div>
                                        <strong class="${item.completed ? 'status-text-complete' : 'status-text-warning'}">${escapeHtml(item.label)}</strong>
                                        <span class="pagination-copy">${escapeHtml(item.message || (item.completed ? 'Completed' : 'Missing'))}</span>
                                    </div>
                                    <span class="field-code">${escapeHtml(item.field)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </article>
                `;
            }).join('');

            dom.view.innerHTML = `
                <div class="view-header">
                    <div>
                        <h2>Onboarding Checklist</h2>
                        <p>Completion status for ${escapeHtml(state.country)} employees.</p>
                    </div>
                </div>
                <div class="grid stats-grid">
                    <div class="card rounded-card"><span class="card-label">Total Employees</span><span class="card-value">${checklist.total_employees || 0}</span></div>
                    <div class="card rounded-card"><span class="card-label">Fully Complete</span><span class="card-value">${checklist.complete_employees || 0}</span></div>
                    <div class="card rounded-card"><span class="card-label">Completion Rate</span><span class="card-value">${Math.round(checklist.overall_percentage || 0)}%</span></div>
                </div>
                <div class="grid" style="margin-top: 18px;">${cards || '<div class="empty-state">No employees found for this country.</div>'}</div>
                <div class="pagination">
                    <span class="pagination-copy">Page ${meta.current_page || 1} of ${meta.last_page || 1}</span>
                    <div class="actions-inline">
                        <button type="button" class="simple-button" id="checklist-prev" ${(meta.current_page || 1) <= 1 ? 'disabled' : ''}>Prev</button>
                        <span class="pagination-copy">${meta.current_page || 1} / ${meta.last_page || 1}</span>
                        <button type="button" class="simple-button" id="checklist-next" ${(meta.current_page || 1) >= (meta.last_page || 1) ? 'disabled' : ''}>Next</button>
                    </div>
                </div>
            `;

            Array.from(document.querySelectorAll('[data-open-modal]')).forEach((el) => {
                el.addEventListener('click', () => {
                    const id = Number(el.dataset.openModal);
                    const emp = (checklist.employee_checklists || []).find((e) => e.employee_id === id);
                    if (emp) openChecklistModal(emp);
                });
            });

            document.getElementById('checklist-prev').addEventListener('click', () => {
                if (state.checklistPage > 1) {
                    state.checklistPage -= 1;
                    loadChecklistView();
                }
            });
            document.getElementById('checklist-next').addEventListener('click', () => {
                if (state.checklistPage < (meta.last_page || 1)) {
                    state.checklistPage += 1;
                    loadChecklistView();
                }
            });
        } catch (error) {
            renderError(error.message);
        }
    }

    async function loadDocumentationView() {
        dom.view.innerHTML = '<div class="loading-state">Loading documentation...</div>';

        try {
            const [employeesResponse, checklistResponse] = await Promise.all([
                request(`/api/v1/employees/${state.country}?per_page=100`),
                request(`/api/v1/checklist/${state.country}`),
            ]);

            const employees = employeesResponse.data || [];
            const checklist = checklistResponse.data || {};
            const docFields = [
                ['doc_work_permit', 'Work Permit'],
                ['doc_tax_card', 'Tax Card'],
                ['doc_health_insurance', 'Health Ins.'],
                ['doc_social_security', 'Social Sec.'],
                ['doc_employment_contract', 'Contract'],
            ];

            dom.view.innerHTML = `
                <div class="view-header">
                    <div>
                        <h2>Documentation</h2>
                        <p>Track document submission for ${escapeHtml(state.country)} employees.</p>
                    </div>
                    <span class="mini-pill">${escapeHtml(state.country)}</span>
                </div>
                <div class="card rounded-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                ${docFields.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('')}
                                <th>Docs</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${employees.length ? employees.map((employee) => {
                                const percent = documentationPercent(employee, checklist.employee_checklists || [], docFields.map(([field]) => field));
                                return `
                                    <tr>
                                        <td><strong>${escapeHtml(employee.name)} ${escapeHtml(employee.last_name)}</strong><br><span class="pagination-copy">#${employee.employee_id || employee.id}</span></td>
                                        ${docFields.map(([field]) => `<td>${employee[field] ? '<span class="mini-pill">Yes</span>' : '<span class="field-code">Missing</span>'}</td>`).join('')}
                                        <td><span class="mini-pill">${percent}%</span></td>
                                    </tr>
                                `;
                            }).join('') : '<tr><td colspan="7" class="empty-state">No employees found for this country.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
        } catch (error) {
            renderError(error.message);
        }
    }

    function renderWidget(widget, employees, employeeMeta, checklist) {
        const value = resolveWidgetValue(widget, employees, employeeMeta, checklist);

        if (widget.type === 'progress_list') {
            return `
                <section class="card rounded-card">
                    <span class="card-label">${escapeHtml(widget.title)}</span>
                    <div class="list-stack" style="margin-top: 14px;">
                        ${employees.length ? employees.map((employee) => `
                            <div class="mini-row">
                                <div>
                                    <strong>${escapeHtml(employee.name)} ${escapeHtml(employee.last_name)}</strong>
                                    <span>${employee.goal ? escapeHtml(employee.goal) : 'Goal pending'}</span>
                                </div>
                                <span class="mini-pill">${employee.goal ? 'Set' : 'Open'}</span>
                            </div>
                        `).join('') : '<div class="empty-state">No employees projected yet.</div>'}
                    </div>
                </section>
            `;
        }

        const copy = widget.type === 'progress_bar'
            ? `<div class="progress-track"><div class="progress-fill" style="width: ${Math.round(value)}%"></div></div><p class="card-copy">${Math.round(value)}% complete</p>`
            : `<p class="card-copy">${escapeHtml(widget.data_source || 'Derived from Hub API data.')}</p>`;

        return `
            <section class="card rounded-card">
                <span class="card-label">${escapeHtml(widget.title)}</span>
                <span class="card-value">${formatWidgetValue(widget, value)}</span>
                ${copy}
            </section>
        `;
    }

    function resolveWidgetValue(widget, employees, employeeMeta, checklist) {
        const field = widget.meta?.field || '';

        if (field === 'meta.total') {
            return employeeMeta.total || employees.length || 0;
        }

        if (field === 'data.avg_salary') {
            return employeeMeta.avg_salary || 0;
        }

        if (field === 'data.overall_percentage') {
            return checklist.overall_percentage || 0;
        }

        if (field === 'data.goals_set_percentage') {
            if (!employees.length) {
                return 0;
            }
            const withGoals = employees.filter((employee) => !!employee.goal).length;
            return Math.round((withGoals / employees.length) * 100);
        }

        return 0;
    }

    function formatWidgetValue(widget, value) {
        if (widget.meta?.format === 'currency') {
            return formatCurrency(value);
        }

        if (widget.meta?.format === 'percentage') {
            return `${Math.round(value)}%`;
        }

        return String(Math.round(value));
    }

    function renderTableCell(row, column) {
        const value = row[column.key];

        if (column.key === 'salary') {
            return escapeHtml(formatCurrency(value));
        }

        if (column.format === 'masked') {
            return escapeHtml(maskValue(value));
        }

        return escapeHtml(value ?? '—');
    }

    function maskValue(value) {
        if (!value) {
            return '—';
        }

        if (String(value).startsWith('***-**-')) {
            return String(value);
        }

        const digits = String(value).replace(/\D/g, '');
        if (digits.length >= 4) {
            return `***-**-${digits.slice(-4)}`;
        }

        return String(value);
    }

    function documentationPercent(employee, employeeChecklists, fields) {
        const employeeId = employee.employee_id || employee.id;
        const checklist = employeeChecklists.find((item) => item.employee_id === employeeId);

        if (checklist?.items) {
            const docItems = checklist.items.filter((item) => item.field.startsWith('doc_'));
            if (docItems.length) {
                const completed = docItems.filter((item) => item.completed).length;
                return Math.round((completed / docItems.length) * 100);
            }
        }

        const completed = fields.filter((field) => !!employee[field]).length;
        return Math.round((completed / fields.length) * 100);
    }

    function openChecklistModal(employee) {
        state.modalEmployee = employee;
        const fullName = [employee.name, employee.last_name].filter(Boolean).join(' ') || `Employee #${employee.employee_id}`;
        const pct = Math.round(employee.completion_percentage || 0);
        const done = (employee.items || []).filter((i) => i.completed);
        const missing = (employee.items || []).filter((i) => !i.completed);

        document.getElementById('modal-title').textContent = fullName;
        document.getElementById('modal-sub').textContent = `#${employee.employee_id} · ${escapeHtml(employee.country)} · ${pct}% complete`;
        document.getElementById('modal-progress-fill').style.width = pct + '%';

        const stepRows = (employee.steps || []).map((step) => {
            const stepDone = step.fields.filter((f) => f.completed).length;
            const stepTotal = step.fields.length;
            const stepPct = step.step_completion_percentage || 0;
            const color = stepPct === 100 ? '#15803d' : stepPct > 0 ? '#b45309' : '#dc2626';
            return `
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="font-size:.875rem;">${escapeHtml(step.label)}</strong>
                        <span style="font-size:.75rem;font-weight:600;color:${color};">${stepDone}/${stepTotal}</span>
                    </div>
                    ${step.fields.map((field) => `
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:6px;background:var(--bg);margin-bottom:4px;">
                            <span style="font-size:1rem;">${field.completed ? '\u2705' : '\u26a0\ufe0f'}</span>
                            <div style="flex:1;min-width:0;">
                                <span style="font-weight:500;font-size:.8125rem;color:${field.completed ? 'var(--ink)' : '#b45309'};">${escapeHtml(field.label)}</span>
                                ${field.message ? `<span style="display:block;font-size:.75rem;color:#b45309;margin-top:2px;">${escapeHtml(field.message)}</span>` : ''}
                            </div>
                            <span style="font-size:.6875rem;color:var(--muted);background:var(--border-light);padding:2px 6px;border-radius:4px;font-family:monospace;">${escapeHtml(field.field)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
        }).join('');

        const summaryRow = `
            <div style="display:flex;gap:8px;margin-bottom:14px;">
                <div style="flex:1;padding:10px;border-radius:6px;background:rgba(21,128,61,.07);border:1px solid rgba(21,128,61,.15);text-align:center;">
                    <div style="font-size:1.375rem;font-weight:700;color:#15803d;">${done.length}</div>
                    <div style="font-size:.75rem;color:#15803d;margin-top:2px;">Complete</div>
                </div>
                <div style="flex:1;padding:10px;border-radius:6px;background:rgba(220,38,38,.07);border:1px solid rgba(220,38,38,.15);text-align:center;">
                    <div style="font-size:1.375rem;font-weight:700;color:#dc2626;">${missing.length}</div>
                    <div style="font-size:.75rem;color:#dc2626;margin-top:2px;">Missing</div>
                </div>
                <div style="flex:1;padding:10px;border-radius:6px;background:rgba(15,118,110,.07);border:1px solid rgba(15,118,110,.15);text-align:center;">
                    <div style="font-size:1.375rem;font-weight:700;color:var(--accent);">${pct}%</div>
                    <div style="font-size:.75rem;color:var(--accent);margin-top:2px;">Overall</div>
                </div>
            </div>
        `;

        document.getElementById('modal-body').innerHTML = summaryRow + (stepRows || `
            ${(employee.items || []).map((item) => `
                <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:6px;background:var(--bg);margin-bottom:4px;">
                    <span>${item.completed ? '\u2705' : '\u26a0\ufe0f'}</span>
                    <span style="flex:1;font-size:.8125rem;">${escapeHtml(item.label)}</span>
                    ${item.message ? `<span style="font-size:.75rem;color:#b45309;">${escapeHtml(item.message)}</span>` : '<span style="font-size:.75rem;color:#15803d;">Done</span>'}
                </div>
            `).join('')}
        `);

        const modal = document.getElementById('checklist-modal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('checklist-modal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
        state.modalEmployee = null;
    }

    function renderError(message) {
        dom.view.innerHTML = `<div class="error-state">${escapeHtml(message)}</div>`;
    }

    function renderEventsPanel() {
        dom.eventsCount.textContent = String(state.events.length);
        dom.eventsPanel.classList.toggle('hidden', !state.showEventsPanel);
        dom.contentShell.classList.toggle('panel-hidden', !state.showEventsPanel);

        if (!state.events.length) {
            dom.eventsList.innerHTML = '<li class="empty-state">No events received yet.</li>';
            return;
        }

        dom.eventsList.innerHTML = state.events.slice().reverse().map((event) => {
            const badgeClass = event.event_type === 'EmployeeCreated'
                ? 'created'
                : event.event_type === 'EmployeeDeleted'
                    ? 'deleted'
                    : 'updated';

            return `
                <li class="event-item" data-event-id="${escapeHtml(event.event_id || '')}">
                    <div class="item-head">
                        <span class="event-badge ${badgeClass}">${escapeHtml(event.event_type || 'employee.updated')}</span>
                        <span class="pagination-copy">${escapeHtml(formatTime(event.timestamp || event._received_at))}</span>
                    </div>
                    <p style="margin: 10px 0 0;"><strong>${escapeHtml(event.employee_data?.name || 'Employee')} ${escapeHtml(event.employee_data?.last_name || '')}</strong></p>
                    <p class="pagination-copy" style="margin: 6px 0 0;">#${escapeHtml(event.employee_id || '?')} · ${escapeHtml(event.country || state.country)} · ${escapeHtml(event._channel || 'employees')}</p>
                </li>
            `;
        }).join('');
    }

    function initWebSocket() {
        connectWebSocket();
        window.addEventListener('beforeunload', disconnectWebSocket);
    }

    function connectWebSocket() {
        if (state.ws && state.ws.readyState <= 1) {
            return;
        }

        const wsUrl = `ws://${window.location.hostname}:8080/app/hr-platform-key?protocol=7&client=js&version=7.0.0&flash=false`;

        try {
            state.ws = new WebSocket(wsUrl);
        } catch (error) {
            scheduleReconnect();
            return;
        }

        state.ws.onmessage = (event) => {
            try {
                handleSocketMessage(JSON.parse(event.data));
            } catch {
                return;
            }
        };

        state.ws.onclose = () => {
            updateConnectionState(false);
            clearInterval(state.pingTimer);
            scheduleReconnect();
        };
    }

    function handleSocketMessage(message) {
        if (message.event === 'pusher:connection_established') {
            updateConnectionState(true);
            subscribeChannels();
            clearInterval(state.pingTimer);
            state.pingTimer = window.setInterval(() => {
                sendSocket({ event: 'pusher:ping', data: {} });
            }, 30000);
            return;
        }

        if (message.event !== 'employee.updated') {
            return;
        }

        const payload = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
        const eventId = payload.event_id;

        if (eventId && state.seenEventIds.has(eventId)) {
            return;
        }

        if (eventId) {
            state.seenEventIds.add(eventId);
            if (state.seenEventIds.size > 500) {
                const remaining = Array.from(state.seenEventIds).slice(-250);
                state.seenEventIds = new Set(remaining);
            }
        }

        state.events.push({
            ...payload,
            _channel: message.channel,
            _received_at: new Date().toISOString(),
        });

        if (state.events.length > 200) {
            state.events = state.events.slice(-200);
        }

        renderEventsPanel();

        if (payload.country === state.country) {
            window.clearTimeout(state.refreshTimer);
            state.refreshTimer = window.setTimeout(() => {
                loadActiveView();
            }, 2000);
        }
    }

    function sendSocket(payload) {
        if (state.ws && state.ws.readyState === WebSocket.OPEN) {
            state.ws.send(JSON.stringify(payload));
        }
    }

    function subscribeChannels() {
        ['employees', `country.${state.country}`].forEach((channel) => {
            sendSocket({
                event: 'pusher:subscribe',
                data: { channel },
            });
        });
    }

    function updateConnectionState(connected) {
        state.connected = connected;
        dom.statusDot.classList.toggle('live', connected);
        dom.statusLabel.textContent = connected ? 'Live' : 'Disconnected';
    }

    function disconnectWebSocket() {
        clearInterval(state.pingTimer);
        if (state.ws) {
            state.ws.close();
            state.ws = null;
        }
    }

    function scheduleReconnect() {
        if (state.reconnectTimer) {
            return;
        }

        state.reconnectTimer = window.setTimeout(() => {
            state.reconnectTimer = null;
            connectWebSocket();
        }, 3000);
    }

    function formatTime(timestamp) {
        if (!timestamp) {
            return 'now';
        }

        try {
            return new Date(timestamp).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        } catch {
            return String(timestamp);
        }
    }
}());