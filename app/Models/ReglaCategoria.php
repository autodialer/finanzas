<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReglaCategoria extends Model
{
    protected $table = 'reglas_categoria';
    protected $fillable = ['comercio', 'tipo', 'categoria_id'];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    // Clave estable para un comercio: mayúsculas, sin signos ni espacios repetidos
    public static function normalizar(?string $comercio): string
    {
        $texto = mb_strtoupper(trim((string) $comercio));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
        $texto = preg_replace('/[^A-Z0-9Ñ ]+/u', ' ', $texto);
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $texto)), 0, 150);
    }
}
