# Skill: actualiza-spec

Actualiza os ficheiros `docs/system_spec/` com base no Debrief e no `SYSTEM_SPEC_MAP` do `CLAUDE.md`.

> **Categoria:** actualiza  
> **Usado em:** `/documenta-implementacao` (passo 3)  
> **Produz:** ficheiros `docs/system_spec/*.md` actualizados

## Contrato

**Input:**
- `docs/debriefs/YYYY-MM-DD-<slug>.md` — secção "SYSTEM_SPEC a actualizar"
- `SYSTEM_SPEC_MAP` do `CLAUDE.md` do repo activo

**Output:** ficheiros `docs/system_spec/*.md` actualizados

**Usado em:** `/documenta-implementacao` (passo 3)

---

## SYSTEM_SPEC_MAP por stack

### dotnet
| Tipo de alteração | Ficheiro a actualizar |
|-------------------|-----------------------|
| Novas entidades, estados, enums | `docs/system_spec/02-domain.md` |
| Novos UseCases ou interfaces | `docs/system_spec/03-usecases.md` |
| Alterações a EF, Redis, Claude, FileSystem | `docs/system_spec/04-infra.md` |
| Novos endpoints, middleware | `docs/system_spec/05-api.md` |
| Alterações de configuração | `docs/system_spec/06-config.md` |

### laravel

**Descoberta:** ler `docs/system_spec/00-index.md` primeiro — lista todas as features, modelos e ficheiros de infra existentes. Depois abrir apenas o ficheiro relevante.

| Tipo de alteração | Ficheiro a actualizar |
|-------------------|-----------------------|
| Nova Action ou Feature (feature existente) | `docs/system_spec/01-features/<slug>.md` |
| Nova Feature (slice nova) | criar `docs/system_spec/01-features/<slug>.md` + actualizar `00-index.md` |
| Novo enum partilhado | `docs/system_spec/02-shared/enums.md` |
| Novo componente HTTP ou handler de erro | `docs/system_spec/02-shared/http.md` |
| Novo estado ou contrato | `docs/system_spec/02-shared/estados.md` |
| Novo Model ou relação Eloquent | `docs/system_spec/03-models/<slug>.md` |
| Novo Repository | `docs/system_spec/04-infra/repositories.md` |
| Novo Job ou Queue config | `docs/system_spec/04-infra/queue-jobs.md` |
| Cache ou Redis | `docs/system_spec/04-infra/cache.md` |
| API externa (IA ou outro) | `docs/system_spec/04-infra/external-apis.md` |
| Nova rota API | `docs/system_spec/05-routes/<slug>.md` |
| Nova configuração ou .env var | `docs/system_spec/06-config.md` |

**Regras de sustentabilidade:**
- Nova feature slice → criar `01-features/<slug>.md` (nunca acrescentar ao ficheiro de outra feature)
- `02-shared/` → apenas componentes em `app/Shared/` (nunca feature-specific)
- `04-infra/` → um ficheiro por subsistema (Redis ≠ Jobs ≠ Repositories)

> **Obrigatório — ficheiro novo → actualizar `00-index.md`.** Sempre que esta skill cria um ficheiro **novo** em `docs/system_spec/` (nova feature slice, novo Model, novo subsistema de infra, etc.), tem de actualizar também `docs/system_spec/00-index.md` com uma linha na tabela correcta — no mesmo commit. Um ficheiro não registado no índice é invisível para a descoberta.

### angular
| Tipo de alteração | Ficheiro a actualizar |
|-------------------|-----------------------|
| Novos componentes ou features | `docs/system_spec/01-features.md` |
| Novos Signal stores | `docs/system_spec/02-state.md` |
| Novos services ou interceptors | `docs/system_spec/03-core.md` |
| Novos interfaces, enums, DTOs | `docs/system_spec/04-models.md` |
| Novas rotas | `docs/system_spec/05-routes.md` |

---

## Regras
- Actualizar apenas as secções afectadas — não reescrever o ficheiro completo
- Cada actualização é um commit separado: `📝 docs: actualizar system_spec após #N`
- A system_spec regista o que **existe**, não o que está planeado
