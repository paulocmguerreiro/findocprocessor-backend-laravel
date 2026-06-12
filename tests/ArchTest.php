<?php

declare(strict_types=1);
use App\Http\Controllers\Controller;

arch()->preset()->laravel()->ignoring(['App\Shared\Enums', 'App\Features']);
arch()->preset()->security();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->toBeFinal()
    ->ignoring(Controller::class);

arch('actions are final')
    ->expect('App\Features')
    ->toBeFinal();
