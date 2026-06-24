# Skill: escreve-spec

Traduz o Brief em requisitos técnicos verificáveis, alinhados com a arquitectura do stack activo.

> **Categoria:** escreve  
> **Usado em:** `/planeia-issue` (passo 7)  
> **Produz:** `docs/specs/YYYY-MM-DD-<slug>.md`

## Contrato

**Input:**
- `docs/briefs/YYYY-MM-DD-<slug>.md` — incluindo `## Questões em aberto` e `## Riscos identificados`
- Issue body (via `gh issue view $N`) — para herdar CAs e Dependências
- `docs/system_spec/*.md` relevantes
- Secção `## ARQUITECTURA` do `CLAUDE.md` do repo activo
- Resposta do utilizador ao Checkpoint A (deve incluir resolução das questões em aberto)

**Output:** `docs/specs/YYYY-MM-DD-<slug>.md`

**Usado em:** `/planeia-issue` (passo 7)

---

## Formato da Spec

```markdown
# Spec: <título>

**Issue:** #N
**Brief:** docs/briefs/YYYY-MM-DD-<slug>.md
**Data:** YYYY-MM-DD

## Requisitos funcionais
- RF-01: ...
- RF-02: ...

## Requisitos não funcionais
- RNF-01: ...

## Contratos de API (se aplicável)

| Método | Path | Request | Response |
| ------ | ---- | ------- | -------- |
| ...    | ...  | ...     | ...      |

## Modelo de dados (se aplicável)

| Campo | Tipo | Obrigatório | Notas |
| ----- | ---- | ----------- | ----- |
| ...   | ...  | ...         | ...   |

## Regras de negócio
- RN-01: ...

## Dependências
- Issues bloqueantes: [#N — título | "nenhuma"]

## Questões resolvidas
| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| ...                | ...     |

## Critérios de aceitação
> Herdados da issue — nunca remover ou reformular os CAs originais sem justificação.
- [ ] CA-01: ... *(issue)*
- [ ] CA-02: ... *(spec)*

## SYSTEM_SPEC a actualizar
- `docs/system_spec/<ficheiro>.md` — secção X
- Ficheiro novo em `docs/system_spec/` (nova feature slice, novo Model, etc.) → criar o ficheiro **e** actualizar `docs/system_spec/00-index.md` com uma linha na tabela correcta

## Verificação RGPD/NIS2
- Dados pessoais: [detalhe]
- Superfície de ataque: [detalhe]
```

---

## Verificação de arquitectura por stack

Antes de gerar a Spec, verificar invariantes da secção `ARQUITECTURA` do `CLAUDE.md` e confirmar com `search-docs` qualquer API ou comportamento que não seja trivial — em particular quando o Brief identificou questões em aberto ou riscos técnicos com base em documentação.

**dotnet (Clean Architecture)**
- Lógica de negócio em `Core`? (nunca em Endpoint ou Worker)
- Novos `DocumentState` implementam a interface base?
- Endpoints usam Minimal API? (nunca `ControllerBase`)
- DTOs mapeados manualmente? (nunca AutoMapper)
- Campos sensíveis excluídos de logs e DTOs?

**laravel (Vertical Slice)**
Antes de finalizar a Spec, verificar conformidade com:
- `docs/system_spec/02-shared/contratos-por-camada.md` — checklist por camada
- `docs/system_spec/02-shared/convencoes-nomenclatura.md` — nomenclatura PT/EN

**angular (Standalone)**
- Todos os componentes com `standalone: true` e `OnPush`?
- Estado com Signals nativos? (nunca NgRx ou BehaviorSubject)
- SSE consumido apenas via `SseStore`?
- Nenhum tipo `any` no TypeScript?

**OpenAPI (qualquer stack se afectar API)**
- O contrato vive em `./openapi.yaml` (raiz deste repo) — não no repo de workflow
- Novo endpoint / alteração de schema → a Spec **declara** o delta do contrato (que rotas/schemas mudam); a escrita efectiva em `./openapi.yaml` é feita na Fase 3a (`/documenta-implementacao`)
- Breaking change → documentar e criar issues linked nos outros repos

---

## Regras
- Cada requisito tem ID (`RF-NN`, `RNF-NN`, `RN-NN`, `CA-NN`)
- CAs da issue são herdados e marcados *(issue)*; CAs adicionados na Spec marcados *(spec)*
- `## Questões resolvidas` cobre todas as entradas de `## Questões em aberto` do Brief
- Critérios de aceitação são verificáveis por testes
- Não incluir detalhes de implementação — isso fica no Plan
