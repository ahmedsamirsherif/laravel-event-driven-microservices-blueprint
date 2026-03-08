<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Service</title>
    <style>
        :root {
            --bg: #f9fafb;
            --surface: #fff;
            --sidebar-bg: #111827;
            --ink: #111827;
            --muted: #6b7280;
            --accent: #0f766e;
            --accent-hover: #0d6960;
            --success: #15803d;
            --warning: #b45309;
            --danger: #dc2626;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --radius: 8px;
        }
        *,*::before,*::after { box-sizing: border-box; margin: 0; }
        body {
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        button, input, select { font: inherit; }

        .app-shell {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            display: flex; flex-direction: column;
            padding: 20px 16px;
            color: rgba(255,255,255,0.88);
            background: var(--sidebar-bg);
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            padding: 0 4px 16px; margin-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .brand-mark {
            width: 28px; height: 28px; border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.6875rem; font-weight: 700;
            background: rgba(255,255,255,0.12);
        }
        .brand-copy strong { font-size: 0.875rem; }
        .brand-copy span { display: block; margin-top: 1px; font-size: 0.75rem; color: rgba(255,255,255,0.5); }

        nav { display: grid; gap: 2px; }
        .nav-button {
            width: 100%; display: flex; align-items: center; justify-content: space-between;
            gap: 8px; padding: 10px 12px; border: none; border-radius: 6px;
            background: transparent; color: rgba(255,255,255,0.7);
            cursor: pointer; text-align: left; font-size: 0.8125rem;
            transition: background .15s, color .15s;
        }
        .nav-button:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-button[aria-current="page"] {
            background: rgba(255,255,255,0.12); color: #fff; font-weight: 500;
        }
        .nav-button strong { font-weight: inherit; }
        .nav-button span { font-size: 0.75rem; color: rgba(255,255,255,0.4); }
        .nav-button > span:last-child { font-size: 0.6875rem; color: rgba(255,255,255,0.3); }

        .sidebar-status {
            margin-top: auto; padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .status-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 10px; border-radius: 6px;
            background: rgba(255,255,255,0.06); font-size: 0.8125rem;
        }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #f87171; transition: background .15s;
        }
        .status-dot.live { background: #4ade80; }

        /* Main */
        .main-shell { padding: 20px 24px; }

        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 16px; padding: 0 0 16px; margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .page-title { margin: 0; font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        .page-copy { display: none; }

        .topbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .toggle-group {
            display: inline-flex; border: 1px solid var(--border);
            border-radius: 6px; overflow: hidden;
        }
        .toggle-button {
            padding: 6px 14px; min-width: 72px; border: none;
            background: transparent; color: var(--muted);
            font-size: 0.8125rem; font-weight: 500; cursor: pointer;
            transition: background .15s, color .15s;
        }
        .toggle-button + .toggle-button { border-left: 1px solid var(--border); }
        .toggle-button[aria-pressed="true"] { background: var(--accent); color: #fff; }
        .toggle-button:hover:not([aria-pressed="true"]) { background: var(--border-light); }

        .action-button {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;
            background: var(--surface); color: var(--ink);
            font-size: 0.8125rem; font-weight: 500; cursor: pointer;
            transition: background .15s;
        }
        .action-button:hover { background: var(--border-light); }
        .action-badge {
            min-width: 18px; padding: 1px 6px; border-radius: 10px;
            background: var(--accent); color: #fff;
            font-size: 0.6875rem; text-align: center;
        }

        /* Content */
        .content-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 16px;
        }
        .content-shell.panel-hidden { grid-template-columns: minmax(0,1fr) 0; }

        .view-panel, .live-panel {
            border: 1px solid var(--border); border-radius: var(--radius);
            background: var(--surface);
        }
        .view-panel { padding: 20px; min-height: 400px; }
        .rounded-panel, .rounded-card {}

        .view-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .view-header h2 { margin: 0; font-size: 1rem; font-weight: 600; }
        .view-header p { margin: 4px 0 0; color: var(--muted); font-size: 0.8125rem; }

        /* Cards */
        .grid { display: grid; gap: 12px; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .card {
            padding: 16px; border-radius: var(--radius);
            background: var(--surface); border: 1px solid var(--border);
        }
        .card-label {
            display: block; font-size: 0.6875rem; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);
        }
        .card-value { display: block; margin-top: 8px; font-size: 1.5rem; font-weight: 700; }
        .card-copy { margin-top: 4px; color: var(--muted); font-size: 0.8125rem; line-height: 1.4; }

        .progress-track {
            height: 6px; border-radius: 3px;
            background: var(--border-light); overflow: hidden; margin-top: 10px;
        }
        .progress-fill { height: 100%; border-radius: inherit; background: var(--accent); }

        .list-stack { display: grid; gap: 8px; }
        .mini-row {
            display: flex; justify-content: space-between; gap: 12px; align-items: center;
            padding: 10px 12px; border-radius: 6px;
            background: var(--bg); border: 1px solid var(--border);
        }
        .mini-row strong, .mini-row span { display: block; }
        .mini-row span { margin-top: 2px; color: var(--muted); font-size: 0.8125rem; }
        .mini-pill {
            padding: 2px 8px; border-radius: 4px;
            background: rgba(15,118,110,0.08); color: var(--accent);
            font-size: 0.75rem; font-weight: 600;
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 8px 12px; text-align: left; font-size: 0.6875rem; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);
            background: var(--bg); border-bottom: 1px solid var(--border);
        }
        tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--border-light); }

        .actions-inline { display: inline-flex; gap: 6px; align-items: center; }
        .simple-button {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 6px; padding: 6px 12px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--surface);
            color: var(--ink); font-size: 0.8125rem; font-weight: 500; cursor: pointer;
            transition: background .15s;
        }
        .simple-button:hover { background: var(--border-light); }
        .simple-button[disabled] { cursor: not-allowed; opacity: 0.4; }

        .pagination {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; margin-top: 12px; padding-top: 12px;
            border-top: 1px solid var(--border); flex-wrap: wrap;
        }
        .pagination-copy { color: var(--muted); font-size: 0.8125rem; }

        /* Checklist */
        .checklist-card {
            border: 1px solid var(--border); border-radius: var(--radius);
            background: var(--surface); overflow: hidden;
        }
        .checklist-toggle {
            width: 100%; display: flex; justify-content: space-between;
            gap: 14px; align-items: center; padding: 12px 16px;
            border: none; background: transparent; cursor: pointer; text-align: left;
        }
        .checklist-toggle:hover { background: var(--border-light); }
        .checklist-meta { display: flex; align-items: center; gap: 10px; }
        .avatar {
            width: 32px; height: 32px; display: inline-flex;
            align-items: center; justify-content: center; border-radius: 6px;
            background: rgba(15,118,110,0.08); color: var(--accent);
            font-size: 0.75rem; font-weight: 700;
        }
        .checklist-items { padding: 0 16px 16px; display: grid; gap: 6px; }
        .checklist-item {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 8px 12px; border-radius: 6px; background: var(--bg);
        }
        .field-code {
            padding: 2px 6px; border-radius: 4px;
            background: var(--border-light); color: var(--muted); font-size: 0.75rem;
        }
        .status-text-complete { color: var(--success); }
        .status-text-warning { color: var(--warning); }
        .status-text-danger { color: var(--danger); }

        /* Live Events panel */
        .live-panel { display: flex; flex-direction: column; min-width: 0; }
        .live-panel.hidden { display: none; }
        .live-header {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .live-header h2 { margin: 0; font-size: 0.875rem; font-weight: 600; }
        .live-list {
            list-style: none; margin: 0; padding: 12px 16px;
            display: grid; gap: 8px; overflow-y: auto;
        }
        .event-item {
            padding: 10px 12px; border-radius: 6px;
            background: var(--bg); border: 1px solid var(--border);
        }
        .item-head {
            display: flex; justify-content: space-between; gap: 8px; align-items: center;
        }
        .event-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 6px; border-radius: 4px;
            font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;
        }
        .event-badge.created { background: rgba(21,128,61,0.08); color: var(--success); }
        .event-badge.updated { background: rgba(180,83,9,0.08); color: var(--warning); }
        .event-badge.deleted { background: rgba(220,38,38,0.08); color: var(--danger); }

        .empty-state, .error-state, .loading-state {
            padding: 32px 16px; text-align: center; color: var(--muted);
        }
        .error-state {
            color: #991b1b; background: rgba(220,38,38,0.06);
            border: 1px solid rgba(220,38,38,0.1); border-radius: var(--radius);
        }

        .hidden { display: none !important; }

        @media (max-width: 1100px) {
            .content-shell, .content-shell.panel-hidden { grid-template-columns: 1fr; }
            .live-panel { min-height: 240px; }
        }
        @media (max-width: 900px) {
            .app-shell { grid-template-columns: 1fr; }
            .sidebar { flex-direction: row; flex-wrap: wrap; gap: 12px; padding: 12px 16px; }
            .brand { padding: 0; margin: 0; border: none; }
            nav { flex-direction: row; gap: 4px; }
            .sidebar-status { margin: 0; padding: 0; border: none; }
            .main-shell { padding: 16px; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">H</div>
                <div class="brand-copy">
                    <strong>Hub Service</strong>
                </div>
            </div>

            <nav id="step-nav" aria-label="Main navigation">
                <button type="button" class="nav-button" disabled>
                    <span><strong>Loading</strong><span>Fetching steps...</span></span>
                </button>
            </nav>

            <div class="sidebar-status">
                <div class="status-pill">
                    <span id="status-dot" class="status-dot"></span>
                    <span id="status-label">Disconnected</span>
                </div>
            </div>
        </aside>

        <div class="main-shell">
            <header class="topbar">
                <div>
                    <h1 class="page-title" id="page-title">Loading...</h1>
                    <p class="page-copy" id="page-copy"></p>
                </div>
                <div class="topbar-actions">
                    <div class="toggle-group" id="country-switcher" aria-label="Country switcher">
                        {{-- Populated dynamically from /api/v1/countries --}}
                    </div>
                    <button type="button" class="action-button" id="seed-event-btn" title="Simulate a live employee event via API" style="border-color:#0f766e;color:#0f766e;">
                        &#9654; Simulate Event
                    </button>
                    <button type="button" class="action-button" id="events-toggle" aria-label="Toggle live events console">
                        Events <span class="action-badge" id="events-count">0</span>
                    </button>
                </div>
            </header>

            <div class="content-shell panel-hidden" id="content-shell">
                <main class="view-panel rounded-panel" id="view-container">
                    <div class="loading-state">Loading...</div>
                </main>

                <div class="live-panel hidden" id="events-panel">
                    <div class="live-header">
                        <h2>Live Events</h2>
                        <button type="button" class="simple-button" id="events-clear">Clear</button>
                    </div>
                    <ul class="live-list" id="events-list">
                        <li class="empty-state">No events received yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('hub-ui.js') }}" defer></script>
</body>
</html>
