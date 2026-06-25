<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Activity::query()->delete());

it('regista actividade created ao criar', function (): void {
    $documento = Documento::factory()->create();

    $actividade = Activity::query()->where('subject_type', Documento::class)->first();

    expect(Activity::query()->where('subject_type', Documento::class)->count())->toBe(1)
        ->and($actividade->event)->toBe('created')
        ->and($actividade->subject_id)->toBe($documento->id);
});

it('regista o campo status (não sensível)', function (): void {
    Documento::factory()->processado()->create();

    $actividade = Activity::query()->where('subject_type', Documento::class)->first();

    expect($actividade->properties->get('attributes'))
        ->toHaveKey('status', EstadoDocumento::Processado->value);
});

it('nunca regista os campos sensíveis hash_sha256, disco_storage e nome_ficheiro_storage', function (): void {
    $documento = Documento::factory()->processado()->create();

    $atributosCriacao = $documento->fresh()->activities->first()->properties->get('attributes');
    expect($atributosCriacao)
        ->not->toHaveKey('hash_sha256')
        ->not->toHaveKey('disco_storage')
        ->not->toHaveKey('nome_ficheiro_storage');

    Activity::query()->delete();
    $documento->update([
        'status' => EstadoDocumento::Erro,
        'disco_storage' => 'erro',
    ]);

    $atributosUpdate = Activity::query()->first()->properties->get('attributes');
    expect($atributosUpdate)
        ->toHaveKey('status', EstadoDocumento::Erro->value)
        ->not->toHaveKey('disco_storage');
});
