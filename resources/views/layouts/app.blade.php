<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BIC Training Dashboard') - BIC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bic-blue: #0b3d91; }
        .topbar { background: var(--bic-blue); color: #fff; height: 52px; }
        @media (min-width: 992px) {
            .sidebar { width: 230px; flex-shrink: 0; min-height: calc(100vh - 52px); }
        }
        .sidebar .nav-link { color: #1b2a41; border-radius: .375rem; font-size: .9rem; }
        .sidebar .nav-link:hover { background: #f1f5fb; }
        .sidebar .nav-link.active { background: #e7efff; color: var(--bic-blue); font-weight: 600; }
        .sidebar .nav-link.disabled { color: #9aa5b5; cursor: default; }
        .card { border-color: #d6dde8; }
    </style>
    @stack('styles')
</head>
<body class="bg-light">

<header class="topbar d-flex align-items-center px-3">
    <button class="btn btn-sm btn-outline-light d-lg-none me-2" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">&#9776;</button>
    <span class="fw-semibold">BIC TRAINING DASHBOARD</span>
</header>

<div class="d-flex">
    <aside id="sidebar" class="sidebar offcanvas-lg offcanvas-start bg-white border-end" tabindex="-1">
        <div class="offcanvas-header d-lg-none">
            <h6 class="offcanvas-title">Menu</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column w-100 p-lg-2">
                <a class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}"
                   href="{{ route('dashboard.index') }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}"
                   href="{{ route('import.index') }}">Import Excel</a>
                <span class="nav-link disabled">Training Data <span class="badge text-bg-light border">Segera</span></span>
                <span class="nav-link disabled">Reports <span class="badge text-bg-light border">Segera</span></span>
            </nav>
        </div>
    </aside>

    <main class="flex-grow-1 p-3 p-lg-4" style="min-width: 0;">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any() && !request()->routeIs('import.index'))
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>