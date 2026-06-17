# Skill: propoe-commit

Formata e propõe uma mensagem de commit em conventional commits com emoji, em português.
**Nunca executar `git commit` sem confirmação explícita do utilizador.**

> **Categoria:** propoe  
> **Usado em:** `/implementa-plano` (checkpoint por tarefa), `/documenta-implementacao`  
> **Produz:** commit executado após confirmação explícita

## Contrato

**Input:**

- `tipo`: feat | fix | refactor | test | docs | chore | perf | style
- `scope`: domínio afectado (ver tabela abaixo)
- `descrição`: descrição em PT, imperativo, sem ponto final
- `footer`: número da issue (ex: `#5`)
- `corpo`: opcional — porquê, não o quê

**Output:** commit executado após confirmação

**Usado em:** `/implementa-plano` (checkpoint por tarefa), `/documenta-implementacao`

---

## Formato

```
<emoji> <tipo>(<scope>): <descrição em PT>

[corpo opcional — porquê, não o quê]

(#N)
```

---

## Tipos e emojis

| Tipo       | Quando usar                             |
| ---------- | --------------------------------------- |
| `feat`     | Nova funcionalidade                     |
| `fix`      | Correcção de bug                        |
| `refactor` | Refactoring sem mudar comportamento     |
| `test`     | Adicionar ou corrigir testes            |
| `docs`     | Documentação                            |
| `chore`    | Configuração, CI, dependências          |
| `perf`     | Melhoria de performance                 |
| `style`    | Formatação, linter (sem mudança lógica) |

---

## Scopes por stack

| Stack   | Scopes disponíveis                                                 |
| ------- | ------------------------------------------------------------------ |
| dotnet  | `domain`, `states`, `usecases`, `infra`, `api`, `workers`, `tests` |
| laravel | `features`, `shared`, `models`, `infra`, `routes`, `jobs`, `tests` |
| angular | `core`, `state`, `features`, `shared`, `models`, `routes`          |

---

## Exemplos

```
feat(domain): adicionar entidade Document com estado inicial

fix(api): corrigir serialização do campo data_documento

refactor(states): extrair lógica de transição para PendingState

test(domain): adicionar testes para InvalidStateTransitionException

docs: actualizar system_spec após #12

(#12)
```

---

## Fluxo de proposta

```
Commit proposto:

  feat(domain): adicionar estado PendingState

  (Resolve tarefa 1 — #5)

Confirmas? [s / edita / cancela]
```

- `s` → executar `git add -p` + `git commit`
- `edita` → utilizador altera a mensagem antes de commitar
- `cancela` → não commitar (continuar sem commit desta tarefa)

---

## Regras

- Descrição em PT, imperativo, sem ponto final
- Máximo 72 caracteres na primeira linha
- Footer `(#N)` obrigatório quando associado a uma issue
- Nunca usar `--no-verify`
- Nunca usar `--amend` em commits já pushed
- Nunca adicionar `Co-Authored-By` ou qualquer referência ao Claude na mensagem de commit
