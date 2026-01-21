<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

try {
    $config        = new stdClass();
    $config->tpamb = 1; // 1 - Produção, 2 - Homologação
    // $config->formatOutput = true; // Para debug retorna XML formatado
    $configJson = json_encode($config);
    $content    = file_get_contents('certificado.pfx');
    $password   = 'senha_certificado';
    $cert       = NFePHP\Common\Certificate::readPfx($content, $password);
    $tools      = new Hadder\NfseNacional\Tools($configJson, $cert);
    // Informar chave NFSe
    $response = $tools->consultarDanfse('00000000000000000000000000000000000000000000000000');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NFSe.pdf"');
    header('Content-Transfer-Encoding: binary');
    //    header('Content-Length: ' . filesize($file));
    header('Accept-Ranges: bytes');

    echo $response;
} catch (Exception $e) {
    dd($e->getMessage(), $e);
}
