# System Spec — Infra: Transações de BD

> Padrão obrigatório. Ver também `CLAUDE.md` — Padrões obrigatórios.

Todas as Actions de escrita (criar, actualizar, eliminar) envolvem a persistência em `DB::transaction()`. Autorização (`Gate::authorize()`) fica **fora** da transação — autorização não é operação de BD. A persistência fica **dentro**.

`DB::transaction()` faz rollback e **re-lança automaticamente** qualquer `\Throwable` levantado dentro do callback. Por isso:

- O `handle()` da Action declara `@throws \Throwable` no PHPDoc — **obrigatório** sempre que houver `DB::transaction()`. Os callers ficam informados estaticamente (IDE + Larastan) de que a operação pode propagar excepções.

---

## Padrão canónico

```php
/**
 * @throws \Throwable
 */
public function handle(CriarXxxDto $dados): Xxx
{
    Gate::authorize('create', Xxx::class);                        // fora — autorização

    return DB::transaction(fn (): Xxx => Xxx::create([...]));     // dentro — persistência
}
```

Para Actions com múltiplas operações:

```php
/**
 * @throws \Throwable
 */
public function handle(Xxx $xxx, ActualizarXxxDto $dados): Xxx
{
    Gate::authorize('update', $xxx);

    return DB::transaction(function () use ($xxx, $dados): Xxx {
        $xxx->fill([...])->save();
        $xxx->refresh();

        return $xxx;
    });
}
```

---

## Actions que implementam este padrão

| Action | Feature |
|---|---|
| `CriarCategoriaAction` | `CategoriaDocumento/Criar` |
| `ActualizarCategoriaAction` | `CategoriaDocumento/Actualizar` |
| `EliminarCategoriaAction` | `CategoriaDocumento/Eliminar` |
| `CriarEntidadeAction` | `Entidade/Criar` |
| `ActualizarEntidadeAction` | `Entidade/Actualizar` |
| `EliminarEntidadeAction` | `Entidade/Eliminar` |
| `ConverterEmEmpresaMaeAction` | `Entidade/EmpresaMae` |
| `RegistarDocumentoManualAction` | `Documento/Criar` |
| `EliminarDocumentoAction` | `Documento/Eliminar` |
| `ReceberUploadDocumentoAction` | `Documento/RecepcaoUpload` |
| `RegistarEtapaExtracaoAction` | `Documento/Processamento` |
| `ReivindicarDocumentoPendenteAction` | `Documento/Atribuicao` |
| `ReivindicarDocumentoEmEtapaAction` | `Documento/Atribuicao` |

Todas as Actions de escrita futuras seguem este padrão obrigatoriamente.

As 8 Actions de transição de estado do `Documento` (`Marcar*DocumentoAction`,
`TransicionarProcessadoDocumentoAction`, em `Documento/Operacoes/TransicoesEstado/`) e as Actions que
as chamam (`CorrigirDocumentoAction`, `ConcluirExtracaoDocumentoAction`,
`RegistarFalhaTecnicaExtracaoAction`, `ReprocessarDocumentoAction`, `Processar*DocumentoAction`) **não
abrem `DB::transaction()` directamente** — delegam em `ExecutorTransicaoDocumento`, que centraliza a
transação (ver "Padrão de reivindicação com `lockForUpdate()`" abaixo).

---

## Nota Jobs — `ShouldQueueAfterCommit`

Jobs disparados **dentro** de uma transação não podem ser processados pelo worker antes de o commit concluir — caso contrário a queue processa o Job sobre dados ainda não persistidos (ou que sofrem rollback).

Duas formas de garantir o despacho só após commit:

| Mecanismo | Âmbito | Como |
|---|---|---|
| `after_commit: true` | Global por connection de queue | `config/queue.php` → `'connections' => ['<conn>' => ['after_commit' => true]]` |
| `ShouldQueueAfterCommit` | Por Job individual | `final class XxxJob implements ShouldQueue, ShouldQueueAfterCommit` |

A interface por Job tem precedência e é preferível quando só alguns Jobs precisam deste comportamento.
**Nome correcto:** `Illuminate\Contracts\Queue\ShouldQueueAfterCommit` — não confundir com
`Illuminate\Contracts\Broadcasting\ShouldBroadcast`/`Illuminate\Contracts\Events\ShouldDispatchAfterCommit`,
exclusiva de Events/Broadcasting (usada pelos Events de domínio do `Documento`, ver `04-infra/queue-jobs.md`).
ArchTest garante esta interface em todo `Job` de `app/Jobs/` (RN-01/CA-02).

---

## Padrão de reivindicação com `lockForUpdate()`

Componente reutilizável para varrer candidatos ao pipeline sem duplo processamento entre workers
concorrentes (`ReivindicarDocumentoPendenteAction`, `app/Features/Documento/Atribuicao/`):

```php
/**
 * @throws \Throwable
 */
public function handle(): ?Documento
{
    return DB::transaction(function (): ?Documento {
        $documento = Documento::query()->wherePendente()->lockForUpdate()->first();

        if (! $documento instanceof Documento) {
            return null;
        }

        return $this->processarAnaliseMalware->handle($documento);   // triagem de malware + transição (savepoint)
    });
}
```

- A `DB::transaction()` abre-se **no ponto de entrada** (esta Action não tem chamador) — o
  `lockForUpdate()` só faz sentido dentro de uma transacção já aberta, mantendo o lock até ao commit.
- `wherePendente()` é o scope existente do Model — sem Repository (ver `04-infra/repositories.md`
  para o critério de quando um se justifica).
- A `ProcessarAnaliseMalwareDocumentoAction` chamada de seguida admite o documento a `AnaliseMalware`, corre o
  scan e transiciona — cada `Marcar*` interna abre a sua própria `DB::transaction()` (via
  `ExecutorTransicaoDocumento`), que Laravel resolve como `SAVEPOINT` (transação aninhada), sem romper
  o lock da linha mantido pela transação exterior.
- Sem `Gate::authorize()` — acção de sistema/pipeline (ver `02-shared/padroes-acoes.md`).
- `RegraTransicaoEstado` actua como último nível de validação: se outro worker já mudou o `estado`
  antes deste obter o lock, a transição falha de forma previsível (`TransicaoInvalidaException`).
