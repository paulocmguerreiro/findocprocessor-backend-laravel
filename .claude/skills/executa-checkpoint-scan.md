# Skill: executa-checkpoint-scan

Executa o scan de segurança e qualidade do pacote Checkpoint e apresenta os resultados.
Pausa se existirem FAILs — aguarda confirmação do utilizador antes de prosseguir.

> **Categoria:** executa  
> **Usado em:** `/implementa-plano` (após `executa-testes`, stack Laravel apenas)  
> **Produz:** relatório de scan — verde ✅ ou alerta 🔴 com pausa para confirmação

## Contrato

**Input:** stack activo (deve ser `laravel`)

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
5. **Se existirem FAILs** → mostrar output completo e aguardar confirmação:
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

- Executar apenas em stack Laravel
- Nunca suprimir FAILs automaticamente — o utilizador confirma sempre
- O aviso registado via `regista-aviso` deve incluir o número de FAILs e os nomes das verificações falhadas
