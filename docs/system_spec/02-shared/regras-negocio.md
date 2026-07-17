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

## Regras de transição do Documento

As 6 regras `Regra*` da máquina de estados do `Documento` (`RegraTransicaoEstado`,
`RegraMoverFicheiro`, `RegraEliminarExtracaoTerminal`, `RegraNomearProcessado`,
`RegraReconciliarLocalizacaoFicheiro`, `RegraReporTentativasExtracao`) estão catalogadas em
`02-shared/regras-transicao-documento.md` — ficheiro dedicado por concentrarem o maior volume de
regras de uma única feature (máquina de estados unificada, #110). A reconciliação de entidades por
lado (`RegraReconciliarEntidadesDocumento`, #111) fica em `01-features/documento-pipeline.md` — não
é uma regra de transição de estado, é específica do pipeline de extracção (regra de sustentabilidade
do `actualiza-spec`: regra feature-específica vai para a feature, mesmo parecendo genérica).

---

## Regras cross-cutting (planeadas)

Regras que atravessam múltiplas features serão documentadas aqui quando implementadas. Candidatos esperados:

| Regra (planeada) | Trigger esperado |
|---|---|
| Hierarquia de acesso (gestor vê dados dos seus membros) | Autenticação/autorização multi-tenant |
| Visibilidade de documentos por papel | Roles acima do utilizador base |

Quando implementadas, cada regra cross-cutting recebe uma subsecção neste ficheiro com o mesmo formato da `RegraUnicidadeEmpresaMae`.
