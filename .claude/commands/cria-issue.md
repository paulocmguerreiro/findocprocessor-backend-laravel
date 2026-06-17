---
description: Cria Issue no GitHub com análise de impacto e verificação RGPD/NIS2
allowed-tools: [Bash, Read]
---

# /cria-issue

Cria uma Issue no GitHub com análise de impacto, invariantes de arquitectura e verificação RGPD/NIS2.

## Argumentos

- `$ARGUMENTS`: descrição da funcionalidade ou bug (ex: `"implementar DocumentRepository com EF Core"`) - opcional; no caso de omissão solicita neste momento uma descrição para criar a issue.

## Passos

1. Skill `escolhe-issue` em modo verificação de conflitos — listar issues abertas similares
2. **MCP `search-docs`** — pesquisar documentação do conceito principal descrito na issue:
   - 1-2 queries temáticas (ex: `"policy authorization"`, `"pagination query params"`)
   - Objectivo: detectar invariantes técnicas e riscos reais antes de escrever o body
   - Se envolver modelos ou queries: executar também `database-schema`
3. Analisar impacto:
    - Conflitos com issues existentes?
    - `docs/system_spec/*.md` afectados?
    - Novas entidades, estados, endpoints ou ajustes no OpenAPI?
    - Dependências de outras issues?
4. Verificar invariantes de arquitectura por stack (ver skill `escreve-spec` — secção stack)
5. Verificar contrato OpenAPI se a issue afecta endpoints ou schemas
6. Verificar RGPD/NIS2 (dados pessoais, nova superfície de ataque, ficheiros, logs)
7. Gerar body da issue e propor ao utilizador:
    ```
    📋 Issue proposta:
    Título: <type>: <descrição>
    Labels: type:<t>, stack:<s>, scope:<sc>, prio:<p>
    [body completo]
    Criar? [s / edita / cancela]
    ```
8. Se `s` → executar:
    ```bash
    gh issue create --repo $GITHUB_REPO --title "..." --body "..." --label "..." --milestone "..."
    ```
9. Detectar impacto cross-repo — se existir, propor issues linked nos repos afectados
10. Mostrar output:
    ```
    ✅ Issue #N criada
    URL: <url>
    Próximo: /planeia-issue #N
    ```

## Formato do body da issue

```markdown
## Contexto

[Porquê esta issue existe — o problema, não a solução]

## Critérios de aceitação

- [ ] CA-01: ...

## Impacto técnico

- Afecta: [camadas/features]
- SYSTEM_SPEC a actualizar: [docs/system_spec/...]
- Dependências: [#N | "nenhuma"]

## Invariantes em risco

[Lista ou "nenhum"]

## Contrato OpenAPI

- openapi.yaml afectado: [sim → detalhe | não]
- Breaking change: [sim → repos listados | não]

## Verificação RGPD/NIS2

- Dados pessoais: [sim — detalhe | não]
- Superfície de ataque: [alterada — detalhe | inalterada]

## Fora de âmbito

[O que NÃO será feito]
```

## Labels

| Label type | Quando                                   |
| ---------- | ---------------------------------------- |
| `feat`     | nova funcionalidade                      |
| `fix`      | correcção de bug                         |
| `refactor` | refactoring sem mudança de comportamento |
| `docs`     | documentação                             |
| `chore`    | configuração, CI, dependências           |
