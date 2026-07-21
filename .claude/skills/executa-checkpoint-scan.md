# Skill: executa-checkpoint-scan

Executa o scan de segurança e qualidade do pacote Checkpoint e apresenta os resultados.
Pausa se existirem FAILs — aguarda confirmação do utilizador antes de prosseguir.

> **Categoria:** executa  
> **Usado em:** `/implementa-plano` (após `executa-testes`)  
> **Produz:** relatório de scan — verde ✅ ou alerta 🔴 com pausa para confirmação

## Contrato

**Input:** nenhum — corre `php artisan checkpoint:scan`

**Output:** resultado do scan — limpo ou alerta com pausa interactiva

---

## Comportamento

1. Executar `php artisan checkpoint:scan`
2. Se o comando não existir (erro de artisan) → avisar e continuar sem bloquear:
   ```
   ⚠️ checkpoint:scan não encontrado — pacote instalado? A continuar.
   ```
3. Analisar o output por linhas que contenham `FAIL`
4. **Se nenhum FAIL:**
   ```
   ✅ Checkpoint scan limpo
   Nenhuma vulnerabilidade ou falha detectada.
   ```
5. **Classificar os FAILs antes de decidir:**
   - Se o **único** FAIL for `Package Freshness (Supply Chain)`
     **E** `Composer CVE Audit` = PASS **E** `NPM CVE Audit` = PASS
     → falso positivo temporal conhecido (pacotes < 3 dias trazidos pelo
     `vendor:repair`/update de arranque; repetição de WRN-001).
     **Não pausar. Não registar novo WRN.** Mostrar 1 linha e continuar:
     ```
     ✅ Checkpoint scan — só "Package Freshness" (< 3 dias) a FAIL;
        CVE audits (Composer + NPM) PASS. Falso positivo temporal
        conhecido [WRN-001] — a continuar sem registar duplicado.
     ```
   - Caso contrário (qualquer outro FAIL, ou algum CVE audit a FAIL)
     → seguir o passo 6 (pausa + confirmação).
6. **Se existirem FAILs bloqueantes** → mostrar output completo e aguardar confirmação:
   ```
   🔴 Checkpoint scan — FAILs detectados

   [output completo do scan]

   Revê os resultados acima.
   Responde:
     [ok]   → registar aviso e continuar
     [stop] → parar aqui; corrige e reinicia manualmente
   ```
   - Se `stop` → parar; o utilizador resolve e reinicia o fluxo
   - Se `ok` → skill `regista-aviso` com o resumo dos FAILs e prosseguir

---

## Regras

- Nunca suprimir FAILs automaticamente — o utilizador confirma sempre
- `Package Freshness (Supply Chain)` isolado, com CVE audits (Composer + NPM) a
  PASS, é falso positivo temporal conhecido [WRN-001] — não bloqueia nem gera novo
  WRN. Qualquer CVE audit a FAIL, ou outro FAIL acompanhante, volta a bloquear.
- O aviso registado via `regista-aviso` deve incluir o número de FAILs e os nomes das verificações falhadas
