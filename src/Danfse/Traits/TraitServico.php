<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;
use Hadder\NfseNacional\Danfse\LocalidadeIbge;
use Hadder\NfseNacional\Danfse\Pdf;

/**
 * Bloco "Dados do Serviço Prestado" — NT-008 §2.1.7 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/serv
 *
 * Campos exigidos pela NT-008 §2.1.7 (mínimos):
 *   • Código de Tributação Nacional / Municipal (cTribNac / cTribMun)
 *   • Código da NBS (cNBS)
 *   • Local da Prestação / Sigla UF / País (cLocPrestacao / UF / cPais)
 *   • Descrição do Código de Tributação Nacional / Municipal (xTribNac / xTribMun, de infNFSe)
 *   • Descrição do Serviço (xDescServ)
 *
 * Layout em 4 linhas:
 *   L1 (alt 6,4mm): [SERVIÇO cinza] | Cód. Trib. Nacional | Cód. Trib. Municipal
 *                                    | Código NBS | Local Prestação (cMun/UF/País)
 *   L2 (alt 6,4mm): Descrição do Código de Tributação Nacional / Municipal (largo)
 *   L3 (alt 12,0mm): Descrição do Serviço (largo, multi-linha com word-wrap)
 *
 * Total: ~25mm. Bloco sempre presente (não há supressão para serviço).
 */
trait TraitServico
{
    protected function blocoServico(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $larguraTextoUtil = $larguraTotal - 1.2;
        $altL1 = 6.4;
        // Altura de uma linha de texto 7pt MS Sans Serif (FontSize 2,47mm × 1,2 ≈ 2,97mm).
        // Usamos 3,0mm para folga marginal contra arredondamentos.
        $lineH = 3.0;

        // L1: título cinza (32mm) + 3 colunas iguais
        $colTitulo = 32.0;
        $colCampo = ($larguraTotal - $colTitulo) / 3;

        $x1 = $xIni;
        $x2 = $xIni + $colTitulo;
        $x3 = $x2 + $colCampo;
        $x4 = $x3 + $colCampo;

        $cServ = $this->getChild($this->serv, 'cServ');
        [$localPrest, $ufPrest, $paisPrest] = $this->extrairLocalPrestacao();

        $cTribNac = $this->formatarCTribNac($this->getTag($cServ, 'cTribNac', ''));
        $cTribMun = $this->getTag($cServ, 'cTribMun', '');
        $codTrib = trim(($cTribNac !== '' ? $cTribNac : '-') . ' / ' . ($cTribMun !== '' ? $cTribMun : '-'));

        // ----- L1: código combinado + NBS + localidade -----
        $this->desenharTituloBlocoCampo($x1, $yIni, $colTitulo, $altL1, 'SERVIÇO PRESTADO');
        $this->desenharCelula($x2, $yIni, $colCampo, $altL1,
            'Código de Tributação Nacional / Municipal', $codTrib);
        $this->desenharCelula($x3, $yIni, $colCampo, $altL1,
            'Código da NBS', $this->formatarCNBS($this->getTag($cServ, 'cNBS', '')));
        $this->desenharCelula($x4, $yIni, $colCampo, $altL1,
            'Local da Prestação / Sigla UF / País',
            $this->formatarLocalPrestacao($localPrest, $ufPrest, $paisPrest));

        // ----- L2: Descrição da Tributação Nacional / Municipal -----
        // NT-008 §2.4.5 Obs.: "Não há título (label) deste campo no DANFSe" — só conteúdo.
        // Altura dinâmica para caber até 167 chars (multi-linha se necessário).
        // xTribNac/xTribMun são filhas de infNFSe (campos calculados pela administração
        // tributária — mesmo grupo de cStat/xLocIncid), não de cServ.
        $y2 = $yIni + $altL1;
        $descTrib = $this->getTag($this->infNFSe, 'xTribNac', '');
        if ($descTrib === '') {
            $descTrib = $this->getTag($this->infNFSe, 'xTribMun', '');
        }
        $descTrib = EnumDecoder::truncateAfter($descTrib, 167);
        if ($descTrib === '') {
            $descTrib = '-';
        }

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $linhasL2 = max(1, $this->pdf->getNumLines($descTrib, $larguraTextoUtil));
        $altL2 = $linhasL2 * $lineH + 0.8;
        $this->pdf->textBox(
            $xIni + 0.6,
            $y2 + 0.4,
            $larguraTextoUtil,
            $altL2 - 0.8,
            $descTrib,
            'L',
            'T'
        );

        // ----- L3: Descrição do Serviço (label "Descrição do Serviço" + texto multi-linha) -----
        // Altura dinâmica para caber até 1297 chars — bloco expande verticalmente conforme
        // necessário, e o bloco "Informações Complementares" abaixo absorve a folga.
        $y3 = $y2 + $altL2;
        $descricao = $this->getTag($cServ, 'xDescServ', '');
        $descricao = EnumDecoder::truncateAfter($descricao, 1297);  // NT-008 §2.4.5
        if ($descricao === '') {
            $descricao = '-';
        }

        $labelOffset = 2.8;  // espaço do label bold 6pt + folga
        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $linhasL3 = max(1, $this->pdf->getNumLines($descricao, $larguraTextoUtil));
        $altL3 = $labelOffset + $linhasL3 * $lineH + 0.4;

        $this->pdf->SetFont(Pdf::FONT_TITULO, 'B', 6);
        $this->pdf->SetXY($xIni + 0.6, $y3 + 0.4);
        $this->pdf->Cell($larguraTextoUtil, 2.2,
            $this->pdf->latin('Descrição do Serviço'), 0, 0, 'L');

        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $this->pdf->textBox(
            $xIni + 0.6,
            $y3 + $labelOffset,
            $larguraTextoUtil,
            $altL3 - $labelOffset - 0.4,
            $descricao,
            'L',
            'T'
        );

        return $y3 + $altL3;
    }

    /**
     * Retorna [nomeLocalPrestacao, UF, País] do local da prestação.
     *
     * O nome do município vem de infNFSe/xLocPrestacao (calculado pela
     * administração tributária); a UF e o País são derivados do código IBGE
     * em serv/locPrest/cLocPrestacao via {@see LocalidadeIbge::resolver()},
     * pois o leiaute não possui tags de UF/País neste nó.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function extrairLocalPrestacao(): array
    {
        $locPrest = $this->getChild($this->serv, 'locPrest');
        $cLoc = $locPrest ? $this->getTag($locPrest, 'cLocPrestacao', '') : '';

        ['uf' => $uf, 'pais' => $pais] = LocalidadeIbge::resolver($cLoc);

        // Nome do local: preferir xLocPrestacao; fallback para xLocEmi quando a
        // prestação ocorre no próprio município emitente (regra geral §2.1.7).
        $nome = $this->xLocPrestacao;
        if ($nome === '' && $cLoc !== '' && $cLoc === $this->cMunEmit) {
            $nome = $this->xLocEmi;
        }

        return [$nome, $uf, $pais];
    }

    private function formatarLocalPrestacao(string $nome, string $uf, string $pais): string
    {
        $partes = array_filter([$nome, $uf, $pais], fn($v) => $v !== '');
        return $partes ? implode(' / ', $partes) : '-';
    }
}
