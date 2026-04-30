<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'RBIM') }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #211b22;
            --muted: #6d6670;
            --line: #ded6dc;
            --surface: #ffffff;
            --surface-soft: #fbf8fa;
            --band: #f2eef1;
            --primary: #7a1f3d;
            --primary-strong: #55162c;
            --accent: #c48a22;
            --accent-soft: #fff5df;
            --info: #2f6f8f;
            --danger: #b42318;
            --shadow: 0 20px 60px rgba(44, 24, 36, 0.13);
            --soft-shadow: 0 10px 24px rgba(44, 24, 36, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                linear-gradient(135deg, rgba(122, 31, 61, 0.08) 0, transparent 34%),
                linear-gradient(180deg, #eee7eb 0, var(--band) 280px, #fbfafb 100%);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid rgba(208, 213, 221, 0.86);
            backdrop-filter: blur(14px);
        }

        .topbar-inner,
        .content {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .topbar-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--ink);
            font-weight: 700;
            letter-spacing: 0;
        }

        .brand::before {
            content: "R";
            display: inline-grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.16);
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a,
        .link-button {
            border-radius: 999px;
            padding: 8px 12px;
        }

        .nav a:hover,
        .link-button:hover {
            background: #eef6f4;
            text-decoration: none;
        }

        .content {
            flex: 1;
            padding: 34px 0 58px;
        }

        .panel {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0, rgba(255, 255, 255, 0.99) 100%);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 34px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), #d99b32, var(--info));
        }

        .auth-panel {
            width: min(520px, 100%);
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .auth-panel::before {
            content: "";
            display: block;
            height: 0;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--info));
            margin: 0;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 34px;
            line-height: 1.2;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 18px;
            color: var(--muted);
        }

        .page-kicker {
            margin: 0 0 8px;
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .auth-copy {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin: 16px 0 6px;
            font-weight: 700;
            font-size: 14px;
            color: #344054;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 11px 13px;
            font: inherit;
            background: #fff;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 3px solid rgba(122, 31, 61, 0.16);
            border-color: var(--primary);
        }

        input[type="file"] {
            padding: 8px;
            background: var(--surface-soft);
        }

        textarea {
            min-height: 96px;
            resize: vertical;
        }

        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
        }

        .check-row input {
            width: auto;
            min-height: auto;
        }

        .check-row label {
            margin: 0;
            font-weight: 400;
        }

        .button,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 0;
            border-radius: 8px;
            padding: 10px 18px;
            background: linear-gradient(180deg, #923052 0, var(--primary) 100%);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(122, 31, 61, 0.22);
            transition: transform .14s ease, box-shadow .14s ease, background .14s ease;
        }

        .primary-block {
            width: 100%;
        }

        .button:hover,
        button:hover {
            background: linear-gradient(180deg, #842747 0, var(--primary-strong) 100%);
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(122, 31, 61, 0.26);
        }

        .secondary-button {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--primary);
            box-shadow: none;
        }

        .secondary-button:hover {
            background: #fff7f9;
            color: var(--primary-strong);
        }

        .link-button {
            min-height: auto;
            background: transparent;
            color: var(--primary);
            font-weight: 700;
            box-shadow: none;
        }

        .link-button:hover {
            background: transparent;
            text-decoration: underline;
        }

        .errors {
            margin: 0 0 18px;
            padding: 12px 14px;
            border: 1px solid #fecdca;
            border-radius: 8px;
            background: #fffbfa;
            color: var(--danger);
        }

        .errors ul {
            margin: 0;
            padding-left: 18px;
        }

        .success {
            margin: 0 0 18px;
            padding: 12px 14px;
            border: 1px solid #abefc6;
            border-radius: 8px;
            background: #f6fef9;
            color: #067647;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .meta {
            border: 1px solid #e5dce2;
            border-radius: 10px;
            padding: 18px;
            background:
                linear-gradient(180deg, #fff 0, var(--surface-soft) 100%);
            box-shadow: 0 4px 14px rgba(16, 24, 40, 0.04);
        }

        .meta strong {
            display: block;
            margin-bottom: 4px;
            color: #344054;
        }

        .stack {
            display: grid;
            gap: 22px;
        }

        .section-title {
            margin: 0 0 10px;
            font-size: 22px;
            line-height: 1.3;
        }

        .form-actions {
            margin-top: 18px;
        }

        .form-footer {
            margin: 18px 0 0;
            padding-top: 18px;
            border-top: 1px solid #e4e7ec;
            color: var(--muted);
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .split-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .submit-form {
            margin-top: 12px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            box-shadow: var(--soft-shadow);
        }

        .form-table-wrap {
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f6eef3;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: .04em;
        }

        tbody tr:hover td {
            background: #fbfdff;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        td input,
        td select {
            min-width: 160px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            border-radius: 999px;
            padding: 3px 10px;
            background: linear-gradient(180deg, #fff8e8 0, var(--accent-soft) 100%);
            color: #8a5711;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #f3d6a4;
        }

        .panel > div:not(.meta-grid):not(.errors):not(.success):not(.page-kicker) {
            border-top: 1px solid #e4e7ec;
            padding-top: 20px;
        }

        .panel > div.meta-grid,
        .panel > div.page-kicker {
            border-top: 0;
            padding-top: 0;
        }

        .auth-panel > div,
        .auth-panel > form {
            border-top: 0;
            padding-top: 0;
        }

        .shell {
            display: grid;
            grid-template-columns: 292px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 26px 18px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.07) 0, transparent 34%),
                linear-gradient(160deg, #612039 0, #3a1527 58%, #24101b 100%);
            color: #f3dce5;
            box-shadow: 12px 0 30px rgba(44, 24, 36, 0.18);
        }

        .sidebar .brand {
            color: #fff;
            margin-bottom: 28px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.06);
        }

        .sidebar .brand::before {
            background: linear-gradient(180deg, #f0bd54 0, #c9821f 100%);
            color: #32131f;
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.22), inset 0 -4px 0 rgba(0, 0, 0, 0.13);
        }

        .side-label {
            margin: 18px 12px 8px;
            color: #e0adbf;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .side-nav {
            display: grid;
            gap: 8px;
        }

        .side-nav a,
        .side-nav button {
            width: 100%;
            justify-content: flex-start;
            min-height: 42px;
            border-radius: 10px;
            padding: 11px 13px;
            background: transparent;
            color: #f3dce5;
            box-shadow: none;
            font-weight: 700;
        }

        .side-nav a:hover,
        .side-nav button:hover,
        .side-nav .active {
            background: rgba(255, 255, 255, 0.13);
            color: #fff;
            text-decoration: none;
            box-shadow: inset 3px 0 0 #e4a43a;
        }

        .side-card {
            margin-top: auto;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            padding: 16px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.10) 0, rgba(255, 255, 255, 0.06) 100%);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
        }

        .side-card strong {
            display: block;
            color: #fff;
            margin-bottom: 3px;
        }

        .side-card span {
            display: block;
            color: #e8c1cf;
            font-size: 13px;
        }

        .main-area {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .pagebar {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 34px;
            background: rgba(255, 255, 255, 0.82);
            border-bottom: 1px solid rgba(208, 213, 221, 0.84);
            backdrop-filter: blur(14px);
        }

        .pagebar-title {
            margin: 0;
            color: #243642;
            font-weight: 800;
            letter-spacing: .01em;
        }

        .pagebar-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
        }

        .content {
            width: min(1220px, calc(100% - 56px));
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .compact-toolbar {
            gap: 8px;
        }

        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .row-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        .signature-box {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 18px;
            background: linear-gradient(180deg, #fff 0, #fbfcfd 100%);
            box-shadow: var(--soft-shadow);
        }

        .signature-box span {
            display: block;
            margin-bottom: 26px;
            color: var(--muted);
            font-weight: 700;
        }

        .signature-box strong {
            display: block;
            min-height: 28px;
            border-bottom: 1px solid var(--ink);
            text-align: center;
        }

        .signature-box small {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            text-align: center;
        }

        .workflow-card {
            border: 1px solid #d8e2df;
            border-radius: 12px;
            padding: 24px;
            background:
                linear-gradient(180deg, #ffffff 0, #fbfcfd 100%);
            box-shadow: var(--soft-shadow);
        }

        .highlight-card {
            border-color: rgba(122, 31, 61, 0.34);
            background:
                linear-gradient(180deg, #fff7fa 0, #ffffff 48%);
            box-shadow: 0 18px 42px rgba(122, 31, 61, 0.10);
        }

        .workflow-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .step-pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            margin-bottom: 8px;
            border-radius: 999px;
            padding: 3px 10px;
            background: #fae8ef;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            border: 1px solid rgba(122, 31, 61, 0.14);
        }

        @media (max-width: 640px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                height: auto;
                padding: 18px 16px;
            }

            .side-card {
                margin-top: 18px;
            }

            .pagebar {
                align-items: flex-start;
                flex-direction: column;
                padding: 18px 16px;
            }

            .content {
                width: min(100% - 24px, 1180px);
                padding: 28px 0;
            }

            .panel {
                padding: 22px;
            }

            .page-head {
                display: grid;
            }

            .workflow-head {
                display: grid;
            }

            .auth-panel::before {
                margin: -22px -22px 22px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">RBIM</a>

            <div class="side-label">Workspace</div>
            <nav class="side-nav" aria-label="Primary">
                @auth
                    @if (auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))
                        <a class="@if(request()->routeIs('dashboard.municipal')) active @endif" href="{{ route('dashboard.municipal') }}">Municipal Review</a>
                    @endif
                    @if (auth()->user()->hasAnyRole([App\Models\User::ROLE_MUNICIPAL_LGU, App\Models\User::ROLE_BARANGAY]))
                        <a class="@if(request()->routeIs('dashboard.barangay')) active @endif" href="{{ route('dashboard.barangay') }}">Barangay Records</a>
                    @endif
                    <a class="@if(request()->routeIs('dashboard.resident')) active @endif" href="{{ route('dashboard.resident') }}">Resident Services</a>
                @else
                    <a class="@if(request()->routeIs('login')) active @endif" href="{{ route('login') }}">Login</a>
                    <a class="@if(request()->routeIs('register')) active @endif" href="{{ route('register') }}">Register</a>
                @endauth
            </nav>

            @auth
                <div class="side-label">Session</div>
                <nav class="side-nav" aria-label="Session">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Logout</button>
                    </form>
                </nav>

                <div class="side-card">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->roleLabel() }}</span>
                </div>
            @else
                <div class="side-card">
                    <strong>Registry Portal</strong>
                    <span>Resident records, barangay updates, and municipal review in one workspace.</span>
                </div>
            @endauth
        </aside>

        <div class="main-area">
            <header class="pagebar">
                <p class="pagebar-title">Registry of Barangay Inhabitants Management</p>
                <div class="pagebar-meta">
                    @auth
                        <span>{{ auth()->user()->roleLabel() }}</span>
                    @else
                        <span>Secure local access</span>
                    @endauth
                </div>
            </header>

            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
