<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'FinDocProcessor') }} — API</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-gray-950 text-gray-100 flex flex-col items-center justify-center p-6 antialiased">

        <div class="w-full max-w-2xl">

            {{-- Hero card --}}
            <div class="rounded-2xl border border-gray-800 bg-gray-900 p-8 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-widest text-gray-500 uppercase mb-2">Backend API</p>
                        <h1 class="text-2xl font-semibold text-white leading-tight">FinDocProcessor</h1>
                        <p class="mt-1 text-sm text-gray-400">Processamento e gestão de documentos financeiros</p>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-400">
                        <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        Em desenvolvimento
                    </span>
                </div>

                <div class="mt-6 border-t border-gray-800 pt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">

                    {{-- Card: Estado --}}
                    <div class="rounded-xl border border-gray-800 bg-gray-950 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Estado da API</p>
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-emerald-400 shadow-[0_0_6px_2px_rgba(52,211,153,.4)]"></span>
                            <span class="text-sm font-medium text-emerald-400">Online</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-600">Laravel v{{ app()->version() }}</p>
                        <p class="text-xs text-gray-600">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</p>
                    </div>

                    {{-- Card: Stack --}}
                    <div class="rounded-xl border border-gray-800 bg-gray-950 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Stack</p>
                        <ul class="space-y-1.5 text-xs text-gray-400">
                            <li class="flex items-center gap-2">
                                <span class="size-1 rounded-full bg-red-400"></span>
                                Laravel 13 · PHP 8.5
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="size-1 rounded-full bg-blue-400"></span>
                                SQLite (dev) · MySQL (prod)
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="size-1 rounded-full bg-red-500"></span>
                                Redis · Queues
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="size-1 rounded-full bg-green-400"></span>
                                Pest 4 · Larastan 9
                            </li>
                        </ul>
                    </div>

                    {{-- Card: Frontend --}}
                    <div class="rounded-xl border border-gray-800 bg-gray-950 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Frontend</p>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="size-2 rounded-full bg-gray-600"></span>
                            <span class="text-sm font-medium text-gray-400">Pendente</span>
                        </div>
                        <p class="text-xs text-gray-600">Angular (externo)</p>
                        <p class="text-xs text-gray-600 mt-1">Esta página é um placeholder temporário.</p>
                    </div>

                </div>
            </div>

            <p class="mt-4 text-center text-xs text-gray-700">
                Ambiente: <span class="text-gray-500">{{ app()->environment() }}</span>
            </p>

        </div>

    </body>
</html>
