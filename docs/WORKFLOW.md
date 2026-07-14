# Workflow deste repositório

> Como este repositório é trabalhado com Claude Code: comandos, skills e agentes, e a
> sequência das fases de uma issue. Para o detalhe de cada comando, abrir o ficheiro
> correspondente em `.claude/commands/`.

## As 3 camadas

```
Commands  → o que o utilizador invoca (/planeia-issue, /implementa-plano, ...)
Skills    → passos reutilizáveis que os commands chamam (escreve-brief, executa-testes, ...)
Agents    → subagentes lançados por commands/skills quando a tarefa justifica (Explore, Plan)
```

- **Commands** (`.claude/commands/*.md`) — pontos de entrada com `$ARGUMENTS`, pré-condições e
  passos. Correspondem a uma fase do workflow (ver tabela abaixo).
- **Skills** (`.claude/skills/*.md`) — unidades de trabalho reutilizadas por vários commands
  (ex: `executa-testes`, `pausa-checkpoint`, `regista-aviso`). Não são invocadas directamente
  pelo utilizador — os commands chamam-nas internamente.
- **Agents** — subagentes (Explore, Plan, general-purpose) lançados quando a tarefa beneficia
  de investigação paralela ou isolamento de contexto.

O estado entre fases persiste em `docs/workflow-state.md` (existe só enquanto uma issue está
em curso) e avisos de processo em `docs/process-warnings.md`.

## Comandos por fase

| Command                                    | Fase    | Produz                                             |
| ------------------------------------------ | ------- | --------------------------------------------------- |
| `/cria-issue <descrição>`                  | —       | Issue #N (genérica)                                |
| `/cria-issue-modelo [entidade]`            | —       | Issue para migration + model + factory + testes    |
| `/cria-issue-persistencia [entidade]`      | —       | Issue para interface + repositório + DTOs + testes |
| `/cria-issue-logica [entidade]`            | —       | Issue para Actions + Controller + Events + testes  |
| `/planeia-issue [#N]`                      | Fase 1  | Brief + Branch + Spec + Plano                       |
| `/implementa-plano [#N] [--stack laravel]` | Fase 2  | Código + Commits                                    |
| `/documenta-implementacao [#N]`            | Fase 3a | Debrief + system_spec + Changelog + README          |
| `/publica-implementacao [#N]`              | Fase 3b | PR no GitHub                                        |
| `/mostra-workflow`                         | —       | Estado actual do workflow                           |
| `/ajusta-workflow [descrição]`             | —       | Corrige/classifica ajuste de processo no local certo |

## Sequência de uma issue

```mermaid
sequenceDiagram
    actor U as Utilizador
    participant CI as /cria-issue*
    participant PL as /planeia-issue
    participant IM as /implementa-plano
    participant DOC as /documenta-implementacao
    participant PUB as /publica-implementacao
    participant WS as workflow-state.md

    U->>CI: descrição da funcionalidade/bug
    CI-->>U: Issue #N criada no GitHub

    U->>PL: /planeia-issue #N
    PL->>WS: fase = planeia
    PL-->>U: Brief + Spec + Plano (checkpoint)

    U->>IM: /implementa-plano #N
    IM->>WS: fase = implementa
    loop por tarefa do Plano
        IM-->>U: checkpoint por tarefa + testes
    end
    IM-->>U: código + commits

    U->>DOC: /documenta-implementacao #N
    DOC->>WS: fase = documenta
    DOC-->>U: Debrief + system_spec + Changelog + README

    U->>PUB: /publica-implementacao #N
    PUB->>WS: fase = publica
    PUB-->>U: PR aberto no GitHub
    PUB->>WS: remove workflow-state.md
```

Em qualquer fase, `/mostra-workflow` mostra o estado actual (útil para retomar após pausa), e
`/ajusta-workflow` corrige desvios de processo — nunca despejando o ajuste directamente em
`CLAUDE.md` ou memória, mas classificando-o para `docs/system_spec/`, `.claude/commands/` ou
`.claude/skills/` conforme a natureza da mudança.
