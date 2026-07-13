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
