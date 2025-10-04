<?php

namespace Genesis\LogHelpers;
/**
 * Classe HybridRenderer
 * ---------------------
 * - Descriptografa campos (AES-256-CBC, base64(IV||cipher))
 * - Decodifica base64→JSON de "rawobj"
 * - Mostra TABELA PLANA para dados planos e fallback ÁRVORE para outros
 * - Normaliza UTF-8 e escapa HTML corretamente
 * - Não usa ob_start — mantém HTML em $this->html
 */
class HybridRenderer
{
    private ?string $secret;
    private array $keysToDecrypt;
    private bool $addMetaCharset;
    private string $html = '';

    public function __construct(?string $secret = null, array $keysToDecrypt = ['rawobj'], bool $addMetaCharset = false)
    {
        $this->secret = $secret;
        $this->keysToDecrypt = $keysToDecrypt;
        $this->addMetaCharset = $addMetaCharset;
    }

    /** Limpa o HTML armazenado */
    public function clear(): void
    {
        $this->html = '';
    }

    /** Recupera o HTML gerado */
    public function getHtml(): string
    {
        return $this->html;
    }

    /** Método principal */
    public function render($data): string
    {
        $this->clear();

        $arr = is_object($data) ? json_decode(json_encode($data, JSON_UNESCAPED_UNICODE), true) : $data;
        $arr = $this->normalizeUtf8Recursive($arr);

        if ($this->addMetaCharset) {
            $this->html .= "<meta charset=\"utf-8\">\n";
        }

        // Record
        if (isset($arr['record']) && is_array($arr['record'])) {
            $this->html .= "<h3>Record</h3>";
            $this->html .= $this->renderTree($arr['record']);
        }

        // DataPage
        if (isset($arr['dataPage']) && is_array($arr['dataPage'])) {
            foreach ($arr['dataPage'] as $detailKey => $detail) {
                $label = $detailKey;
                if (isset($detail['composition']['var']))      $label = $detail['composition']['var'];
                elseif (isset($detail['class']))               $label = $detail['class'];

                $flat = $this->renderFlatDataTableFromDetail($detail, $label);
                if ($flat !== null) {
                    $this->html .= $flat;
                } else {
                    $this->html .= "<h3>" . $this->e($label) . "</h3>";
                    $this->html .= $this->renderTree($detail);
                }
            }
        }

        // Outros
        $others = $arr;
        unset($others['record'], $others['dataPage']);
        if (!empty($others)) {
            $this->html .= "<h3>Outros</h3>";
            $this->html .= $this->renderTree($others);
        }

        return $this->html;
    }

    /** Alias compatível */
    public static function renderMultilevelTable($data, array $keysToDecrypt = ['rawobj'], ?string $secret = null, bool $addMetaCharset = false): string
    {
        $r = new self($secret, $keysToDecrypt, $addMetaCharset);
        return $r->render($data);
    }

    /* ==============================================================
       ========== MÉTODOS PRIVADOS: RENDERIZAÇÃO =====================
       ============================================================== */

    private function renderFlatDataTableFromDetail(array $detail, string $titulo = ''): ?string
    {
        if (!isset($detail['data']) || !is_array($detail['data'])) return null;

        $rows = [];
        foreach ($detail['data'] as $item) {
            if (!is_array($item) || !array_key_exists('rawobj', $item)) return null;

            $raw = $item['rawobj'];

            if ($this->secret && in_array('rawobj', $this->keysToDecrypt, true) && is_string($raw) && $this->looksLikeBase64($raw)) {
                $pt = $this->decryptValue($raw, $this->secret);
                if ($pt !== null) $raw = $this->normalizeUtf8($pt);
            }

            $decoded = is_string($raw) ? $this->base64JsonToArray($raw) : null;
            if ($decoded === null) return null;

            foreach ($decoded as $vv) {
                if (is_array($vv) || is_object($vv)) return null;
            }

            $rows[] = $this->normalizeUtf8Recursive($decoded);
        }

        if (!$rows) return null;

        $cols = array_keys($rows[0]);
        foreach ($rows as $r)
            foreach ($r as $ck => $_)
                if (!in_array($ck, $cols, true)) $cols[] = $ck;

        $html = "<style>
        .mono{font-family:ui-monospace,Menlo,Consolas,\"Liberation Mono\",monospace}
        .dt-table{width:100%;border-collapse:collapse}
        .dt-table th,.dt-table td{border:1px solid #ddd;padding:6px 8px;vertical-align:top}
        .dt-table th{background:#f5f5f7}
        .dt-scroll{overflow:auto;max-width:100%}
        .section{margin:.8rem 0 1.2rem}
        </style>";

        $html .= "<div class='section'>";
        if ($titulo) $html .= "<h3>" . $this->e($titulo) . " <span style='font-size:.75rem;background:#eef;color:#335;border-radius:.4rem;padding:.1rem .4rem;margin-left:.5rem'>tabela</span></h3>";
        $html .= "<div class='dt-scroll'><table class='dt-table'><thead><tr>";
        foreach ($cols as $c) $html .= "<th>" . $this->e($c) . "</th>";
        $html .= "</tr></thead><tbody>";
        foreach ($rows as $r) {
            $html .= "<tr>";
            foreach ($cols as $c) {
                $val = array_key_exists($c, $r) ? (is_null($r[$c]) ? 'null' : (is_bool($r[$c]) ? ($r[$c] ? 'true' : 'false') : (string)$r[$c])) : '';
                $html .= "<td class='mono'>" . $this->e($val) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table></div></div>";
        return $html;
    }

    private function renderTree($data): string
    {
        $data = $this->normalizeUtf8Recursive($data);

        $build = function($node, $level = 0) use (&$build) {
            $html = '';
            if (is_array($node)) {
                foreach ($node as $k => $v) {
                    $pad = str_repeat('&nbsp;', $level * 4);
                    if (is_array($v) || is_object($v)) {
                        $html .= "<tr><td class='mono'>{$pad}" . $this->e((string)$k) . "</td><td class='mono'>[…]</td></tr>";
                        $html .= $build(is_object($v) ? json_decode(json_encode($v, JSON_UNESCAPED_UNICODE), true) : $v, $level + 1);
                    } else {
                        $val = is_null($v) ? 'null' : (is_bool($v) ? ($v ? 'true' : 'false') : (string)$v);
                        $html .= "<tr><td class='mono'>{$pad}" . $this->e((string)$k) . "</td><td class='mono'>" . $this->e($val) . "</td></tr>";
                    }
                }
            } else {
                $val = is_null($node) ? 'null' : (is_bool($node) ? ($node ? 'true' : 'false') : (string)$node);
                $html .= "<tr><td class='mono'>(value)</td><td class='mono'>" . $this->e($val) . "</td></tr>";
            }
            return $html;
        };

        $html = "<style>
        .mono{font-family:ui-monospace,Menlo,Consolas,\"Liberation Mono\",monospace}
        .dt-table{width:100%;border-collapse:collapse}
        .dt-table th,.dt-table td{border:1px solid #ddd;padding:6px 8px;vertical-align:top}
        .dt-table th{background:#f5f5f7}
        </style>";
        $html .= "<div class='section'><table class='dt-table'><thead><tr><th style='width:40%'>Chave</th><th>Valor</th></tr></thead><tbody>";
        $html .= $build($data, 0);
        $html .= "</tbody></table></div>";
        return $html;
    }

    /* ==============================================================
       ========== MÉTODOS AUXILIARES (UTF-8, crypto, JSON) ===========
       ============================================================== */
    private function e($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normalizeUtf8($s): string
    {
        if (!is_string($s)) return $s;
        if (mb_detect_encoding($s, ['UTF-8','ISO-8859-1','Windows-1252','ASCII'], true) !== 'UTF-8') {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
            if ($converted !== false) return $converted;
            return mb_convert_encoding($s, 'UTF-8', 'auto');
        }
        if (class_exists('\\Normalizer')) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_C);
            if ($n !== false) return $n;
        }
        return $s;
    }

    private function normalizeUtf8Recursive($val)
    {
        if (is_string($val)) return $this->normalizeUtf8($val);
        if (is_array($val)) {
            $out = [];
            foreach ($val as $k => $v) {
                $nk = is_string($k) ? $this->normalizeUtf8($k) : $k;
                $out[$nk] = $this->normalizeUtf8Recursive($v);
            }
            return $out;
        }
        if (is_object($val)) {
            $val = json_decode(json_encode($val, JSON_UNESCAPED_UNICODE), true);
            return $this->normalizeUtf8Recursive($val);
        }
        return $val;
    }

    private function decryptValue(string $cipher_b64, string $secret): ?string
    {
        $bin = base64_decode($cipher_b64, true);
        if ($bin === false) return null;
        $method = 'AES-256-CBC';
        $ivlen  = openssl_cipher_iv_length($method);
        if (strlen($bin) <= $ivlen) return null;
        $iv  = substr($bin, 0, $ivlen);
        $ct  = substr($bin, $ivlen);
        $key = hash('sha256', $secret, true);
        $pt  = openssl_decrypt($ct, $method, $key, OPENSSL_RAW_DATA, $iv);
        return $pt === false ? null : $pt;
    }

    private function looksLikeBase64(string $s): bool
    {
        $s = preg_replace('/\s+/', '', $s);
        return $s !== '' && strlen($s) % 4 === 0 && base64_decode($s, true) !== false;
    }

    private function base64JsonToArray(string $s): ?array
    {
        if (!$this->looksLikeBase64($s)) return null;
        $bin = base64_decode($s, true);
        if ($bin === false) return null;
        $jsonStr = $this->normalizeUtf8($bin);
        $j = json_decode($jsonStr, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        return (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $this->normalizeUtf8Recursive($j) : null;
    }
}
