# Process Warnings

Registo de erros de processo com ID sequencial. Verificar no início de cada sessão.

---

WRN-001 | 2026-06-26T15:25:38Z | implementa (#57) | STATUS: PENDENTE
- Descrição: `php artisan checkpoint:scan` devolveu 2 FAILs no fecho da Fase 2 da Issue #57, ambos **não introduzidos por esta issue** e fora do âmbito da máquina de estados:
  (1) NPM CVE Audit — `shell-quote` (GHSA-w7jw-789q-3m8p, critical) via `concurrently` (devDependency, dev-only, sem input não confiável → risco baixo);
  (2) Package Freshness — 7 pacotes Composer publicados há < 3 dias (laravel/framework v13.17.0, guzzle, flysystem, prompts, …). `composer audit` confirma **zero advisories reais** — é heurística temporal de supply-chain, não um defeito.
- Comando: php artisan checkpoint:scan
- Sugestão: (1) `npm audit fix` para o `shell-quote`; (2) deixar o Freshness auto-resolver (> 3 dias) ou publicar `config/checkpoint.php` e fazer whitelist. Decisão do utilizador (2026-06-26): ignorar por agora e tratar à parte — `composer test` está verde (100% coverage/types, arch).
