<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

try {
    $config        = new stdClass();
    $config->tpamb = 1; // 1 - Produção, 2 - Homologação
    $configJson    = json_encode($config);
    $content       = file_get_contents('certificado.pfx');
    $password      = 'senha_certificado';
    $cert          = NFePHP\Common\Certificate::readPfx($content, $password);
    $tools         = new QuantumTecnology\NfseNacional\Tools($configJson, $cert);
    // Informar chave da DPS para obter a chave da NFSe
    $response = $tools->consultarDpsChave('DPS000000000000000000000000000000000000000000');

    var_dump($response);

} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
