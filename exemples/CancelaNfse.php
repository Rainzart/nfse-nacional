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
    $tools         = new Hadder\NfseNacional\Tools($configJson, $cert);

    $std                       = new stdClass();
    $std->infPedReg            = new stdClass();
    $std->infPedReg->chNFSe    = '00000000000000000000000000000000000000000000000000'; // Chaveda NFS-e a qual o evento será vinculada.
    $std->infPedReg->CNPJAutor = '00000000000000';
    // $std->infPedReg->CPFAutor = '00000000000';
    $std->infPedReg->dhEvento = now()->format('Y-m-d\TH:i:sP');
    $std->infPedReg->tpAmb    = 1; // 1 - Produção; 2 - Homologação
    $std->infPedReg->verAplic = 'Nome_Sistema_V1.0';

    // Evento e101101 - Cancelamento
    $std->infPedReg->e101101          = new stdClass();
    $std->infPedReg->e101101->xDesc   = 'Cancelamento de NFS-e'; // Descrição do evento
    $std->infPedReg->e101101->cMotivo = 9; // Código de cancelamento: 1 - Erro na Emissão; 2 - Serviço não Prestado; 9 - Outros;
    $std->infPedReg->e101101->xMotivo = 'Teste de cancelamento da NFSe em produção pois em homologação não funciona!'; // Descrição para explicitar o motivo indicado neste evento.

    // Evento e105102 - Cancelamento por Substituição
    /*
     * Código de cancelamento
     * 1 - Desenquadramento de NFS-e do Simples Nacional;
     * 2 - Enquadramento de NFS-e no Simples Nacional;
     * 3 - Inclusão Retroativa de Imunidade/Isenção para NFS-e;
     * 4 - Exclusão Retroativa de Imunidade/Isenção para NFS-e;
     * 5 - Rejeição de NFS-e pelo tomador ou pelo intermediário se responsável pelo recolhimento do tributo;
     * 9 - Outros;
     * Obtido do campo da DPS ""DPS/infDPS/subst/cMotivo""."
     */
    //    $std->infPedReg->e105102 = new stdClass();
    //    $std->infPedReg->e105102->xDesc = 'Cancelamento de NFS-e por Substituição';// Descrição do evento
    //    $std->infPedReg->e105102->cMotivo = 1;// Código de cancelamento
    //    $std->infPedReg->e105102->xMotivo = '';// Descrição para explicitar o motivo indicado neste evento. Obtido do campo da DPS DPS/infDPS/subst/xMotivo
    //    $std->infPedReg->e105102->chSubstituta = '00000000000000000000000000000000000000000000000000';// Chave de Acesso da NFS-e substituta.

    $response = $tools->cancelaNfse($std);
    dd($response);

} catch (Exception $e) {
    dd($e->getMessage(), $e);
}
