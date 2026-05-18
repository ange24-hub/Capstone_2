<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'RBIM') }}</title>
    @stack('head')
    <style>
        :root {
            color-scheme: light;
            --ink: #172033;
            --muted: #667085;
            --line: #d8dee8;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --band: #eef3f8;
            --primary: #2457a6;
            --primary-strong: #183d78;
            --accent: #0f8b6f;
            --accent-soft: #e8f7f2;
            --info: #6b4fd8;
            --danger: #b42318;
            --shadow: 0 18px 44px rgba(16, 24, 40, 0.08);
            --soft-shadow: 0 8px 20px rgba(16, 24, 40, 0.06);
            --sidebar-width: 248px;
            --workspace-width: 1360px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                linear-gradient(90deg, rgba(36, 87, 166, 0.035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(15, 139, 111, 0.035) 1px, transparent 1px),
                radial-gradient(circle at top right, rgba(36, 87, 166, 0.12), transparent 32%),
                linear-gradient(180deg, #f5f8fb 0, var(--band) 340px, #f8fafc 100%);
            background-size: 44px 44px, 44px 44px, auto, auto;
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, Helvetica, sans-serif;
            line-height: 1.5;
            overflow-x: auto;
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
            font-weight: 900;
            letter-spacing: 0;
        }

        .brand::before {
            content: "R";
            display: inline-grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: var(--primary);
            color: #fff;
            box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.12), 0 12px 26px rgba(36, 87, 166, 0.18);
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
                linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0, rgba(255, 255, 255, 0.94) 100%);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: visible;
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--info));
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
            font-size: 32px;
            line-height: 1.2;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 18px;
            color: var(--muted);
        }

        .page-kicker {
            margin: 0 0 8px;
            color: var(--primary);
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
            border-radius: 10px;
            padding: 11px 13px;
            font: inherit;
            background: #fff;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 3px solid rgba(36, 87, 166, 0.16);
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
            border-radius: 10px;
            padding: 10px 18px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(36, 87, 166, 0.18);
            transition: transform .14s ease, box-shadow .14s ease, background .14s ease;
        }

        .primary-block {
            width: 100%;
        }

        .button:hover,
        button:hover {
            background: var(--primary-strong);
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(36, 87, 166, 0.24);
        }

        .secondary-button {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--primary);
            box-shadow: none;
        }

        .secondary-button:hover {
            background: #f4f7fb;
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
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px;
            background:
                linear-gradient(180deg, #fff 0, #f9fbff 100%);
            box-shadow: 0 4px 14px rgba(16, 24, 40, 0.04);
            position: relative;
            overflow: hidden;
        }

        .meta::after {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
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
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--soft-shadow);
        }

        .form-table-wrap {
            margin-top: 18px;
        }

        table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5fb;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: .06em;
        }

        tbody tr:hover td {
            background: #f8fbff;
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
            background: var(--accent-soft);
            color: #086653;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #bfe8dc;
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
            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            min-height: 100vh;
            min-width: calc(var(--sidebar-width) + var(--workspace-width));
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 16px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92)),
                linear-gradient(135deg, rgba(36, 87, 166, 0.08), transparent 45%);
            color: var(--ink);
            border-right: 1px solid rgba(216, 222, 232, 0.92);
            box-shadow: 12px 0 30px rgba(16, 24, 40, 0.04);
            backdrop-filter: blur(14px);
        }

        .sidebar .brand {
            color: var(--ink);
            margin-bottom: 28px;
            padding: 10px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: var(--soft-shadow);
        }

        .sidebar .brand::before {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 18px rgba(36, 87, 166, 0.22);
        }

        .side-label {
            margin: 18px 12px 8px;
            color: #7a8699;
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
            color: #334155;
            box-shadow: none;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .side-nav a:hover,
        .side-nav button:hover,
        .side-nav .active {
            background: #fff;
            color: var(--primary-strong);
            text-decoration: none;
            border-color: #d9e6f7;
            box-shadow: 0 10px 24px rgba(36, 87, 166, 0.08), inset 4px 0 0 var(--primary);
        }

        .side-card {
            margin-top: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px;
            background:
                linear-gradient(180deg, #ffffff 0, #f7fbff 100%);
            box-shadow: var(--soft-shadow);
            position: relative;
            overflow: hidden;
        }

        .side-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: linear-gradient(180deg, var(--primary), var(--accent), var(--info));
        }

        .side-card strong {
            display: block;
            color: var(--ink);
            margin-bottom: 3px;
        }

        .side-card span {
            display: block;
            color: var(--muted);
            font-size: 13px;
        }

        .main-area {
            min-width: var(--workspace-width);
            display: flex;
            flex-direction: column;
            overflow-x: visible;
        }

        .pagebar {
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 28px;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.86));
            border-bottom: 1px solid rgba(208, 213, 221, 0.84);
            backdrop-filter: blur(14px);
        }

        .pagebar-title {
            margin: 0 0 3px;
            color: #243642;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: .01em;
        }

        .pagebar-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
        }

        .pagebar-kicker {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            border: 1px solid #d9e6f7;
            border-radius: 999px;
            padding: 6px 12px;
            background: #fff;
            color: var(--primary-strong);
            font-weight: 800;
            box-shadow: var(--soft-shadow);
        }

        .content {
            width: calc(var(--workspace-width) - 36px);
            max-width: none;
            min-width: 0;
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
            padding-bottom: 6px;
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
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            background:
                linear-gradient(180deg, #ffffff 0, #fbfdff 100%);
            box-shadow: var(--soft-shadow);
        }

        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr);
            gap: 22px;
            align-items: stretch;
            padding: 28px;
            border: 1px solid rgba(36, 87, 166, 0.14);
            border-radius: 20px;
            background:
                linear-gradient(135deg, rgba(36, 87, 166, 0.96) 0, rgba(18, 61, 122, 0.96) 52%, rgba(15, 139, 111, 0.94) 100%);
            color: #fff;
            box-shadow: 0 24px 60px rgba(24, 61, 120, 0.22);
            overflow: hidden;
            position: relative;
            min-width: 0;
        }

        .dashboard-hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -120px auto;
            width: 320px;
            height: 320px;
            border: 48px solid rgba(255, 255, 255, 0.10);
            border-radius: 50%;
        }

        .dashboard-hero h1,
        .dashboard-hero p {
            color: #fff;
            position: relative;
            z-index: 1;
        }

        .dashboard-hero h1 {
            font-size: clamp(30px, 3vw, 48px);
            max-width: 820px;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .hero-actions .button,
        .hero-actions button {
            background: #fff;
            color: var(--primary-strong);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.14);
        }

        .hero-side {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 12px;
        }

        .hero-mini-card {
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }

        .hero-mini-card strong {
            display: block;
            font-size: 28px;
            line-height: 1.1;
        }

        .hero-mini-card span {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 13px;
            font-weight: 700;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 16px;
        }

        .stat-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 20px;
            background:
                linear-gradient(180deg, #fff 0, #f8fbff 100%);
            box-shadow: var(--soft-shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .stat-label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .stat-value {
            display: block;
            margin-top: 10px;
            color: #14213d;
            font-size: 34px;
            line-height: 1;
            font-weight: 900;
        }

        .stat-note {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(360px, .42fr);
            gap: 22px;
            align-items: start;
            min-width: 0;
        }

        .timeline-list {
            display: grid;
            gap: 12px;
        }

        .timeline-item {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(16, 24, 40, 0.04);
        }

        .timeline-item strong {
            display: block;
            color: var(--ink);
        }

        .timeline-item span {
            display: block;
            color: var(--muted);
            font-size: 13px;
        }

        .highlight-card {
            border-color: rgba(36, 87, 166, 0.28);
            background: #f8fbff;
            box-shadow: 0 18px 42px rgba(36, 87, 166, 0.08);
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
            background: #eaf1fb;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            border: 1px solid rgba(36, 87, 166, 0.14);
        }

        .section-title {
            color: #1b2a41;
            font-weight: 900;
        }

        .content {
            padding-top: 40px;
        }

        .panel.stack {
            gap: 26px;
        }

        .map-shell {
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--soft-shadow);
        }

        .large-map,
        .picker-map {
            width: 100%;
            background: #e8f0ed;
        }

        .large-map {
            height: min(68vh, 680px);
            min-height: 460px;
        }

        .picker-map {
            height: 360px;
            margin-top: 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .nav-mark {
            display: inline-grid;
            place-items: center;
            width: 26px;
            height: 26px;
            margin-right: 8px;
            border-radius: 9px;
            background: #eef4fb;
            color: var(--primary);
            font-size: 11px;
            font-weight: 900;
        }

        .side-nav .active .nav-mark,
        .side-nav a:hover .nav-mark,
        .side-nav button:hover .nav-mark {
            background: var(--primary);
            color: #fff;
        }

        .leaflet-container {
            font: inherit;
        }

        @media (max-width: 1180px) {
            :root {
                --sidebar-width: 228px;
            }

            .dashboard-hero,
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }

            .content {
                width: calc(var(--workspace-width) - 24px);
            }
        }

        @media (max-width: 640px) {
            body {
                overflow-x: hidden;
            }

            .shell {
                grid-template-columns: 1fr;
                min-width: 0;
            }

            .main-area {
                min-width: 0;
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

            .dashboard-hero,
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: 1fr;
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
                        <a class="@if(request()->routeIs('dashboard.municipal')) active @endif" href="{{ route('dashboard.municipal') }}"><span class="nav-mark">MR</span>Municipal Review</a>
                    @endif
                    @if (auth()->user()->hasAnyRole([App\Models\User::ROLE_MUNICIPAL_LGU, App\Models\User::ROLE_BARANGAY]))
                        <a class="@if(request()->routeIs('dashboard.barangay')) active @endif" href="{{ route('dashboard.barangay') }}"><span class="nav-mark">BR</span>Barangay Records</a>
                        <a class="@if(request()->routeIs('registry.*')) active @endif" href="{{ route('registry.index') }}"><span class="nav-mark">CR</span>Central Registry</a>
                        <a class="@if(request()->routeIs('migration.dashboard')) active @endif" href="{{ route('migration.dashboard') }}"><span class="nav-mark">MM</span>Migration Monitor</a>
                        <a class="@if(request()->routeIs('spatial.index')) active @endif" href="{{ route('spatial.index') }}"><span class="nav-mark">SM</span>Spatial Map</a>
                    @endif
                    <a class="@if(request()->routeIs('dashboard.resident')) active @endif" href="{{ route('dashboard.resident') }}"><span class="nav-mark">RS</span>Resident Services</a>
                @else
                    <a class="@if(request()->routeIs('login')) active @endif" href="{{ route('login') }}"><span class="nav-mark">IN</span>Login</a>
                    <a class="@if(request()->routeIs('register')) active @endif" href="{{ route('register') }}"><span class="nav-mark">UP</span>Register</a>
                @endauth
            </nav>

            @auth
                <div class="side-label">Session</div>
                <nav class="side-nav" aria-label="Session">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><span class="nav-mark">EX</span>Logout</button>
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
                <div>
                    <p class="pagebar-title">Registry of Barangay Inhabitants Management</p>
                    <p class="pagebar-kicker">Municipal population intelligence, household mapping, and migration operations</p>
                </div>
                <div class="pagebar-meta">
                    @auth
                        <span class="status-chip">{{ auth()->user()->roleLabel() }}</span>
                        <span>{{ now()->format('M d, Y') }}</span>
                    @else
                        <span class="status-chip">Secure local access</span>
                    @endauth
                </div>
            </header>

            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
