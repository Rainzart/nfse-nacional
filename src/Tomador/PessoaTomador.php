<?php

namespace Hadder\NfseNacional\Tomador;

/**
 * Dados normalizados de uma pessoa (CPF ou CNPJ) usados para preencher
 * o tomador da DPS. Os campos de endereco seguem a mesma nomenclatura
 * esperada pelo grupo "toma" do src/Dps.php.
 */
class PessoaTomador
{
    /**
     * @param string $tipo "cpf" ou "cnpj"
     * @param string $documento Somente digitos (CPF) ou alfanumerico (CNPJ)
     * @param string $nome Nome da pessoa fisica ou razao social
     * @param string $logradouro
     * @param string $numero
     * @param string $complemento
     * @param string $bairro
     * @param string $cep Somente digitos
     * @param string $cidade
     * @param string $uf
     * @param string $codigoMunicipioIbge Codigo IBGE de 7 digitos
     * @param string|null $fantasia Nome fantasia (apenas CNPJ)
     * @param bool|null $simplesNacional Optante pelo Simples Nacional (apenas CNPJ, pacote 6)
     * @param string|null $situacao Situacao cadastral (apenas CNPJ, pacote 6)
     * @param string|null $porte Porte da empresa (apenas CNPJ, pacote 6)
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $documento,
        public readonly string $nome,
        public readonly string $logradouro = '',
        public readonly string $numero = '',
        public readonly string $complemento = '',
        public readonly string $bairro = '',
        public readonly string $cep = '',
        public readonly string $cidade = '',
        public readonly string $uf = '',
        public readonly string $codigoMunicipioIbge = '',
        public readonly ?string $fantasia = null,
        public readonly ?bool $simplesNacional = null,
        public readonly ?string $situacao = null,
        public readonly ?string $porte = null
    ) {
    }
}
