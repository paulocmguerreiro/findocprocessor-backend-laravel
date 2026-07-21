# Skill: executa-testes

Executa a suite de testes do projecto e reporta o resultado. Auto-retry até 3x em caso de falha.

> **Categoria:** executa  
> **Usado em:** `/implementa-plano` (após todas as tarefas)  
> **Produz:** resultado dos testes — verde ✅ ou vermelho ⚠️ com detalhe

## Contrato

**Input:** `TEST_RUNNER` do `CLAUDE.md` (`composer test`)

**Output:** resultado dos testes (verde / vermelho com detalhe)

**Usado em:** `/implementa-plano` (após todas as tarefas)

---

## Comando

`composer test` (definido em `TEST_RUNNER` no `CLAUDE.md`; padrão de ficheiros `**/*.php`).

> `composer test` é a pipeline completa — inclui Rector (dry-run), Pint, testes arquitecturais, type-coverage (Pest, 100%), PHPStan nível 9 e testes com cobertura de 100%. Equivale a `test:lint → test:arch → test:type-coverage → test:types → test:coverage`.

---

## O que testar

- **Unit:** Actions com interfaces mockadas (Mockery)
- **Unit:** State transitions
- **Feature:** Endpoints com `RefreshDatabase`

---

## Comportamento

1. Executar o `TEST_RUNNER` do `CLAUDE.md`
2. Se falhar → aguardar 2s e tentar novamente (máximo 3 tentativas)
3. Se persistir após 3 tentativas → skill `regista-aviso` com WRN-NNN + avisar utilizador:
   ```
   ⚠️ Testes falharam após 3 tentativas — registado como WRN-NNN
   Detalhe:
   [output dos testes]
   Resolve antes de continuar para /documenta-implementacao
   ```
4. Se verde → reportar:
   ```
   ✅ Testes a verde
   <N> testes passaram em <Xs>
   ```

---

## Regras
- Testes são escritos na mesma tarefa que o código (nunca numa tarefa separada)
- Nunca mockar a base de dados em testes de integração
- Nomes descritivos: `"deve_retornar_409_quando_estado_invalido"`
