<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendedor extends Model
{
    protected $table = 'vendedores';
    protected $fillable = ['nombre', 'telefono', 'email'];

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }
}
