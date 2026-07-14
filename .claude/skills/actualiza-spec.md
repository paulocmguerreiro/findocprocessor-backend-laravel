# Skill: actualiza-spec

Actualiza os ficheiros `docs/system_spec/` com base no Debrief e no `SYSTEM_SPEC_MAP` do `CLAUDE.md`.

> **Categoria:** actualiza
> **Usado em:** `/documenta-implementacao` (passo 3)
> **Produz:** ficheiros `docs/system_spec/*.md` actualizados

## Contrato

**Input:**
- `docs/debriefs/YYYY-MM-DD-<slug>.md` — secção "SYSTEM_SPEC a actualizar"
- `SYSTEM_SPEC_MAP` do `CLAUDE.md` do repo activo — **única fonte** de "que tipo de alteração vai para
  que ficheiro"; esta skill não mantém cópia própria do mapa (ver "Fonte da verdade" abaixo)

**Output:** ficheiros `docs/system_spec/*.md` actualizados

**Usado em:** `/documenta-implementacao` (passo 3)

---

## Fonte da verdade — sem cópia do mapa aqui

O mapa "tipo de alteração → ficheiro a actualizar" vive **exclusivamente** em `SYSTEM_SPEC_MAP` no
`CLAUDE.md` do repo activo (cada stack — dotnet/laravel/angular — define o seu próprio). Ler sempre
essa tabela antes de decidir o ficheiro a actualizar. Manter uma segunda cópia do mapa aqui foi a
causa de, no passado, um enum feature-specific (`CampoOrdenacaoDocumentos`) ter ficado em
`02-shared/enums.md` em vez de `01-features/documento.md` — a cópia da skill não foi actualizada com
a mesma disciplina que o `CLAUDE.md`. Se o repo activo não tiver `SYSTEM_SPEC_MAP` no `CLAUDE.md`,
perguntar ao utilizador antes de assumir uma localização.

**Descoberta (Laravel):** ler `docs/system_spec/00-index.md` primeiro — lista todas as features,
modelos e ficheiros de infra existentes. Depois abrir apenas o ficheiro relevante.

---

## Regras de sustentabilidade (transversais a qualquer stack)

- Nova feature slice → criar `01-features/<slug>.md` (nunca acrescentar ao ficheiro de outra feature)
- `02-shared/` (ou equivalente do stack) → **apenas** componentes verdadeiramente partilhados
  (`app/Shared/`); um enum, DTO ou regra de uma feature específica vai para `01-features/<slug>.md`
  **mesmo que pareça genérico** — é o critério que falhou no caso do `CampoOrdenacaoDocumentos`
- `04-infra/` (ou equivalente) → um ficheiro por subsistema (Redis ≠ Jobs ≠ Repositories)
- **Obrigatório — ficheiro novo → actualizar `00-index.md`** no mesmo commit. Um ficheiro não
  registado no índice é invisível para a descoberta.

---

## Convenções de escrita (evitar desactualização e ruído)

- **Sem decoração de issue/PR solta** no corpo do texto (`(#94)`, "Issue #57" a meio de uma frase) —
  isso é o papel do `CHANGELOG.md` e do `git log`, não da system_spec. Manter o "porquê" de uma
  decisão não-óbvia quando ajuda a entender o desenho actual, mas sem o número da issue agarrado.
  Ex: preferir *"`restrictOnDelete()` — anteriormente `nullOnDelete`, decisão revertida por X"* a
  *"`restrictOnDelete()` (Issue #68)"*.
- **Não reproduzir blocos de código completos** que dupliquem o ficheiro fonte (corpo de um enum,
  `casts()`, esqueleto de uma classe) — descrever a semântica/valores em prosa ou tabela e apontar
  para o ficheiro (`ver app/Shared/Enums/X.php`). Um bloco de código no spec e o ficheiro real podem
  divergir silenciosamente; o ficheiro é sempre a fonte da verdade.
  **Excepção:** `02-shared/padroes-*.md` — aí o código É o produto (um template a copiar em código
  novo), não a documentação de uma instância já existente.
- Actualizar apenas as secções afectadas — não reescrever o ficheiro completo.
- Cada actualização é um commit separado: `📝 docs: actualizar system_spec após #N`.
- A system_spec regista o que **existe**, não o que está planeado nem o histórico de como lá chegou.

---

## Verificação obrigatória antes de terminar (checklist anti-esquecimento)

Antes de reportar esta skill como concluída, confirmar explicitamente — um a um, não por amostragem:

1. **Cobertura da secção "SYSTEM_SPEC a actualizar" do Debrief** — para cada ficheiro ali listado,
   confirmar que foi de facto aberto e alterado (ou justificar por que não precisou de alteração).
   Não avançar com ficheiros por abrir.
2. **`git diff` desta issue vs. `SYSTEM_SPEC_MAP`** — percorrer o `git diff <branch-base>...HEAD` (ou
   `git log` da branch) e confrontar cada tipo de alteração (nova Action, novo enum, novo Model, nova
   rota, nova config, etc.) com a tabela `SYSTEM_SPEC_MAP` do `CLAUDE.md`; qualquer tipo de alteração
   sem o ficheiro `docs/system_spec/` correspondente tocado é um esquecimento a corrigir antes de
   terminar — não confiar apenas na lista do Debrief, que pode estar incompleta.
3. **`00-index.md`** — se esta issue criou **qualquer** ficheiro novo em `docs/system_spec/` (Model,
   feature slice, infra, enum partilhado), confirmar que `00-index.md` tem uma linha nova na tabela
   correcta. Esta verificação é obrigatória mesmo que o Debrief não a mencione explicitamente.
4. **Tamanho dos ficheiros tocados** — para cada ficheiro `docs/system_spec/*.md` editado ou criado
   nesta passagem, contar as linhas (`wc -l`). Se ultrapassar **~200 linhas**, reportar uma nota
   informativa no output final (ver formato abaixo) — não desdobrar automaticamente; desdobrar é uma
   reorganização estrutural que passa sempre por `/ajusta-workflow`, nunca por esta skill.

Reportar o resultado desta checklist no output final da skill (ficheiros cobertos vs. ficheiros que
precisaram de correcção adicional face ao Debrief; ficheiros grandes sinalizados).

### Formato da nota de tamanho

```
⚠️ docs/system_spec/01-features/documento.md tem 289 linhas (> 200) — considerar desdobrar via /ajusta-workflow.
```

Se a **mesma** nota se repetir em execuções sucessivas desta skill sem que o ficheiro tenha sido
desdobrado entretanto, registar via `regista-aviso` (ver contrato dessa skill — campo `sugestão`
obrigatório e deve nomear `/ajusta-workflow` explicitamente).
