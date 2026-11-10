<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = ['nombre', 'tipo', 'requiere_factura'];
    protected $casts = ['requiere_factura' => 'boolean'];

    public function ingresos()
    {
        return $this->hasMany(Ingreso::class);
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}
