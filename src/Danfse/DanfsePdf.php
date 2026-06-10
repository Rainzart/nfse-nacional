<?php

namespace Hadder\NfseNacional\Danfse;

use TCPDF;

class DanfsePdf
{
    public function renderFromXml(string $xml, ?string $copyright = null): string
    {
        $data = DanfseXmlData::fromXml($xml);

        if ($copyright !== null && trim($copyright) !== '') {
            $data['copyright'] = $this->ensureUtf8(trim($copyright));
        }

        return $this->render($data);
    }

    public function renderFromFile(string $path, ?string $copyright = null): string
    {
        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new \InvalidArgumentException('Não foi possível ler o arquivo XML: ' . $path);
        }

        return $this->renderFromXml($xml, $copyright);
    }

    /** @param array<string, mixed> $data */
    public function render(array $data): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Oswaldo Aranda');
        $pdf->SetAuthor('Prios Automação Contábil');
        $pdf->SetTitle('DANFSe ' . ($data['chave_acesso'] ?? ''));
        $pdf->SetSubject('Documento Auxiliar da NFS-e Nacional');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 5, 8);
        $pdf->SetAutoPageBreak(true, 2);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 8);

        $html = DanfseTemplate::html($data);
        $pdf->writeHTML($html, true, false, true, false, '');

        $url = trim((string) ($data['url_consulta'] ?? ''));
        if ($url !== '') {
            $style = [
                'border' => 0,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1,
            ];

            // O QR Code deve ser impresso na primeira página, dentro da célula superior direita.
            // Como o writeHTML pode avançar para outra página, voltamos para a página 1 antes de desenhar.
            $pdf->setPage(1);
            $qrY = !empty($data['homologacao']) ? 4 : 4;
            // QR Code centralizado dentro da célula superior direita do cabeçalho.
            // Cálculo para A4 com margens de 8mm e coluna direita com 28% da largura útil.
            $pdf->write2DBarcode($url, 'QRCODE,H', 164, $qrY, 30, 30, $style, 'N');
        }

        return $pdf->Output('', 'S');
    }


    private function ensureUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1, Windows-1252');
        }

        return $text;
    }

    public function saveFromXml(string $xml, string $path, ?string $copyright = null): void
    {
        file_put_contents($path, $this->renderFromXml($xml, $copyright));
    }

    public function saveFromFile(string $xmlPath, string $pdfPath, ?string $copyright = null): void
    {
        file_put_contents($pdfPath, $this->renderFromFile($xmlPath, $copyright));
    }
}
