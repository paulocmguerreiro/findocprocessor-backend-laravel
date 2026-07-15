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
em curso) e avisos de processo em `docs/process-warnings.md` (só entradas activas —
`PENDENTE`/`PARCIALMENTE RESOLVIDO`; histórico de `RESOLVIDO`/`IGNORADO` em
`docs/process-warnings-concluidos.md`).

## Comandos por fase

| Command                                    | Fase    | Produz                                               |
| ------------------------------------------ | ------- | ---------------------------------------------------- |
| `/cria-issue <descrição>`                  | —       | Issue #N (genérica)                                  |
| `/cria-issue-modelo [entidade]`            | —       | Issue para migration + model + factory + testes      |
| `/cria-issue-persistencia [entidade]`      | —       | Issue para interface + repositório + DTOs + testes   |
| `/cria-issue-logica [entidade]`            | —       | Issue para Actions + Controller + Events + testes    |
| `/planeia-issue [#N]`                      | Fase 1  | Brief + Branch + Spec + Plano                        |
| `/implementa-plano [#N] [--stack laravel]` | Fase 2  | Código + Commits                                     |
| `/documenta-implementacao [#N]`            | Fase 3a | Debrief + system_spec + Changelog + README           |
| `/publica-implementacao [#N]`              | Fase 3b | PR no GitHub                                         |
| `/mostra-workflow`                         | —       | Estado actual do workflow                            |
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
    PL-->>U: Brief
    Note over U,PL: Checkpoint A — validação contra<br/>compreensão da issue (nunca só "sim")
    PL-->>U: Spec
    Note over U,PL: Checkpoint B — Spec verificada contra<br/>CLAUDE.md, utilizador confirma ou corrige
    PL-->>U: Plano

    U->>IM: /implementa-plano #N
    IM->>WS: fase = implementa
    loop por tarefa do Plano
        IM-->>U: tarefa implementada
        Note over U,IM: Checkpoint task — utilizador lê o diff,<br/>commit só após confirmação explícita
        IM->>IM: propoe-commit (git add -p + git commit)
    end
    IM-->>U: testes + php artisan checkpoint:scan
    Note over U,IM: Se o scan reportar FAILs, pausa e aguarda<br/>[ok] ou [stop] — nunca suprime automaticamente
    IM-->>U: Checkpoint ② — implementação completa

    U->>DOC: /documenta-implementacao #N
    DOC->>WS: fase = documenta
    DOC-->>U: Debrief
    Note over U,DOC: Checkpoint D — validação de cada<br/>decisão antes do system_spec ser actualizado
    DOC-->>U: system_spec + Changelog + README

    U->>PUB: /publica-implementacao #N
    PUB->>WS: fase = publica
    Note over U,PUB: Checkpoint E — validação que<br/>compreende e defende cada decisão do PR body
    PUB-->>U: PR aberto no GitHub
    PUB->>WS: remove workflow-state.md
```

Em qualquer fase, `/mostra-workflow` mostra o estado actual (útil para retomar após pausa), e
`/ajusta-workflow` corrige desvios de processo — nunca despejando o ajuste directamente em
`CLAUDE.md` ou memória, mas classificando-o para `docs/system_spec/`, `.claude/commands/` ou
`.claude/skills/` conforme a natureza da mudança.

## Checkpoints humanos

Nenhuma fase avança sem uma pausa explícita para decisão/validação do utilizador — a skill `pausa-checkpoint`
implementa os 6 tipos abaixo e **nunca aceita "sim" isolado** como resposta suficiente; exige
conteúdo que demonstre compreensão real da decisão.

| Checkpoint | Quando                                               | O que confirma                                                                                                                                                                 |
| ---------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **A**      | Após o Brief (`/planeia-issue`)                      | O que muda no domínio, que risco existe, que camada é mais afectada — nas palavras do utilizador. Bloqueia avanço para a Spec enquanto houver questões em aberto sem resposta. |
| **B**      | Após a Spec (`/planeia-issue`)                       | Spec verificada contra a secção Arquitectura do `CLAUDE.md` — desvios e violações de "O que NÃO fazer" listados explicitamente antes de confirmar.                             |
| **task**   | Após cada tarefa (`/implementa-plano`)               | Ficheiros alterados lidos pelo utilizador; só depois disso o commit é proposto (`propoe-commit`) e executado — nunca automático, nunca `--no-verify`.                          |
| **scan**   | Fecho da Fase 2 (`/implementa-plano`, stack Laravel) | `php artisan checkpoint:scan` — se houver FAILs, pausa e aguarda `[ok]` (regista aviso e prossegue) ou `[stop]` (utilizador corrige antes de continuar).                       |
| **②**      | Após todas as tarefas (`/implementa-plano`)          | Resumo por ficheiro + testes + scan, antes de avançar para a documentação.                                                                                                     |
| **D**      | Após o Debrief (`/documenta-implementacao`)          | O porquê de cada decisão tomada na issue — em especial as não óbvias — antes de propagar para o `system_spec`.                                                                 |
| **E**      | Antes do PR (`/publica-implementacao`)               | Capacidade de defender cada decisão do PR body perante um revisor.                                                                                                             |

Este é o mecanismo concreto de supervisão sobre trabalho gerado por IA neste repositório:
nenhum commit, alteração ao `system_spec` ou PR acontece sem uma decisão explícita e justificada
num destes pontos — não uma aprovação genérica.
