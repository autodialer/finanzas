<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\GastoController;

use App\Http\Controllers\DashboardController;


Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

use App\Http\Controllers\ReporteController;

Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');

Route::resource('negocios', NegocioController::class);
Route::resource('areas', AreaController::class);
Route::resource('categorias', CategoriaController::class);
Route::resource('vendedores', VendedorController::class);
Route::resource('bancos', BancoController::class);
Route::resource('cuentas', CuentaController::class);
Route::resource('proveedores', ProveedorController::class);
Route::resource('clientes', ClienteController::class);
Route::resource('ingresos', IngresoController::class);
Route::resource('gastos', GastoController::class);
