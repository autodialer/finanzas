<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — Finanzas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-size: 0.9rem;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #1e293b;
        }

        .sidebar a {
            color: #94a3b8;
            text-decoration: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: #fff;
            background-color: #334155;
            border-radius: 6px;
        }

        .sidebar .nav-header {
            color: #475569;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-content {
            background-color: #f8fafc;
            min-height: 100vh;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar px-3 py-4 d-none d-md-block">
                <h5 class="text-white mb-4 fw-bold">
                    <i class="bi bi-graph-up-arrow me-2"></i>Finanzas
                </h5>

                <nav class="nav flex-column mb-3">
                    <a href="{{ route('dashboard') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </nav>

                <div class="nav-header mb-2">Principal</div>
                <nav class="nav flex-column mb-3">
                    <a href="{{ route('ingresos.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('ingresos*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-down-circle me-2"></i>Ingresos
                    </a>
                    <a href="{{ route('gastos.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('gastos*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-up-circle me-2"></i>Gastos
                    </a>
                    <a href="{{ route('reportes.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('reportes*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart me-2"></i>Reportes
                    </a>
                </nav>

                <div class="nav-header mb-2">Nómina</div>
                <nav class="nav flex-column mb-3">
                    <a href="{{ route('nominas.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('nominas*') ? 'active' : '' }}">
                        <i class="bi bi-cash-stack me-2"></i>Nóminas
                    </a>
                    <a href="{{ route('empleados.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('empleados*') ? 'active' : '' }}">
                        <i class="bi bi-person-vcard me-2"></i>Empleados
                    </a>
                </nav>

                <div class="nav-header mb-2">Catálogos</div>
                <nav class="nav flex-column mb-3">
                    <a href="{{ route('negocios.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('negocios*') ? 'active' : '' }}">
                        <i class="bi bi-building me-2"></i>Negocios
                    </a>
                    <a href="{{ route('areas.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('areas*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3 me-2"></i>Áreas
                    </a>
                    <a href="{{ route('categorias.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('categorias*') ? 'active' : '' }}">
                        <i class="bi bi-tag me-2"></i>Categorías
                    </a>
                    <a href="{{ route('clientes.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('clientes*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i>Clientes
                    </a>
                    <a href="{{ route('vendedores.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('vendedores*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge me-2"></i>Vendedores
                    </a>
                    <a href="{{ route('proveedores.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('proveedores*') ? 'active' : '' }}">
                        <i class="bi bi-truck me-2"></i>Proveedores
                    </a>
                    <a href="{{ route('bancos.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('bancos*') ? 'active' : '' }}">
                        <i class="bi bi-bank me-2"></i>Bancos
                    </a>
                    <a href="{{ route('cuentas.index') }}" class="nav-link px-2 py-1 mb-1 {{ request()->routeIs('cuentas*') ? 'active' : '' }}">
                        <i class="bi bi-credit-card me-2"></i>Cuentas
                    </a>
                </nav>

                <div class="mt-auto pt-3 border-top border-secondary">
                    <p class="text-muted small mb-1 px-2">
                        <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}
                    </p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

            <!-- Contenido principal -->
            <div class="col-md-10 main-content p-4">
                <!-- Navbar móvil -->
                <nav class="navbar navbar-dark navbar-expand-md d-md-none bg-dark mb-3 rounded">
                    <div class="container-fluid">
                        <span class="navbar-brand text-white">Finanzas</span>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuMovil">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="menuMovil">
                            <ul class="navbar-nav">
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('ingresos.index') }}">Ingresos</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('gastos.index') }}">Gastos</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('reportes.index') }}">Reportes</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('nominas.index') }}">Nóminas</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('empleados.index') }}">Empleados</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('negocios.index') }}">Negocios</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('areas.index') }}">Áreas</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('categorias.index') }}">Categorías</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('clientes.index') }}">Clientes</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('vendedores.index') }}">Vendedores</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('proveedores.index') }}">Proveedores</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('bancos.index') }}">Bancos</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="{{ route('cuentas.index') }}">Cuentas</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                @if(session('exito'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('exito') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @yield('contenido')
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>