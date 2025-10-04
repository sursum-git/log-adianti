# log-adianti

Log para facilitar o debug no formato de dados utilizado pelo Adianti Framework.

## HybridRenderer

Este repositório inclui a classe [`HybridRenderer`](src/HybridRenderer.php), responsável por converter o conteúdo de logs do Adianti em HTML com duas visualizações complementares:

- **Tabela plana** para coleções homogêneas (dados planos provenientes de `rawobj`).
- **Árvore hierárquica** como fallback para estruturas complexas.

Recursos extras:

- Descriptografia opcional de campos com AES-256-CBC.
- Normalização UTF-8 e escaping seguro de HTML.
- Inserção opcional de `<meta charset="utf-8">` no início do HTML.

## Exemplos

Na pasta [`examples`](examples) há scripts PHP que podem ser executados diretamente:

```bash
php examples/basic_usage.php
php examples/decrypt_usage.php
```

Cada script gera um arquivo HTML com o resultado da renderização na própria pasta `examples/`.

## Documentação

Para mais detalhes sobre o funcionamento interno, estrutura esperada dos dados e dicas de uso, consulte [`docs/hybrid-renderer.md`](docs/hybrid-renderer.md).
