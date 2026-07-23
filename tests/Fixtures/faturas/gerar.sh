#!/usr/bin/env bash
# Gera faturas A4 realistas (SVG -> PDF texto nativo + PNG para OCR) usadas pela
# suite de testes E2E do pipeline. Ferramenta de desenvolvimento (host-only):
# requer `rsvg-convert` (librsvg). Os PDFs/PNGs resultantes ficam versionados —
# a suite E2E consome os ficheiros estáticos, nunca re-gera em runtime.
#
# Uso:  tests/Fixtures/faturas/gerar.sh
set -euo pipefail
cd "$(dirname "$0")"

command -v rsvg-convert >/dev/null || { echo "rsvg-convert em falta (brew install librsvg)"; exit 1; }

# Desenha uma fatura A4. O bloco do emitente é o letterhead (topo esquerdo); o
# destinatário aparece sob "Exmo(s). Senhor(es)" — deliberadamente SEM rótulos
# "Fornecedor"/"Cliente", como numa fatura real.
# Escapa os caracteres reservados de XML (&, <, >) num valor de texto.
xmlesc() { local s="$1"; s="${s//&/&amp;}"; s="${s//</&lt;}"; s="${s//>/&gt;}"; printf '%s' "$s"; }

gerar() {
  local out="$1" titulo="$2" num="$3" data="$4" venc="$5" \
        e_nome="$6" e_nif="$7" e_morada="$8" e_local="$9" \
        d_nome="${10}" d_nif="${11}" d_morada="${12}" d_local="${13}" \
        i1d="${14}" i1q="${15}" i1p="${16}" i1t="${17}" \
        i2d="${18}" i2q="${19}" i2p="${20}" i2t="${21}" \
        i3d="${22}" i3q="${23}" i3p="${24}" i3t="${25}" \
        sub="${26}" iva="${27}" total="${28}" iban="${29}"

  local v; for v in titulo num data venc e_nome e_nif e_morada e_local \
      d_nome d_nif d_morada d_local i1d i2d i3d iban; do
    printf -v "$v" '%s' "$(xmlesc "${!v}")"
  done

  cat > "${out}.svg" <<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 794 1123">
  <rect width="794" height="1123" fill="#ffffff"/>
  <style>
    text { font-family: 'Helvetica','Arial',sans-serif; fill:#1a1a1a; }
    .nome { font-size:22px; font-weight:bold; fill:#0b3d66; }
    .small { font-size:12px; fill:#444; }
    .lbl { font-size:11px; fill:#888; letter-spacing:1px; }
    .h { font-size:13px; font-weight:bold; fill:#ffffff; }
    .cell { font-size:13px; }
    .r { text-anchor:end; }
    .tt { font-size:13px; font-weight:bold; }
  </style>

  <!-- Letterhead do emitente -->
  <text x="60" y="80" class="nome">${e_nome}</text>
  <text x="60" y="104" class="small">${e_morada}</text>
  <text x="60" y="122" class="small">${e_local}</text>
  <text x="60" y="140" class="small">Contribuinte n.º ${e_nif}</text>

  <!-- Bloco do documento (direita) -->
  <rect x="500" y="55" width="234" height="96" fill="#0b3d66"/>
  <text x="617" y="92" text-anchor="middle" style="font-size:26px;font-weight:bold;fill:#ffffff;">${titulo}</text>
  <text x="617" y="118" text-anchor="middle" style="font-size:13px;fill:#cfe0ee;">N.º ${num}</text>
  <text x="617" y="138" text-anchor="middle" style="font-size:12px;fill:#cfe0ee;">Data: ${data}   Venc.: ${venc}</text>

  <!-- Destinatário (sem rótulo de papel) -->
  <text x="60" y="215" class="lbl">EXMO(S). SENHOR(ES)</text>
  <text x="60" y="240" style="font-size:16px;font-weight:bold;">${d_nome}</text>
  <text x="60" y="262" class="small">${d_morada}</text>
  <text x="60" y="280" class="small">${d_local}</text>
  <text x="60" y="298" class="small">Contribuinte n.º ${d_nif}</text>

  <!-- Tabela de linhas -->
  <rect x="60" y="340" width="674" height="30" fill="#0b3d66"/>
  <text x="72" y="360" class="h">Descrição</text>
  <text x="512" y="360" class="h r">Qt.</text>
  <text x="620" y="360" class="h r">Preço</text>
  <text x="722" y="360" class="h r">Total</text>

  <text x="72" y="396" class="cell">${i1d}</text>
  <text x="512" y="396" class="cell r">${i1q}</text>
  <text x="620" y="396" class="cell r">${i1p}</text>
  <text x="722" y="396" class="cell r">${i1t}</text>
  <line x1="60" y1="410" x2="734" y2="410" stroke="#dddddd"/>

  <text x="72" y="432" class="cell">${i2d}</text>
  <text x="512" y="432" class="cell r">${i2q}</text>
  <text x="620" y="432" class="cell r">${i2p}</text>
  <text x="722" y="432" class="cell r">${i2t}</text>
  <line x1="60" y1="446" x2="734" y2="446" stroke="#dddddd"/>

  <text x="72" y="468" class="cell">${i3d}</text>
  <text x="512" y="468" class="cell r">${i3q}</text>
  <text x="620" y="468" class="cell r">${i3p}</text>
  <text x="722" y="468" class="cell r">${i3t}</text>
  <line x1="60" y1="482" x2="734" y2="482" stroke="#dddddd"/>

  <!-- Totais -->
  <text x="620" y="522" class="cell r">Subtotal:</text>
  <text x="722" y="522" class="cell r">${sub}</text>
  <text x="620" y="546" class="cell r">IVA (23%):</text>
  <text x="722" y="546" class="cell r">${iva}</text>
  <rect x="512" y="560" width="222" height="34" fill="#eef3f8"/>
  <text x="620" y="583" class="tt r">TOTAL:</text>
  <text x="722" y="583" class="tt r">${total}</text>

  <!-- Rodapé -->
  <line x1="60" y1="1000" x2="734" y2="1000" stroke="#cccccc"/>
  <text x="60" y="1024" class="small">Pagamento por transferência — IBAN ${iban}</text>
  <text x="60" y="1042" class="small">Documento processado por computador. Obrigado pela preferência.</text>
</svg>
SVG

  rsvg-convert -f pdf -o "${out}.pdf" "${out}.svg"
  rsvg-convert -f png -d 150 -p 150 -o "${out}.png" "${out}.svg"
  echo "gerado: ${out}.pdf / ${out}.png"
}

EMPRESA_MAE_NOME="FinDoc Serviços, Lda."
EMPRESA_MAE_NIF="501234567"
EMPRESA_MAE_MORADA="Avenida da Liberdade, 200"
EMPRESA_MAE_LOCAL="1250-096 Lisboa"

# Cenário A — empresa mãe = CLIENTE (recebe do fornecedor); extrai o fornecedor.
gerar fatura-compra "FATURA" "FT 2026/00042" "2026-07-15" "2026-08-14" \
  "Papelaria Central, Lda." "502777888" "Rua das Flores, 123" "1000-001 Lisboa" \
  "$EMPRESA_MAE_NOME" "$EMPRESA_MAE_NIF" "$EMPRESA_MAE_MORADA" "$EMPRESA_MAE_LOCAL" \
  "Resmas de papel A4 (caixa)" "5" "5,00" "25,00" \
  "Toner para impressora laser" "2" "37,72" "75,45" \
  "Material de escritório diverso" "1" "23,00" "23,00" \
  "123,45" "28,39" "151,84" "PT50 0002 0123 1234 5678 9015 4"

# Cenário B — empresa mãe = FORNECEDOR (emite ao cliente); extrai o cliente.
gerar fatura-venda "FATURA" "FT 2026/00108" "2026-07-18" "2026-08-17" \
  "$EMPRESA_MAE_NOME" "$EMPRESA_MAE_NIF" "$EMPRESA_MAE_MORADA" "$EMPRESA_MAE_LOCAL" \
  "Construções Alves & Filhos, Lda." "503888999" "Rua do Comércio, 45" "4000-100 Porto" \
  "Processamento documental (avença mensal)" "1" "450,00" "450,00" \
  "Consultoria de arquivo digital" "8" "35,00" "280,00" \
  "Licença de utilizador adicional" "3" "15,00" "45,00" \
  "775,00" "178,25" "953,25" "PT50 0002 0123 9999 8888 7777 6"

# Cenário C — extrai AMBOS (empresa mãe é o destinatário, mas o tipo espera as duas partes).
gerar fatura-servicos "FATURA" "2026/SVC/771" "2026-07-20" "2026-08-19" \
  "TechSoft Solutions, Lda." "504111222" "Parque das Nações, Lote 7" "1990-095 Lisboa" \
  "$EMPRESA_MAE_NOME" "$EMPRESA_MAE_NIF" "$EMPRESA_MAE_MORADA" "$EMPRESA_MAE_LOCAL" \
  "Subscrição plataforma SaaS (anual)" "1" "1.200,00" "1.200,00" \
  "Integração e configuração inicial" "1" "350,00" "350,00" \
  "Suporte prioritário" "1" "150,00" "150,00" \
  "1.700,00" "391,00" "2.091,00" "PT50 0002 0123 5555 4444 3333 2"

echo "OK — 3 cenários gerados em $(pwd)"
