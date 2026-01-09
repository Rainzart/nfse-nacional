<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
// ini_set('timezone', 'America/Sao_Paulo');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

try {
    $config        = new stdClass();
    $config->tpamb = 1; // 1 - Produção, 2 - Homologação

    $configJson = json_encode($config);
    $content    = file_get_contents('certificado.pfx');
    $password   = 'senha_certificado';

    $cert  = NFePHP\Common\Certificate::readPfx($content, $password);
    $tools = new Hadder\NfseNacional\Tools($configJson, $cert);

    $std = new stdClass();

    $std->infDPS           = new stdClass();
    $std->infDPS->tpAmb    = 1; // 1 - Produção, 2 - Homologação
    $std->infDPS->dhEmi    = now()->format('Y-m-d\TH:i:sP'); // "Data e hora da emissão da DPS. AAAA-MM-DDThh:mm:ssTZD"
    $std->infDPS->verAplic = 'Nome_Sistema_V1.0';
    $std->infDPS->serie    = 1;
    $std->infDPS->nDPS     = 2;
    $std->infDPS->dCompet  = now()->format('Y-m-d'); // "Data de competência da prestação do serviço. Ano, Mês e Dia (AAAA-MM-DD)"
    $std->infDPS->tpEmit   = 1; // Emitente da DPS: 1 - Prestador; 2 - Tomador; 3 - Intermediário;
    $std->infDPS->cLocEmi  = '0000000'; // Código IBGE 7 dígitos da cidade emissora da NFS-e.

    //    $std->infDPS->subst = new stdClass();
    //    $std->infDPS->subst->chSubstda = 'DPS000000000000000000000000000000000000000000'; // Chave de Acesso da NFS-e a ser substituída.
    //    $std->infDPS->subst->cMotivo = '01'; // 01 - Desenquadramento de NFS-e do Simples Nacional; 02 - Enquadramento de NFS-e no Simples Nacional; 03 - Inclusão Retroativa de Imunidade/Isenção para NFS-e; 04 - Exclusão Retroativa de Imunidade/Isenção para NFS-e; 05 - Rejeição de NFS-e pelo tomador ou pelo intermediário se responsável pelo recolhimento do tributo; 99 - Outros;
    //    $std->infDPS->subst->xMotivo = 'Descreva o motivo'; // Descrição do motivo da substituição quando cMotivo = 9

    // Dados do Prestador de serviço
    $std->infDPS->prest       = new stdClass();
    $std->infDPS->prest->CNPJ = '00000000000000';
    //    $std->infDPS->prest->CPF = '00000000000';
    //    $std->infDPS->prest->NIF = ''; // Número de identificação fiscal fornecido por órgão de administração tributária no exterior.
    //    $std->infDPS->prest->cNaoNIF = 0; // Motivo para não informação do NIF: 0 - Não informado na nota de origem; 1 - Dispensado do NIF; 2 - Não exigência do NIF;
    //    $std->infDPS->prest->CAEPF = '0'; // Número do Cadastro de Atividade Econômica da Pessoa Física (CAEPF) do prestador do serviço.
    //    $std->infDPS->prest->IM = '0'; // Número de inscrição municipal do prestador do serviço.
    //    $std->infDPS->prest->xNome = 'Hadder Soft';
    $std->infDPS->prest->fone = '00000000000';
    //    $std->infDPS->prest->email = '';

    //    $std->infDPS->prest->end = new stdClass();
    //    $std->infDPS->prest->end->xLgr = 'Logradouro';
    //    $std->infDPS->prest->end->nro = '000';
    //    $std->infDPS->prest->end->xCpl = 'Complemento';
    //    $std->infDPS->prest->end->xBairro = 'Bairro';
    //    $std->infDPS->prest->end->endNac = new stdClass();
    //    $std->infDPS->prest->end->endNac->cMun = '0000000'; //Código IBGE 7 dígitos
    //    $std->infDPS->prest->end->endNac->CEP = '00000000';

    //    $std->infDPS->prest->end->endExt = new stdClass();
    //    $std->infDPS->prest->end->endExt->cPais = ''; // Código do país do endereço do prestador do prestador do serviço. (Tabela de Países ISO)
    //    $std->infDPS->prest->end->endExt->cEndPost = ''; // Código alfanumérico do Endereçamento Postal no exterior do prestador do serviço.
    //    $std->infDPS->prest->end->endExt->xCidade = ''; // Nome da cidade no exterior do prestador do serviço.
    //    $std->infDPS->prest->end->endExt->xEstProvReg = ''; // Estado, província ou região da cidade no exterior do prestador do serviço.
    $std->infDPS->prest->regTrib            = new stdClass();
    $std->infDPS->prest->regTrib->opSimpNac = 2; // Situação perante Simples Nacional: 1 - Não Optante; 2 - Optante - Microempreendedor Individual (MEI); 3 - Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP);
    //    $std->infDPS->prest->regTrib->regApTribSN = 2; // Regime de Apuração Tributária pelo Simples Nacional. 1 – Regime de apuração dos tributos pelo SN; 2 – Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal; 3 – Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legilações federal e municipal de cada tributo;"
    $std->infDPS->prest->regTrib->regEspTrib = 0; // Tipos de Regimes: 0 - Nenhum; 1 - Ato Cooperado (Cooperativa); 2 - Estimativa; 3 - Microempresa Municipal; 4 - Notário ou Registrador; 5 - Profissional Autônomo; 6 - Sociedade de Profissionais;"

    // Dados do Tomador do Serviço
    $std->infDPS->toma = new stdClass();
    //    $std->toma->CNPJ = '00000000000000';
    $std->infDPS->toma->CPF = '00000000000';
    //    $std->infDPS->toma->NIF = ''; // Número de identificação fiscal fornecido por órgão de administração tributária no exterior.
    //    $std->infDPS->toma->cNaoNIF = 0; // Motivo para não informação do NIF: 0 - Não informado na nota de origem; 1 - Dispensado do NIF; 2 - Não exigência do NIF;
    //    $std->infDPS->toma->CAEPF = '0'; // Número do Cadastro de Atividade Econômica da Pessoa Física (CAEPF) do tomaador do serviço.
    //    $std->infDPS->toma->IM = '0'; // Número de inscrição municipal do tomaador do serviço.
    $std->infDPS->toma->xNome = 'Hadder Soft'; // Número de inscrição municipal do tomaador do serviço.
    //    $std->infDPS->toma->fone = '';
    //    $std->infDPS->toma->email = '';

    $std->infDPS->toma->end       = new stdClass();
    $std->infDPS->toma->end->xLgr = 'Logradouro';
    $std->infDPS->toma->end->nro  = '000';
    //    $std->infDPS->toma->end->xCpl = 'Complemento';
    $std->infDPS->toma->end->xBairro = 'Bairro';

    $std->infDPS->toma->end->endNac       = new stdClass();
    $std->infDPS->toma->end->endNac->cMun = '0000000'; // Código IBGE 7 dígitos
    $std->infDPS->toma->end->endNac->CEP  = '00000000';

    // $std->infDPS->toma->end->endExt = new stdClass();
    // $std->infDPS->toma->end->endExt->cPais = ''; // Código do país do endereço do tomaador do tomaador do serviço. (Tabela de Países ISO)
    // $std->infDPS->toma->end->endExt->cEndPost = ''; // Código alfanumérico do Endereçamento Postal no exterior do tomaador do serviço.
    // $std->infDPS->toma->end->endExt->xCidade = ''; // Nome da cidade no exterior do tomaador do serviço.
    // $std->infDPS->toma->end->endExt->xEstProvReg = ''; // Estado, província ou região da cidade no exterior do tomaador do serviço.

    $std->infDPS->serv                          = new stdClass();
    $std->infDPS->serv->locPrest                = new stdClass();
    $std->infDPS->serv->locPrest->cLocPrestacao = '0000000'; // Código da localidade da prestação do serviço.
    // $std->infDPS->serv->locPrest->cPaisPrestacao = 'BR';// Código do país onde ocorreu a prestação do serviço. (Tabela de Países ISO)
    // $std->infDPS->serv->locPrest->cPaisConsum = 'BR';// Código do país onde ocorreu o consumo do serviço prestado. (Tabela de Países ISO)

    $std->infDPS->serv->cServ           = new stdClass();
    $std->infDPS->serv->cServ->cTribNac = '010101'; // Código de tributação Nacional
    //        $std->infDPS->serv->cServ->cTribMun = ''; //Código de tributação municipal do ISSQN.
    $std->infDPS->serv->cServ->xDescServ = 'Descrição do Serviço';
    //        $std->infDPS->serv->cServ->cNBS = '';// Código NBS correspondente ao serviço prestado
    $std->infDPS->serv->cServ->cIntContrib = '1234'; // Código interno do contribuinte - Utilizado para identificação da DPS no Sistema interno do Contribuinte

    //    Grupo de informações sobre transações entre residentes ou domiciliados no Brasil com residentes ou domiciliados no exterior
    //    $std->infDPS->serv->comExt = new stdClass();
    //    $std->infDPS->serv->comExt->mdPrestacao = 4; // Modo de Prestação:     0 - Desconhecido (tipo não informado na nota de origem); 1 - Transfronteiriço; 2 - Consumo no Brasil; 3 - Movimento Temporário de Pessoas Físicas; 4 - Consumo no Exterior;
    //    $std->infDPS->serv->comExt->vincPrest = 0; // Vínculo entre as partes no negócio: 0 - Sem vínculo com o Tomador/Prestador 1 - Controlada; 2 - Controladora; 3 - Coligada; 4 - Matriz; 5 - Filial ou sucursal; 6 - Outro vínculo;
    //    $std->infDPS->serv->comExt->tpMoeda = 840; //
    //    $std->infDPS->serv->comExt->vServMoeda = (float)1.00;
    //    $std->infDPS->serv->comExt->mecAFComexP = "01";
    //    $std->infDPS->serv->comExt->mecAFComexT = "01";
    //    $std->infDPS->serv->comExt->movTempBens = 1;
    //    $std->infDPS->serv->comExt->mdic = 0;

    //    $std->infDPS->serv->infoCompl = new stdClass();
    //    $std->infDPS->serv->infoCompl->xInfComp = 'Informações complementares';//Campo livre para preenchimento pelo contribuinte.

    $std->infDPS->valores             = new stdClass();
    $std->infDPS->valores->vServPrest = new stdClass();
    //    $std->infDPS->valores->vServPrest->vReceb = 0.0; //Valor monetário recebido pelo intermediário do serviço (R$).
    $std->infDPS->valores->vServPrest->vServ = number_format(1.0, 2, '.', ''); // Valor monetário do serviço (R$).

    $std->infDPS->valores->trib                      = new stdClass();
    $std->infDPS->valores->trib->tribMun             = new stdClass();
    $std->infDPS->valores->trib->tribMun->tribISSQN  = 1; // Tributação do ISSQN sobre o serviço prestado: 1 - Operação tributável; 2 - Imunidade 3 - Exportação de serviço; 4 - Não Incidência;
    $std->infDPS->valores->trib->tribMun->tpRetISSQN = 1; // Tipo de retencao do ISSQN: 1 - Não Retido; 2 - Retido pelo Tomador; 3 - Retido pelo Intermediario;

    $std->infDPS->valores->trib->totTrib             = new stdClass();
    $std->infDPS->valores->trib->totTrib->indTotTrib = 0;

    $dps      = new Hadder\NfseNacional\Dps($std);
    $response = $tools->enviaDps($dps->render());

    //    $response = [
    //        "tipoAmbiente" => 1,
    //        "versaoAplicativo" => "SefinNacional_1.3.0",
    //        "dataHoraProcessamento" => "2024-10-29T17:50:37.2687546-03:00",
    //        "idDps" => "NFS43030042228189466000104000000000004624101791626748",
    //        "chaveAcesso" => "43030042228189466000104000000000004624101791626748",
    //        "nfseXmlGZipB64" => "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",
    //        "alertas" => null
    //    ];
    if (isset($response['nfseXmlGZipB64'])) {
        $xml = gzdecode(base64_decode($response['nfseXmlGZipB64']));
        dd($response, $xml);
    } else {
        dd('Erro:', $response);
    }
} catch (Exception $e) {
    dd($e->getMessage(), $e);
}
