<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\LoginController;
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
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\PeriodoNominaController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::resource('negocios', NegocioController::class);
    Route::resource('areas', AreaController::class);
    Route::resource('categorias', CategoriaController::class);
    Route::resource('vendedores', VendedorController::class);
    Route::resource('bancos', BancoController::class);
    Route::resource('cuentas', CuentaController::class);
    Route::resource('proveedores', ProveedorController::class);
    Route::get('proveedores-importar', [ProveedorController::class, 'importForm'])->name('proveedores.import.form');
    Route::post('proveedores-importar', [ProveedorController::class, 'import'])->name('proveedores.import');

    Route::resource('clientes', ClienteController::class);
    Route::get('clientes-importar', [ClienteController::class, 'importForm'])->name('clientes.import.form');
    Route::post('clientes-importar', [ClienteController::class, 'import'])->name('clientes.import');
    Route::resource('ingresos', IngresoController::class);
    Route::resource('gastos', GastoController::class);

    Route::resource('empleados', EmpleadoController::class);
    Route::resource('nominas', PeriodoNominaController::class)->except(['edit', 'update']);
    Route::patch('nominas/{nomina}/cerrar', [PeriodoNominaController::class, 'cerrar'])->name('nominas.cerrar');
    Route::patch('nominas/linea/{linea}', [PeriodoNominaController::class, 'updateNomina'])->name('nominas.linea.update');
    Route::post('nominas/linea/{linea}/recalcular', [PeriodoNominaController::class, 'recalcularLinea'])->name('nominas.linea.recalcular');
});
