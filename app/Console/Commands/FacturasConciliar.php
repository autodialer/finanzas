<?php

namespace App\Console\Commands;

use App\Models\Contribuyente;
use App\Services\Sat\ConciliadorFacturas;
use Illuminate\Console\Command;

class FacturasConciliar extends Command
{
    protected $signature = 'facturas:conciliar {--rfc= : Solo este RFC}';
    protected $description = 'Vincula automáticamente los gastos con sus facturas recibidas';

    public function handle(ConciliadorFacturas $conciliador): int
    {
        Contribuyente::query()
            ->when($this->option('rfc'), fn ($q, $rfc) => $q->where('rfc', mb_strtoupper($rfc)))
            ->get()
            ->each(fn ($c) => $this->line("{$c->rfc}: " . $conciliador->conciliar($c) . ' factura(s) vinculadas.'));

        return self::SUCCESS;
    }
}
