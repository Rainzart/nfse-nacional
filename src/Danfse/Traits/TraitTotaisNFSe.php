<?php

namespace Hadder\NfseNacional\Danfse\Traits;

/**
 * Bloco "Valor Total da NFS-e" — NT-008 §2.1.11 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/valores (+ valores agregados de IBS/CBS)
 *
 * Layout em 2 linhas de 6,4mm (alt total ~12,8mm), grade uniforme de 4 cols:
 *   L1: [VALOR TOTAL DA NFS-E cinza] | VALOR DA OPERAÇÃO / SERVIÇO
 *       | Desconto Incondicionado | Desconto Condicionado
 *   L2: Total das Retenções (ISSQN / Federais) | VALOR LÍQUIDO DA NFS-e
 *       | Total do IBS/CBS | [VALOR LÍQUIDO DA NFS-e + IBS/CBS cinza]
 *
 * Os campos "totalizadores finais" (último cell da L2) e o título do bloco
 * recebem fundo cinza 5% (NT-008 §2.2.3 — destaque de valor relevante).
 */
trait TraitTotaisNFSe
{
    protected function blocoTotaisNFSe(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;
        $colQuarta = $larguraTotal / 4;

        // ----- Valores extraídos do XML -----
        // vServ declarado em infDPS/valores/vServPrest/vServ (estrutura nova) com fallback ao
        // caminho direto antigo (vServ no nível valores).
        $vServPrest = $this->getChild($this->valores, 'vServPrest');
        $vServ = (float) ($this->getTag($vServPrest, 'vServ', '')
                          ?: $this->getTag($this->valores, 'vServ', '0'));

        // Descontos: infDPS/valores/vDescCondIncond/{vDescIncond, vDescCond} com fallback.
        $vDescCI = $this->getChild($this->valores, 'vDescCondIncond');
        $vDescIncond = (float) ($this->getTag($vDescCI, 'vDescIncond', '')
                                ?: $this->getTag($this->valores, 'vDescIncond', '0'));
        $vDescCond   = (float) ($this->getTag($vDescCI, 'vDescCond', '')
                                ?: $this->getTag($this->valores, 'vDescCond', '0'));

        // Total retenções e Valor Líquido: preferencial em infNFSe/valores (calculados);
        // fallback à soma componente a componente do XML sintético antigo.
        $vTotalRetXml = $this->getTag($this->valoresNFSe, 'vTotalRet', '');
        $vLiqXml      = $this->getTag($this->valoresNFSe, 'vLiq', '');

        if ($vTotalRetXml !== '' && $vLiqXml !== '') {
            $totRetencoes = (float) $vTotalRetXml;
            $vLiq         = (float) $vLiqXml;
        } else {
            $vISSQNRet = (float) $this->extrairValor($this->valores, 'trib/tribMun/vISSQNRet', '0');
            $vRetCP    = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCP', '0');
            $vIRRF     = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetIRRF', '0');
            $vRetPIS   = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetPis', '0');
            $vRetCOF   = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCofins', '0');
            $vRetCSLL  = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCSLL', '0');
            $totRetencoes = $vISSQNRet + $vRetCP + $vIRRF + $vRetPIS + $vRetCOF + $vRetCSLL;
            $vLiq = $vServ - $vDescIncond - $vDescCond - $totRetencoes;
        }

        // Totais IBS/CBS: preferencial em infNFSe/IBSCBS/totCIBS; fallback a $ibscbs (DPS antigo).
        $totCIBS = $this->getChild($this->ibscbsNFSe, 'totCIBS');
        $vIBS = (float) ($this->extrairValor($totCIBS, 'gIBS/vIBSTot', '')
                         ?: $this->getTag($this->ibscbs, 'vIBS', '0'));
        $vCBS = (float) ($this->extrairValor($totCIBS, 'gCBS/vCBS', '')
                         ?: $this->getTag($this->ibscbs, 'vCBS', '0'));
        $totIBSCBS = $vIBS + $vCBS;

        // Valor Líquido + IBS/CBS: preferencial em infNFSe/IBSCBS/totCIBS/vTotNF.
        $vTotNFXml = $this->getTag($totCIBS, 'vTotNF', '');
        $vLiqMaisIBSCBS = $vTotNFXml !== '' ? (float) $vTotNFXml : ($vLiq + $totIBSCBS);

        // ----- L1 -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'VALOR TOTAL DA NFS-E');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'VALOR DA OPERAÇÃO / SERVIÇO', $this->moeda($vServ));
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, $colQuarta, $altLinha,
            'Desconto Incondicionado', $this->moedaOrDash($vDescIncond));
        $this->desenharCelula($xIni + 3 * $colQuarta, $yIni, $colQuarta, $altLinha,
            'Desconto Condicionado', $this->moedaOrDash($vDescCond));

        // ----- L2 -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($xIni, $y2, $colQuarta, $altLinha,
            'Total das Retenções (ISSQN / Federais)', $this->moedaOrDash($totRetencoes));
        $this->desenharCelula($xIni + $colQuarta, $y2, $colQuarta, $altLinha,
            'VALOR LÍQUIDO DA NFS-e', $this->moeda($vLiq));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Total do IBS/CBS', $this->moedaOrDash($totIBSCBS));

        // Destaque: VALOR LÍQUIDO + IBS/CBS (fundo cinza 5%)
        $this->pdf->SetFillColor(242, 242, 242);
        $this->pdf->Rect($xIni + 3 * $colQuarta, $y2, $colQuarta, $altLinha, 'F');
        $this->desenharCelula($xIni + 3 * $colQuarta, $y2, $colQuarta, $altLinha,
            'VALOR LÍQUIDO DA NFS-e + IBS/CBS', $this->moeda($vLiqMaisIBSCBS));

        return $yIni + 2 * $altLinha;
    }

    private function moeda(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    private function moedaOrDash(float $v): string
    {
        return $v > 0 ? $this->moeda($v) : '-';
    }
}
