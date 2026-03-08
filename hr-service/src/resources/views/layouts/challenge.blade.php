<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'HR Service' }}</title>
    <style>
        :root {
            --bg: #f9fafb;
            --surface: #fff;
            --ink: #111827;
            --muted: #6b7280;
            --accent: #0f766e;
            --accent-hover: #0d6960;
            --danger: #dc2626;
            --danger-soft: rgba(220,38,38,0.06);
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
        button, input, select, textarea { font: inherit; }
        a { color: inherit; text-decoration: none; }

        .shell { max-width: 1060px; margin: 0 auto; padding: 0 20px 64px; }
        .app-bar {
            display: flex; align-items: center; gap: 8px;
            height: 52px; margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .app-bar-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 6px;
            background: var(--accent); color: #fff;
            font-size: 0.6875rem; font-weight: 700;
        }
        .app-bar-name { font-size: 0.8125rem; font-weight: 600; color: var(--muted); }

        .panel { border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); }
        .panel-soft {}
        .panel-header {
            display: flex; justify-content: space-between; align-items: center;
            gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border);
        }
        .panel-title { margin: 0; font-size: 1rem; font-weight: 600; }
        .panel-copy { margin: 2px 0 0; color: var(--muted); font-size: 0.8125rem; }

        .toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
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

        .button, .button-link {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 6px; padding: 7px 14px; border: 1px solid transparent;
            border-radius: 6px; font-size: 0.8125rem; font-weight: 500;
            cursor: pointer; transition: background .15s; text-decoration: none;
        }
        .button, .button-link { background: var(--accent); color: #fff; }
        .button:hover, .button-link:hover { background: var(--accent-hover); }
        .button-secondary { background: var(--surface); border-color: var(--border); color: var(--ink); }
        .button-secondary:hover { background: var(--border-light); }
        .button-danger { background: var(--danger); color: #fff; border-color: transparent; }
        .button-danger:hover { opacity: 0.9; }
        .button[disabled], .toggle-button[disabled] { cursor: not-allowed; opacity: 0.4; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 8px 16px; text-align: left; font-size: 0.6875rem; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);
            background: var(--bg); border-bottom: 1px solid var(--border);
        }
        tbody td { padding: 10px 16px; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--border-light); }

        .name-stack strong, .name-stack span { display: block; }
        .name-stack span { margin-top: 1px; color: var(--muted); font-size: 0.8125rem; }
        .country-chip {
            display: inline-block; padding: 2px 7px; border-radius: 4px;
            background: rgba(15,118,110,0.08); color: var(--accent);
            font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;
        }
        .muted { color: var(--muted); }
        .empty-state, .inline-message { padding: 32px 16px; text-align: center; color: var(--muted); }

        .summary-line { padding: 8px 20px; color: var(--muted); font-size: 0.8125rem; }
        .pagination {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 20px; border-top: 1px solid var(--border); flex-wrap: wrap;
        }
        .pagination-controls { display: flex; align-items: center; gap: 6px; }
        .pagination-number { min-width: 56px; text-align: center; color: var(--muted); font-size: 0.8125rem; }

        .flash, .server-error {
            margin: 12px 20px; padding: 10px 14px; border-radius: 6px;
            font-size: 0.8125rem; line-height: 1.5;
        }
        .flash { background: rgba(15,118,110,0.06); border: 1px solid rgba(15,118,110,0.15); color: var(--accent); }
        .server-error { background: var(--danger-soft); border: 1px solid rgba(220,38,38,0.12); color: #991b1b; }

        .grid { display: grid; gap: 14px; }
        .grid-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        .form-panel { padding: 20px; }
        .form-section { margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--border-light); }
        .form-section h2 { margin: 0; font-size: 0.875rem; font-weight: 600; }
        .form-section p { margin: 4px 0 0; color: var(--muted); font-size: 0.8125rem; }
        label { display: block; margin-bottom: 5px; font-size: 0.8125rem; font-weight: 500; }
        input, select, textarea {
            width: 100%; padding: 8px 10px; border: 1px solid var(--border);
            border-radius: 6px; background: var(--surface); color: var(--ink);
        }
        textarea { min-height: 100px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid rgba(15,118,110,0.2); border-color: var(--accent);
        }
        .helper-copy { margin-top: 4px; color: var(--muted); font-size: 0.75rem; }
        .readonly-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 10px; border-radius: 6px; background: var(--bg); color: var(--muted);
        }
        .readonly-badge strong { color: var(--ink); }
        .form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; flex-wrap: wrap; }
        .field-error[data-has-error="true"] { margin-top: 4px; }
        .text-red-500 { margin: 0; color: var(--danger); font-size: 0.8125rem; }

        .hidden { display: none !important; }
        .fixed { position: fixed; }
        .inset-0 { inset: 0; }
        .z-50 { z-index: 50; }

        .modal-backdrop {
            display: flex; align-items: center; justify-content: center;
            padding: 20px; background: rgba(0,0,0,0.4);
        }
        .modal-card {
            width: min(420px, calc(100vw - 40px)); padding: 24px;
            border-radius: var(--radius); background: var(--surface);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .modal-card h2 { margin: 0 0 8px; font-size: 1rem; font-weight: 600; }
        .modal-card p { margin: 0; color: var(--muted); font-size: 0.875rem; line-height: 1.5; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }

        @media (max-width: 860px) {
            .panel-header, .pagination { flex-direction: column; align-items: stretch; }
            .grid-two { grid-template-columns: 1fr; }
        }
    </style>
    @stack('head')
</head>
<body data-page="{{ $page ?? '' }}" data-employee-id="{{ $employeeId ?? '' }}">
    <div class="shell">
        <div class="app-bar">
            <span class="app-bar-mark">HR</span>
            <span class="app-bar-name">HR Service</span>
        </div>

        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>