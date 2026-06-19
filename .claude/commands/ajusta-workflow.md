---
description: "Classifica um ajuste de workflow/processo e aplica-o no local correcto (system_spec vs commands/skills vs CLAUDE.md)"
allowed-tools: [Bash, Read, Edit, Write]
---

# /ajusta-workflow

Invocar quando algo no workflow ou no sistema precisa de ser ajustado — algo não funcionou, foi esquecido, ou uma convenção melhorou. O comando **classifica** a natureza da mudança e aplica-a no **local certo**, em vez de a despejar sempre no `CLAUDE.md` ou na memória.

## Problema que resolve

Informação **estrutural da aplicação** (padrões, contratos, convenções de código, ciclos de estado) deve viver em `docs/system_spec/`. Mudanças de **comportamento de workflow** (como o agente age, checkpoints, sequência de passos) devem viver em `.claude/commands/` ou `.claude/skills/`. Ajustes mal colocados (tudo em `CLAUDE.md`) tornam o spec incompleto e o `CLAUDE.md` insustentável.

## Argumentos

- `$ARGUMENTS`: descrição do problema/melhoria — opcional. Se omitido, perguntar:
  > "O que precisa de ser ajustado? Descreve o que não funcionou, foi esquecido, ou a convenção que melhorou."

## Heurística principal

> **"Se um developer novo que lê só o `docs/system_spec/` ficaria a saber isto → vai para system_spec. Se só faz sentido para o agente que executa o workflow → vai para commands/skills."**

## Passos

### 1 — Receber a descrição

Obter de `$ARGUMENTS` ou perguntar interactivamente. Reformular numa frase única para confirmar o entendimento.

### 2 — Classificar a natureza da mudança

Determinar o(s) tipo(s):

| Tipo | Natureza | Destino |
|---|---|---|
| **A — Instrução de agente / comportamento de workflow** | Como o agente age, checkpoints, sequência de passos, quando perguntar/parar | `.claude/commands/` ou `.claude/skills/` |
| **B — Conhecimento estrutural da aplicação** | Padrões arquitecturais, contratos, ciclos de estado, como o sistema funciona | `docs/system_spec/` (secção relevante) |
| **C — Convenção de codificação** | Naming, tipagem, estrutura de ficheiros, padrões que todo o código de domínio segue | `docs/system_spec/02-shared/` |
| **D — Misto** | Componentes em múltiplos locais | combinação dos acima |

Tabela de decisão detalhada:

| Se a mudança é sobre… | Vai para… |
|---|---|
| Passos do workflow (planear, implementar, publicar) | `.claude/commands/<comando>.md` |
| Como uma skill executa uma tarefa específica | `.claude/skills/<skill>.md` |
| O que o sistema faz / como funciona | `docs/system_spec/` |
| Uma convenção de código que todo o código de domínio segue | `docs/system_spec/02-shared/` |
| Um padrão obrigatório numa camada (model, action, repo) | `docs/system_spec/` (ficheiro da camada) |
| Comportamento do agente (quando perguntar, quando parar) | `CLAUDE.md` **apenas** se não couber em commands/skills |

Mapa de destinos em `docs/system_spec/` (ler `docs/system_spec/00-index.md` para confirmar):

| Tema | Ficheiro |
|---|---|
| Padrões de Actions / autorização | `02-shared/padroes-acoes.md` |
| Padrões de DTOs | `02-shared/padroes-dtos.md` |
| Tipagem (`@throws`, array shape) | `02-shared/padroes-tipagem.md` |
| Nomenclatura PT/EN | `02-shared/convencoes-nomenclatura.md` |
| Contratos por camada | `02-shared/contratos-por-camada.md` |
| Enums / HTTP / Estados | `02-shared/enums.md` · `http.md` · `estados.md` |
| Convenções de Models | `03-models/00-convencoes-models.md` |
| Transacções / Repositories / Jobs / Cache / APIs | `04-infra/<concern>.md` |
| Testes | `07-testing.md` |

### 3 — Identificar o(s) ficheiro(s) específico(s)

Listar o(s) ficheiro(s) concreto(s) a alterar, **com justificação** para cada um (porque é esse o local correcto segundo a heurística). Se for Tipo B/C, ler primeiro `docs/system_spec/00-index.md` para descobrir o ficheiro certo.

### 4 — Propor as alterações (diff conceptual)

Mostrar, por ficheiro:
- O que **adicionar** (secção/linhas novas).
- O que **modificar** (antes → depois).
- Se a mudança cria um ficheiro novo em `docs/system_spec/`, indicar que `00-index.md` será actualizado.

### 5 — Aguardar confirmação

Apresentar o plano e **parar**:
```
📋 Ajuste proposto
Tipo: [A / B / C / D]
Ficheiro(s):
  - <caminho> — <o que muda> (justificação: <…>)
Aplicar? [s / edita / cancela]
```
Não aplicar nada antes de resposta explícita do utilizador. "continua" ou "tenta novamente" **não** são aprovação.

### 6 — Executar

Aplicar as alterações nos ficheiros identificados (Edit/Write). Seguir o estilo dos ficheiros existentes (tabelas, code blocks PHP, secções `##`, Português de Portugal). Para system_spec: documentar *o que existe*, não *o que está planeado*.

### 7 — Registar no índice

Sempre que um ficheiro **novo** é criado em `docs/system_spec/` (qualquer tipo — feature, Model, infra, shared), o `docs/system_spec/00-index.md` é **obrigatoriamente** actualizado com uma linha na tabela correcta. A "porta de entrada" lista sempre tudo o que existe — um ficheiro não registado no índice é invisível para a descoberta. O mesmo se aplica quando a estrutura do spec muda. Se um novo tipo de alteração passou a ter destino fixo, considerar actualizar o `SYSTEM_SPEC_MAP` do `CLAUDE.md`.

### 8 — Output final

```
✅ Ajuste aplicado
Tipo: [A / B / C / D]
Alterados:
  - <caminho>
[Índice actualizado: 00-index.md]  (se aplicável)
```

## Exemplos de uso

- `/ajusta-workflow "esquecemo-nos de declarar @throws \Throwable no handle() das Actions com transacção"`
  → Tipo C (tipagem) → `02-shared/padroes-tipagem.md` + `04-infra/transactions.md`.

- `/ajusta-workflow "o checkpoint de commit deve recusar 'continua' como aprovação"`
  → Tipo A (comportamento) → `.claude/skills/pausa-checkpoint.md` (e/ou `propoe-commit.md`).

- `/ajusta-workflow "todas as listagens têm de usar cursorPaginate, nunca OFFSET"`
  → Tipo C/B → `02-shared/http.md` (convenção já documentada; reforçar/expandir).

- `/ajusta-workflow "ao planear, ler sempre 00-index.md antes de abrir ficheiros de spec"`
  → Tipo A (workflow) → `.claude/commands/planeia-issue.md` / skill `escreve-spec.md`.
