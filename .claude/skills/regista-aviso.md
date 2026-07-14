# Skill: regista-aviso

Regista um erro ou anomalia de processo em `docs/process-warnings.md` com ID sequencial.

> **Categoria:** regista  
> **Usado em:** qualquer command ou skill quando ocorre uma falha de processo  
> **Produz:** entrada `WRN-NNN` em `docs/process-warnings.md`

## Contrato

**Input:**
- `descrição`: descrição do problema encontrado
- `fase`: em que fase do workflow ocorreu
- `categoria`: tag curta e estável (ex: `system-spec-manutencao`, `testes`, `scan-seguranca`) —
  **obrigatória**; permite a outro command/skill (ex: `/ajusta-workflow`) filtrar avisos relevantes
  sem ter de ler a descrição inteira de todas as entradas
- `comando_falhado`: o comando que falhou (opcional)
- `sugestão`: como resolver — **obrigatória quando `categoria` é acionável por outro comando** (ex.
  `system-spec-manutencao`); nesse caso tem de nomear explicitamente o comando que resolve (ex.
  `/ajusta-workflow`) e o tipo de mudança esperado, para esse comando poder agir sem precisar de
  reconstruir o raciocínio a partir do zero. Nos restantes casos, opcional.

**Output:** entrada adicionada a `docs/process-warnings.md`

**Usado em:** qualquer command ou skill quando ocorre uma falha de processo

---

## Formato da entrada

```
WRN-NNN | YYYY-MM-DDTHH:MM:SSZ | <fase> | <categoria> | STATUS: PENDENTE
- Descrição: <descrição clara do problema>
- Comando: <comando que falhou, se aplicável>
- Sugestão: <como resolver — nomear o comando accionável e o que fazer, quando a categoria for accionável>
```

Quando resolvido, actualizar para `STATUS: RESOLVIDO | YYYY-MM-DDTHH:MM:SSZ`.

### Exemplo — categoria accionável por `/ajusta-workflow`

```
WRN-012 | 2026-07-14T00:00:00Z | documenta (#94) | system-spec-manutencao | STATUS: PENDENTE
- Descrição: docs/system_spec/01-features/documento.md com 289 linhas (> 200), repetido em 2
  execuções sucessivas de actualiza-spec sem ter sido desdobrado.
- Comando: skill actualiza-spec (verificação de tamanho)
- Sugestão: invocar /ajusta-workflow para separar o pipeline de background (Marcar*, Reivindicar,
  Triar, ExecutorTransicaoDocumento) para um novo docs/system_spec/01-features/documento-pipeline.md,
  mantendo em documento.md apenas a superfície HTTP.
```

---

## Quando usar
- Pré-condição de um command não satisfeita
- Comando `gh` ou `git` falhou após retry
- Testes falharam após 3 tentativas
- Branch ou ficheiro esperado não existe
- Qualquer situação que impeça o workflow de continuar normalmente

---

## Regras
- IDs sequenciais: `WRN-001`, `WRN-002`, etc.
- Verificar o maior ID existente antes de criar um novo
- Ao iniciar qualquer sessão de trabalho, verificar avisos `PENDENTE` em `docs/process-warnings.md`
- `categoria` estável e reutilizada entre entradas semelhantes (não inventar uma nova categoria por
  entrada) — permite `grep` futuro; ex: `system-spec-manutencao`, `checkpoint-scan`, `testes-flaky`
