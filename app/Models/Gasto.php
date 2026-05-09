<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';
    protected $fillable = ['negocio_id', 'area_id', 'categoria_id', 'proveedor_id', 'cuenta_id', 'monto', 'fecha', 'concepto', 'forma_pago', 'notas'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function area()
    {
        return $this->belongsTo(Area::class);
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
}
