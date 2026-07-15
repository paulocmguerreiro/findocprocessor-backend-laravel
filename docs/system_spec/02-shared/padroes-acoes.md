# System Spec — Shared: Padrões de Actions

> Padrões transversais a todas as Actions em `app/Features/<Feature>/<Action>/`.

A Action é a unidade de lógica de negócio da arquitectura Vertical Slice. Cada operação (criar, listar, ver, actualizar, eliminar, …) tem a sua própria Action com um único método público `handle()`.

---

## Regras estruturais

- Controllers **sem lógica** — apenas fazem dispatch para a Action.
- Actions injectam **interface quando há substituição prevista** (ex.: Repository, cliente de API externa) — para trocar a implementação sem tocar na Action. Quando **não** se prevê substituição, injectar a classe concreta directamente é legítimo (ver abaixo).
- Acesso directo ao Eloquent só em CRUD simples; caso contrário, via Repository (ver `04-infra/repositories.md`).
- Transição de estado via Actions de transição (`ExecutorTransicaoDocumento` + `RegraTransicaoEstado`), nunca `if ($doc->status == ...)`. Os state objects (`$documento->estado()`) são read-only — sem `correct()` (ver `02-shared/estados.md`).
- Actions de escrita envolvem a persistência em `DB::transaction()` (ver `04-infra/transactions.md`).
- Blocos condicionais quase idênticos dentro de um método (ex.: vários `if` a acumular o mesmo tipo de motivo/erro) são extraídos para métodos privados dedicados, um por validação/responsabilidade. Nenhuma ferramenta de lint/Larastan detecta esta duplicação — exige leitura da intenção do método.

### Interface vs classe concreta na injecção

A regra "injectar interface" existe para **isolar pontos de substituição** — não é um fim em si. Extrair interface quando se prevê trocar a implementação (diferentes implementações de Repository, cliente de IA), ou quando o mock só é possível por dupla de teste.

Classes concretas injectadas directamente são legítimas quando **não** se prevê substituição e o teste não precisa de interface:

| Classe concreta | Porque não tem interface |
|---|---|
| `CacheServico` (`app/Shared/Cache`) | Wrapper fino sobre o `Cache`; nos testes faz-se `Cache::fake()` — não é preciso mockar o serviço |
| `Regra*` (ex.: `RegraTransicaoEstado`, `RegraMoverFicheiro`) | Regras de domínio puras; podem ter handlers distintos — uma interface obrigaria a binds desnecessários sem ganho |
| `ExecutorTransicaoDocumento` | Orquestrador interno à feature `Documento`; partilhado pelas Actions de transição, sem variante alternativa prevista |

> Critério: há mais do que uma implementação plausível **ou** o teste exige substituir o colaborador? → interface. Caso contrário, classe concreta.

---

## Autorização dupla camada (obrigatório)

A autorização acontece em **dois sítios distintos** — não é redundância, são dois contextos de invocação diferentes:

| Camada | Onde | Contexto que protege |
|---|---|---|
| HTTP | `FormRequest::authorize()` via `Gate::authorize()` / `$this->authorize()` | Pedidos HTTP — o Laravel converte falha em `403` automaticamente |
| Lógica | `Action::handle()` via `Gate::authorize()` | Invocações **fora** de HTTP: Jobs, Artisan, Events, testes de integração |

Se a autorização estivesse só no `FormRequest`, uma Action invocada por um Job ou comando Artisan correria sem qualquer verificação de Policy. A duplicação garante que a Policy se aplica **independentemente do ponto de entrada**.

### Exemplo

`FormRequest` (camada HTTP):
```php
final class CriarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CategoriaDocumento::class);
    }

    // rules(), messages()...
}
```

`Action` (camada de lógica):
```php
final class CriarCategoriaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        Gate::authorize('create', CategoriaDocumento::class);   // fora da transação

        return DB::transaction(fn (): CategoriaDocumento => CategoriaDocumento::create([
            'nome'           => $dados->nome,
            'slug'           => $dados->slug,
            'tipo_movimento' => $dados->tipoMovimento,
        ]));
    }
}
```

`Gate::authorize()` lança `AuthorizationException` (convertida em `403` pelo exception handler — ver `02-shared/http.md`). Fica **fora** da `DB::transaction()` — autorização não é operação de BD.

### Excepção: Actions de sistema (background, sem login)

Actions que correm **sempre** em background (Jobs de pipeline), **sem endpoint HTTP e sem utilizador autenticado**, não têm `Gate::authorize` — não há actor a autorizar. São transições de sistema: registam a `EtapaDocumento` como passo automático (`id_utilizador = null`).

Exemplo: as transições intermédias do Documento (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`, `MarcarErro`, `MarcarPerigoso`) — ver `01-features/documento.md`.

Critério: a Action é **exclusivamente** de pipeline (nunca invocada por um pedido do utilizador) **e** não escreve dados de negócio que justifiquem autorização. Se escrever dados significativos (ex.: `TransicionarProcessadoDocumentoAction` preenche fornecedor/valor/categoria), **mantém** `Gate::authorize`, mesmo sem endpoint. Na dúvida, manter o Gate.

---

## Posição do `Gate::authorize()` face à transação

```
Gate::authorize(...)            ← fora — autorização não é operação de BD
DB::transaction(fn () => ...)   ← dentro — apenas a persistência
```

Detalhe do padrão transaccional em `04-infra/transactions.md`.
