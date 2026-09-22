<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;
use Hadder\NfseNacional\Danfse\LocalidadeIbge;

/**
 * Bloco "Tributação IBS / CBS" — NT-008 §2.1.10 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/IBSCBS
 *
 * Layout em 4 linhas de 6,4mm (alt total ~25,6mm):
 *   L1: CST / cClassTrib | Indicador de Operação / Código IBGE Incidência
 *       / Município Incidência / Sigla UF
 *   L2: Exclusões e Reduções da Base de Cálculo | Base de Cálculo Após
 *       Exclusões e Reduções | Red. Alíquota IBS / Red. Alíquota CBS
 *       | Alíquota - IBS UF / IBS Mun
 *   L3: Alíq. Efetiva Municipal - IBS | Valor Apurado Municipal - IBS
 *       | Alíq. Efetiva Estadual - IBS | Valor Apurado Estadual - IBS
 *   L4: Valor Total Apurado - IBS | Alíquota - CBS | Alíquota Efetiva
 *       - CBS | Valor Total Apurado - CBS
 */
trait TraitTributacaoIBSCBS
{
    protected function blocoTributacaoIBSCBS(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altLinha = 6.4;
        $colQuarta = $larguraTotal / 4;

        // $ibscbs    = infDPS/IBSCBS   (declarados: CST, cClassTrib, cIndOp, finNFSe)
        // $ibscbsNFSe = infNFSe/IBSCBS (calculados: valores/{uf,mun,fed}, totCIBS/{gIBS,gCBS})
        $ibsValores = $this->getChild($this->ibscbsNFSe, 'valores');
        $ibsUF      = $this->getChild($ibsValores, 'uf');
        $ibsMun     = $this->getChild($ibsValores, 'mun');
        $ibsFed     = $this->getChild($ibsValores, 'fed');
        $totCIBS    = $this->getChild($this->ibscbsNFSe, 'totCIBS');
        $gIBSTot    = $this->getChild($totCIBS, 'gIBS');
        $gCBSTot    = $this->getChild($totCIBS, 'gCBS');

        // Estrutura DPS-nova traz CST/cClassTrib em infDPS/IBSCBS/valores/trib/gIBSCBS.
        // Fallback para CST/cClassTrib direto em $ibscbs (estrutura antiga sintética).
        $gIBSCBSDec = $this->getChild(
            $this->getChild($this->getChild($this->ibscbs, 'valores'), 'trib'),
            'gIBSCBS'
        );

        // ----- L1: TÍTULO cinza | CST / cClassTrib | Indic. Op. / Cód. IBGE / Munic. / UF -----
        $cst        = $this->getTag($gIBSCBSDec, 'CST', '')        ?: $this->getTag($this->ibscbs, 'CST', '');
        $cClassTrib = $this->getTag($gIBSCBSDec, 'cClassTrib', '') ?: $this->getTag($this->ibscbs, 'cClassTrib', '');
        $cstComp = trim(($cst !== '' ? $cst : '-') . ' / ' . ($cClassTrib !== '' ? $cClassTrib : '-'));

        $cIndOp = EnumDecoder::decode(EnumDecoder::C_IND_OP, $this->getTag($this->ibscbs, 'cIndOp', ''));
        $cLocIncid = $this->getTag($this->ibscbsNFSe, 'cLocalidadeIncid', '')
                     ?: $this->getTag($this->ibscbs, 'cLocIncid', '');
        $xLocIncid = $this->getTag($this->ibscbsNFSe, 'xLocalidadeIncid', '')
                     ?: (($cLocIncid !== '' && $cLocIncid === $this->cMunEmit) ? $this->xLocEmi : '');
        // UF derivada do código IBGE da localidade (leiaute não possui tag de UF aqui).
        $uf = LocalidadeIbge::resolver($cLocIncid)['uf'];
        $idComp = $this->montarComSeparador(' / ', [
            $cIndOp !== '-' ? $cIndOp : '',
            $cLocIncid,
            $xLocIncid,
            $uf,
        ]);

        // ----- L1 (grade 4 colunas): TÍTULO | CST / cClassTrib | Indic. Op./IBGE/Munic./UF (2 cols) -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $colQuarta, $altLinha,
            'TRIBUTAÇÃO IBS / CBS');
        $this->desenharCelula($xIni + $colQuarta, $yIni, $colQuarta, $altLinha,
            'CST / cClassTrib', $cstComp);
        $this->desenharCelula($xIni + 2 * $colQuarta, $yIni, 2 * $colQuarta, $altLinha,
            'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF',
            EnumDecoder::truncate($idComp, 80));

        // ----- L2: Excl/Red BC | BC Após | Red. Alíq IBS/CBS | Alíq IBS Est/Mun -----
        $y2 = $yIni + $altLinha;
        $vCalcReeRepRes = $this->getTag($ibsValores, 'vCalcReeRepRes', '');
        $this->desenharCelula($xIni, $y2, $colQuarta, $altLinha,
            'Exclusões e Reduções da Base de Cálculo',
            $this->formatar($vCalcReeRepRes, 'moeda'));

        $vBCIBS = $this->getTag($ibsValores, 'vBC', '')
                  ?: $this->getTag($this->ibscbs, 'vBC', '');
        $this->desenharCelula($xIni + $colQuarta, $y2, $colQuarta, $altLinha,
            'Base de Cálculo Após Exclusões e Reduções',
            $this->formatar($vBCIBS, 'moeda'));

        $pRedAliqUF  = $this->getTag($ibsUF, 'pRedAliqUF', '');
        $pRedAliqMun = $this->getTag($ibsMun, 'pRedAliqMun', '');
        $pRedAliqCBS = $this->getTag($ibsFed, 'pRedAliqCBS', '');
        $this->desenharCelula($xIni + 2 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Red. Alíquota IBS / Red. Alíquota CBS',
            $this->montarComSeparador(' / ', [
                $pRedAliqUF !== '' || $pRedAliqMun !== '' ? (($pRedAliqUF ?: '0') . '+' . ($pRedAliqMun ?: '0')) . '%' : '',
                $pRedAliqCBS !== '' ? $pRedAliqCBS . '%' : '',
            ]));

        $pAliqUF  = $this->getTag($ibsUF,  'pIBSUF',  '');
        $pAliqMun = $this->getTag($ibsMun, 'pIBSMun', '');
        $this->desenharCelula($xIni + 3 * $colQuarta, $y2, $colQuarta, $altLinha,
            'Alíquota - IBS UF / IBS Mun',
            $this->montarComSeparador(' / ', [
                $pAliqUF !== ''  ? number_format((float) $pAliqUF,  2, ',', '.') . '%' : '',
                $pAliqMun !== '' ? number_format((float) $pAliqMun, 2, ',', '.') . '%' : '',
            ]));

        // ----- L3: Alíq Efetiva IBS Mun | Valor IBS Mun | Alíq Efetiva IBS Est | Valor IBS Est -----
        $y3 = $yIni + 2 * $altLinha;
        $pAliqEfetMun = $this->getTag($ibsMun, 'pAliqEfetMun', '');
        $pAliqEfetUF  = $this->getTag($ibsUF,  'pAliqEfetUF',  '');
        $vIBSMun = $this->getTag($this->getChild($gIBSTot, 'gIBSMunTot'), 'vIBSMun', '');
        $vIBSUF  = $this->getTag($this->getChild($gIBSTot, 'gIBSUFTot'),  'vIBSUF',  '');

        $this->desenharCelula($xIni, $y3, $colQuarta, $altLinha,
            'Alíq. Efetiva Municipal - IBS',
            $this->formatar($pAliqEfetMun, 'percent'));
        $this->desenharCelula($xIni + $colQuarta, $y3, $colQuarta, $altLinha,
            'Valor Apurado Municipal - IBS',
            $this->formatar($vIBSMun, 'moeda'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Alíq. Efetiva Estadual - IBS',
            $this->formatar($pAliqEfetUF, 'percent'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y3, $colQuarta, $altLinha,
            'Valor Apurado Estadual - IBS',
            $this->formatar($vIBSUF, 'moeda'));

        // ----- L4: Valor Total IBS | Alíq CBS | Alíq Efetiva CBS | Valor Total CBS -----
        $y4 = $yIni + 3 * $altLinha;
        $vIBSTotApurado = $this->getTag($gIBSTot, 'vIBSTot', '');
        $pAliqCBS       = $this->getTag($ibsFed, 'pCBS', '');
        $pAliqEfetCBS   = $this->getTag($ibsFed, 'pAliqEfetCBS', '');
        $vCBSTotApurado = $this->getTag($gCBSTot, 'vCBS', '');

        $this->desenharCelula($xIni, $y4, $colQuarta, $altLinha,
            'Valor Total Apurado - IBS',
            $this->formatar($vIBSTotApurado, 'moeda'));
        $this->desenharCelula($xIni + $colQuarta, $y4, $colQuarta, $altLinha,
            'Alíquota - CBS',
            $this->formatar($pAliqCBS, 'percent'));
        $this->desenharCelula($xIni + 2 * $colQuarta, $y4, $colQuarta, $altLinha,
            'Alíquota Efetiva - CBS',
            $this->formatar($pAliqEfetCBS, 'percent'));
        $this->desenharCelula($xIni + 3 * $colQuarta, $y4, $colQuarta, $altLinha,
            'Valor Total Apurado - CBS',
            $this->formatar($vCBSTotApurado, 'moeda'));

        return $yIni + 4 * $altLinha;
    }

    /** Tenta uma lista de tags; retorna o primeiro valor não-vazio. */
    private function getTagFallbackIBS(?\DOMElement $parent, array $tags): string
    {
        foreach ($tags as $tag) {
            $v = $this->getTag($parent, $tag, '');
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** Junta partes não-vazias com separador; retorna '-' se tudo vazio. */
    private function montarComSeparador(string $sep, array $partes): string
    {
        $filtradas = array_filter($partes, fn($v) => $v !== '');
        return $filtradas ? implode($sep, $filtradas) : '-';
    }
}
