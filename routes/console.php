<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Facturas del SAT: pedir las nuevas cada mañana y revisar cada hora si el SAT ya las tiene listas
Schedule::command('sat:descargar')->dailyAt('05:10')->withoutOverlapping();
Schedule::command('sat:verificar')->hourlyAt(17)->withoutOverlapping();
