<?php

namespace Hadder\NfseNacional\Danfse;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// O package setasign/fpdf declara a classe FPDF no namespace global
// (sem PSR-4). O autoload classmap do Composer normalmente cuida do
// carregamento; este require_once é apenas um fallback defensivo.
if (!class_exists('FPDF', false)) {
    $fpdfPath = __DIR__ . '/../../../setasign/fpdf/fpdf.php';
    if (is_readable($fpdfPath)) {
        require_once $fpdfPath;
    }
}

/**
 * Wrapper FPDF mínimo com helpers exigidos pelo DANFSe.
 *
 * Standalone — substitui NFePHP\DA\Legacy\Pdf sem trazer o sped-da inteiro.
 * Mantém o ciclo extends/helpers familiar a quem usa o ecossistema NFePHP.
 *
 * NOTA: extends \FPDF (namespace global) — não há namespace "setasign\Fpdf"
 * no package setasign/fpdf.
 */
class Pdf extends \FPDF
{
    /**
     * Fonte para títulos/labels (NT-008 §2.4: Arial).
     */
    public const FONT_TITULO = 'Arial';

    /**
     * Fonte para conteúdos (NT-008 §2.4: Microsoft Sans Serif).
     */
    public const FONT_CONTEUDO = 'MicrosoftSansSerif';

    public function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->fontpath = __DIR__ . '/fonts/';
        $this->AddFont(self::FONT_TITULO,   '',  'arial.php');
        $this->AddFont(self::FONT_TITULO,   'B', 'arialb.php');
        $this->AddFont(self::FONT_CONTEUDO, '',  'microsoftsansserif.php');
    }

    /**
     * Desenha caixa de texto com word-wrap automático.
     * Usado em descrição de serviço, informações complementares e endereços longos.
     */
    public function textBox(
        float $x, float $y, float $w, float $h,
        string $text,
        string $align = 'L',
        string $valign = 'T',
        bool $border = false,
        bool $fill = false
    ): void {
        if ($fill) {
            $this->Rect($x, $y, $w, $h, 'F');
        }
        if ($border) {
            $this->Rect($x, $y, $w, $h);
        }
        $lines = $this->wordWrap($text, $w - 1);
        $lineH = $this->FontSize * 1.2;
        $totalH = count($lines) * $lineH;
        $yOffset = match ($valign) {
            'M' => max(0, ($h - $totalH) / 2),
            'B' => max(0, $h - $totalH),
            default => 0,
        };
        foreach ($lines as $i => $line) {
            $this->SetXY($x, $y + $yOffset + $i * $lineH);
            $this->Cell($w, $lineH, $this->latin($line), 0, 0, $align);
        }
    }

    /**
     * Quebra texto em linhas que cabem na largura $w (mm).
     *
     * @return string[]
     */
    public function wordWrap(string $text, float $w): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            $words = explode(' ', $paragraph);
            $current = '';
            foreach ($words as $word) {
                $test = $current === '' ? $word : "$current $word";
                if ($this->GetStringWidth($this->latin($test)) <= $w) {
                    $current = $test;
                } else {
                    if ($current !== '') {
                        $lines[] = $current;
                    }
                    $current = $word;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines;
    }

    public function getNumLines(string $text, float $w): int
    {
        return count($this->wordWrap($text, $w));
    }

    /**
     * Cell com auto-encolhimento de fonte quando o texto não cabe.
     */
    public function cellFit(
        float $w, float $h, string $text,
        int $border = 0, int $ln = 0, string $align = 'L', bool $fill = false
    ): void {
        $startSize = $this->FontSizePt;
        $size = $startSize;
        while ($size > 4 && $this->GetStringWidth($this->latin($text)) > $w - 1) {
            $size -= 0.5;
            $this->SetFontSize($size);
        }
        $this->Cell($w, $h, $this->latin($text), $border, $ln, $align, $fill);
        $this->SetFontSize($startSize);
    }

    /**
     * Renderiza QR Code (NT-008 §2.4.3) via chillerlan/php-qrcode.
     */
    public function qrCode(float $x, float $y, float $size, string $data): void
    {
        $options = new QROptions([
            'outputType'  => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'    => QRCode::ECC_M,
            'scale'       => 5,
            'imageBase64' => true,
        ]);
        $dataUri = (new QRCode($options))->render($data);
        $base64 = substr($dataUri, strpos($dataUri, ',') + 1);
        $tmp = tempnam(sys_get_temp_dir(), 'danfse_qr_') . '.png';
        file_put_contents($tmp, base64_decode($base64));
        $this->Image($tmp, $x, $y, $size, $size, 'PNG');
        @unlink($tmp);
    }

    /**
     * Imprime marca d'água diagonal (NT-008 §2.5.1 CANCELADA / §2.5.2 SUBSTITUÍDA).
     */
    public function marcaDagua(
        string $texto,
        int $cinzaK = 35,            // K35 conforme NT-008 §2.5: 255*(1-K/100) = 165
        int $tamanho = 50,           // NT-008 §2.5: tamanho mínimo 50pt
        float $deslocamentoY = 25.0  // mm acima do centro da página — posiciona a marca no terço superior
    ): void {
        $tomCinza = (int) (255 - (255 * $cinzaK / 100));
        $this->SetFont(self::FONT_TITULO, '', $tamanho);
        $this->SetTextColor($tomCinza, $tomCinza, $tomCinza);
        $this->_out('q');
        $angle = 45;
        $rad = deg2rad($angle);
        $yCentro = $this->h / 2 - $deslocamentoY;
        // Pivot da rotação em coordenadas PDF (origem inferior, Y invertido em relação ao FPDF).
        $cx = $this->w / 2 * $this->k;
        $cy = ($this->h - $yCentro) * $this->k;
        $this->_out(sprintf(
            '%.5F %.5F %.5F %.5F %.5F %.5F cm',
            cos($rad), sin($rad), -sin($rad), cos($rad),
            $cx - cos($rad) * $cx + sin($rad) * $cy,
            $cy - sin($rad) * $cx - cos($rad) * $cy
        ));
        $w = $this->GetStringWidth($this->latin($texto));
        $this->Text($this->w / 2 - $w / 2, $yCentro, $this->latin($texto));
        $this->_out('Q');
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * FPDF nativo é Latin1. Converte UTF-8 para Windows-1252 para preservar
     * acentuação portuguesa (ç, ã, õ, etc.) na renderização.
     */
    public function latin(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}
