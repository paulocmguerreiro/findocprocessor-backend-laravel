# Skill: regista-aviso

Regista um erro ou anomalia de processo em `docs/process-warnings.md` com ID sequencial.

> **Categoria:** regista  
> **Usado em:** qualquer command ou skill quando ocorre uma falha de processo  
> **Produz:** entrada `WRN-NNN` em `docs/process-warnings.md`

## Contrato

**Input:**
- `descrição`: descrição do problema encontrado
- `fase`: em que fase do workflow ocorreu
- `comando_falhado`: o comando que falhou (opcional)
- `sugestão`: como resolver (opcional)

**Output:** entrada adicionada a `docs/process-warnings.md`

**Usado em:** qualquer command ou skill quando ocorre uma falha de processo

---

## Formato da entrada

```
WRN-NNN | YYYY-MM-DDTHH:MM:SSZ | <fase> | STATUS: PENDENTE
- Descrição: <descrição clara do problema>
- Comando: <comando que falhou, se aplicável>
- Sugestão: <como resolver>
```

Quando resolvido, actualizar para `STATUS: RESOLVIDO | YYYY-MM-DDTHH:MM:SSZ`.

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
