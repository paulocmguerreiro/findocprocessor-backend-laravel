# System Spec — Shared: Padrões de Value Objects (VO)

> Distinto de `02-shared/padroes-dtos.md` (DTOs de Feature, construtor público, validação
> incondicional). Este ficheiro cobre VOs de **resultado/veredicto** com múltiplos estados
> válidos, construtor privado e factories estáticas nomeadas — ex.: `ResultadoAnaliseMalware`,
> `ResultadoExtracao`, `ResultadoExtracaoIA`.

## Diferença estrutural face a um DTO de Feature

| | DTO de Feature (`padroes-dtos.md`) | VO de resultado/veredicto (este ficheiro) |
|---|---|---|
| Construtor | público | **privado** — só instanciável via factories nomeadas |
| Criação | `new self(...)` directo ou `fromRequest()` | `NomeDoVo::estado(...)` (uma factory por estado válido) |
| Validação | incondicional (campo nunca vazio) | **condicionada ao veredicto/estado** (ex.: `assinatura` só é obrigatória quando `Infectado`) |
| Exemplo | `CriarXxxDto` | `ResultadoAnaliseMalware`, `ResultadoExtracao`, `ResultadoExtracaoIA` |

Um VO deste tipo nunca é instanciável fora dos seus estados válidos nomeados — não existe
equivalente a um `new CriarXxxDto(...)` genérico.

## Propriedades: ordem de prioridade obrigatória

Para expor os campos do VO, seguir esta ordem — cada nível só se justifica quando o anterior não
serve:

1. **Classe `readonly`** (`final readonly class`) com propriedades `public` — quando **todas** as
   propriedades do VO são imutáveis (o caso normal). É o padrão por omissão.
2. **`public readonly` por propriedade** — só quando a classe tem uma mistura de propriedades
   imutáveis e mutáveis (não há hoje nenhum caso no repo; documentado para esse cenário futuro).
3. **`private` + getter/setter manual** — reservado a propriedades com **lógica associada à
   leitura** (não uma simples devolução de valor). Nestes VOs, essa lógica vive tipicamente em
   **métodos nomeados à parte** que calculam algo a partir do estado interno (ex.:
   `estaInfectado()`, `ehCompleto()`), não em getters de campo simples — por isso este nível
   raramente se aplica aos campos de dados do VO em si.

**Property hooks (PHP 8.4+) ficam em standby.** A sintaxe está disponível (projecto corre PHP
8.5), mas o suporte de tooling ainda não está maduro o suficiente para os gates obrigatórios deste
repo (Larastan nível 9 zero erros, Rector sem sugestões pendentes): a análise de property hooks no
PHPStan só chegou na v2.1 e ainda tem verificações por implementar; o Rector só tem uma regra
opcional/recente para o padrão. Revisitar quando estas ferramentas fecharem essas lacunas.

## Exemplo canónico

```php
/**
 * VO do veredicto de `Algo::operacao()`: `estadoA`, `estadoB` (com campo extra) ou `estadoC`.
 * Construtor privado — só acessível via as factories, garantindo que nunca existe num estado
 * ambíguo. Propriedades `public readonly` — sem lógica associada à leitura, dispensam getters.
 */
final readonly class ResultadoAlgo
{
    /**
     * @throws \InvalidArgumentException
     */
    private function __construct(
        public EstadoAlgo $estado,
        public ?string $campoExtra = null,
    ) {
        if ($this->estado === EstadoAlgo::EstadoB && trim((string) $this->campoExtra) === '') {
            throw new \InvalidArgumentException('campoExtra é obrigatório quando o estado é EstadoB.');
        }
    }

    public static function estadoA(): self
    {
        return new self(EstadoAlgo::EstadoA);
    }

    public static function estadoB(string $campoExtra): self
    {
        return new self(EstadoAlgo::EstadoB, $campoExtra);
    }

    // Método nomeado — não um getter de campo, calcula algo a partir do estado.
    public function estaNoEstadoB(): bool
    {
        return $this->estado === EstadoAlgo::EstadoB;
    }
}
```

## Classes existentes (referência)

| Classe | Ficheiro | Propriedades |
|---|---|---|
| `ResultadoAnaliseMalware` | `app/Infrastructure/Malware/ResultadoAnaliseMalware.php` | `public readonly` |
| `ResultadoExtracao` | `app/Infrastructure/Extracao/ResultadoExtracao.php` | `public readonly` |
| `ResultadoExtracaoIA` | `app/Infrastructure/AI/ResultadoExtracaoIA.php` | `public readonly` |

Não existem hoje no repo VOs deste tipo em `private` + getters — as 3 classes acima foram
alinhadas ao padrão `public readonly` (nível 1) numa revisão de consistência (2026-07-15,
`WRN-019`).
