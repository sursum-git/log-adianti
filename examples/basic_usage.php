<?php
require __DIR__ . '/../src/HybridRenderer.php';

$pedido = [
    'record' => [
        'id' => 1024,
        'cliente' => 'João da Silva',
        'status' => 'aberto',
    ],
    'dataPage' => [
        'itens' => [
            'composition' => ['var' => 'Itens do Pedido'],
            'data' => [
                ['rawobj' => base64_encode(json_encode([
                    'produto' => 'Café em grãos',
                    'quantidade' => 2,
                    'preco_unitario' => 39.9,
                ], JSON_UNESCAPED_UNICODE))],
                ['rawobj' => base64_encode(json_encode([
                    'produto' => 'Filtro de papel',
                    'quantidade' => 1,
                    'preco_unitario' => 8.5,
                ], JSON_UNESCAPED_UNICODE))],
            ],
        ],
        'enderecos' => [
            'class' => 'EnderecoEntrega',
            'data' => [
                'principal' => [
                    'logradouro' => 'Rua das Laranjeiras',
                    'numero' => 321,
                    'cidade' => 'Fortaleza',
                ],
                'alternativo' => [
                    'logradouro' => 'Av. Atlântica',
                    'numero' => 1000,
                    'cidade' => 'Recife',
                ],
            ],
        ],
    ],
    'meta' => [
        'usuario' => 'ana.rocha',
        'executado_em' => '2024-06-01T10:22:00Z',
    ],
];

$renderer = new HybridRenderer(addMetaCharset: true);
$html = $renderer->render($pedido);

file_put_contents(__DIR__ . '/basic_usage.html', $html);

echo "HTML gerado em examples/basic_usage.html\n";
