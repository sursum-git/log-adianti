# HybridRenderer

A classe `HybridRenderer` transforma o formato de log utilizado pelo Adianti Framework em HTML amigável, combinando a apresentação em tabela para dados planos e uma visualização hierárquica em árvore para estruturas mais complexas.

## Principais recursos

- **Descriptografia transparente**: suporta campos criptografados com AES-256-CBC, no formato `base64(IV||cipher)`.
- **Decodificação de `rawobj`**: espera receber strings em base64 que representam um JSON plano. Após decodificar, o conteúdo é normalizado em UTF-8.
- **Dois modos de visualização**:
  - **Tabela plana** para coleções homogêneas compostas apenas por valores escalares.
  - **Árvore** (fallback) para estruturas aninhadas, exibindo chaves e valores em uma lista hierárquica.
- **HTML seguro**: todo o conteúdo é corretamente escapado para evitar injeção.
- **Controle sobre `<meta charset>`**: opcionalmente insere `<meta charset="utf-8">` no início do HTML gerado.

## Instalação

Copie o arquivo [`src/HybridRenderer.php`](../src/HybridRenderer.php) para o seu projeto ou utilize este repositório como submódulo. Não há dependências externas além das extensões padrão do PHP para JSON, OpenSSL e (opcionalmente) `intl` para normalização Unicode.

## Uso básico

```php
require 'src/HybridRenderer.php';

$renderer = new HybridRenderer(addMetaCharset: true);
$html = $renderer->render($dadosDoLog);

echo $html;
```

## Estrutura esperada

- `record`: array associativo com os dados principais do registro.
- `dataPage`: coleção de *details* retornados pelo Adianti. Cada `detail` deve conter uma chave `data`.
- Outros campos são agrupados e exibidos na seção **Outros**.

Sempre que existir a chave `rawobj` em um *detail*, o componente tentará decodificá-la seguindo as regras:

1. Se a propriedade `$secret` foi informada, tenta descriptografar o valor de `rawobj`.
2. Tenta decodificar o resultado como base64 contendo um JSON.
3. Caso o JSON contenha apenas valores escalares (strings, números, booleanos ou `null`), o conjunto é exibido em tabela. Caso contrário, a visualização volta para a árvore.

## Exemplos

Veja a pasta [`examples`](../examples) para scripts executáveis que demonstram:

- `basic_usage.php`: renderização simples com dados em claro.
- `decrypt_usage.php`: renderização de dados criptografados, demonstrando como preparar o conteúdo antes de armazená-lo em `rawobj`.

Execute os exemplos com:

```bash
php examples/basic_usage.php
php examples/decrypt_usage.php
```

Os arquivos HTML gerados são gravados na própria pasta `examples/`.

## Boas práticas

- Utilize os métodos `clear()` e `getHtml()` quando precisar controlar o fluxo manualmente.
- Prefira fornecer objetos simples (arrays ou `stdClass`); a classe cuidará da normalização para UTF-8.
- Garanta que o JSON dentro de `rawobj` seja composto por valores escalares para usufruir da renderização tabular.
