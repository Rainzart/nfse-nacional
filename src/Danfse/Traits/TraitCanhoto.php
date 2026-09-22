<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\Pdf;

/**
 * Bloco "Ciência da Operação" (canhoto) — NT-008 §2.1.13 e §2.4.5.
 *
 * Bloco OPCIONAL (controlado por `$this->exibirCanhoto`). Fica DENTRO da moldura
 * principal, fixado no rodapé da página. A borda externa e o separador superior
 * são desenhados pela moldura global; aqui só desenhamos as divisórias verticais
 * entre as 3 colunas e os textos:
 *
 *   | DATA CIENTIFICAÇÃO: | IDENTIFICAÇÃO E ASSINATURA | Nº NFS-e / CHAVE NFS-e |
 *                                                       | <nNFSe> / <chave>      |
 *
 * Colunas (grade uniforme): col 1 e col 2 = 1/4 da largura cada; col 3
 * (Nº/CHAVE) = 1/2 da largura (a chave de acesso é longa).
 */
trait TraitCanhoto
{
    protected function blocoCanhoto(float $xIni, float $yIni, float $altura): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $colQuarta = $larguraTotal / 4;

        // ----- Divisórias verticais entre as 3 colunas (a moldura global desenha
        //       a borda externa e o separador superior) -----
        $x2 = $xIni + $colQuarta;          // fim col 1
        $x3 = $xIni + 2 * $colQuarta;      // fim col 2 / início col 3 (largura 2*colQuarta)
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.176);   // 0,5pt — NT-008 §2.2.3
        $this->pdf->Line($x2, $yIni, $x2, $yIni + $altura);
        $this->pdf->Line($x3, $yIni, $x3, $yIni + $altura);

        // ----- Col 1: DATA CIENTIFICAÇÃO -----
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont(Pdf::FONT_TITULO, 'B', 7);
        $this->pdf->SetXY($xIni + 0.6, $yIni + 0.6);
        $this->pdf->Cell($colQuarta - 1.2, 2.2, $this->pdf->latin('DATA CIENTIFICAÇÃO:'), 0, 0, 'L');

        // ----- Col 2: IDENTIFICAÇÃO E ASSINATURA -----
        $this->pdf->SetXY($x2 + 0.6, $yIni + 0.6);
        $this->pdf->Cell($colQuarta - 1.2, 2.2, $this->pdf->latin('IDENTIFICAÇÃO E ASSINATURA'), 0, 0, 'L');

        // ----- Col 3: Nº NFS-e / CHAVE NFS-e (label + valor) -----
        $this->pdf->SetXY($x3 + 0.6, $yIni + 0.6);
        $this->pdf->Cell(2 * $colQuarta - 1.2, 2.2, $this->pdf->latin('Nº NFS-e / CHAVE NFS-e'), 0, 0, 'L');

        $nNFSe = $this->getTag($this->infNFSe, 'nNFSe', '-');
        $chave = $this->chaveAcesso !== '' ? $this->chaveAcesso : '-';
        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $this->pdf->SetXY($x3 + 0.6, $yIni + 3.2);
        $this->pdf->cellFit(2 * $colQuarta - 1.2, 3.0, "{$nNFSe} / {$chave}", 0, 0, 'L');

        return $yIni + $altura;
    }
}
