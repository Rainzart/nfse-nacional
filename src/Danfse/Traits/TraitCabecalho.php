<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;
use Hadder\NfseNacional\Danfse\Pdf;

/**
 * Cabeçalho + bloco "DADOS DA NFS-e" — NT-008 §2.1.1, §2.1.2, §2.4.3.
 *
 * Estrutura visual (Anexo I da NT-008):
 *   - Cabeçalho (alt 11,6mm): faixa com fundo cinza 5%, contendo
 *     logo NFS-e à esquerda, título "DANFSe v2.0" ao centro, identificação
 *     do município/ambiente à direita.
 *   - Bloco "DADOS DA NFS-e" (alt ~27mm): bloco fechado com borda externa e
 *     linhas horizontais separando as 4 linhas de campos. Sem bordas
 *     verticais entre as células.
 *   - QR Code posicionado absolutamente no canto superior direito
 *     (X=174,8 Y=16,7) sobrepondo a área à direita do bloco DADOS.
 */
trait TraitCabecalho
{
    protected function blocoCabecalho(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altCabecalho = 11.6;
        $cw = $larguraTotal / 4;        // grade uniforme (igual aos blocos abaixo)

        // ----- Cabeçalho: faixa com fundo cinza 5% (sem borda própria;
        //       a moldura global desenhada por Danfse::desenharMolduraGlobal cuida) -----
        $this->pdf->SetFillColor(242, 242, 242);
        $this->pdf->Rect($xIni, $yIni, $larguraTotal, $altCabecalho, 'F');

        // Zonas do cabeçalho relativas à grade: logo (col 1), título central
        // (cols 2-3) e identificação município/ambiente (col 4).
        $this->desenharLogoNfseNacional($xIni + 1.9, $yIni + 1.4);
        $this->desenharTituloCentral($xIni + $cw, $yIni, 2 * $cw);
        $this->desenharIdentMunicipioAmbiente($xIni + 3 * $cw, $yIni, $cw);

        // ----- Bloco "DADOS DA NFS-e" -----
        // L1 ocupa cols 1+2+3 (CHAVE longa); L2/L3/L4 usam cols 1, 2 e 3
        // (3 campos cada). Col 4 fica reservada à área do QR Code.
        // NT-008 §2.4.5: L1 (Chave) tem altura 0,77cm; demais linhas 0,67cm.
        $altL1Chave = 7.7;
        $altLinha   = 6.7;
        $alturaBlocoDados = $altL1Chave + 3 * $altLinha;
        $larguraEsq = 3 * $cw;          // L1 ocupa as 3 primeiras colunas
        $yDados = $yIni + $altCabecalho;

        // Separador full-width entre faixa cinza e área DADOS NFS-e (0,5pt = NT-008 §2.2.3).
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.176);
        $this->pdf->Line($xIni, $yDados, $xIni + $larguraTotal, $yDados);

        // L1: Chave de acesso (cols 1+2+3; col 4 livre para QR)
        $this->desenharCelulaCaixaAlta($xIni, $yDados, $larguraEsq, $altL1Chave,
            'CHAVE DE ACESSO DA NFS-E', $this->chaveAcesso);

        // L2: nNFSe | dCompet | dhProc (cols 1, 2, 3)
        $yL2 = $yDados + $altL1Chave;
        $this->desenharCelulaCaixaAlta($xIni, $yL2, $cw, $altLinha,
            'NÚMERO DA NFS-E', $this->getTag($this->infNFSe, 'nNFSe', ''));
        $this->desenharCelulaCaixaAlta($xIni + $cw, $yL2, $cw, $altLinha,
            'COMPETÊNCIA DA NFS-E', $this->formatarDataSimples($this->getTag($this->infDPS, 'dCompet', '')));
        $this->desenharCelulaCaixaAlta($xIni + 2 * $cw, $yL2, $cw, $altLinha,
            'DATA E HORA DA EMISSÃO DA NFS-E', $this->formatar($this->getTag($this->infNFSe, 'dhProc', ''), 'data'));

        // L3: nDPS | serie | dhEmi (cols 1, 2, 3)
        $yL3 = $yDados + $altL1Chave + $altLinha;
        $this->desenharCelulaCaixaAlta($xIni, $yL3, $cw, $altLinha,
            'NÚMERO DA DPS', $this->getTag($this->infDPS, 'nDPS', ''));
        $this->desenharCelulaCaixaAlta($xIni + $cw, $yL3, $cw, $altLinha,
            'SÉRIE DA DPS', $this->getTag($this->infDPS, 'serie', ''));
        $this->desenharCelulaCaixaAlta($xIni + 2 * $cw, $yL3, $cw, $altLinha,
            'DATA E HORA DA EMISSÃO DA DPS', $this->formatar($this->getTag($this->infDPS, 'dhEmi', ''), 'data'));

        // L4: tpEmit (cinza obrigatório §2.2.3) | cStat | finNFSe (cols 1, 2, 3)
        $yL4 = $yDados + $altL1Chave + 2 * $altLinha;
        $this->pdf->SetFillColor(242, 242, 242);
        $this->pdf->Rect($xIni, $yL4, $cw, $altLinha, 'F');
        $this->desenharCelulaCaixaAlta($xIni, $yL4, $cw, $altLinha,
            'EMITENTE DA NFS-e',
            EnumDecoder::decode(EnumDecoder::TP_EMIT, $this->getTag($this->infDPS, 'tpEmit', '')));
        $this->desenharCelulaCaixaAlta($xIni + $cw, $yL4, $cw, $altLinha,
            'SITUAÇÃO DA NFS-e',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::C_STAT, $this->cStat), 40));
        $this->desenharCelulaCaixaAlta($xIni + 2 * $cw, $yL4, $cw, $altLinha,
            'FINALIDADE',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::FIN_NFSE, $this->getTag($this->infDPS, 'finNFSe', '')), 40));

        // ----- QR Code (NT-008 §2.4.3) posicionado na col 4 da grade -----
        $this->desenharQrCodeENotaConsulta($xIni, $larguraTotal);

        return $yDados + $alturaBlocoDados;
    }

    /**
     * Logo NFS-e horizontal (fixo do padrão nacional) ou placeholder textual.
     */
    private function desenharLogoNfseNacional(float $x, float $y): void
    {
        $logoFixo = self::STORAGE_LOGOS . '/logo-nfs-e-horizontal.png';
        if (is_readable($logoFixo)) {
            // Altura 0 → FPDF calcula proporcionalmente à largura (evita distorção).
            $this->pdf->Image($logoFixo, $x, $y + 1.2, 40, 0, 'PNG');
            return;
        }
        // Fallback: placeholder textual estilizado
        $this->pdf->SetTextColor(60, 100, 60);
        $this->pdf->SetFont(Pdf::FONT_TITULO, 'B', 14);
        $this->pdf->SetXY($x, $y + 1.0);
        $this->pdf->Cell(40, 5, 'NFSe', 0, 0, 'L');
        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 6);
        $this->pdf->SetXY($x, $y + 5.5);
        $this->pdf->Cell(40, 3, $this->pdf->latin('Nota Fiscal de Serviço Eletrônica'), 0, 0, 'L');
        $this->pdf->SetTextColor(0, 0, 0);
    }

    private function desenharTituloCentral(float $x, float $yBase, float $w): void
    {
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont(Pdf::FONT_TITULO, 'B', 9);

        $this->pdf->SetXY($x, $yBase + 1.5);
        $this->pdf->Cell($w, 4, $this->pdf->latin('DANFSe v2.0'), 0, 0, 'C');

        $this->pdf->SetXY($x, $yBase + 5);
        $this->pdf->Cell($w, 4, $this->pdf->latin('Documento Auxiliar da NFS-e'), 0, 0, 'C');

        if ($this->tpAmb === '2') {
            $this->pdf->SetTextColor(255, 0, 0);
            $this->pdf->SetXY($x, $yBase + 8.5);
            $this->pdf->Cell($w, 3, $this->pdf->latin('NFS-e SEM VALIDADE JURÍDICA'), 0, 0, 'C');
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    private function desenharIdentMunicipioAmbiente(float $x, float $yBase, float $w): void
    {
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 8);
        $this->pdf->SetXY($x + 0.6, $yBase + 0.8);
        $municipio = $this->xLocEmi !== '' ? "Município: {$this->xLocEmi}" : 'Município: -';
        $this->pdf->Cell($w - 1.2, 4, $this->pdf->latin($municipio), 0, 0, 'L');

        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 6);
        $ambGer = $this->getTag($this->infNFSe, 'ambGer', '');
        $this->pdf->SetXY($x + 0.6, $yBase + 5.5);
        $this->pdf->Cell($w - 1.2, 3,
            $this->pdf->latin('Ambiente Gerador: ' . ($ambGer !== '' ? $ambGer : '-')), 0, 0, 'L');

        $this->pdf->SetXY($x + 0.6, $yBase + 8.2);
        $this->pdf->Cell($w - 1.2, 3,
            $this->pdf->latin('Tipo de Ambiente: ' . EnumDecoder::decode(EnumDecoder::TP_AMB, $this->tpAmb)),
            0, 0, 'L');
    }

    /**
     * QR Code (NT-008 §2.4.3, tamanho 1,52cm) + descrição complementar (6pt),
     * posicionados na 4ª coluna da grade (à direita do bloco DADOS NFS-e).
     */
    private function desenharQrCodeENotaConsulta(float $xIni, float $larguraTotal): void
    {
        if ($this->chaveAcesso === '') {
            return;
        }
        $cw = $larguraTotal / 4;
        $xCol4 = $xIni + 3 * $cw;
        $qrSize = 15.2;
        $xQr = $xCol4 + ($cw - $qrSize) / 2;   // centralizado na col 4

        $url = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . $this->chaveAcesso;
        $this->pdf->qrCode($xQr, 13.5, $qrSize, $url);

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 6);
        $this->pdf->textBox(
            $xCol4,
            29.5,
            $cw,
            6.8,
            'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e',
            'C',
            'T'
        );
    }

    private function formatarDataSimples(string $iso): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $iso ?: '-';
    }
}
