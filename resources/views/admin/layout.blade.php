<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Kolabri</title>
    <link rel="stylesheet" href="{{ asset('admin/admin.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* Nav subsection label (e.g. LMIV under Katalog) */
        .nav-subsection {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--c-muted, #94a3b8);
            padding: 8px 16px 2px 24px;
        }
        /* Indented sub-links */
        .nav .nav-sub {
            padding-left: 28px !important;
        }
        /* Aktionen-Dropdown (details/summary pattern — JS-free) */
        .actions-dropdown {
            position: relative;
            display: inline-block;
        }
        .actions-dropdown > summary {
            list-style: none;
            cursor: pointer;
            user-select: none;
        }
        .actions-dropdown > summary::-webkit-details-marker { display: none; }
        /* Invisible overlay closes dropdown on outside click */
        .actions-dropdown[open] > summary::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 9;
        }
        .actions-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            z-index: 10;
            background: #fff;
            border: 1px solid var(--c-border, #e2e8f0);
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            min-width: 200px;
            padding: 4px 0;
            white-space: nowrap;
        }
        .actions-menu a {
            display: block;
            padding: 8px 16px;
            font-size: .875rem;
            color: inherit;
            text-decoration: none;
        }
        .actions-menu a:hover {
            background: var(--c-surface, #f8fafc);
        }
        .actions-menu-divider {
            border: none;
            border-top: 1px solid var(--c-border, #e2e8f0);
            margin: 4px 0;
        }
    </style>
    @stack('head')
</head>
<body>
<div class="layout">

    {{-- ── Sidebar Navigation ── --}}
    @include('admin._partials.sidebar')

    {{-- ── Main Content ── --}}
    <div class="main">
        <div class="topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="topbar-actions">@yield('actions')</div>
        </div>

        <div class="content">
            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="alert alert-error">
                    <strong>Validierungsfehler:</strong>
                    <ul style="margin:6px 0 0; padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

</div>
@stack('scripts')
</body>
</html>
