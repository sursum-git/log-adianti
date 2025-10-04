<?php
require __DIR__ . '/../src/HybridRenderer.php';

use Genesis\LogHelpers\HybridRenderer;

function encryptBase64Payload(string $base64Payload, string $secret): string
{
    $method = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($method);
    $iv = random_bytes($ivLength);
    $key = hash('sha256', $secret, true);
    $cipher = openssl_encrypt($base64Payload, $method, $key, OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        throw new RuntimeException('Falha ao criptografar payload.');
    }

    return base64_encode($iv . $cipher);
}

$secret = 'segredo-ultra-seguro';

$log = [
    'dataPage' => [
        'Historico' => [
            'class' => 'HistoricoPedidos',
            'data' => [
                [
                    'rawobj' => encryptBase64Payload(
                        base64_encode(json_encode([
                            'evento' => 'aprovação',
                            'responsavel' => 'Marina',
                            'timestamp' => '2024-06-02T09:15:31-03:00',
                        ], JSON_UNESCAPED_UNICODE)),
                        $secret
                    ),
                ],
                [
                    'rawobj' => encryptBase64Payload(
                        base64_encode(json_encode([
                            'evento' => 'expedição',
                            'responsavel' => 'Rogério',
                            'timestamp' => '2024-06-02T14:02:10-03:00',
                        ], JSON_UNESCAPED_UNICODE)),
                        $secret
                    ),
                ],
            ],
        ],
    ],
];

$renderer = new HybridRenderer(secret: $secret, addMetaCharset: true);

$html = $renderer->render($log);
file_put_contents(__DIR__ . '/decrypt_usage.html', $html);

echo "HTML descriptografado gerado em examples/decrypt_usage.html\n";
