<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .navbar-brand { font-weight: 600; letter-spacing: .3px; }
        .navbar .nav-link.active { color: #fff; border-bottom: 2px solid #0d6efd; }
        .stat-card, .card { border: 0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .stat-icon { font-size: 2rem; opacity: .85; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <i class="bi bi-calendar-check"></i> Smart Attendance
            </a>

            @auth
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                               href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('students.*') ? 'active' : '' }}"
                               href="{{ route('students.index') }}"><i class="bi bi-people"></i> Students</a>
                        </li>
                        @if (auth()->user()->isClassTeacher())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('subjects.*') ? 'active' : '' }}"
                                   href="{{ route('subjects.index') }}"><i class="bi bi-journal-bookmark"></i> Subjects</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('teachers.*') ? 'active' : '' }}"
                                   href="{{ route('teachers.index') }}"><i class="bi bi-person-badge"></i> Teachers</a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('attendance.mark') ? 'active' : '' }}"
                               href="{{ route('attendance.mark') }}"><i class="bi bi-check2-square"></i> Mark Attendance</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('attendance.index') ? 'active' : '' }}"
                               href="{{ route('attendance.index') }}"><i class="bi bi-clock-history"></i> History</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                               href="{{ route('reports.attendance') }}"><i class="bi bi-bar-chart"></i> Reports</a>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2 text-white">
                        <span class="small">
                            {{ auth()->user()->name }}
                            <span class="badge bg-secondary">{{ auth()->user()->isClassTeacher() ? 'Class Teacher' : 'Subject Teacher' }}</span>
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-light">Logout</button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>

    <div class="container pb-5">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <footer class="text-center text-muted small pb-4">
        Smart Attendance &copy; {{ date('Y') }} &middot; Built with Laravel
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
