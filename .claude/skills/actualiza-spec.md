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
| Tipo de alteração | Ficheiro a actualizar |
|-------------------|-----------------------|
| Novas Actions ou Features | `docs/system_spec/01-features.md` |
| Novos enums, DTOs, estados | `docs/system_spec/02-shared.md` |
| Novos Modelos Eloquent | `docs/system_spec/03-models.md` |
| Alterações a infra | `docs/system_spec/04-infra.md` |
| Novas rotas | `docs/system_spec/05-routes.md` |
| Alterações de configuração | `docs/system_spec/06-config.md` |

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
