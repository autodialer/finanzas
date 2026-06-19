<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\PeriodoNominaController;
use App\Http\Controllers\TraspasoController;
use App\Http\Controllers\PerfilController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Recuperación de contraseña
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/cuentas', [ReporteController::class, 'cuentas'])->name('reportes.cuentas');
    Route::resource('negocios', NegocioController::class);
    Route::resource('categorias', CategoriaController::class);
    Route::resource('vendedores', VendedorController::class)->parameters(['vendedores' => 'vendedor']);
    Route::resource('bancos', BancoController::class);
    Route::resource('cuentas', CuentaController::class);
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
    Route::get('proveedores-importar', [ProveedorController::class, 'importForm'])->name('proveedores.import.form');
    Route::post('proveedores-importar', [ProveedorController::class, 'import'])->name('proveedores.import');

    Route::resource('clientes', ClienteController::class);
    Route::get('clientes-importar', [ClienteController::class, 'importForm'])->name('clientes.import.form');
    Route::post('clientes-importar', [ClienteController::class, 'import'])->name('clientes.import');
    Route::resource('ingresos', IngresoController::class);
    Route::resource('gastos', GastoController::class);
    Route::resource('traspasos', TraspasoController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::get('perfil/password', [PerfilController::class, 'editPassword'])->name('perfil.password.edit');
    Route::put('perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password.update');

    Route::resource('empleados', EmpleadoController::class);
    Route::resource('nominas', PeriodoNominaController::class)->except(['edit', 'update']);
    Route::patch('nominas/{nomina}/cerrar', [PeriodoNominaController::class, 'cerrar'])->name('nominas.cerrar');
    Route::patch('nominas/linea/{linea}', [PeriodoNominaController::class, 'updateNomina'])->name('nominas.linea.update');
    Route::post('nominas/linea/{linea}/recalcular', [PeriodoNominaController::class, 'recalcularLinea'])->name('nominas.linea.recalcular');
});

// Gestión de usuarios (solo admin)
Route::middleware(['auth', 'es_admin'])->group(function () {
    Route::resource('usuarios', UserController::class);
    Route::post('usuarios/{usuario}/reset-link', [UserController::class, 'generarEnlaceReset'])->name('usuarios.reset-link');
});
