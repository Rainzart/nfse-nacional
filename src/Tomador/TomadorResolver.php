<?php

namespace Hadder\NfseNacional\Tomador;

use stdClass;

/**
 * Monta o grupo "toma" (tomador) da DPS a partir de um documento,
 * consultando uma PessoaLookup. O retorno e um stdClass no mesmo formato
 * esperado por Dps::render(), pronto para ser atribuido a
 * $std->infDPS->toma.
 *
 * Uso:
 *   $resolver = new TomadorResolver(new CpfCnpjComBrLookup($token));
 *   $std->infDPS->toma = $resolver->porDocumento('00000000000');
 */
class TomadorResolver
{
    private PessoaLookup $lookup;

    public function __construct(PessoaLookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * Resolve o tomador detectando automaticamente se o documento e CPF
     * (11 digitos) ou CNPJ (14 caracteres).
     */
    public function porDocumento(string $documento): stdClass
    {
        $limpo = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $documento) ?? '');
        $somenteDigitos = preg_replace('/[^0-9]/', '', $documento) ?? '';

        if (strlen($somenteDigitos) === 11 && strlen($limpo) === 11) {
            return $this->porCpf($somenteDigitos);
        }
        if (strlen($limpo) === 14) {
            return $this->porCnpj($limpo);
        }

        throw new TomadorLookupException("Documento invalido: {$documento}. Informe um CPF (11 digitos) ou CNPJ (14 caracteres).");
    }

    /**
     * Resolve o tomador a partir de um CPF.
     */
    public function porCpf(string $cpf): stdClass
    {
        return $this->montar($this->lookup->consultarCpf($cpf));
    }

    /**
     * Resolve o tomador a partir de um CNPJ.
     */
    public function porCnpj(string $cnpj): stdClass
    {
        return $this->montar($this->lookup->consultarCnpj($cnpj));
    }

    /**
     * Converte os dados normalizados no stdClass do grupo "toma".
     */
    private function montar(PessoaTomador $pessoa): stdClass
    {
        $toma = new stdClass();
        if ($pessoa->tipo === 'cnpj') {
            $toma->CNPJ = $pessoa->documento;
        } else {
            $toma->CPF = $pessoa->documento;
        }
        $toma->xNome = $pessoa->nome;

        $toma->end = new stdClass();
        $toma->end->xLgr = $pessoa->logradouro;
        $toma->end->nro = $pessoa->numero !== '' ? $pessoa->numero : 'S/N';
        if ($pessoa->complemento !== '') {
            $toma->end->xCpl = $pessoa->complemento;
        }
        $toma->end->xBairro = $pessoa->bairro;

        $toma->end->endNac = new stdClass();
        $toma->end->endNac->cMun = $pessoa->codigoMunicipioIbge;
        $toma->end->endNac->CEP = $pessoa->cep;

        return $toma;
    }
}
