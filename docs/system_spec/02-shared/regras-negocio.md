# System Spec — Shared: Regras de Negócio

> Catálogo das classes `Regra*` da aplicação — invariantes de domínio que não pertencem a uma Action específica.

---

## O que é uma classe `Regra*`

Uma classe `Regra*` encapsula um **invariante de domínio** que:

- É invocada por **mais do que uma Action** dentro da mesma feature (ou, futuramente, por Actions de features distintas); e/ou
- Representa uma **regra explícita do domínio** com nome próprio, distinta da lógica de persistência de uma Action.

### Distinção face a uma Action

| | `Regra*` | `Action` |
|---|---|---|
| Ponto de entrada | Chamada por outras Actions | Chamada pelo Controller (via FormRequest) |
| Autorização | **Sem** `Gate::authorize()` própria — a Action chamante já autorizou | `Gate::authorize()` obrigatório |
| HTTP | Nunca exposta directamente | Mapeada a um endpoint |
| Transação | Executa **dentro** da transação da Action chamante | Abre a própria transação |
| Naming | `Regra<Invariante>` | `<Operação><Entidade>Action` |

### Padrão estrutural

```php
final readonly class RegraXxx
{
    public function __construct(
        private AlgumaAction $accao,        // dependência injectada
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(/* parâmetros do invariante */): void
    {
        if (/* condição que activa a regra */) {
            $this->accao->handle(/* ... */);
        }
    }
}
```

- `final readonly` — sem herança, sem estado mutável.
- Sem `Gate::authorize()` — a autorização é responsabilidade da Action chamante.
- `@throws \Throwable` — porque invoca Actions que abrem transações.
- Localização: `app/Features/<Feature>/<SubContexto>/Regra<Invariante>.php`.

---

## Catálogo de regras

### `RegraUnicidadeEmpresaMae`

**Ficheiro:** `app/Features/Entidade/EmpresaMae/RegraUnicidadeEmpresaMae.php`

**Invariante:** Só pode existir uma `Entidade` com `e_empresa_aplicacao = true` em simultâneo. Se uma nova entidade for marcada como Empresa Mãe, a marcação anterior é removida automaticamente.

**Activação:** Sempre que `$eEmpresaAplicacao === true` (em criar, actualizar, ou converter).

**Invocada por:**
- `CriarEntidadeAction`
- `ActualizarEntidadeAction`
- `ConverterEmEmpresaMaeAction`

**Acção interna:** `RemoverMarcacaoEmpresaMaeAction` — executa `UPDATE entidades SET e_empresa_aplicacao = false WHERE e_empresa_aplicacao = true`. Sem autorização própria (operação interna ao domínio).

**Diagrama de fluxo:**
```
Action chamante
  └─ Gate::authorize()          ← autorização (fora da transação)
  └─ DB::transaction()
       └─ RegraUnicidadeEmpresaMae::handle($eEmpresaAplicacao)
            └─ if ($eEmpresaAplicacao)
                 └─ RemoverMarcacaoEmpresaMaeAction::handle()
                      └─ Entidade::whereEmpresaAplicacao()->update([...])
       └─ persistência principal
```

---

### `RegraTransicaoEstado`

**Ficheiro:** `app/Features/Documento/Transicao/RegraTransicaoEstado.php`

**Invariante:** Toda a mudança de estado do `Documento` tem de constar do mapa central De→Para. Transições
inválidas lançam `TransicaoInvalidaException` (→ 422). Nunca `if ($doc->status == ...)`.

**Activação:** Chamada por `ExecutorTransicaoDocumento` antes de qualquer persistência.

**Invocada por:** `ExecutorTransicaoDocumento` (que é usado por todas as 8 Actions de transição simples).

**Mapa central (match exaustivo sem `default`):**

```
Pendente       → AguardaEnvio
AguardaEnvio   → Enviado
Enviado        → AguardaResposta
AguardaResposta → Processado | Erro | Perigoso
Pendente       → Perigoso        (pré-scan)
Erro           → AguardaEnvio    (reprocessamento)
Processado     → Processado      (correcção — self-loop)
```

**Nota:** O `match` em PHP 8.5 sem `default` garante que se um novo `case` de `EstadoDocumento` for
adicionado, o Larastan 9 dá erro de compilação — impossível esquecer um caso no mapa.

---

### `RegraMoverFicheiro`

**Ficheiro:** `app/Features/Documento/Transicao/RegraMoverFicheiro.php`

**Invariante:** `status ↔ disco_storage ↔ nome_ficheiro_storage` ficam sempre consistentes após uma
transição. O ficheiro é movido entre discos distintos com verificação do valor de retorno (discos
configurados com `'throw' => false`).

**Activação:** Chamada por `ExecutorTransicaoDocumento` antes da transação.

**Invocada por:** `ExecutorTransicaoDocumento`.

**Implementação:** `Storage::disk($destino)->put($nome, Storage::disk($origem)->get($nome))` + `delete()`
na origem. Verifica o retorno de cada operação e lança em falha. Compensação best-effort em excepção:
tenta mover de volta para a origem.

**Mapa estado→disco** (ver `02-shared/estados.md` para tabela completa):
- `Pendente`, `AguardaEnvio` → `entrada`
- `Enviado`, `AguardaResposta` → `enviado`
- `Processado` → `processado`
- `Erro` → `erro`
- `Perigoso` → `perigoso`

**Limitação:** em dupla falha (mover OK + compensação falha), o ficheiro fica no disco destino com a
BD revertida para o estado anterior — inconsistência persistente; requer reconciliação manual.

---

### `RegraNomearProcessado`

**Ficheiro:** `app/Features/Documento/Transicao/RegraNomearProcessado.php`

**Invariante:** O nome de ficheiro de um `Documento` processado segue o formato canónico
`yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`, derivado dos dados de domínio.

**Activação:** Sempre que um documento transiciona para `Processado` ou quando uma correcção altera
o slug (fornecedor, categoria ou data).

**Invocada por:** `TransicionarProcessadoDocumentoAction`, `CorrigirDocumentoAction`.

**Geração:** `Str::slug($nomeFornecedor)`, `Str::slug($nomeCategoria)`; data de `data_documento`;
extensão preservada de `nome_ficheiro_original`.

**Limitação:** dois documentos com o mesmo fornecedor, categoria e data terão o mesmo nome canónico
— `Storage::put()` sobrepõe silenciosamente. Diferido.

---

## Regras cross-cutting (planeadas)

Regras que atravessam múltiplas features serão documentadas aqui quando implementadas. Candidatos esperados:

| Regra (planeada) | Trigger esperado |
|---|---|
| Hierarquia de acesso (gestor vê dados dos seus membros) | Autenticação/autorização multi-tenant |
| Visibilidade de documentos por papel | Roles acima do utilizador base |

Quando implementadas, cada regra cross-cutting recebe uma subsecção neste ficheiro com o mesmo formato da `RegraUnicidadeEmpresaMae`.
