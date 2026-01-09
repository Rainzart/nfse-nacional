<?php

namespace Hadder\NfseNacional;

use NFePHP\DA\Common\DaCommon;
use NFePHP\DA\Legacy\Pdf;
use Exception;

/**
 * Classe para geração do DANFSE (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica)
 * em modo offline/simplificado
 * 
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
class DanfseSimples extends DaCommon
{
    /**
     * Tamanho do Papel
     * @var string
     */
    public $papel = 'A4';

    /**
     * XML da NFSe
     * @var string
     */
    protected $xml;

    /**
     * Mensagens de erro
     * @var string
     */
    protected $errMsg = '';

    /**
     * Status de erro
     * @var boolean
     */
    protected $errStatus = false;

    /**
     * Array com estrutura da NFSe
     * @var array
     */
    protected $nfseArray = [];

    /**
     * Dados principais da NFSe
     * @var array
     */
    protected $infNfse = [];

    /**
     * Dados do prestador
     * @var array
     */
    protected $prestador = [];

    /**
     * Dados do tomador
     * @var array
     */
    protected $tomador = [];

    /**
     * Dados do serviço
     * @var array
     */
    protected $servico = [];

    /**
     * Construtor
     * 
     * @param string $xml Conteúdo XML da NFSe
     * @param string $orientacao Orientação do PDF (P=Retrato, L=Paisagem)
     */
    public function __construct($xml, $orientacao = 'P')
    {
        $this->orientacao = $orientacao;
        $this->loadDoc($xml);
    }

    /**
     * Carrega o documento XML da NFSe
     * 
     * @param string $xml
     * @return void
     * @throws Exception
     */
    private function loadDoc($xml)
    {
        $this->xml = $xml;
        
        if (empty($xml)) {
            throw new Exception('XML da NFSe não pode estar vazio!');
        }

        try {
            // Remove possíveis declarações XML duplicadas e namespace
            $xml = preg_replace('/<\?xml.*?\?>/', '', $xml, -1, $count);
            if ($count > 1) {
                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . $xml;
            }

            // Carrega o XML
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXML($xml);

            // Converte para array para facilitar manipulação
            $stdClass = simplexml_load_string($xml);
            $json = json_encode($stdClass, JSON_OBJECT_AS_ARRAY);
            $this->nfseArray = json_decode($json, true);

            // Identifica a estrutura da NFSe (pode variar conforme o padrão)
            $this->parseNfseData();

        } catch (Exception $e) {
            throw new Exception('Erro ao carregar XML da NFSe: ' . $e->getMessage());
        }
    }

    /**
     * Parseia os dados da NFSe para estrutura interna
     * Suporta diferentes padrões de NFSe
     * 
     * @return void
     */
    private function parseNfseData()
    {
        // Padrão Nacional SEFIN (mais comum - estrutura NFSe/infNFSe)
        if (isset($this->nfseArray['infNFSe'])) {
            $this->parseNfseNacional();
        }
        // Padrão GINFES
        elseif (isset($this->nfseArray['Nfse'])) {
            $this->parseNfseGinfes();
        }
        // Padrão ABRASF
        elseif (isset($this->nfseArray['nfse'])) {
            $this->parseNfseAbrasf();
        }
        // Tenta estrutura genérica
        else {
            $this->parseNfseGenerico();
        }
    }

    /**
     * Parse para padrão Nacional SEFIN
     * Estrutura: NFSe/infNFSe
     */
    private function parseNfseNacional()
    {
        $infNfse = $this->nfseArray['infNFSe'] ?? [];
        $dps = $infNfse['DPS']['infDPS'] ?? [];
        
        // Informações principais da NFSe
        $this->infNfse = [
            'numero' => $infNfse['nNFSe'] ?? $infNfse['nDFSe'] ?? 'S/N',
            'codigo_verificacao' => $infNfse['cVerif'] ?? '',
            'chave_acesso' => $infNfse['@attributes']['Id'] ?? '',
            'data_emissao' => $dps['dhEmi'] ?? '',
            'data_processamento' => $infNfse['dhProc'] ?? '',
            'competencia' => $dps['dCompet'] ?? '',
            'numero_dps' => $dps['nDPS'] ?? '',
            'serie_dps' => $dps['serie'] ?? '',
            'data_emissao_dps' => $dps['dhEmi'] ?? '',
            'status' => $infNfse['cStat'] ?? '',
            'ambiente' => $infNfse['ambGer'] ?? $dps['tpAmb'] ?? 2,
            'local_emissao' => $infNfse['xLocEmi'] ?? '',
            'local_prestacao' => $infNfse['xLocPrestacao'] ?? '',
            'local_incidencia' => $infNfse['xLocIncid'] ?? '',
            'codigo_local_incidencia' => $infNfse['cLocIncid'] ?? '',
            'tributacao_nacional' => $infNfse['xTribNac'] ?? '',
        ];

        // Dados do Prestador (emit no padrão nacional)
        $emit = $infNfse['emit'] ?? [];
        $enderEmit = $emit['enderNac'] ?? [];
        $prestDps = $dps['prest'] ?? [];
        $regTrib = $prestDps['regTrib'] ?? [];
        
        $this->prestador = [
            'razao_social' => $emit['xNome'] ?? '',
            'nome_fantasia' => $emit['xFant'] ?? '',
            'cnpj' => $emit['CNPJ'] ?? $emit['cnpj'] ?? '',
            'inscricao_municipal' => $emit['IM'] ?? '',
            'fone' => $emit['fone'] ?? '',
            'email' => $emit['email'] ?? '',
            'optante_simples' => $regTrib['opSimpNac'] ?? '',
            'regime_tributacao' => $regTrib['regApTribSN'] ?? '',
            'regime_especial' => $regTrib['regEspTrib'] ?? '',
            'endereco' => [
                'logradouro' => $enderEmit['xLgr'] ?? '',
                'numero' => $enderEmit['nro'] ?? '',
                'complemento' => $enderEmit['xCpl'] ?? '',
                'bairro' => $enderEmit['xBairro'] ?? '',
                'municipio' => $enderEmit['xMun'] ?? '',
                'codigo_municipio' => $enderEmit['cMun'] ?? '',
                'uf' => $enderEmit['UF'] ?? '',
                'cep' => $enderEmit['CEP'] ?? '',
            ],
        ];

        // Dados do Tomador (toma no DPS)
        $toma = $dps['toma'] ?? [];
        $endToma = $toma['end'] ?? [];
        $endNacToma = $endToma['endNac'] ?? [];
        
        $this->tomador = [
            'razao_social' => $toma['xNome'] ?? '',
            'cnpj' => $toma['CNPJ'] ?? '',
            'cpf' => $toma['CPF'] ?? '',
            'inscricao_municipal' => $toma['IM'] ?? '',
            'fone' => $toma['fone'] ?? '',
            'email' => $toma['email'] ?? '',
            'endereco' => [
                'logradouro' => $endToma['xLgr'] ?? '',
                'numero' => $endToma['nro'] ?? '',
                'complemento' => $endToma['xCpl'] ?? '',
                'bairro' => $endToma['xBairro'] ?? '',
                'municipio' => $endNacToma['xMun'] ?? '',
                'codigo_municipio' => $endNacToma['cMun'] ?? '',
                'uf' => $endNacToma['UF'] ?? '',
                'cep' => $endNacToma['CEP'] ?? '',
            ],
        ];

        // Dados do Serviço
        $serv = $dps['serv'] ?? [];
        $cServ = $serv['cServ'] ?? [];
        $locPrest = $serv['locPrest'] ?? [];
        $valores = $dps['valores'] ?? [];
        $vServPrest = $valores['vServPrest'] ?? [];
        $trib = $valores['trib'] ?? [];
        $tribMun = $trib['tribMun'] ?? [];
        $tribFed = $trib['tribFed'] ?? [];
        
        // Valores do serviço
        $valorServico = (float)($vServPrest['vServ'] ?? 0);
        $valorDeducoes = (float)($vServPrest['vDed'] ?? 0);
        $valorDescontoIncond = (float)($vServPrest['vDesc'] ?? 0);
        $valorDescontoCond = (float)($vServPrest['vDescCond'] ?? 0);
        
        // ISS
        $vISS = (float)($tribMun['vISSQN'] ?? 0);
        $aliqISS = (float)($tribMun['pAliq'] ?? 0);
        $vBC = (float)($tribMun['vBC'] ?? $valorServico - $valorDeducoes);
        $issRetido = (int)($tribMun['tpRetISSQN'] ?? 2);
        $tribISSQN = (int)($tribMun['tribISSQN'] ?? 1);
        
        // Outras retenções federais
        $vPIS = (float)($tribFed['vPIS'] ?? 0);
        $vCOFINS = (float)($tribFed['vCOFINS'] ?? 0);
        $vINSS = (float)($tribFed['vINSS'] ?? 0);
        $vIR = (float)($tribFed['vIR'] ?? 0);
        $vCSLL = (float)($tribFed['vCSLL'] ?? 0);
        
        // Valor líquido (vem do nível superior infNFSe/valores)
        $valoresNfse = $infNfse['valores'] ?? [];
        $vLiq = (float)($valoresNfse['vLiq'] ?? $valorServico);
        
        // Informações complementares
        $infoCompl = $serv['infoCompl'] ?? [];
        
        $this->servico = [
            'descricao' => $cServ['xDescServ'] ?? '',
            'codigo_tributacao_nacional' => $cServ['cTribNac'] ?? '',
            'codigo_interno' => $cServ['cIntContrib'] ?? '',
            'local_prestacao' => $locPrest['cLocPrestacao'] ?? '',
            'pais_prestacao' => $locPrest['cPais'] ?? '',
            'info_complementar' => $infoCompl['xInfComp'] ?? '',
            'valores' => [
                'servicos' => $valorServico,
                'deducoes' => $valorDeducoes,
                'base_calculo' => $vBC,
                'aliquota' => $aliqISS,
                'iss' => $vISS,
                'iss_retido' => $issRetido,
                'tipo_tributacao' => $tribISSQN,
                'pis' => $vPIS,
                'cofins' => $vCOFINS,
                'inss' => $vINSS,
                'ir' => $vIR,
                'csll' => $vCSLL,
                'outras_retencoes' => 0,
                'desconto_incondicionado' => $valorDescontoIncond,
                'desconto_condicionado' => $valorDescontoCond,
                'valor_liquido' => $vLiq,
            ],
        ];
    }

    /**
     * Parse para padrão GINFES
     */
    private function parseNfseGinfes()
    {
        // Implementação similar adaptada para GINFES
        $this->parseNfseNacional();
    }

    /**
     * Parse para padrão ABRASF
     */
    private function parseNfseAbrasf()
    {
        // Implementação similar adaptada para ABRASF
        $this->parseNfseNacional();
    }

    /**
     * Parse genérico - tenta identificar campos automaticamente
     */
    private function parseNfseGenerico()
    {
        // Busca recursiva por campos conhecidos
        $this->infNfse = [
            'numero' => $this->findValue(['numero', 'Numero', 'nNfse']),
            'codigo_verificacao' => $this->findValue(['codigoVerificacao', 'CodigoVerificacao', 'cVerif']),
            'data_emissao' => $this->findValue(['dataEmissao', 'DataEmissao', 'dhEmi']),
        ];
    }

    /**
     * Busca um valor no array recursivamente
     * 
     * @param array $keys
     * @return mixed
     */
    private function findValue($keys)
    {
        foreach ($keys as $key) {
            $value = $this->searchArray($this->nfseArray, $key);
            if ($value !== null) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Busca recursiva em array
     * 
     * @param array $array
     * @param string $key
     * @return mixed
     */
    private function searchArray($array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach ($array as $value) {
            if (is_array($value)) {
                $result = $this->searchArray($value, $key);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Monta o PDF do DANFSE
     * 
     * @param string|null $logo
     * @return void
     */
    protected function monta($logo = null)
    {
        // Inicializa PDF
        if (empty($this->orientacao)) {
            $this->orientacao = 'P';
        }

        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        
        // Define dimensões da página
        if ($this->orientacao == 'L') {
            $this->maxW = 297;
            $this->maxH = 210;
        } else {
            $this->maxW = 210;
            $this->maxH = 297;
        }

        $this->wPrint = $this->maxW - ($this->margesq * 2);
        $this->hPrint = $this->maxH - $this->margsup - $this->marginf;

        // Configurações do PDF
        $this->pdf->aliasNbPages();
        $this->pdf->setMargins($this->margesq, $this->margsup);
        $this->pdf->setDrawColor(0, 0, 0);
        $this->pdf->setFillColor(255, 255, 255);
        $this->pdf->open();
        $this->pdf->addPage($this->orientacao, $this->papel);
        $this->pdf->setLineWidth(0.1);
        $this->pdf->settextcolor(0, 0, 0);
        $this->pdf->setAutoPageBreak(true, $this->marginf);

        // Renderiza o conteúdo
        $this->renderCabecalho($logo);
        $this->renderPrestador();
        $this->renderTomador();
        $this->renderServico();
        $this->renderValores();
        $this->renderRodape();
    }

    /**
     * Renderiza o cabeçalho do DANFSE
     * 
     * @param string|null $logo
     * @return void
     */
    private function renderCabecalho($logo = null)
    {
        $y = $this->margsup;
        $x = $this->margesq;

        // Borda externa do cabeçalho
        $this->pdf->rect($x, $y, $this->wPrint, 35);

        // Logo NFSe padrão à esquerda (texto)
        $this->pdf->setFont($this->fontePadrao, 'B', 20);
        $this->pdf->setXY($x + 2, $y + 3);
        $this->pdf->setTextColor(0, 128, 0); // Verde
        $this->pdf->cell(30, 8, 'NFSe', 0, 0, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setTextColor(0, 0, 0);
        $this->pdf->setXY($x + 2, $y + 10);
        $this->pdf->cell(30, 3, 'Nota Fiscal de', 0, 1, 'L');
        $this->pdf->setX($x + 2);
        $this->pdf->cell(30, 3, 'Servico eletronica', 0, 0, 'L');

        // Título centralizado
        $this->pdf->setFont($this->fontePadrao, 'B', 12);
        $this->pdf->setXY($x + 35, $y + 3);
        $this->pdf->cell($this->wPrint - 70, 5, 'DANFSe V1.0', 0, 1, 'C');
        
        $this->pdf->setFont($this->fontePadrao, '', 10);
        $this->pdf->setXY($x + 35, $y + 9);
        $this->pdf->cell($this->wPrint - 70, 5, 'Documento Auxiliar da NFS-e', 0, 1, 'C');
        
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setTextColor(255, 0, 0); // Vermelho
        $this->pdf->setXY($x + 35, $y + 15);
        $this->pdf->cell($this->wPrint - 70, 4, '*** DOCUMENTO PROVISORIO ***', 0, 1, 'C');
        $this->pdf->setTextColor(0, 0, 0);

        // QR Code placeholder (direita)
        $qrSize = 30;
        $qrX = $x + $this->wPrint - $qrSize - 2;
        $this->pdf->rect($qrX, $y + 2, $qrSize, $qrSize);
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setXY($qrX, $y + 13);
        $this->pdf->cell($qrSize, 3, 'A autenticidade desta', 0, 1, 'C');
        $this->pdf->setX($qrX);
        $this->pdf->cell($qrSize, 3, 'NFS-e pode ser verificada', 0, 1, 'C');
        $this->pdf->setX($qrX);
        $this->pdf->cell($qrSize, 3, 'pela leitura do QR Code', 0, 1, 'C');
        $this->pdf->setX($qrX);
        $this->pdf->cell($qrSize, 3, 'ou pela consulta da', 0, 1, 'C');
        $this->pdf->setX($qrX);
        $this->pdf->cell($qrSize, 3, 'chave de acesso no', 0, 1, 'C');
        $this->pdf->setX($qrX);
        $this->pdf->cell($qrSize, 3, 'portal nacional da NFS-e', 0, 0, 'C');

        // Informações principais abaixo
        $y = $y + 36;
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        
        // Chave de Acesso
        if (!empty($this->infNfse['chave_acesso'])) {
            $this->pdf->setXY($x, $y);
            $this->pdf->cell(40, 4, 'Chave de Acesso da NFS-e', 0, 0, 'L');
            $this->pdf->setFont($this->fontePadrao, '', 7);
            $this->pdf->cell(90, 4, $this->infNfse['chave_acesso'], 0, 1, 'L');
        }
        
        // Linha de informações
        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 4, 'Numero da NFS-e', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Competencia da NFS-e', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Data e Hora de Emissao da NFS-e', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $numeroNfse = $this->infNfse['numero'] ?? 'S/N';
        $this->pdf->cell($w3, 4, $numeroNfse, 0, 0, 'L');
        $competencia = $this->formatarData($this->infNfse['competencia'] ?? '', 'd/m/Y');
        $this->pdf->cell($w3, 4, $competencia, 0, 0, 'L');
        $dataEmissao = $this->formatarData($this->infNfse['data_emissao'] ?? '');
        $this->pdf->cell($w3, 4, $dataEmissao, 0, 1, 'L');
        
        // Número do DPS
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 4, 'Numero de DPS', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Serie do DPS', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Data e Hora de Emissao do DPS', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w3, 4, $this->infNfse['numero_dps'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w3, 4, $this->infNfse['serie_dps'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w3, 4, $this->formatarData($this->infNfse['data_emissao_dps'] ?? ''), 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 1;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza dados do prestador
     * 
     * @return void
     */
    private function renderPrestador()
    {
        $y = $this->pdf->getY() + 2;
        $x = $this->margesq;

        // Título
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'Emitente da NFS-e', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        $w2 = $this->wPrint / 2;

        // Linha 1: CNPJ/CPF, Inscrição Municipal, Telefone
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 3, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Inscricao Municipal', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Telefone', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $cnpj = $this->formatarCnpjCpf($this->prestador['cnpj'] ?? '');
        $this->pdf->cell($w3, 3, $cnpj, 0, 0, 'L');
        $im = $this->prestador['inscricao_municipal'] ?? '';
        $this->pdf->cell($w3, 3, $im, 0, 0, 'L');
        $fone = $this->prestador['fone'] ?? '';
        $this->pdf->cell($w3, 3, $fone, 0, 1, 'L');

        // Linha 2: Nome/Razão Social
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Nome / Nome Empresarial', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $razaoSocial = $this->prestador['razao_social'] ?? $this->prestador['nome_fantasia'] ?? '';
        $this->pdf->cell($this->wPrint, 3, $razaoSocial, 0, 1, 'L');

        // Linha 3: Endereço
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Endereco', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $endereco = $this->montarEnderecoSimples($this->prestador['endereco'] ?? []);
        $this->pdf->cell($this->wPrint, 3, $endereco, 0, 1, 'L');

        // Linha 4: Regime SN, Email, CEP, Município
        $y = $this->pdf->getY();
        $w4 = $this->wPrint / 4;
        
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w2, 3, 'Regime de Apuracao Tributaria pelo SN', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'E-mail', 0, 0, 'L');
        $this->pdf->cell($w4/2, 3, 'CEP', 0, 0, 'L');
        $this->pdf->cell($w4/2, 3, 'Municipio', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $regimeSN = $this->getRegimeSimples($this->prestador['optante_simples'] ?? '');
        $this->pdf->cell($w2, 3, $regimeSN, 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->prestador['email'] ?? '', 0, 0, 'L');
        $cep = $this->formatarCep($this->prestador['endereco']['cep'] ?? '');
        $this->pdf->cell($w4/2, 3, $cep, 0, 0, 'L');
        $municipio = $this->prestador['endereco']['municipio'] ?? '';
        $this->pdf->cell($w4/2, 3, $municipio, 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza dados do tomador
     * 
     * @return void
     */
    private function renderTomador()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Título
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'TOMADOR DE SERVICO', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        $w4 = $this->wPrint / 4;

        // Linha 1: CNPJ/CPF, Inscrição Municipal, Telefone
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 3, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Inscricao Municipal', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Telefone', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $doc = !empty($this->tomador['cnpj']) ? $this->tomador['cnpj'] : ($this->tomador['cpf'] ?? '');
        $docFormatado = $this->formatarCnpjCpf($doc);
        $this->pdf->cell($w3, 3, $docFormatado, 0, 0, 'L');
        $im = $this->tomador['inscricao_municipal'] ?? '';
        $this->pdf->cell($w3, 3, $im, 0, 0, 'L');
        $fone = $this->tomador['fone'] ?? '';
        $this->pdf->cell($w3, 3, $fone, 0, 1, 'L');

        // Linha 2: Nome/Razão Social
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Nome / Nome Empresarial', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $razaoSocial = $this->tomador['razao_social'] ?? '';
        $this->pdf->cell($this->wPrint, 3, $razaoSocial, 0, 1, 'L');

        // Linha 3: Endereço
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Endereco', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $endereco = $this->montarEnderecoSimples($this->tomador['endereco'] ?? []);
        $this->pdf->cell($this->wPrint, 3, $endereco, 0, 1, 'L');

        // Linha 4: Email, CEP, Município
        $y = $this->pdf->getY();
        
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3 + $w4, 3, 'E-mail', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'CEP', 0, 0, 'L');
        $this->pdf->cell($w3 - $w4, 3, 'Municipio', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w3 + $w4, 3, $this->tomador['email'] ?? '', 0, 0, 'L');
        $cep = $this->formatarCep($this->tomador['endereco']['cep'] ?? '');
        $this->pdf->cell($w4, 3, $cep, 0, 0, 'L');
        $municipio = $this->tomador['endereco']['municipio'] ?? '';
        $this->pdf->cell($w3 - $w4, 3, $municipio, 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza dados do serviço
     * 
     * @return void
     */
    private function renderServico()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Título SERVIÇO PRESTADO
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'SERVICO PRESTADO', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w4 = $this->wPrint / 4;

        // Linha 1: Códigos e Local
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'Codigo de Tributacao Nacional', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Codigo de Tributacao Municipal', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Local da Prestacao', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Pais da Prestacao', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 3, $this->servico['codigo_tributacao_nacional'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w4, 3, '', 0, 0, 'L'); // Código Municipal não disponível no XML
        $localPrest = $this->servico['local_prestacao'] ?? '';
        if ($localPrest == '0000000') $localPrest = 'Aguas Maritimas';
        $this->pdf->cell($w4, 3, $localPrest, 0, 0, 'L');
        $this->pdf->cell($w4, 3, '', 0, 1, 'L');

        // Linha 2: Descrição do Serviço
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Descricao do Servico', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $descricao = $this->servico['descricao'] ?? '';
        $this->pdf->multiCell($this->wPrint, 3, $descricao, 0, 'L');

        // Linha 3: Tributação (compacta em uma linha)
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'TRIBUTACAO DO ISSQN', 0, 1, 'L');
        
        $y = $this->pdf->getY();
        $valores = $this->servico['valores'] ?? [];
        
        // Primeira linha de tributação
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Tributacao Tributavel', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Pais Resultado da Prestacao de Servico', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Municipio de Incidencia do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Regime Especial de Tributacao', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $tipoTrib = $valores['tipo_tributacao'] ?? 1;
        $tribTexto = $tipoTrib == 1 ? 'Operacao Tributavel' : 'Nao Tributavel';
        $this->pdf->cell($w4, 2.5, $tribTexto, 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Brasil', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->infNfse['local_incidencia'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Nenhum', 0, 1, 'L');

        // Segunda linha de tributação
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Tipo de Imunidade', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Suspensao da Exigibilidade do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Numero Processo Suspenso', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Beneficio Municipal', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 2.5, 'Nao', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Nao', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, '', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, '', 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza valores
     * 
     * @return void
     */
    private function renderValores()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;
        $valores = $this->servico['valores'] ?? [];
        $w4 = $this->wPrint / 4;
        $w2 = $this->wPrint / 2;

        // Seção: Valores do Serviço e ISS
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'Valor do Servico', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Total Deducoes/Reducoes', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Calculo do BM', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['servicos'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['desconto_incondicionado'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['deducoes'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, '', 0, 1, 'L');

        // BC ISSQN e Alíquota
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'BC ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Aliquota Aplicada', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Retencao do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'ISSQN Apurado', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['base_calculo'] ?? 0), 0, 0, 'L');
        $aliquota = isset($valores['aliquota']) ? number_format($valores['aliquota'], 2, ',', '.') : '0,00';
        $this->pdf->cell($w4, 3, $aliquota . ' %', 0, 0, 'L');
        $issRetido = ($valores['iss_retido'] ?? 2) == 1 ? 'Nao Retido' : 'Nao Retido';
        $this->pdf->cell($w4, 3, $issRetido, 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['iss'] ?? 0), 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);

        // TRIBUTAÇÃO FEDERAL
        $y = $y + 1;
        $outrasRetencoes = ($valores['pis'] ?? 0) + ($valores['cofins'] ?? 0) + 
                          ($valores['inss'] ?? 0) + ($valores['ir'] ?? 0) + 
                          ($valores['csll'] ?? 0);

        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 4, 'TRIBUTACAO FEDERAL', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w5 = $this->wPrint / 5;
        
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w5, 3, 'IRRF', 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'CP', 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'CSLL', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['ir'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w5, 3, '', 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['csll'] ?? 0), 0, 1, 'L');

        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w5, 3, 'PIS', 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'COFINS', 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'Retencao do PIS/COFINS', 0, 0, 'L');
        $this->pdf->cell($w5 * 2, 3, 'TOTAL TRIBUTACAO FEDERAL', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['pis'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['cofins'] ?? 0), 0, 0, 'L');
        $retPisCofins = (($valores['pis'] ?? 0) > 0 || ($valores['cofins'] ?? 0) > 0) ? 'Nao Retido' : 'Nao Retido';
        $this->pdf->cell($w5, 3, $retPisCofins, 0, 0, 'L');
        $this->pdf->cell($w5 * 2, 3, 'R$ ' . $this->formatarValor($outrasRetencoes), 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);

        // VALOR TOTAL DA NFS-E
        $y = $y + 1;
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 4, 'VALOR TOTAL DA NFS-E', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'Valor do Servico', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Desconto Condicionado', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'ISSQN Retido', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['servicos'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['desconto_condicionado'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['desconto_incondicionado'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 3, '', 0, 1, 'L');

        // Segunda linha
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w2, 3, 'IRRF, CP, CSLL - Retidos', 0, 0, 'L');
        $this->pdf->cell($w2, 3, 'PIS/COFINS Retidos', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $totalIRCSLL = ($valores['ir'] ?? 0) + ($valores['csll'] ?? 0) + ($valores['inss'] ?? 0);
        $this->pdf->cell($w2, 3, 'R$ ' . $this->formatarValor($totalIRCSLL), 0, 0, 'L');
        $totalPisCofins = ($valores['pis'] ?? 0) + ($valores['cofins'] ?? 0);
        $this->pdf->cell($w2, 3, 'R$ ' . $this->formatarValor($totalPisCofins), 0, 1, 'L');

        // Valor Líquido em destaque
        $valorLiquido = $valores['valor_liquido'] ?? 
                       (($valores['servicos'] ?? 0) - ($valores['deducoes'] ?? 0) - 
                        ($valores['desconto_incondicionado'] ?? 0) - ($valores['desconto_condicionado'] ?? 0));
        
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->setFont($this->fontePadrao, 'B', 9);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w2, 5, 'Valor Liquido da NFS-e', 1, 0, 'L', true);
        $this->pdf->cell($w2, 5, 'R$ ' . $this->formatarValor($valorLiquido), 1, 1, 'R', true);

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza rodapé
     * 
     * @return void
     */
    private function renderRodape()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Informações Complementares
        if (!empty($this->servico['info_complementar'])) {
            $this->pdf->setFont($this->fontePadrao, 'B', 8);
            $this->pdf->setXY($x, $y);
            $this->pdf->cell($this->wPrint, 4, 'INFORMACOES COMPLEMENTARES', 1, 1, 'L', true);
            
            $this->pdf->setFont($this->fontePadrao, '', 7);
            $y = $this->pdf->getY();
            $this->pdf->setXY($x, $y);
            $this->pdf->multiCell($this->wPrint, 3, $this->servico['info_complementar'], 1, 'L');
            
            $y = $this->pdf->getY() + 1;
        }

        // Texto informativo
        $this->pdf->setFont($this->fontePadrao, 'I', 7);
        $this->pdf->setXY($x, $y);
        
        $textoRodape = 'Este documento e uma representacao grafica da NFSe e foi impresso apenas para facilitar a consulta. ' .
                      'A NFSe pode ser consultada atraves do codigo de verificacao no site da prefeitura ou portal nacional.';
        
        $this->pdf->multiCell($this->wPrint, 3, $textoRodape, 0, 'C');

        // Adiciona créditos se configurado
        if ($this->powered) {
            $y = $this->pdf->getY() + 1;
            $this->pdf->setFont($this->fontePadrao, '', 6);
            $this->pdf->setXY($x, $y);
            $credito = !empty($this->creditos) ? $this->creditos . ' - ' : '';
            $this->pdf->cell($this->wPrint, 3, $credito . 'Powered by NFePHP', 0, 1, 'C');
        }
    }
    
    /**
     * Monta endereço de forma simplificada (uma linha)
     * 
     * @param array $endereco
     * @return string
     */
    private function montarEnderecoSimples($endereco)
    {
        if (empty($endereco)) {
            return '';
        }

        $partes = [];

        $logradouro = $endereco['xLgr'] ?? $endereco['logradouro'] ?? '';
        $numero = $endereco['nro'] ?? $endereco['numero'] ?? '';
        $bairro = $endereco['xBairro'] ?? $endereco['bairro'] ?? '';
        
        if (!empty($logradouro)) {
            $parte = $logradouro;
            if (!empty($numero)) {
                $parte .= ', ' . $numero;
            }
            $partes[] = $parte;
        }
        
        if (!empty($bairro)) {
            $partes[] = $bairro;
        }

        return implode(' - ', $partes);
    }
    
    /**
     * Formata CEP
     * 
     * @param string $cep
     * @return string
     */
    private function formatarCep($cep)
    {
        if (empty($cep)) {
            return '';
        }
        
        $cep = preg_replace('/[^0-9]/', '', $cep);
        if (strlen($cep) == 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }
        
        return $cep;
    }
    
    /**
     * Retorna texto do regime do Simples Nacional
     * 
     * @param string $codigo
     * @return string
     */
    private function getRegimeSimples($codigo)
    {
        $regimes = [
            '1' => 'Microempresa Municipal',
            '2' => 'Estimativa',
            '3' => 'Sociedade de Profissionais',
            '4' => 'Cooperativa',
            '5' => 'Microempresario Individual (MEI)',
            '6' => 'Microempresa ou Pequeno Porte (ME EPP)',
        ];
        
        return $regimes[$codigo] ?? 'Nao Optante';
    }

    /**
     * Formata valor monetário
     * 
     * @param float $valor
     * @return string
     */
    private function formatarValor($valor)
    {
        $decimals = $this->decimalPlaces ?? 2;
        return number_format((float)$valor, $decimals, ',', '.');
    }

    /**
     * Formata CNPJ/CPF
     * 
     * @param string $doc
     * @return string
     */
    private function formatarCnpjCpf($doc)
    {
        $doc = preg_replace('/[^0-9]/', '', $doc);
        
        if (strlen($doc) == 14) {
            // CNPJ: 00.000.000/0000-00
            return substr($doc, 0, 2) . '.' . 
                   substr($doc, 2, 3) . '.' . 
                   substr($doc, 5, 3) . '/' . 
                   substr($doc, 8, 4) . '-' . 
                   substr($doc, 12, 2);
        } elseif (strlen($doc) == 11) {
            // CPF: 000.000.000-00
            return substr($doc, 0, 3) . '.' . 
                   substr($doc, 3, 3) . '.' . 
                   substr($doc, 6, 3) . '-' . 
                   substr($doc, 9, 2);
        }
        
        return $doc;
    }

    /**
     * Formata data
     * 
     * @param string $data
     * @param string $formato Formato de saída (padrão: d/m/Y H:i:s)
     * @return string
     */
    private function formatarData($data, $formato = 'd/m/Y H:i:s')
    {
        if (empty($data)) {
            return '';
        }

        try {
            $dt = new \DateTime($data);
            return $dt->format($formato);
        } catch (Exception $e) {
            return $data;
        }
    }

    /**
     * Monta string de endereço
     * 
     * @param array $endereco
     * @return string
     */
    private function montarEndereco($endereco)
    {
        if (empty($endereco)) {
            return '';
        }

        $partes = [];

        // Suporta múltiplas variações de nomes de campos
        $logradouro = $endereco['xLgr'] ?? $endereco['Endereco'] ?? $endereco['endereco'] ?? 
                     $endereco['Logradouro'] ?? $endereco['logradouro'] ?? '';
        $numero = $endereco['nro'] ?? $endereco['Numero'] ?? $endereco['numero'] ?? '';
        $complemento = $endereco['xCpl'] ?? $endereco['Complemento'] ?? $endereco['complemento'] ?? '';
        $bairro = $endereco['xBairro'] ?? $endereco['Bairro'] ?? $endereco['bairro'] ?? '';
        $cidade = $endereco['xMun'] ?? $endereco['municipio'] ?? $endereco['Cidade'] ?? $endereco['cidade'] ?? '';
        $uf = $endereco['UF'] ?? $endereco['uf'] ?? $endereco['Uf'] ?? '';
        $cep = $endereco['CEP'] ?? $endereco['cep'] ?? $endereco['Cep'] ?? '';

        if (!empty($logradouro)) {
            $partes[] = $logradouro;
        }
        if (!empty($numero)) {
            $partes[] = 'no ' . $numero;
        }
        if (!empty($complemento)) {
            $partes[] = $complemento;
        }
        if (!empty($bairro)) {
            $partes[] = $bairro;
        }
        if (!empty($cidade)) {
            $partes[] = $cidade;
        }
        if (!empty($uf)) {
            $partes[] = $uf;
        }
        if (!empty($cep)) {
            $cepFormatado = preg_replace('/[^0-9]/', '', $cep);
            if (strlen($cepFormatado) == 8) {
                $cepFormatado = substr($cepFormatado, 0, 5) . '-' . substr($cepFormatado, 5, 3);
            }
            $partes[] = 'CEP: ' . $cepFormatado;
        }

        return implode(', ', $partes);
    }

    /**
     * Retorna erros
     * 
     * @return string
     */
    public function getErrors()
    {
        return $this->errMsg;
    }

    /**
     * Verifica se há erros
     * 
     * @return boolean
     */
    public function hasErrors()
    {
        return $this->errStatus;
    }
}
