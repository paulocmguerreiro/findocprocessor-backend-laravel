<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Configura o audit trail (spatie/laravel-activitylog) com a política
 * canónica do projecto: regista os campos fillable, apenas quando há
 * alterações reais e nunca submete logs vazios.
 *
 * Modelos com campos sensíveis sobrepõem atributosExcluidosDaActividade().
 */
trait RegistaActividade
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept($this->atributosExcluidosDaActividade())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Campos fillable a excluir do registo de actividade (ex.: dados sensíveis).
     *
     * @return list<string>
     */
    protected function atributosExcluidosDaActividade(): array
    {
        return [];
    }
}
