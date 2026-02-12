<?php

// Este exemplo é para a consulta de um DPS utilizando SOAP, o XML modelo para esta consulta pode ser encontrado em storage/prefeituras/xml/consulta_dps_3147105.xml



function makeNfseSoap (string $protocol): string
{
    // Carregar o XML modelo para a consulta de DPS
    $xmlModelo = file_get_contents(storage_path("prefeituras/xml/consulta_dps_3147105.xml"));

    // Criar uma instância do SOAP
    $soap = new \Hadder\NfseNacional\Soap();

    // Carregar o XML modelo para o SOAP
    $soap->loadXml($xmlModelo);

    // Devolver todas as tags editáveis do XML para que possam ser preenchidas
    $tags = $soap->getEditableTags();

    // Alterar os valores dos campos do XML com o que você deseja enviar para a prefeitura e devolver o XML pronto para ser enviado
    return $soap->fill([
        'ConsultarDps/CNPJ'         => '11.111.111/0001-11', // CNPJ do prestador
        'ConsultarDps/IM'           => '12345678', // Inscrição Municipal do prestador
        'ConsultarDps/Protocolo'    => $protocol, // Protocolo de emissão do DPS
    ]);
}

function getNfseSoap (string $xml): array
{
    $config             = new stdClass();
    $config->tpamb      = app()->environment('production') ? 1 : 2; //1 - Produção, 2 - Homologaçã
    $config->prefeitura = '3147105';

    $configJson = json_encode($config);
    $content    = file_get_contents(storage_path("certificates/11111111000111/certificate.pfx"));
    $password   = '11111111';
    $cert       = \NFePHP\Common\Certificate::readPfx($content, $password);
    $tools      = new \Hadder\NfseNacional\Tools($configJson, $cert);
    return $tools->consultarDanfseWsdl($xml, 'ConsultarDps');
}

$xml        = makeNfseSoap('111111111111111');
$response   = getNfseSoap($xml);