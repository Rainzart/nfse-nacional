<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

use QuantumTecnology\NfseNacional\SchemaValidationException;
use QuantumTecnology\NfseNacional\Tools;

/*
 * Cancelamento por SUBSTITUIÇÃO (evento e105102).
 *
 * Diferente do cancelamento comum (e101101), aqui a NFS-e original é
 * substituída por outra, cuja chave vai em chSubstituta.
 *
 * Atenção às enumerações — elas são DIFERENTES entre os dois eventos:
 *   e101101 (cancelamento)  → cMotivo: 1, 2 ou 9
 *   e105102 (substituição)  → cMotivo: 01, 02, 03, 04, 05 ou 99  (com zero!)
 *
 * O xDesc NÃO deve ser informado: é enumeração de valor fixo no schema e o
 * pacote o deriva do próprio evento.
 */

try {
    $config        = new stdClass();
    $config->tpamb = 2; // 1 - Produção, 2 - Homologação
    $configJson    = json_encode($config);

    $cert  = NFePHP\Common\Certificate::readPfx(file_get_contents('certificado.pfx'), 'senha_certificado');
    $tools = new Tools($configJson, $cert);

    $std = new stdClass();

    $std->infPedReg            = new stdClass();
    $std->infPedReg->tpAmb     = 2;
    $std->infPedReg->verAplic  = '1.00';
    $std->infPedReg->dhEvento  = date('Y-m-d\TH:i:sP');
    $std->infPedReg->CNPJAutor = '00000000000000';
    // $std->infPedReg->CPFAutor = '00000000000';  // alternativa ao CNPJ

    // Chave da NFS-e que está sendo substituída (50 dígitos)
    $std->infPedReg->chNFSe = '00000000000000000000000000000000000000000000000000';

    $std->infPedReg->e105102               = new stdClass();
    $std->infPedReg->e105102->cMotivo      = '01'; // 01..05, 99 — com zero à esquerda
    $std->infPedReg->e105102->xMotivo      = 'Substituicao por erro de valor'; // opcional
    $std->infPedReg->e105102->chSubstituta = '00000000000000000000000000000000000000000000000000';

    $response = $tools->cancelaNfse($std);

    var_dump($response);
} catch (SchemaValidationException $e) {
    echo 'Evento fora do schema:', PHP_EOL;
    echo '  - ' . implode(PHP_EOL . '  - ', $e->getErrors()), PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
