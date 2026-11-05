<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Militaires - Gendarmerie Nationale</title>
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .alert-promotion { border-left: 5px solid #007bff; }
        .alert-formation { border-left: 5px solid #28a745; }
        .alert-retraite { border-left: 5px solid #dc3545; }
        .alert-certificat { border-left: 5px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar bg-dark">
                <div class="sidebar-sticky pt-3 text-center">
                    <!-- Logo Gendarmerie -->
                    @if(file_exists(public_path('images/logo-gendarmerie.png')))
                        <div class="mb-2">
                            <img src="{{ asset('images/logo-gendarmerie.png') }}" alt="Gendarmerie Nationale" style="max-height: 60px; width: auto;">
                        </div>
                    @endif
                    <!-- Titre Gendarmerie -->
                    <h5 class="text-white mb-4 fw-bold text-uppercase">Gendarmerie</h5>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link">
                                <i class="bi bi-speedometer2"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('militaires.index') }}" class="nav-link">
                                <i class="bi bi-people"></i> Militaires
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('alertes.index') }}" class="nav-link">
                                <i class="bi bi-bell"></i> Alertes
                                @if(isset($alertesCount) && $alertesCount > 0)
                                    <span class="badge bg-danger ms-1">{{ $alertesCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('eligibilites.index') }}" class="nav-link">
                                <i class="bi bi-check-circle"></i> Ã‰ligibilitÃ©s
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('grades.index') }}" class="nav-link">
                                <i class="bi bi-star"></i> Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('certificats.index') }}" class="nav-link">
                                <i class="bi bi-award"></i> Certificats
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link text-white">
                                    <i class="bi bi-box-arrow-right"></i> DÃ©connexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">@yield('title')</h1>
                    @yield('actions')
                </div>

                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @yield('scripts')
</body>
</html>
