<?php

declare(strict_types=1);

use App\Features\Role\Criar\CriarRoleDto;

it('lança InvalidArgumentException quando nome é vazio', function (): void {
    expect(fn (): CriarRoleDto => new CriarRoleDto(nome: '   ', permissoes: []))
        ->toThrow(InvalidArgumentException::class, 'nome não pode ser vazio.');
});

it('cria DTO válido com nome e permissões', function (): void {
    $dto = new CriarRoleDto(nome: 'editor', permissoes: ['entidades.ver']);

    expect($dto->nome)->toBe('editor')
        ->and($dto->permissoes)->toBe(['entidades.ver']);
});
