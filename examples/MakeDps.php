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
    $tools = new QuantumTecnology\NfseNacional\Tools($configJson, $cert);

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
    //    $std->infDPS->prest->xNome = 'Empresa Exemplo LTDA';
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
    $std->infDPS->toma->xNome = 'Empresa Exemplo LTDA'; // Número de inscrição municipal do tomaador do serviço.
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

    $std->infDPS->valores->trib                     = new stdClass();
    $std->infDPS->valores->trib->tribMun            = new stdClass();
    $std->infDPS->valores->trib->tribMun->tribISSQN = 1; // Tributação do ISSQN sobre o serviço prestado: 1 - Operação tributável; 2 - Imunidade 3 - Exportação de serviço; 4 - Não Incidência;
    // $std->infDPS->valores->trib->tribMun->tpImunidade = 2; // Se a tributação do ISSQN for igual a "2 - Imunidade" é necessário especificar o tipo de imunidade: 1 - Patrimônio, renda ou serviços, uns dos outros (CF88, Art 150, VI, "a"); 2 - Templos de qualquer culto (CF88, Art 150, VI, "b"); 3 - Patrimônio, renda ou serviços dos partidos políticos, inclusive suas fundações, das entidades sindicais dos trabalhadores, das instituições de educação e de assistência social, sem fins lucrativos, atendidos os requisitos da lei (CF88, Art 150, VI, "c"); 4 - Livros, jornais, periódicos e o papel destinado a sua impressão (CF88, Art 150, VI, "d"); 5 - Fonogramas e videofonogramas musicais produzidos no Brasil contendo obras musicais ou literomusicais de autores brasileiros e/ou obras em geral interpretadas por artistas brasileiros bem como os suportes materiais ou arquivos digitais que os contenham, salvo na etapa de replicação industrial de mídias ópticas de leitura a laser. (CF88, Art 150, VI, "e")
    $std->infDPS->valores->trib->tribMun->tpRetISSQN = 1; // Tipo de retencao do ISSQN: 1 - Não Retido; 2 - Retido pelo Tomador; 3 - Retido pelo Intermediario;

    $std->infDPS->valores->trib->totTrib             = new stdClass();
    $std->infDPS->valores->trib->totTrib->indTotTrib = 0;

    $dps      = new QuantumTecnology\NfseNacional\Dps($std);
    $response = $tools->enviaDps($dps->render());

    // Retorno da SEFAZ: array com chaveAcesso, idDps, nfseXmlGZipB64 (XML
    // autorizado, gzip+base64) e alertas — ou o grupo de erro, quando rejeitada.

    if (isset($response['nfseXmlGZipB64'])) {
        $xml = gzdecode(base64_decode($response['nfseXmlGZipB64']));
        var_dump($response, $xml);
    } else {
        var_dump('Erro:', $response);
    }
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
