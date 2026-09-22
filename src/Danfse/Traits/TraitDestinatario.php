<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Destinatário da Operação" — NT-008 §2.1.5 e §2.4.5.
 *
 * Nó XML: NFSe/infNFSe/DPS/infDPS/IBSCBS/dest (introduzido pelo grupo IBS/CBS
 * para identificar quem é o destinatário do serviço quando difere do tomador,
 * em operações sujeitas ao novo modelo de tributação federal).
 *
 * Mesmo padrão visual de Tomador: bloco fechado por moldura única, sem
 * bordas verticais. Suporta supressão (§2.3.2 nota 3) quando o destinatário
 * não está informado.
 *
 * Layout em 3 linhas de 6,4mm cada (alt total 19,2mm) quando preenchido:
 *   L1: [DESTINATÁRIO cinza]   | CNPJ/CPF/NIF | IM | Telefone
 *   L2: Nome (largo)            | Município/UF | Código IBGE/CEP
 *   L3: Endereço (largo)        | E-mail (largo)
 */
trait TraitDestinatario
{
    protected function blocoDestinatario(float $xIni, float $yIni): float
    {
        $larguraTotal = $this->maxW - 2 * $this->margesq;

        // ----- Supressão: destinatário não informado -----
        if (!$this->dest || $this->extrairDocumento($this->dest) === '-') {
            $altFaixa = 4.2;
            $this->desenharFaixaSupressao(
                $xIni, $yIni, $larguraTotal, $altFaixa,
                'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e'
            );
            return $yIni + $altFaixa;
        }

        $altLinha = 6.4;
        $col = $larguraTotal / 4;        // grade uniforme (igual aos demais blocos)
        $colDupla = 2 * $col;

        $x1 = $xIni;
        $x2 = $xIni + $col;
        $x3 = $xIni + 2 * $col;
        $x4 = $xIni + 3 * $col;

        // ----- L1: título + doc + IM + Telefone -----
        $this->desenharTituloBlocoCampo($x1, $yIni, $col, $altLinha, 'DESTINATÁRIO');
        $this->desenharCelula($x2, $yIni, $col, $altLinha,
            'CNPJ / CPF / NIF', $this->extrairDocumento($this->dest));
        $this->desenharCelula($x3, $yIni, $col, $altLinha,
            'Indicador Municipal (Inscrição)', $this->getTag($this->dest, 'IM', ''));
        $this->desenharCelula($x4, $yIni, $col, $altLinha,
            'Telefone', $this->formatarTelefone($this->getTag($this->dest, 'fone', '')));

        // ----- L2: Nome | Município/UF | Código IBGE/CEP -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($x1, $y2, $colDupla, $altLinha,
            'Nome / Nome Empresarial',
            EnumDecoder::truncate($this->getTag($this->dest, 'xNome', ''), 80));

        [$municipio, $uf, $cep, $cMun] = $this->extrairEndereco($this->dest);
        $munUf = $municipio !== '-' ? $municipio . ($uf !== '' ? " / {$uf}" : '') : '-';
        $this->desenharCelula($x3, $y2, $col, $altLinha, 'Município / Sigla UF', $munUf);

        $codCep = $cMun !== '' ? "{$cMun} / {$this->formatarCEP($cep)}" : '-';
        $this->desenharCelula($x4, $y2, $col, $altLinha, 'Código IBGE / CEP', $codCep);

        // ----- L3: Endereço | E-mail (NT nota *: suprime se ambos vazios) -----
        $y = $yIni + 2 * $altLinha;
        $endereco = $this->extrairEnderecoLogradouro($this->dest);
        $email = $this->getTag($this->dest, 'email', '');
        if ($endereco !== '-' || $email !== '') {
            $this->desenharCelula($x1, $y, $colDupla, $altLinha, 'Endereço',
                EnumDecoder::truncate($endereco, 80));
            $this->desenharCelula($x3, $y, $colDupla, $altLinha, 'E-mail',
                EnumDecoder::truncate($email, 80));
            $y += $altLinha;
        }

        return $y;
    }
}
