<?php

declare(strict_types=1);

use App\Jobs\ReconciliarFicheirosJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove tokens Sanctum expirados há mais de 24h (expiração global: 8h).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Reconciliação ficheiro↔BD de documentos presos no pipeline (#90).
Schedule::job(new ReconciliarFicheirosJob)->everyFiveMinutes()->onOneServer()->name('reconciliar-ficheiros');

// Pipeline de extracção (#111): um comando por etapa activa, cada um `withoutOverlapping()`
// (o mesmo comando nunca se sobrepõe) — a exclusão por documento fica no lease + lockForUpdate.
// Etapas leves em lote a cada minuto; a IA cloud a cada 5 minutos.
Schedule::command('extracao:run-scan')->everyMinute()->withoutOverlapping();
Schedule::command('extracao:run-parser')->everyMinute()->withoutOverlapping();
Schedule::command('extracao:run-tesseract')->everyMinute()->withoutOverlapping();
Schedule::command('extracao:run-ia-local')->everyMinute()->withoutOverlapping();
Schedule::command('extracao:run-ia-cloud')->everyFiveMinutes()->withoutOverlapping();
