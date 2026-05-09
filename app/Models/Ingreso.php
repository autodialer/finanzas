<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'ingresos';
    protected $fillable = ['negocio_id', 'area_id', 'categoria_id', 'cliente_id', 'cuenta_id', 'monto', 'fecha', 'concepto', 'forma_pago', 'notas'];

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
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
}
