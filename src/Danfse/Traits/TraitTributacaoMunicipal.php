<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;
use Hadder\NfseNacional\Danfse\LocalidadeIbge;

/**
 * Bloco "Tributação Municipal (ISSQN)" — NT-008 §2.1.8 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun
 *
 * Layout em 4 linhas de 6,4mm (alt total ~25,6mm):
 *   L1: Tipo de Tributação do ISSQN | Município / Sigla UF / País de
 *       Incidência do ISSQN
 *   L2: Regime Especial de Tributação do ISSQN | Tipo de Imunidade do ISSQN
 *       | Suspensão da Exigibilidade do ISSQN | Número Processo Suspensão
 *   L3: Benefício Municipal | Cálculo do BM | Total Deduções/Reduções
 *       | Desconto Incondicionado
 *   L4: BC ISSQN | Alíquota Aplicada | Retenção do ISSQN | ISSQN Apurado
 */
trait TraitTributacaoMunicipal
{
    protected function blocoTributacaoMunicipal(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;

        $tribMun = $this->getChild($this->getChild($this->valores, 'trib'), 'tribMun');
        $colQuarta = $larguraTotal / 4;

        // ----- L1 (grade 4 colunas): TÍTULO | Tipo Tributação | Munic./UF/País (2 cols) -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'TRIBUTAÇÃO MUNICIPAL (ISSQN)');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'Tipo de Tributação do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TRIB_ISSQN, $this->getTag($tribMun, 'tribISSQN', '')),
                40));
        // Código IBGE de incidência: infNFSe/cLocIncid (calculado pela administração
        // tributária); fallback para tribMun/cLocIncid em XML legado. UF/País são
        // derivados do código IBGE; o nome vem de infNFSe/xLocIncid.
        $cLocIncid = $this->getTag($this->infNFSe, 'cLocIncid', '')
                     ?: $this->getTag($tribMun, 'cLocIncid', '');
        ['uf' => $ufIncid, 'pais' => $paisIncid] = LocalidadeIbge::resolver($cLocIncid);
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, 2 * $colQuarta, $altLinha,
            'Município / Sigla UF / País de Incidência do ISSQN',
            $this->formatarMunicipioUfPais($this->xLocIncid, $ufIncid, $paisIncid));

        // Cursor de Y corrente — L2 e L3 podem ser suprimidas (NT nota **).
        $y = $yIni + $altLinha;

        // ----- L2: Reg. Especial | Imunidade | Suspensão | Nº Processo
        //       (NT nota **: suprime se TODOS os campos da linha vazios) -----
        if (!$this->todosVazios($tribMun, ['regEspTrib', 'tpImunidade', 'tpSusp', 'nProcesso'])) {
            $this->desenharCelula($xIni, $y, $colQuarta, $altLinha,
                'Regime Especial de Tributação do ISSQN',
                EnumDecoder::truncate(
                    EnumDecoder::decode(EnumDecoder::REG_ESP_TRIB, $this->getTag($tribMun, 'regEspTrib', '')),
                    40));
            $this->desenharCelula($xIni + $colQuarta, $y, $colQuarta, $altLinha,
                'Tipo de Imunidade do ISSQN',
                EnumDecoder::truncate(
                    EnumDecoder::decode(EnumDecoder::TP_IMUNIDADE, $this->getTag($tribMun, 'tpImunidade', '')),
                    40));
            $this->desenharCelula($xIni + 2 * $colQuarta, $y, $colQuarta, $altLinha,
                'Suspensão da Exigibilidade do ISSQN',
                EnumDecoder::truncate(
                    EnumDecoder::decode(EnumDecoder::TP_SUSP, $this->getTag($tribMun, 'tpSusp', '')),
                    40));
            $this->desenharCelula($xIni + 3 * $colQuarta, $y, $colQuarta, $altLinha,
                'Número Processo Suspensão', $this->getTag($tribMun, 'nProcesso', ''));
            $y += $altLinha;
        }

        // ----- L3: Benefício Municipal | Cálculo do BM | Total Deduções/Reduções | Desc. Incondicionado
        //       (NT nota **: suprime se TODOS os campos da linha vazios) -----
        $l3Vazia = $this->todosVazios($tribMun, ['tpBM', 'cBM', 'vTotDR', 'vDed'])
            && $this->getTag($this->valores, 'vDescIncond', '') === '';
        if (!$l3Vazia) {
            $this->desenharCelula($xIni, $y, $colQuarta, $altLinha,
                'Benefício Municipal',
                EnumDecoder::truncate(
                    EnumDecoder::decode(EnumDecoder::TP_BM, $this->getTag($tribMun, 'tpBM', '')),
                    40));
            $this->desenharCelula($xIni + $colQuarta, $y, $colQuarta, $altLinha,
                'Cálculo do BM', $this->getTag($tribMun, 'cBM', ''));
            $this->desenharCelula($xIni + 2 * $colQuarta, $y, $colQuarta, $altLinha,
                'Total Deduções/Reduções',
                $this->formatar($this->getTagFallback($tribMun, ['vTotDR', 'vDed']), 'moeda'));
            $this->desenharCelula($xIni + 3 * $colQuarta, $y, $colQuarta, $altLinha,
                'Desconto Incondicionado',
                $this->formatar($this->getTag($this->valores, 'vDescIncond', ''), 'moeda'));
            $y += $altLinha;
        }

        // ----- L4: BC | Alíquota | Retenção | ISSQN Apurado (sempre impressa) -----
        // Valores calculados em infNFSe/valores (spec); fallback ao tribMun (DPS) para XML legado.
        $vBC    = $this->getTag($this->valoresNFSe, 'vBC', '')
                  ?: $this->getTag($tribMun, 'vBC', '');
        $pAliq  = $this->getTag($this->valoresNFSe, 'pAliqAplic', '')
                  ?: $this->getTag($tribMun, 'pAliq', '');
        $vISSQN = $this->getTag($this->valoresNFSe, 'vISSQN', '')
                  ?: $this->getTag($tribMun, 'vISSQN', '');

        $this->desenharCelula($xIni, $y, $colQuarta, $altLinha,
            'BC ISSQN', $this->formatar($vBC, 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y, $colQuarta, $altLinha,
            'Alíquota Aplicada', $this->formatar($pAliq, 'percent'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y, $colQuarta, $altLinha,
            'Retenção do ISSQN',
            EnumDecoder::truncate(
                EnumDecoder::decode(EnumDecoder::TP_RET_ISSQN, $this->getTag($tribMun, 'tpRetISSQN', '')),
                40));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y, $colQuarta, $altLinha,
            'ISSQN Apurado', $this->formatar($vISSQN, 'moeda'));
        $y += $altLinha;

        return $y;
    }

    /** Tenta uma lista de tags; retorna o primeiro valor não-vazio. */
    private function getTagFallback(?\DOMElement $parent, array $tags): string
    {
        foreach ($tags as $tag) {
            $v = $this->getTag($parent, $tag, '');
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private function formatarMunicipioUfPais(string $nome, string $uf, string $pais): string
    {
        $partes = array_filter([$nome, $uf, $pais], fn($v) => $v !== '');
        return $partes ? implode(' / ', $partes) : '-';
    }
}
