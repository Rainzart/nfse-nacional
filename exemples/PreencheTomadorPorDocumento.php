<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Tomador\CpfCnpjComBrLookup;
use Hadder\NfseNacional\Tomador\TomadorResolver;

/*
 * Exemplo opcional: preencher o grupo "toma" (tomador) da DPS a partir de
 * um CPF ou CNPJ, consultando a API da CPF.CNPJ (https://www.cpfcnpj.com.br).
 *
 * O token e obtido no painel da CPF.CNPJ em API > Tokens. Abaixo usamos o
 * token publico de testes, que devolve dados ficticios.
 */

$token = '5ae973d7a997af13f0aaf2bf60e65803';

$resolver = new TomadorResolver(
    new CpfCnpjComBrLookup($token)
);

$std = new stdClass();
$std->infDPS = new stdClass();

// Detecta automaticamente se e CPF (11 digitos) ou CNPJ (14 caracteres).
$std->infDPS->toma = $resolver->porDocumento('00000000000');

// Tambem e possivel ser explicito:
// $std->infDPS->toma = $resolver->porCpf('00000000000');
// $std->infDPS->toma = $resolver->porCnpj('00000000000000');

print_r($std->infDPS->toma);
