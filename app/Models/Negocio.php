<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Negocio extends Model
{
    protected $table = 'negocios';
    protected $fillable = ['nombre', 'descripcion'];

    public function areas()
    {
        return $this->hasMany(Area::class);
    }
    public function cuentas()
    {
        return $this->hasMany(Cuenta::class);
    }
    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }
    public function proveedores()
    {
        return $this->hasMany(Proveedor::class);
    }
    public function ingresos()
    {
        return $this->hasMany(Ingreso::class);
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}
