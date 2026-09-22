<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\Pdf;

/**
 * Bloco "Informações Complementares" — NT-008 §2.1.12 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS (vários filhos compostos)
 *
 * Layout:
 *   L1 (alt 6,4mm): [INFORMAÇÕES COMPLEMENTARES] título full-width (cinza)
 *   L2 (expande até $yBottom): textBox multi-linha com os fragmentos na ordem
 *       da NT-008 §2.4.5, um por linha:
 *         Inf. Cont.: ...
 *         Cod. Obra: ...
 *         Insc. Imob.: ...
 *         Cod. Evt.: ...
 *         Inf. A. T. Mun.: ...
 *         Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012:
 *           Federais: R$ ...; Estaduais: R$ ...; Municipais: R$ ...
 */
trait TraitInfoComplementares
{
    protected function blocoInfoComplementares(float $xIni, float $yIni, float $yBottom): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $altTitulo = 6.4;

        // ----- L1: Título full-width cinza -----
        $this->desenharTituloBlocoCampo($xIni, $yIni, $larguraTotal, $altTitulo,
            'INFORMAÇÕES COMPLEMENTARES');

        // ----- L2: textBox expandindo até o fundo da moldura -----
        $texto = $this->montarTextoComplementar();
        $yTexto = $yIni + $altTitulo + 0.6;
        $altTexto = max(8.0, $yBottom - $yTexto - 0.6);

        $this->pdf->SetFont(Pdf::FONT_CONTEUDO, '', 7);
        $this->pdf->textBox(
            $xIni + 0.6,
            $yTexto,
            $larguraTotal - 1.2,
            $altTexto,
            $texto,
            'L',
            'T'
        );

        return $yBottom;
    }

    /**
     * Monta o texto das informações complementares, na ordem fixa da NT-008
     * §2.4.5, um fragmento por linha. A NT sugere separá-los por pipes ( | ),
     * mas o DANFSe gerado pelo Portal Nacional usa quebra de linha, e é esse o
     * comportamento seguido aqui. Fragmentos sem dados são omitidos, exceto a
     * linha de Totais Aproximados (Lei 12.741/2012), sempre presente.
     */
    private function montarTextoComplementar(): string
    {
        $linhas = [];

        $infoCompl = $this->getTag($this->infDPS, 'xInfComp', '');
        if ($infoCompl !== '') {
            $linhas[] = 'Inf. Cont.: ' . $infoCompl;
        }

        // Grupo de Obra (NT-008 nota 8): cObra e inscImobFisc vivem em serv/obra,
        // não em serv/infoCompl. O cCIB, alternativa ao cObra no <xs:choice> do
        // XSD, não é previsto pela NT e por isso não é impresso.
        $obra = $this->extrairValor($this->infDPS, 'serv/obra/cObra', '');
        if ($obra !== '') {
            $linhas[] = 'Cod. Obra: ' . $obra;
        }

        $imovel = $this->extrairValor($this->infDPS, 'serv/obra/inscImobFisc', '');
        if ($imovel !== '') {
            $linhas[] = 'Insc. Imob.: ' . $imovel;
        }

        // Grupo de Evento (NT-008 nota 9): apenas o idAtvEvt é previsto. Quando o
        // evento é identificado pelo endereço (a outra opção do <xs:choice>), não
        // há linha de evento nas informações complementares.
        $evento = $this->extrairValor($this->infDPS, 'serv/atvEvento/idAtvEvt', '');
        if ($evento !== '') {
            $linhas[] = 'Cod. Evt.: ' . $evento;
        }

        $infMun = $this->extrairValor($this->infDPS, 'serv/infoCompl/infATMun', '');
        if ($infMun !== '') {
            $linhas[] = 'Inf. A. T. Mun.: ' . $infMun;
        }

        // Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012 (sempre presente)
        // ISSQN apurado vem de infNFSe/valores (calculado); fallback ao caminho legacy.
        $federais   = $this->calcularTributosFederais();
        $gIBSTot    = $this->getChild($this->getChild($this->ibscbsNFSe, 'totCIBS'), 'gIBS');
        $estaduais  = (float) ($this->extrairValor($gIBSTot, 'gIBSUFTot/vIBSUF', '')
                               ?: $this->getTag($this->ibscbs, 'vIBSUF', '0'));
        $vISSQN     = (float) ($this->getTag($this->valoresNFSe, 'vISSQN', '')
                               ?: $this->extrairValor($this->valores, 'trib/tribMun/vISSQN', '0'));
        $vIBSMun    = (float) ($this->extrairValor($gIBSTot, 'gIBSMunTot/vIBSMun', '')
                               ?: $this->getTag($this->ibscbs, 'vIBSMun', '0'));
        $municipais = $vISSQN + $vIBSMun;

        $linhas[] = sprintf(
            'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: R$ %s; Estaduais: R$ %s; Municipais: R$ %s',
            number_format($federais, 2, ',', '.'),
            number_format($estaduais, 2, ',', '.'),
            number_format($municipais, 2, ',', '.')
        );

        return implode("\n", $linhas);
    }

    private function calcularTributosFederais(): float
    {
        $vPis    = (float) $this->extrairValor($this->valores, 'trib/tribFed/piscofins/vPis', '0');
        $vCofins = (float) $this->extrairValor($this->valores, 'trib/tribFed/piscofins/vCofins', '0');
        $vIRRF   = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetIRRF', '0');
        $vRetCP  = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCP', '0');
        $vRetCSLL = (float) $this->extrairValor($this->valores, 'trib/tribFed/vRetCSLL', '0');
        $vCBS    = (float) ($this->extrairValor($this->ibscbsNFSe, 'totCIBS/gCBS/vCBS', '')
                            ?: $this->getTag($this->ibscbs, 'vCBS', '0'));
        return $vPis + $vCofins + $vIRRF + $vRetCP + $vRetCSLL + $vCBS;
    }
}
