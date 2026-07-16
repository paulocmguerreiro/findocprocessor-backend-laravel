# System Spec — Shared: Regras de Transição do Documento

> Regras `Regra*` específicas da máquina de estados do `Documento` — padrão geral e distinção
> face a Action em `02-shared/regras-negocio.md`. Extraído desse ficheiro (WRN-033) por limiar de
> tamanho (~200 linhas) após a unificação da máquina de estados (#110).

---

### `RegraTransicaoEstado`

**Ficheiro:** `app/Features/Documento/Transicao/RegraTransicaoEstado.php`

**Invariante:** Toda a mudança de estado do `Documento` tem de constar do mapa central De→Para. Transições
inválidas lançam `TransicaoInvalidaException` (→ 422). Nunca `if ($doc->estado == ...)`.

**Activação:** Chamada por `ExecutorTransicaoDocumento` antes de qualquer persistência.

**Invocada por:** `ExecutorTransicaoDocumento` (que é usado pelas 10 Actions de transição).

**Mapa central (match exaustivo sem `default`):**

```
Pendente        → AnaliseMalware
AnaliseMalware  → AnaliseTexto | Perigoso | Erro
AnaliseTexto    → AnaliseIaLocal | AnaliseOcr | Erro
AnaliseOcr      → AnaliseIaLocal | Erro
AnaliseIaLocal  → Processado | AnaliseCloud | Perigoso | Erro
AnaliseCloud    → Processado | Erro | Perigoso
Erro            → Pendente        (reprocessamento reabre o pipeline)
Processado      → Processado      (correcção — self-loop)
Perigoso        → (terminal, sem saída)
```

**Nota:** O `match` em PHP 8.5 sem `default` garante que se um novo `case` de `EstadoDocumento` for
adicionado, o Larastan 9 dá erro de compilação — impossível esquecer um caso no mapa.

---

### `RegraMoverFicheiro`

**Ficheiro:** `app/Features/Documento/Transicao/RegraMoverFicheiro.php`

**Invariante:** `status ↔ disco_storage ↔ nome_ficheiro_storage` ficam sempre consistentes após uma
transição. O ficheiro é movido entre discos distintos com verificação do valor de retorno (discos
configurados com `'throw' => false`).

**Activação:** Chamada por `ExecutorTransicaoDocumento` antes da transação.

**Invocada por:** `ExecutorTransicaoDocumento`.

**Implementação:** `Storage::disk($destino)->put($nome, Storage::disk($origem)->get($nome))` + `delete()`
na origem. Verifica o retorno de cada operação e lança em falha. Compensação best-effort em excepção:
tenta mover de volta para a origem.

**Mapa estado→disco** (ver `02-shared/estados.md` para tabela completa):
- `Pendente`, `AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr` → `entrada`
- `AnaliseIaLocal`, `AnaliseCloud` → `enviado`
- `Processado` → `processado`
- `Erro` → `erro`
- `Perigoso` → `perigoso`

**Limitação:** em dupla falha (mover OK + compensação falha), o ficheiro fica no disco destino com a
BD revertida para o estado anterior — reconciliada automaticamente por `ReconciliarFicheirosJob`,
ver `RegraReconciliarLocalizacaoFicheiro`.

**Nota:** `discoParaEstado(EstadoDocumento $estado): string` é `public` — reutilizado por
`RegraReconciliarLocalizacaoFicheiro` para listar os discos conhecidos sem duplicar o mapa.

---

### `RegraEliminarExtracaoTerminal`

**Ficheiro:** `app/Features/Documento/Transicao/RegraEliminarExtracaoTerminal.php`

**Invariante:** Um `Documento` que atinge um **estado terminal** não retém `ExtracaoDocumento` — o
scratch space da extracção (`texto_extraido`/`dados_json`, PII) é eliminado por minimização de dados
(RGPD). Estados terminais para este efeito: `Processado`, `Erro`, `Perigoso`.

**Activação:** Chamada por `ExecutorTransicaoDocumento` **dentro** da transacção, após o
`documento->update(...)`, sempre que o novo estado é terminal. Idempotente (mass-delete por
`id_documento`: apaga 0 ou mais linhas, sem erro se não houver nenhuma).

**Invocada por:** `ExecutorTransicaoDocumento`.

**Terminal de RGPD ≠ terminal do grafo:** `Erro` tem a aresta `Erro → Pendente` (reprocessamento),
logo **não** é terminal no grafo de `RegraTransicaoEstado`; é, ainda assim, terminal para efeitos de
eliminação de extracção (ao reabrir, o documento recomeça o pipeline sem herdar dados antigos). Esta
diferença de definição é a razão de a regra ter `match` próprio e teste exaustivo, em vez de reusar o
mapa de transições.

**Implementação:** `match` exaustivo sem `default` sobre os 9 estados (terminal → `delete()` por
`where('id_documento', ...)`; não-terminal → no-op). A `ReprocessarDocumentoAction` mantém ainda um
`delete()` defensivo idempotente como rede de segurança (a linha já foi eliminada ao entrar em `Erro`).

---

### `RegraNomearProcessado`

**Ficheiro:** `app/Features/Documento/Transicao/RegraNomearProcessado.php`

**Invariante:** O nome de ficheiro de um `Documento` processado segue o formato canónico
`yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`, derivado dos dados de domínio.

**Activação:** Sempre que um documento transiciona para `Processado` ou quando uma correcção altera
o slug (fornecedor, categoria ou data).

**Invocada por:** `TransicionarProcessadoDocumentoAction`, `CorrigirDocumentoAction`.

**Geração:** `Str::slug($nomeFornecedor)`, `Str::slug($nomeCategoria)`; data de `data_documento`;
extensão preservada de `nome_ficheiro_original`.

**Limitação:** dois documentos com o mesmo fornecedor, categoria e data terão o mesmo nome canónico
— `Storage::put()` sobrepõe silenciosamente. Diferido.

---

### `RegraReconciliarLocalizacaoFicheiro`

**Ficheiro:** `app/Features/Documento/Transicao/RegraReconciliarLocalizacaoFicheiro.php`

**Invariante:** `disco_storage`/`nome_ficheiro_storage` de um `Documento` reflectem a localização
real do ficheiro. Quando a compensação best-effort de `ExecutorTransicaoDocumento` falha, a BD pode
ficar dessincronizada do filesystem — ver `01-features/documento-reconciliacao.md` (Contrato de
atomicidade ficheiro↔BD).

**Activação:** Chamada por `ReconciliarFicheirosJob`, agendado a cada 5 min, sobre `Documento`s
presos num estado transitório há mais tempo que `config('pipeline.reconciliacao_limiar_minutos')`.

**Invocada por:** `ReconciliarFicheirosJob`.

**Implementação:** verifica primeiro `Storage::disk($documento->disco_storage)->exists($nome)`; se
ausente, itera os 4 discos restantes (mapa de `RegraMoverFicheiro::discoParaEstado()`, sem
duplicação), lê cada ficheiro candidato com o mesmo nome e compara `hash('sha256', $conteudo)` com
`$documento->hash_sha256` para confirmar identidade. Devolve `ResultadoReconciliacaoFicheiro`
(`coerente`, `encontrado`, `disco`, `nome`).

**Limitação:** só verifica os 5 discos conhecidos com o nome persistido na BD — não cobre o caso
de o ficheiro ter sido simultaneamente movido **e** renomeado (fora do âmbito desta issue; o único
rename existente, `RegraNomearProcessado`, ocorre dentro da mesma transição que grava o novo nome
na BD, não deixando janela de inconsistência de nome).
