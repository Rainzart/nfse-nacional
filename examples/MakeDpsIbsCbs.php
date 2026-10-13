<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

use QuantumTecnology\NfseNacional\Dps;
use QuantumTecnology\NfseNacional\SchemaValidationException;
use QuantumTecnology\NfseNacional\Tools;

/*
 * Emissão com destaque de IBS/CBS (Reforma Tributária — EC 132/2023).
 *
 * A partir de 01/08/2026 o grupo <IBSCBS> é OBRIGATÓRIO na recepção da NFS-e.
 * Optantes do Simples Nacional só são exigidos a partir de 01/01/2027.
 *
 * No DPS você apenas DECLARA a situação tributária (CST + cClassTrib). As
 * alíquotas e os valores de IBS/CBS são calculados pelo Ambiente de Dados
 * Nacional e voltam no <infNFSe> da nota autorizada — não envie valores.
 */

try {
    $config        = new stdClass();
    $config->tpamb = 2; // 1 - Produção, 2 - Homologação
    $config->cnpj  = '00000000000000';
    $config->im    = '000000';
    $config->cmun  = '3170206';
    $config->razao = 'Empresa Exemplo LTDA';
    $configJson    = json_encode($config);

    $cert  = NFePHP\Common\Certificate::readPfx(file_get_contents('certificado.pfx'), 'senha_certificado');
    $tools = new Tools($configJson, $cert);

    $std = new stdClass();

    $std->infDPS           = new stdClass();
    $std->infDPS->tpAmb    = 2;
    $std->infDPS->dhEmi    = date('Y-m-d\TH:i:sP');
    $std->infDPS->verAplic = '1.00';
    $std->infDPS->serie    = '1';
    $std->infDPS->nDPS     = '1';
    $std->infDPS->dCompet  = date('Y-m-d');
    $std->infDPS->tpEmit   = 1;
    $std->infDPS->cLocEmi  = '3170206';

    $std->infDPS->prest                      = new stdClass();
    $std->infDPS->prest->CNPJ                = '00000000000000';
    $std->infDPS->prest->regTrib             = new stdClass();
    $std->infDPS->prest->regTrib->opSimpNac  = 1; // 1 - Não optante
    $std->infDPS->prest->regTrib->regEspTrib = 0;

    $std->infDPS->toma        = new stdClass();
    $std->infDPS->toma->CPF   = '00000000000';
    $std->infDPS->toma->xNome = 'Tomador de Exemplo';

    $std->infDPS->serv                          = new stdClass();
    $std->infDPS->serv->locPrest                = new stdClass();
    $std->infDPS->serv->locPrest->cLocPrestacao = '3170206';
    $std->infDPS->serv->cServ                   = new stdClass();
    $std->infDPS->serv->cServ->cTribNac         = '060401';
    $std->infDPS->serv->cServ->xDescServ        = 'Servico de exemplo';
    $std->infDPS->serv->cServ->cNBS             = '122051200'; // obrigatório

    $std->infDPS->valores                            = new stdClass();
    $std->infDPS->valores->vServPrest                = new stdClass();
    $std->infDPS->valores->vServPrest->vServ         = '100.00';
    $std->infDPS->valores->trib                      = new stdClass();
    $std->infDPS->valores->trib->tribMun             = new stdClass();
    $std->infDPS->valores->trib->tribMun->tribISSQN  = 1;
    $std->infDPS->valores->trib->tribMun->tpRetISSQN = 1;
    $std->infDPS->valores->trib->totTrib             = new stdClass();
    $std->infDPS->valores->trib->totTrib->indTotTrib = 0;

    /*
    |--------------------------------------------------------------------------
    | Grupo IBSCBS — o mínimo aceito pelo ADN
    |--------------------------------------------------------------------------
    |
    | ATENÇÃO: todos os códigos são STRING. Zero à esquerda é significativo —
    | um cast para int transforma '000001' em 1 e a nota é rejeitada.
    */
    $std->infDPS->IBSCBS = (object) [
        'finNFSe'  => '0',      // 0 - NFS-e regular
        'indFinal' => '0',      // 0 - não é uso/consumo pessoal
        'cIndOp'   => '030101', // tabela "código indicador de operação" (Anexo VII)
        'indDest'  => '0',      // 0 - o destinatário é o próprio tomador
        'valores'  => (object) [
            'trib' => (object) [
                'gIBSCBS' => (object) [
                    'CST'        => '000',    // 3 dígitos
                    'cClassTrib' => '000001', // 6 dígitos
                ],
            ],
        ],
    ];

    /*
    | Grupos opcionais — descomente conforme a operação:
    |
    | $std->infDPS->IBSCBS->tpOper    = '5';
    | $std->infDPS->IBSCBS->tpEnteGov = '4';
    |
    | // Destinatário diferente do tomador (indDest = 1)
    | $std->infDPS->IBSCBS->dest = (object) [
    |     'CNPJ'  => '00000000000000',
    |     'xNome' => 'Destinatario Exemplo',
    |     'end'   => (object) [
    |         'endNac'  => (object) ['cMun' => '3170206', 'CEP' => '38400000'],
    |         'xLgr'    => 'Rua Exemplo',
    |         'nro'     => '100',
    |         'xBairro' => 'Centro',
    |     ],
    | ];
    |
    | // NFS-e referenciadas (até 99)
    | $std->infDPS->IBSCBS->gRefNFSe = (object) ['refNFSe' => ['...50 digitos...']];
    |
    | // Crédito presumido / tributação regular / diferimento
    | $g = $std->infDPS->IBSCBS->valores->trib->gIBSCBS;
    | $g->cCredPres    = '01';
    | $g->gTribRegular = (object) ['CSTReg' => '000', 'cClassTribReg' => '000001'];
    | $g->gDif         = (object) ['pDifUF' => '1.00', 'pDifMun' => '2.00', 'pDifCBS' => '3.00'];
    */

    $dps = new Dps($std);
    $xml = $dps->render();

    // Valide ANTES de transmitir: economiza uma ida à SEFAZ e aponta o campo.
    if ($erros = $dps->validate($xml)) {
        echo 'XML fora do schema:', PHP_EOL;
        echo '  - ' . implode(PHP_EOL . '  - ', $erros), PHP_EOL;

        exit(1);
    }

    // Campos obrigatórios que ficaram vazios durante a montagem.
    if ($dps->getErrors()) {
        echo 'Erros de preenchimento: ', implode('; ', $dps->getErrors()), PHP_EOL;
    }

    $response = $tools->enviaDps($xml);

    if (isset($response['nfseXmlGZipB64'])) {
        echo 'Chave: ', $response['chaveAcesso'] ?? '(sem chave)', PHP_EOL;
        echo gzdecode(base64_decode($response['nfseXmlGZipB64'])), PHP_EOL;
    } else {
        var_dump($response);
    }
} catch (SchemaValidationException $e) {
    // O pacote barra o envio de XML fora do schema antes de assinar.
    echo 'XML inválido: ', PHP_EOL;
    echo '  - ' . implode(PHP_EOL . '  - ', $e->getErrors()), PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
