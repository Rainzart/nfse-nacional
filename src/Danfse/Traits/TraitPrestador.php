<?php

namespace Hadder\NfseNacional\Danfse\Traits;

use Hadder\NfseNacional\Danfse\EnumDecoder;

/**
 * Bloco "Prestador / Fornecedor" — NT-008 §2.1.3 e §2.4.5.
 *
 * Conforme modelo do Anexo I: bloco fechado por moldura única, sem bordas verticais
 * entre células. Apenas o título "PRESTADOR / FORNECEDOR" tem fundo cinza, ocupando
 * a primeira "coluna" da primeira linha.
 *
 * Layout em 4 linhas de 6,4mm cada (alt total 25,6mm):
 *   L1: [PRESTADOR/FORNECEDOR cinza] | CNPJ/CPF/NIF | IM | Telefone
 *   L2: Nome (largo)                  | Município/UF | Código IBGE/CEP
 *   L3: Endereço (largo)              | E-mail (largo)
 *   L4: Simples Nacional              | Regime Apuração Tributária SN (largo)
 */
trait TraitPrestador
{
    protected function blocoPrestador(float $xIni, float $yIni): float
    {
        $altLinha = 6.4;
        $larguraTotal = $this->maxW - 2 * $this->margesq;
        $col = $larguraTotal / 4;
        $colDupla = 2 * $col;

        $x1 = $xIni;
        $x2 = $xIni + $col;
        $x3 = $xIni + 2 * $col;
        $x4 = $xIni + 3 * $col;

        // No XML de produção, dados do prestador ficam em infNFSe/emit (confirmados).
        // Fallback para infDPS/prest (declarado) caso emit não exista.
        $documento = $this->extrairDocumento($this->emit);
        if ($documento === '-') {
            $documento = $this->extrairDocumento($this->prest);
        }
        $im    = $this->getTag($this->emit, 'IM', '')    ?: $this->getTag($this->prest, 'IM', '');
        $fone  = $this->getTag($this->emit, 'fone', '')  ?: $this->getTag($this->prest, 'fone', '');
        $xNome = $this->getTag($this->emit, 'xNome', '') ?: $this->getTag($this->prest, 'xNome', '');
        $email = $this->getTag($this->emit, 'email', '') ?: $this->getTag($this->prest, 'email', '');

        // ----- L1: título cinza + CNPJ/CPF/NIF + IM + Telefone -----
        $this->desenharTituloBlocoCampo($x1, $yIni, $col, $altLinha, 'PRESTADOR / FORNECEDOR');
        $this->desenharCelula($x2, $yIni, $col, $altLinha, 'CNPJ / CPF / NIF', $documento);
        $this->desenharCelula($x3, $yIni, $col, $altLinha,
            'Indicador Municipal (Inscrição)', $im !== '' ? $im : '-');
        $this->desenharCelula($x4, $yIni, $col, $altLinha,
            'Telefone', $this->formatarTelefone($fone));

        // ----- L2: Nome | Município/UF | Código IBGE/CEP -----
        $y2 = $yIni + $altLinha;
        $this->desenharCelula($x1, $y2, $colDupla, $altLinha,
            'Nome / Nome Empresarial',
            EnumDecoder::truncate($xNome !== '' ? $xNome : '-', 80));

        // Endereço: emit (estrutura flat enderNac) tem prioridade; senão prest (end/endNac).
        if ($this->emit && $this->getChild($this->emit, 'enderNac')) {
            [$endLog, $municipio, $uf, $cep, $cMun] = $this->extrairEnderecoEmit($this->emit);
        } else {
            [$municipio, $uf, $cep, $cMun] = $this->extrairEndereco($this->prest);
            $endLog = $this->extrairEnderecoLogradouro($this->prest);
        }
        $munUf = $municipio !== '-' ? $municipio . ($uf !== '' ? " / {$uf}" : '') : '-';
        $this->desenharCelula($x3, $y2, $col, $altLinha, 'Município / Sigla UF', $munUf);

        $codCep = $cMun !== '' ? "{$cMun} / {$this->formatarCEP($cep)}" : '-';
        $this->desenharCelula($x4, $y2, $col, $altLinha, 'Código IBGE / CEP', $codCep);

        $y = $yIni + 2 * $altLinha;

        // ----- L3: Endereço | E-mail (NT nota *: suprime se ambos vazios) -----
        if ($endLog !== '-' || $email !== '') {
            $this->desenharCelula($x1, $y, $colDupla, $altLinha, 'Endereço',
                EnumDecoder::truncate($endLog, 80));
            $this->desenharCelula($x3, $y, $colDupla, $altLinha, 'E-mail',
                EnumDecoder::truncate($email !== '' ? $email : '-', 80));
            $y += $altLinha;
        }

        // ----- L4: Simples Nacional | Regime de Apuração (emit/regTrib > prest/regTrib) -----
        $regTribEmit  = $this->getChild($this->emit,  'regTrib');
        $regTribPrest = $this->getChild($this->prest, 'regTrib');
        $opSimpNac   = $this->getTag($regTribEmit,  'opSimpNac',   '')
                       ?: $this->getTag($regTribPrest, 'opSimpNac',   '');
        $regApTribSN = $this->getTag($regTribEmit,  'regApTribSN', '')
                       ?: $this->getTag($regTribPrest, 'regApTribSN', '');

        $this->desenharCelula($x1, $y, $col, $altLinha,
            'Simples Nacional na Data de Competência',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::OP_SIMP_NAC, $opSimpNac), 40));
        $this->desenharCelula($x2, $y, 3 * $col, $altLinha,
            'Regime de Apuração Tributária pelo SN',
            EnumDecoder::truncate(EnumDecoder::decode(EnumDecoder::REG_AP_TRIB_SN, $regApTribSN), 120));
        $y += $altLinha;

        return $y;
    }
}
