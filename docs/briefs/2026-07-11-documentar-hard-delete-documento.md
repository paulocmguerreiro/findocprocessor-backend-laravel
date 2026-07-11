# Brief: Documentar decisão de hard-delete do Documento no system_spec

**Issue:** #92
**Data:** 2026-07-11
**Branch:** fix/documentar-hard-delete-documento

## Contexto

`02-shared/soft-delete.md` já estabelece o critério geral de quando usar SoftDelete — "só faz sentido em tabelas pai/transversais... referenciadas por FK de outras tabelas" — e lista exemplos de "Usar" (`entidades`, `categorias_documento`, `users`) e "Não usar" (tabelas folha genéricas como `etapas_documento`, registos de auditoria, pivots). O que falta é registar **explicitamente** que `Documento` e `TipoDocumento` — os dois modelos concretos do domínio que hoje fazem hard-delete puro (`$model->delete()` sem `SoftDeletes`) — foram uma decisão deliberada e não um esquecimento, com o racional de negócio (documento incorrecto = eliminar + re-submeter) e a verificação de integridade referencial que a suporta. Sem este registo, uma issue futura pode adicionar `SoftDeletes` a `Documento` ou `TipoDocumento` por assumir que o Padrão B é universal a todo o domínio.

Verificação de código feita nesta sessão (confirma o enunciado da issue):
- `EliminarDocumentoAction::handle()` (`app/Features/Documento/Eliminar/EliminarDocumentoAction.php`) faz `$documento->delete()` — hard delete, `Documento` não usa o trait `SoftDeletes`. O ficheiro em disco é apagado após o commit da transacção.
- `etapas_documento.id_documento` tem `cascadeOnDelete()` (`database/migrations/2026_06_26_100641_create_etapas_documento_table.php:17`) — o histórico (`EtapaDocumento`) cai automaticamente quando o `Documento` é eliminado.
- Nenhuma migration declara FK para `tipos_documento` — confirmado por grep em `database/migrations/*.php`; a tabela não é referenciada por ninguém.
- `EliminarTipoDocumentoAction::handle()` (`app/Features/TipoDocumento/Eliminar/EliminarTipoDocumentoAction.php`) também faz `delete()` simples — mesma situação.

## O que muda

- `docs/system_spec/02-shared/soft-delete.md` — secção "Quando usar SoftDelete" / "Não usar SoftDelete": acrescentar `documentos` e `tipos_documento` à lista de "Não usar", com o racional específico (hard-delete deliberado: documento incorrecto é removido directamente e re-submetido via re-upload; contraste explícito com `entidades`/`categorias_documento` — soft-delete porque são referenciadas por `documentos` — e com `users` — soft-delete + anonimização RGPD).
- `docs/system_spec/03-models/documento.md` — secção "Notas arquitecturais": nota curta a apontar para a decisão em `02-shared/soft-delete.md` (o ficheiro já documenta "Sem Repository" nesta secção; segue o mesmo padrão de nota breve + link).
- `docs/system_spec/03-models/tipo-documento.md` — mesma nota curta (âmbito alargado, decisão confirmada no Checkpoint A: `TipoDocumento` está na mesma situação de facto que `Documento` — hard-delete, sem FKs a apontar para `tipos_documento` — por isso a mesma armadilha fica coberta nesta issue em vez de ficar para issue futura).

## O que NÃO muda

- Nenhum ficheiro de código (`app/`, `database/migrations/`, `routes/`) é alterado — issue estritamente doc-only.
- Nenhuma migration nova, nenhuma alteração a `EliminarDocumentoAction`/`EliminarTipoDocumentoAction`.
- Nenhuma alteração ao `00-index.md` — os ficheiros `02-shared/soft-delete.md` e `03-models/documento.md` já estão indexados; esta issue só edita conteúdo existente, não cria ficheiro novo.
- Sem testes novos — não há comportamento executável a cobrir.

## Riscos identificados

- **Assimetria entre issue e código real:** a issue pede a nota apenas em `03-models/documento.md`, mas a verificação de código confirma que `TipoDocumento` está na mesma situação (hard-delete, sem FKs a apontar para `tipos_documento`). Documentar a decisão só para `Documento` e não para `TipoDocumento` deixa a mesma armadilha aberta para esse modelo — registar como questão em aberto para a Spec decidir o âmbito exacto.
- **Consistência com o padrão de "Não usar SoftDelete" já existente:** a lista actual em `soft-delete.md` usa exemplos genéricos por categoria (tabelas folha, auditoria, pivots); `documentos` não é uma tabela folha (é pai de `etapas_documento`) nem puramente transversal — a redacção tem de deixar claro que a exclusão de `documentos` da lista de SoftDelete não é por ser "folha", mas por decisão de negócio explícita (re-upload substitui correcção), para não gerar confusão com o critério geral da secção.

## Questões em aberto

Nenhuma — âmbito confirmado no Checkpoint A (2026-07-11): a nota de hard-delete deliberado é adicionada tanto a `03-models/documento.md` como a `03-models/tipo-documento.md`, alargando ligeiramente o âmbito literal da issue #92 para cobrir a mesma situação de facto em `TipoDocumento`.
