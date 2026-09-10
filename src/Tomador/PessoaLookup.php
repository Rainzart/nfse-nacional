<?php

namespace Hadder\NfseNacional\Tomador;

/**
 * Contrato para consulta de dados cadastrais de um tomador a partir do
 * seu documento. Cada implementacao decide a origem dos dados (uma API
 * externa, um cache local, um banco de dados etc).
 */
interface PessoaLookup
{
    /**
     * Consulta os dados de uma pessoa fisica pelo CPF.
     *
     * @param string $cpf CPF com ou sem formatacao
     * @return PessoaTomador
     * @throws TomadorLookupException Quando a consulta falha ou nao retorna dados
     */
    public function consultarCpf(string $cpf): PessoaTomador;

    /**
     * Consulta os dados de uma pessoa juridica pelo CNPJ.
     *
     * @param string $cnpj CNPJ com ou sem formatacao (aceita alfanumerico)
     * @return PessoaTomador
     * @throws TomadorLookupException Quando a consulta falha ou nao retorna dados
     */
    public function consultarCnpj(string $cnpj): PessoaTomador;
}
