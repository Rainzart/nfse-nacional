<?php

namespace QuantumTecnology\NfseNacional\Danfse;

use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\Common\DaCommon;
use Exception;

/**
 * Base do DANFSE (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica).
 *
 * Concentra TODO o comportamento comum — parsing do XML e desenho de todas as
 * seções. As subclasses só decidem o que aparece no canto do cabeçalho:
 *
 *   - {@see \QuantumTecnology\NfseNacional\Danfse}        logo em imagem + QR Code
 *   - {@see \QuantumTecnology\NfseNacional\DanfseSimples} logo em texto, sem QR
 *
 * Why: as duas classes eram cópias de ~1.300 linhas que divergiram em silêncio.
 * A cópia "Simples" carimbava '*** DOCUMENTO PROVISORIO ***' de forma
 * incondicional (inclusive em nota autorizada, cStat 100) e não desenhava as
 * tarjas reais de homologação/cancelamento; a outra fazia o oposto. Manter uma
 * base única é o que impede esse tipo de divergência de voltar.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
abstract class AbstractDanfse extends DaCommon
{
    /**
     * Fonte com acentuação distribuída pelo pacote (ver storage/fonts/).
     */
    const FONTE_ACENTUADA = 'dejavusanscondensed';

    /**
     * Layout histórico desta biblioteca, herdado do sped-da.
     *
     * NÃO corresponde ao modelo oficial de nenhuma versão do DANFSe: é o
     * desenho que a lib sempre produziu, mantido para quem já o utiliza.
     */
    const LAYOUT_V1 = '1.0';

    /**
     * Modelo oficial do Anexo I da Nota Técnica nº 008 v1.02 (14/07/2026).
     */
    const LAYOUT_V2 = '2.0';

    /**
     * Versão do layout a desenhar.
     *
     * O padrão é o v2.0 porque é o modelo em vigor: a NT nº 008 suspendeu a API
     * de geração do DANFSe do Ambiente Nacional em 03/08/2026, de modo que o
     * render local deixou de ser plano B e passou a ser o documento que o
     * contribuinte entrega.
     *
     * @var string
     */
    protected $layoutVersao = self::LAYOUT_V2;

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
     * Grupo IBS/CBS da Reforma Tributária (EC 132/2023)
     * @var array
     */
    protected $ibsCbs = [];

    /**
     * Dados do intermediário do serviço (opcional no leiaute)
     * @var array
     */
    protected $intermediario = [];

    /**
     * Dados do destinatário da operação (bloco próprio no DANFSe v2.0).
     *
     * Vive em `infDPS/IBSCBS/dest` — é o adquirente do IBS/CBS, que nem sempre
     * é o tomador do serviço. O layout v1 não tem esse bloco.
     *
     * @var array
     */
    protected $destinatario = [];

    /**
     * Dados do órgão emissor exibidos no cabeçalho (opcional).
     *
     * O DANFSe oficial mostra "Prefeitura Municipal de X / Secretaria de
     * Fazenda / telefone / e-mail" ao lado do brasão. Esses dados NÃO estão no
     * XML — o portal os obtém de cadastro próprio por município. Quem tiver a
     * informação pode fornecê-la por {@see setOrgaoEmissor()}; sem ela, o
     * espaço fica livre em vez de exibir dado inventado.
     *
     * @var array
     */
    protected $orgaoEmissor = [];

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

            // O FPDF legado trabalha com ISO-8859-1 em fontes core.
            // Convertemos os campos textuais vindos do XML para evitar
            // caracteres acentuados quebrados na visualização do PDF.
            $this->normalizeDataEncodingForPdf();

        } catch (Exception $e) {
            throw new Exception('Erro ao carregar XML da NFSe: ' . $e->getMessage());
        }
    }

    /**
     * Normaliza os dados textuais para o encoding esperado pelo motor de PDF.
     *
     * @return void
     */
    private function normalizeDataEncodingForPdf()
    {
        $this->infNfse = $this->convertDataToPdfEncoding($this->infNfse);
        $this->prestador = $this->convertDataToPdfEncoding($this->prestador);
        $this->tomador = $this->convertDataToPdfEncoding($this->tomador);
        $this->servico = $this->convertDataToPdfEncoding($this->servico);
        $this->ibsCbs = $this->convertDataToPdfEncoding($this->ibsCbs);
        $this->intermediario = $this->convertDataToPdfEncoding($this->intermediario);
        $this->destinatario = $this->convertDataToPdfEncoding($this->destinatario);
    }

    /**
     * Converte recursivamente strings para ISO-8859-1.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function convertDataToPdfEncoding($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->convertDataToPdfEncoding($value);
            }
            return $data;
        }

        if (!is_string($data) || $data === '') {
            return $data;
        }

        $text = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_convert_encoding($text, 'ISO-8859-1', ['UTF-8', 'windows-1252', 'ISO-8859-1']);
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
            'tributacao_municipal' => $infNfse['xTribMun'] ?? '',
            'descricao_nbs' => $infNfse['xNBS'] ?? '',
            'outras_informacoes' => $infNfse['xOutInf'] ?? '',

            // --- Campos exigidos pelo DANFSe v2.0 ---

            // ATENÇÃO: `ambiente` acima mistura dois conceitos distintos do
            // leiaute (ambGer = QUEM gerou; tpAmb = produção ou homologação).
            // O v1 usa a chave antiga; o v2 exibe os dois em campos separados,
            // como o modelo oficial, então cada um ganha chave própria.
            'ambiente_gerador' => $infNfse['ambGer'] ?? '',
            'tipo_ambiente' => $dps['tpAmb'] ?? $infNfse['ambGer'] ?? 2,
            'tipo_emitente' => $dps['tpEmit'] ?? '1',
            'finalidade' => $dps['IBSCBS']['finNFSe'] ?? '0',

            // Substituição (nota 7): a chave da nota substituída vai para as
            // informações complementares; a da substituta marca esta como
            // substituída e dispara a marca d'água.
            'chave_substituida' => $dps['subst']['chSubstda'] ?? '',
            'chave_substituta' => $infNfse['NFSeSubstituidora'] ?? '',
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
                // `enderNac` NÃO traz xMun — só o código IBGE. O nome do
                // município do emitente vem de xLocEmi, no topo da NFS-e; a UF
                // sai da tabela IBGE quando o endereço não a informa.
                'municipio' => $this->formatarMunicipio(
                    $enderEmit['cMun'] ?? '',
                    $enderEmit['xMun'] ?? $infNfse['xLocEmi'] ?? '',
                    $enderEmit['UF'] ?? ''
                ),
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
                // O tomador não tem nome de município em lugar algum do XML —
                // só o código IBGE. Sem a tabela, este campo saía vazio.
                'municipio' => $this->formatarMunicipio(
                    $endNacToma['cMun'] ?? '',
                    $endNacToma['xMun'] ?? '',
                    $endNacToma['UF'] ?? ''
                ),
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
        
        // Valores APURADOS pela SEFAZ. Ficam em infNFSe/valores — e é daqui que
        // o DANFSe tem de ler. O DPS declara a intenção (tribISSQN, tpRetISSQN);
        // quem calcula base, alíquota e imposto é o Fisco. Ler do DPS fazia
        // "Alíquota Aplicada" e "ISSQN Apurado" saírem sempre zerados, porque
        // `tribMun` do DPS não tem vBC, pAliq nem vISSQN.
        $valoresNfse = $infNfse['valores'] ?? [];

        $vISS    = (float)($valoresNfse['vISSQN'] ?? $tribMun['vISSQN'] ?? 0);
        $aliqISS = (float)($valoresNfse['pAliqAplic'] ?? $tribMun['pAliq'] ?? 0);
        $vBC     = (float)($valoresNfse['vBC'] ?? $tribMun['vBC'] ?? $valorServico - $valorDeducoes);

        $issRetido = (int)($tribMun['tpRetISSQN'] ?? 2);
        $tribISSQN = (int)($tribMun['tribISSQN'] ?? 1);

        // Retenções federais — os nomes do schema v1.01 são vPis/vCofins (dentro
        // de `piscofins`) e vRetCP/vRetIRRF/vRetCSLL. Os nomes antigos (vPIS,
        // vCOFINS, vINSS, vIR, vCSLL) não existem em lugar nenhum do XML, então
        // toda a tributação federal saía zerada.
        $pisCofins = $tribFed['piscofins'] ?? [];

        $vPIS    = (float)($pisCofins['vPis'] ?? $tribFed['vPIS'] ?? 0);
        $vCOFINS = (float)($pisCofins['vCofins'] ?? $tribFed['vCOFINS'] ?? 0);
        $vINSS   = (float)($tribFed['vRetCP'] ?? $tribFed['vINSS'] ?? 0);
        $vIR     = (float)($tribFed['vRetIRRF'] ?? $tribFed['vIR'] ?? 0);
        $vCSLL   = (float)($tribFed['vRetCSLL'] ?? $tribFed['vCSLL'] ?? 0);

        $retPisCofins = (int)($pisCofins['tpRetPisCofins'] ?? 2);

        // Total apurado dos tributos (seção "Totais Aproximados" do oficial).
        $totTrib     = $trib['totTrib'] ?? [];
        $vTotTrib    = $totTrib['vTotTrib'] ?? [];
        $totTribFed  = (float)($vTotTrib['vTotTribFed'] ?? 0);
        $totTribEst  = (float)($vTotTrib['vTotTribEst'] ?? 0);
        $totTribMun  = (float)($vTotTrib['vTotTribMun'] ?? 0);

        $vLiq = (float)($valoresNfse['vLiq'] ?? $valorServico);
        
        // Informações complementares
        $infoCompl = $serv['infoCompl'] ?? [];
        
        $this->servico = [
            'descricao' => $cServ['xDescServ'] ?? '',
            'codigo_tributacao_nacional' => $cServ['cTribNac'] ?? '',
            // O XML autorizado traz a DESCRIÇÃO dos códigos (xTribNac/xTribMun);
            // o oficial imprime "08.02.01 - 08.02 - Instrução, treinamento...".
            'descricao_tributacao_nacional' => $infNfse['xTribNac'] ?? '',
            'codigo_tributacao_municipal' => $cServ['cTribMun'] ?? '',
            'descricao_tributacao_municipal' => $infNfse['xTribMun'] ?? '',
            'codigo_nbs' => $cServ['cNBS'] ?? '',
            'descricao_nbs' => $infNfse['xNBS'] ?? '',
            'codigo_interno' => $cServ['cIntContrib'] ?? '',
            'local_prestacao' => $this->formatarMunicipio(
                $locPrest['cLocPrestacao'] ?? '',
                $infNfse['xLocPrestacao'] ?? ''
            ),
            'pais_prestacao' => $locPrest['cPaisPrestacao'] ?? $locPrest['cPais'] ?? '',
            'pais_resultado' => $tribMun['cPaisResult'] ?? '',
            'tipo_imunidade' => $tribMun['tpImunidade'] ?? '',
            'regime_especial' => $regTrib['regEspTrib'] ?? '',
            'info_complementar' => $infoCompl['xInfComp'] ?? '',

            // --- Campos exigidos pelo DANFSe v2.0 (itens 2.1.8 e 2.1.12) ---
            'suspensao_exigibilidade' => $tribMun['exigSusp']['tpSusp'] ?? '',
            'numero_processo_suspensao' => $tribMun['exigSusp']['nProcesso'] ?? '',
            'beneficio_municipal' => $tribMun['BM']['tpBM'] ?? '',
            'codigo_obra' => $serv['obra']['cObra'] ?? '',
            'inscricao_imobiliaria' => $serv['obra']['inscImobFisc']
                ?? $dps['IBSCBS']['imovel']['inscImobFisc'] ?? '',
            'codigo_evento' => $serv['atvEvento']['idAtvEvt'] ?? '',
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
                'ret_pis_cofins' => $retPisCofins,
                'calculo_bm' => (float) ($tribMun['BM']['vCalcBM'] ?? $tribMun['BM']['vRedBCBM'] ?? 0),
                'outras_retencoes' => 0,
                'desconto_incondicionado' => $valorDescontoIncond,
                'desconto_condicionado' => $valorDescontoCond,
                'valor_liquido' => $vLiq,
                'total_tributos_federais' => $totTribFed,
                'total_tributos_estaduais' => $totTribEst,
                'total_tributos_municipais' => $totTribMun,

                // --- Campos exigidos pelo DANFSe v2.0 ---

                // "Total das Retenções (ISSQN / Federais)" (item 2.1.11). O XML
                // traz vTotalRet pronto; sem ele, soma o que foi efetivamente
                // retido — e PIS/COFINS só entram quando tpRetPisCofins = 1.
                'total_retencoes' => (float) ($valoresNfse['vTotalRet'] ?? (
                    (1 === $issRetido ? $vISS : 0)
                    + $vIR + $vINSS + $vCSLL
                    + (1 === $retPisCofins ? $vPIS + $vCOFINS : 0)
                )),

                // Regra da NT 008 v1.02 (notas dos campos, pág. 19): com
                // PIS/COFINS RETIDO (tpRetPisCofins = 1), os campos de débito de
                // apuração própria saem ZERADOS e os valores migram para
                // "Contribuições Sociais - Retidas". Imprimir vPis nos dois
                // lugares contaria o mesmo tributo duas vezes.
                'pis_debito_proprio' => 1 === $retPisCofins ? 0.0 : $vPIS,
                'cofins_debito_proprio' => 1 === $retPisCofins ? 0.0 : $vCOFINS,
                'contribuicoes_sociais_retidas' => 1 === $retPisCofins
                    ? $vCSLL + $vPIS + $vCOFINS
                    : $vCSLL,
            ],
        ];

        // Intermediário do serviço — opcional no leiaute. A geração do grupo
        // ainda não existe no Dps (TODO lá), mas o XML autorizado pode trazê-lo
        // quando a nota veio de outro emissor, então o DANFSe precisa saber ler.
        $interm     = $dps['interm'] ?? [];
        $endInterm  = $interm['end'] ?? [];
        $endNacInt  = $endInterm['endNac'] ?? [];

        $this->intermediario = [] === $interm ? [] : [
            'razao_social' => $interm['xNome'] ?? '',
            'cnpj' => $interm['CNPJ'] ?? '',
            'cpf' => $interm['CPF'] ?? '',
            'inscricao_municipal' => $interm['IM'] ?? '',
            'fone' => $interm['fone'] ?? '',
            'email' => $interm['email'] ?? '',
            'municipio' => $this->formatarMunicipio(
                $endNacInt['cMun'] ?? '',
                $endNacInt['xMun'] ?? '',
                $endNacInt['UF'] ?? ''
            ),
        ];

        // Destinatário da operação — bloco próprio do DANFSe v2.0. Mora dentro
        // do grupo IBSCBS do DPS (é o adquirente para fins de IBS/CBS) e NÃO é
        // necessariamente o tomador do serviço: por isso ganha bloco separado no
        // modelo oficial. Repare que não há IM aqui, ao contrário dos demais.
        $dest       = $dps['IBSCBS']['dest'] ?? [];
        $endDest    = $dest['end'] ?? [];
        $endNacDest = $endDest['endNac'] ?? $endDest['endExt'] ?? $endDest;

        $this->destinatario = [] === $dest ? [] : [
            'razao_social' => $dest['xNome'] ?? '',
            'cnpj' => $dest['CNPJ'] ?? '',
            'cpf' => $dest['CPF'] ?? '',
            'nif' => $dest['NIF'] ?? '',
            'fone' => $dest['fone'] ?? '',
            'email' => $dest['email'] ?? '',
            'municipio' => $this->formatarMunicipio(
                $endNacDest['cMun'] ?? '',
                $endNacDest['xMun'] ?? $endNacDest['xCidade'] ?? '',
                $endNacDest['UF'] ?? ''
            ),
            'codigo_municipio' => $endNacDest['cMun'] ?? '',
            'cep' => $endNacDest['CEP'] ?? $endNacDest['cEndPost'] ?? '',
            'endereco' => [
                'logradouro' => $endNacDest['xLgr'] ?? '',
                'numero' => $endNacDest['nro'] ?? '',
                'complemento' => $endNacDest['xCpl'] ?? '',
                'bairro' => $endNacDest['xBairro'] ?? '',
            ],
        ];

        $this->parseIbsCbs($infNfse, $dps);
    }

    /**
     * Grupo IBS/CBS — Reforma Tributária (EC 132/2023).
     *
     * ATENÇÃO: existem DOIS grupos `<IBSCBS>` no XML autorizado, e eles têm
     * conteúdos diferentes:
     *
     *  - `infNFSe/IBSCBS`        → o que o Fisco CALCULOU (alíquotas e valores)
     *  - `infNFSe/DPS/infDPS/IBSCBS` → o que o contribuinte DECLAROU (CST,
     *                                  cClassTrib, finalidade)
     *
     * Ler o do DPS achando que são os valores devolve tudo zerado — foi
     * exatamente esse tipo de troca que zerava o ISSQN antes.
     *
     * Obrigatório a partir de 01/10/2026 (Dps::IBSCBS_OBRIGATORIO_EM) e, para
     * o Simples Nacional, 01/01/2027.
     *
     * @param array $infNfse
     * @param array $dps
     * @return void
     */
    private function parseIbsCbs(array $infNfse, array $dps)
    {
        // Lado NFS-e: valores apurados.
        $grupo    = $infNfse['IBSCBS'] ?? [];
        $valores  = $grupo['valores'] ?? [];
        $totCIBS  = $grupo['totCIBS'] ?? [];
        $gIBS     = $totCIBS['gIBS'] ?? [];
        $gCBS     = $totCIBS['gCBS'] ?? [];

        // Lado DPS: o que foi declarado (CST/cClassTrib/indicador de operação).
        $dpsIbsCbs = $dps['IBSCBS'] ?? [];
        $gIBSCBS   = $dpsIbsCbs['valores']['trib']['gIBSCBS'] ?? [];

        $this->ibsCbs = [
            'presente' => [] !== $grupo,
            'localidade_incidencia' => $this->formatarMunicipio(
                $grupo['cLocalidadeIncid'] ?? '',
                $grupo['xLocalidadeIncid'] ?? ''
            ),
            'cst' => $gIBSCBS['CST'] ?? '',
            'classificacao_tributaria' => $gIBSCBS['cClassTrib'] ?? '',
            'base_calculo' => (float)($valores['vBC'] ?? 0),
            'aliquota_ibs_uf' => (float)($valores['uf']['pIBSUF'] ?? 0),
            'aliquota_ibs_mun' => (float)($valores['mun']['pIBSMun'] ?? 0),
            'aliquota_cbs' => (float)($valores['fed']['pCBS'] ?? 0),
            'valor_ibs_uf' => (float)($gIBS['gIBSUFTot']['vIBSUF'] ?? 0),
            'valor_ibs_mun' => (float)($gIBS['gIBSMunTot']['vIBSMun'] ?? 0),
            'valor_ibs' => (float)($gIBS['vIBSTot'] ?? 0),
            'valor_cbs' => (float)($gCBS['vCBS'] ?? 0),
            // vTotNF é o total da nota já com IBS/CBS. Em 2026 (ano de teste)
            // equivale a vLiq; a partir de 2027 passa a ser vLiq + vCBS + vIBS.
            'valor_total' => (float)($totCIBS['vTotNF'] ?? 0),

            // --- Campos exigidos pelo DANFSe v2.0 (NT 008, item 2.1.10) ---

            // Indicador da operação, concatenado no documento com a localidade
            // de incidência: "cIndOp / cLocalidade / Município / UF".
            'indicador_operacao' => $dpsIbsCbs['cIndOp'] ?? '',
            'codigo_localidade_incidencia' => $grupo['cLocalidadeIncid'] ?? '',

            // Alíquotas EFETIVAS: o que de fato incidiu depois das reduções.
            // Não são as nominais acima — numa operação com redução as duas
            // divergem, e o documento oficial exibe ambas.
            'aliquota_efetiva_ibs_uf' => (float)($valores['uf']['pAliqEfetUF'] ?? 0),
            'aliquota_efetiva_ibs_mun' => (float)($valores['mun']['pAliqEfetMun'] ?? 0),
            'aliquota_efetiva_cbs' => (float)($valores['fed']['pAliqEfetCBS'] ?? 0),

            // Reduções de alíquota declaradas (impressas como "% / % / %").
            'reducao_aliquota_ibs_uf' => (float)($valores['uf']['pRedAliqUF'] ?? 0),
            'reducao_aliquota_ibs_mun' => (float)($valores['mun']['pRedAliqMun'] ?? 0),
            'reducao_aliquota_cbs' => (float)($valores['fed']['pRedAliqCBS'] ?? 0),

            // "Exclusões e Reduções da Base de Cálculo" é somatório, não um campo
            // do XML: vDescIncond + vCalcReeRepRes + vISSQN + vPIS + vCOFINS
            // (NT 008, item 2.4.5).
            'exclusoes_reducoes_bc' => $this->somaExclusoesReducoesBc($valores),
            'valor_reembolso' => (float)($valores['vCalcReeRepRes'] ?? 0),
        ];
    }

    /**
     * Somatório das "Exclusões e Reduções da Base de Cálculo" do IBS/CBS.
     *
     * A NT 008 (2.4.5) define o campo como a soma de vDescIncond,
     * vCalcReeRepRes, vISSQN, vPIS e vCOFINS — e não como uma tag do XML. Como
     * `$this->servico` já está resolvido quando parseIbsCbs roda, os tributos
     * saem de lá em vez de serem relidos da árvore.
     *
     * @param array $valoresIbsCbs Grupo `infNFSe/IBSCBS/valores`
     * @return float
     */
    private function somaExclusoesReducoesBc(array $valoresIbsCbs)
    {
        $valores = $this->servico['valores'] ?? [];

        return (float) ($valores['desconto_incondicionado'] ?? 0)
            + (float) ($valoresIbsCbs['vCalcReeRepRes'] ?? 0)
            + (float) ($valores['iss'] ?? 0)
            + (float) ($valores['pis'] ?? 0)
            + (float) ($valores['cofins'] ?? 0);
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

        $this->pdf = new PdfComFontes($this->orientacao, 'mm', $this->papel);
        $this->registraFonteAcentuada();

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

        // Quem desenha é a estratégia de layout — ver setLayout().
        $this->resolveLayout()->desenha($this, $logo);
    }

    /**
     * Nota já interpretada, para consumo dos layouts.
     *
     * Os layouts leem estes dados, nunca o XML: o parsing é responsabilidade
     * desta classe e precisa valer igual para todas as versões do documento.
     *
     * @return array{
     *     infNfse: array, prestador: array, tomador: array, destinatario: array,
     *     intermediario: array, servico: array, ibsCbs: array, orgaoEmissor: array
     * }
     */
    public function dadosDaNota()
    {
        return [
            'infNfse'       => $this->infNfse,
            'prestador'     => $this->prestador,
            'tomador'       => $this->tomador,
            'destinatario'  => $this->destinatario,
            'intermediario' => $this->intermediario,
            'servico'       => $this->servico,
            'ibsCbs'        => $this->ibsCbs,
            'orgaoEmissor'  => $this->orgaoEmissor,
        ];
    }

    /**
     * Desenha a marca da NFS-e numa área definida pelo layout.
     *
     * O v1 posiciona a marca por conta própria; o v2 tem coordenada e caixa
     * fixadas pela NT (0,85 × 4,00 @ 0,49/0,44), então precisa mandar o espaço.
     * A forma de desenhar continua sendo decisão da subclasse (imagem ou texto).
     *
     * @param float       $x       Em mm
     * @param float       $y       Em mm
     * @param float       $largura Em mm
     * @param float       $altura  Em mm
     * @param string|null $logo
     * @return void
     */
    public function desenhaMarcaNoLayout($x, $y, $largura, $altura, $logo = null)
    {
        $this->renderMarcaNfse($x, $y, $logo, $largura, $altura);
    }

    /**
     * A marca pode ficar sobre o fundo cinza do cabeçalho do v2?
     *
     * A marca em texto não pinta fundo, então combina com qualquer cor. A
     * subclasse que desenha imagem responde false quando o arquivo traria um
     * fundo próprio (ver Danfse).
     *
     * @param string|null $logo Logo informado em render($logo)
     * @return bool
     */
    public function marcaAceitaFundoCinza($logo = null)
    {
        return true;
    }

    /**
     * Desenha o QR Code de consulta pública na posição exigida pelo layout.
     *
     * @param float $x    Em mm
     * @param float $y    Em mm
     * @param float $lado Em mm
     * @return void
     */
    public function desenhaQrCodeNoLayout($x, $y, $lado)
    {
        $this->desenhaQrCode($x, $y, $lado);
    }

    /**
     * Formata CNPJ/CPF para os layouts.
     *
     * @param string $documento
     * @return string
     */
    public function formatarDocumentoNoLayout($documento)
    {
        return $this->formatarCnpjCpf($documento);
    }

    /**
     * Resolve um código IBGE em "Município - UF" para os layouts.
     *
     * @param string $codigoIbge
     * @return string
     */
    public function formatarMunicipioNoLayout($codigoIbge)
    {
        return $this->formatarMunicipio($codigoIbge);
    }

    /**
     * Formata data para os layouts.
     *
     * @param string $valor
     * @param string $formato
     * @return string
     */
    public function formatarDataNoLayout($valor, $formato = 'd/m/Y H:i:s')
    {
        return $this->formatarData($valor, $formato);
    }

    /**
     * Corta texto na largura disponível, para uso dos layouts.
     *
     * Exposto porque o recorte depende do encoding da fonte já registrada — e
     * essa lógica (que evita partir caractere acentuado ao meio) não deve ser
     * reimplementada por versão de layout.
     *
     * @param string $texto
     * @param float  $larguraMm
     * @return string
     */
    public function truncaNoLayout($texto, $larguraMm)
    {
        return $this->truncaNaLargura($texto, $larguraMm);
    }

    /**
     * Objeto de PDF em uso (já inicializado por {@see monta()}).
     *
     * @return Pdf
     */
    public function pdf()
    {
        return $this->pdf;
    }

    /**
     * Fonte ativa — a acentuada quando o registro deu certo.
     *
     * @return string
     */
    public function fonte()
    {
        return $this->fontePadrao;
    }

    /**
     * Sequência de seções do layout v1, na ordem histórica.
     *
     * Mora aqui, e não em {@see Layout\LayoutV1}, porque as seções do v1 são
     * métodos desta classe que dependem do estado do desenho (cada uma continua
     * de onde a anterior parou, via `getY()`). Movê-las para fora exigiria
     * reescrevê-las — e o compromisso do v1 é sair exatamente como saía.
     *
     * @param string|null $logo
     * @return void
     */
    public function desenhaSecoesV1($logo = null)
    {
        $this->renderCabecalho($logo);
        $this->renderPrestador();
        $this->renderTomador();
        $this->renderIntermediario();
        $this->renderServico();
        $this->renderValores();
        $this->renderIbsCbs();
        $this->renderTotaisAproximados();
        $this->renderRodape();
    }

    /**
     * Escolhe a versão do layout do documento.
     *
     * ```php
     * (new Danfse($xml))->setLayout(Danfse::LAYOUT_V1)->render();
     * ```
     *
     * Versão desconhecida cai no padrão em vez de lançar: este método está no
     * caminho de geração de um documento fiscal, e ficar sem PDF é pior que
     * receber o layout vigente.
     *
     * @param string $versao {@see LAYOUT_V1} ou {@see LAYOUT_V2}
     * @return $this
     */
    public function setLayout($versao)
    {
        $versao = trim((string) $versao);

        $this->layoutVersao = in_array($versao, [self::LAYOUT_V1, self::LAYOUT_V2], true)
            ? $versao
            : self::LAYOUT_V2;

        return $this;
    }

    /**
     * Versão de layout em uso.
     *
     * @return string
     */
    public function getLayout()
    {
        return $this->layoutVersao;
    }

    /**
     * Instancia a estratégia de desenho da versão escolhida.
     *
     * @return Layout\LayoutDanfseInterface
     */
    protected function resolveLayout()
    {
        return self::LAYOUT_V1 === $this->layoutVersao
            ? new Layout\LayoutV1()
            : new Layout\LayoutV2();
    }

    /**
     * Renderiza o cabeçalho do DANFSE
     * 
     * @param string|null $logo
     * @return void
     */
    protected function renderCabecalho($logo = null)
    {
        $y = $this->margsup;
        $x = $this->margesq;

        // Borda externa do cabeçalho
        $this->pdf->rect($x, $y, $this->wPrint, 35);

        // Marca da NFS-e no canto esquerdo — imagem ou texto, conforme a subclasse.
        $this->renderMarcaNfse($x, $y, $logo);

        // Título centralizado
        $this->pdf->setFont($this->fontePadrao, 'B', 12);
        $this->pdf->setXY($x + 35, $y + 3);
        $this->pdf->cell($this->wPrint - 70, 5, 'DANFSe v1.0', 0, 1, 'C');

        $this->pdf->setFont($this->fontePadrao, '', 10);
        $this->pdf->setXY($x + 35, $y + 9);
        $this->pdf->cell($this->wPrint - 70, 5, 'Documento Auxiliar da NFS-e', 0, 1, 'C');

        // Órgão emissor (brasão + Prefeitura/Secretaria). Fica ABAIXO da faixa
        // do título: as duas linhas centrais ocupam a largura toda e, colocado
        // ao lado delas, o bloco se sobrepunha ao "Documento Auxiliar da NFS-e".
        // Só aparece quando informado — o XML não traz esses dados.
        $this->renderOrgaoEmissor($x + 36, $y + 16, $x + $this->wPrint - 46);

        // QR Code (quando a subclasse souber gerar) + o texto de autenticidade,
        // que o documento oficial traz sempre ao lado do código.
        $this->renderQrCode($x, $y);

        // Informações principais abaixo
        $y = $y + 36;
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        
        // Chave de Acesso
        if (!empty($this->infNfse['chave_acesso'])) {
            $this->pdf->setXY($x, $y);
            $this->pdf->cell(40, 4, 'Chave de Acesso da NFS-e', 0, 0, 'L');
            $this->pdf->setFont($this->fontePadrao, '', 7);
            $this->pdf->cell(90, 4, $this->infNfse['chave_acesso'], 0, 1, 'L');
            //$this->pdf->cell(90, 4, Str::substr($this->infNfse['chave_acesso'], 3, 100), 0, 1, 'L');
        }
        
        // Linha de informações
        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 4, 'Número da NFS-e', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Competência da NFS-e', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Data e Hora da emissão da NFS-e', 0, 1, 'L');
        
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
        $this->pdf->cell($w3, 4, 'Número da DPS', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Série da DPS', 0, 0, 'L');
        $this->pdf->cell($w3, 4, 'Data e Hora da emissão da DPS', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w3, 4, $this->infNfse['numero_dps'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w3, 4, $this->infNfse['serie_dps'] ?? '', 0, 0, 'L');
        $this->pdf->cell($w3, 4, $this->formatarData($this->infNfse['data_emissao_dps'] ?? ''), 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 1;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);

        //####################################################################################
        //Indicação de NF Homologação, cancelamento e falta de protocolo
        //dd($this->infNfse);
        $tpAmb = $this->infNfse['ambiente'];
        //indicar cancelamento
        $resp = $this->statusNFSe();
        if (!$resp['status']) {
            $n = count($resp['message']);
            $alttot = $n * 15;
            $x = 80;
            $y = 83; // $this->hPrint / 2 - $alttot / 2;
            //dd($y);
            $h = 15;
            $w = 60; //$maxW - (2 * $x);
            $this->pdf->settextcolor(170, 170, 170);

            foreach ($resp['message'] as $msg) {
                $aFont = ['font' => $this->fontePadrao, 'size' => 58, 'style' => 'B'];
                $this->pdf->textBox($x, $y, $w, $h, $msg, $aFont, 'C', 'C', 0, '');
                $y += $h;
            }
            $texto = $resp['submessage'];
            if (!empty($texto)) {
                $y += 3;
                $h = 5;
                $aFont = ['font' => $this->fontePadrao, 'size' => 20, 'style' => 'B'];
                $this->pdf->textBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $y += $h;
            }
            $y += 0;
            $w = 60; //$maxW - (2 * $x);
            $texto = "SEM VALOR FISCAL";
            $aFont = ['font' => $this->fontePadrao, 'size' => 58, 'style' => 'B'];
            $this->pdf->textBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->settextcolor(0, 0, 0);
        }
        if (!empty($this->epec) && $this->tpEmis == 4) {
            //EPEC
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = 60; //$maxW - (2 * $x);
            $this->pdf->SetTextColor(200, 200, 200);
            $texto = "DANFE impresso em contingência -\n" .
                "EPEC regularmente recebido pela Receita\n" .
                "Federal do Brasil";
            $aFont = ['font' => $this->fontePadrao, 'size' => 48, 'style' => 'B'];
            $this->pdf->textBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    /**
     * Renderiza dados do prestador
     * 
     * @return void
     */
    protected function renderPrestador()
    {
        $y = $this->pdf->getY() + 2;
        $x = $this->margesq;

        // Título
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'EMITENTE DA NFS-e', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        $w2 = $this->wPrint / 2;

        // Linha 1: CNPJ/CPF, Inscrição Municipal, Telefone
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 3, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Inscrição Municipal', 0, 0, 'L');
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
        $this->pdf->cell($this->wPrint, 3, 'Endereço', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $endereco = $this->montarEnderecoSimples($this->prestador['endereco'] ?? []);
        $this->pdf->cell($this->wPrint, 3, $endereco, 0, 1, 'L');

        // Linha 4: E-mail, Município, CEP — como no documento oficial.
        $y = $this->pdf->getY();
        $w4 = $this->wPrint / 4;

        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w2, 3, 'E-mail', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Município', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'CEP', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w2, 3, $this->ouTraco(mb_strtolower($this->prestador['email'] ?? '')), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->prestador['endereco']['municipio'] ?? ''), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->formatarCep($this->prestador['endereco']['cep'] ?? '')), 0, 1, 'L');

        // Linha 5: os DOIS campos do Simples Nacional. São domínios distintos —
        // opSimpNac (situação) e regApTribSN (regime de apuração) — e o oficial
        // imprime os dois lado a lado.
        $y = $this->pdf->getY();

        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w2, 3, 'Simples Nacional na Data de Competência', 0, 0, 'L');
        $this->pdf->cell($w2, 3, 'Regime de Apuração Tributária pelo SN', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $optante = $this->getOptanteSimplesNacional($this->prestador['optante_simples'] ?? '');
        $this->pdf->cell($w2, 3, $this->ouTraco($optante), 0, 0, 'L');
        $regimeSN = $this->getRegimeApuracaoSN($this->prestador['regime_tributacao'] ?? '');
        $this->pdf->cell($w2, 3, $this->ouTraco($regimeSN), 0, 1, 'L');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza dados do tomador
     * 
     * @return void
     */
    protected function renderTomador()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Título
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'TOMADOR DO SERVIÇO', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w3 = $this->wPrint / 3;
        $w4 = $this->wPrint / 4;

        // Linha 1: CNPJ/CPF, Inscrição Municipal, Telefone
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 3, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->cell($w3, 3, 'Inscrição Municipal', 0, 0, 'L');
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
        $this->pdf->cell($this->wPrint, 3, 'Endereço', 0, 1, 'L');
        
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
        $this->pdf->cell($w3 - $w4, 3, 'Município', 0, 1, 'L');
        
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
    protected function renderServico()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Título SERVIÇO PRESTADO
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'SERVIÇO PRESTADO', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $w4 = $this->wPrint / 4;

        // Linha 1: Códigos e Local
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'Código de Tributação Nacional', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Código de Tributação Municipal', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Local da Prestação', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'País da Prestação', 0, 1, 'L');
        
        // O oficial imprime código E descrição ("08.02.01 - 08.02 - Instrução,
        // treinamento..."); a descrição vem em xTribNac/xTribMun do XML
        // autorizado e era simplesmente ignorada.
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        // Truncado na largura da coluna: as descrições da lista de serviços são
        // longas e, sem isso, transbordam por cima da coluna vizinha. O oficial
        // corta do mesmo jeito ("08.02 - Instrução, treinamento, orientação
        // pedagógica e e...").
        $this->pdf->cell($w4, 3, $this->truncaNaLargura($this->ouTraco($this->codigoComDescricao(
            $this->servico['codigo_tributacao_nacional'] ?? '',
            $this->servico['descricao_tributacao_nacional'] ?? ''
        )), $w4 - 2), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->truncaNaLargura($this->ouTraco($this->codigoComDescricao(
            $this->servico['codigo_tributacao_municipal'] ?? '',
            $this->servico['descricao_tributacao_municipal'] ?? ''
        )), $w4 - 2), 0, 0, 'L');
        $localPrest = $this->servico['local_prestacao'] ?? '';
        if ('0000000' === $localPrest) {
            $localPrest = 'Águas Marítimas';
        }
        $this->pdf->cell($w4, 3, $this->ouTraco($localPrest), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->nomePais($this->servico['pais_prestacao'] ?? '')), 0, 1, 'L');

        // Linha 2: Descrição do Serviço
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Descrição do Serviço', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $descricao = $this->servico['descricao'] ?? '';
        $this->pdf->multiCell($this->wPrint, 3, $descricao, 0, 'L');

        // Linha 3: Tributação (compacta em uma linha)
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'TRIBUTAÇÃO MUNICIPAL', 0, 1, 'L');
        
        $y = $this->pdf->getY();
        $valores = $this->servico['valores'] ?? [];
        
        // Primeira linha de tributação
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Tributação do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'País Resultado da Prestação do Serviço', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Município de Incidência do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Regime Especial de Tributação', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $tipoTrib = $valores['tipo_tributacao'] ?? 1;
        $tribTexto = 1 == $tipoTrib ? 'Operação Tributável' : 'Não Tributável';
        $this->pdf->cell($w4, 2.5, $tribTexto, 0, 0, 'L');
        // Antes fixos em 'Brasil' e 'Nenhum', ignorando cPaisResult e regEspTrib
        // que já vinham parseados.
        $this->pdf->cell($w4, 2.5, $this->ouTraco($this->nomePais($this->servico['pais_resultado'] ?? '')), 0, 0, 'L');
        $municipioIncid = $this->formatarMunicipio(
            $this->infNfse['codigo_local_incidencia'] ?? '',
            $this->infNfse['local_incidencia'] ?? ''
        );
        $this->pdf->cell($w4, 2.5, $this->ouTraco($municipioIncid), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->getRegimeEspecialTributacao(
            $this->servico['regime_especial'] ?? '0'
        ), 0, 1, 'L');

        // Segunda linha de tributação
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Tipo de Imunidade', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Suspensão da Exigibilidade do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Número Processo Suspensão', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Benefício Municipal', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 2.5, 'Não', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Não', 0, 0, 'L');
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
    protected function renderValores()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;
        $valores = $this->servico['valores'] ?? [];
        $w4 = $this->wPrint / 4;
        $w2 = $this->wPrint / 2;

        // Seção: Valores do Serviço e ISS
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Total Deduções/Reduções', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Cálculo do BM', 0, 1, 'L');
        
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
        $this->pdf->cell($w4, 3, 'Alíquota Aplicada', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Retenção do ISSQN', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'ISSQN Apurado', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 3, 'R$ ' . $this->formatarValor($valores['base_calculo'] ?? 0), 0, 0, 'L');
        $aliquota = isset($valores['aliquota']) ? number_format($valores['aliquota'], 2, ',', '.') : '0,00';
        $this->pdf->cell($w4, 3, $aliquota . ' %', 0, 0, 'L');
        // tpRetISSQN: 1 = Retido pelo tomador/intermediário, 2 = Não retido.
        // Os dois ramos deste ternário eram idênticos ('Nao Retido'), então a
        // retenção NUNCA aparecia no documento, ainda que declarada no XML.
        $issRetido = 1 === (int) ($valores['iss_retido'] ?? 2) ? 'Retido' : 'Não Retido';
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
        $this->pdf->cell($this->wPrint, 4, 'TRIBUTAÇÃO FEDERAL', 1, 1, 'L', true);

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
        $this->pdf->cell($w5, 3, 'Retenção do PIS/COFINS', 0, 0, 'L');
        $this->pdf->cell($w5 * 2, 3, 'TOTAL TRIBUTAÇÃO FEDERAL', 0, 1, 'L');
        
        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['pis'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w5, 3, 'R$ ' . $this->formatarValor($valores['cofins'] ?? 0), 0, 0, 'L');
        // Idem ISSQN: os dois ramos eram 'Nao Retido'. A retenção é declarada em
        // tpRetPisCofins (1 = retido), não inferida da existência de valor.
        $retPisCofins = 1 === (int) ($valores['ret_pis_cofins'] ?? 2) ? 'Retido' : 'Não Retido';
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
        $this->pdf->cell($w4, 3, 'Valor do Serviço', 0, 0, 'L');
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
        $this->pdf->cell($w2, 5, 'Valor Líquido da NFS-e', 1, 0, 'L', true);
        $this->pdf->cell($w2, 5, 'R$ ' . $this->formatarValor($valorLiquido), 1, 1, 'R', true);

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Renderiza o quadro IBS/CBS — Reforma Tributária (EC 132/2023).
     *
     * O bloco é SEMPRE exibido, com zeros quando a nota não traz o grupo: a
     * ausência do tributo é informação fiscal tanto quanto sua presença, e a
     * partir de 01/08/2026 o grupo passa a ser obrigatório (01/01/2027 para o
     * Simples Nacional).
     *
     * @return void
     */
    protected function renderIbsCbs()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        $ibs = $this->ibsCbs;
        $w4  = $this->wPrint / 4;

        // Título da seção
        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'TRIBUTAÇÃO IBS / CBS (REFORMA TRIBUTÁRIA)', 1, 1, 'L', true);

        // Situação tributária e localidade de incidência
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'CST', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Classificação Tributária', 0, 0, 'L');
        $this->pdf->cell($w4 * 2, 2.5, 'Município de Incidência do IBS/CBS', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 2.5, $this->ouTraco($ibs['cst'] ?? ''), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->ouTraco($ibs['classificacao_tributaria'] ?? ''), 0, 0, 'L');
        $this->pdf->cell($w4 * 2, 2.5, $this->ouTraco($ibs['localidade_incidencia'] ?? ''), 0, 1, 'L');

        // Base de cálculo e alíquotas
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Base de Cálculo IBS/CBS', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Alíquota IBS Estadual', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Alíquota IBS Municipal', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Alíquota CBS', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 2.5, 'R$ ' . $this->formatarValor($ibs['base_calculo'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->formatarPercentual($ibs['aliquota_ibs_uf'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->formatarPercentual($ibs['aliquota_ibs_mun'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, $this->formatarPercentual($ibs['aliquota_cbs'] ?? 0), 0, 1, 'L');

        // Valores apurados
        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 2.5, 'Valor IBS Estadual', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Valor IBS Municipal', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Valor Total do IBS', 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'Valor da CBS', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w4, 2.5, 'R$ ' . $this->formatarValor($ibs['valor_ibs_uf'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'R$ ' . $this->formatarValor($ibs['valor_ibs_mun'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'R$ ' . $this->formatarValor($ibs['valor_ibs'] ?? 0), 0, 0, 'L');
        $this->pdf->cell($w4, 2.5, 'R$ ' . $this->formatarValor($ibs['valor_cbs'] ?? 0), 0, 1, 'L');

        // Total da nota com IBS/CBS — só faz sentido quando o Fisco o devolveu.
        if (($ibs['valor_total'] ?? 0) > 0) {
            $y  = $this->pdf->getY() + 0.5;
            $w2 = $this->wPrint / 2;

            $this->pdf->setFont($this->fontePadrao, 'B', 8);
            $this->pdf->setXY($x, $y);
            $this->pdf->cell($w2, 5, 'Valor Total da NFS-e com IBS/CBS', 1, 0, 'L', true);
            $this->pdf->cell($w2, 5, 'R$ ' . $this->formatarValor($ibs['valor_total']), 1, 1, 'R', true);
        }

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Define os dados do órgão emissor mostrados no cabeçalho.
     *
     * Uso:
     * ```php
     * (new Danfse($xml))
     *     ->setOrgaoEmissor([
     *         'nome'       => 'Prefeitura Municipal de Americana',
     *         'secretaria' => 'Secretaria de Fazenda',
     *         'fone'       => '(19)3475-9049',
     *         'email'      => 'iss@americana.sp.gov.br',
     *         'brasao'     => '/caminho/brasao.png', // opcional
     *     ])
     *     ->render();
     * ```
     *
     * @param array $dados
     * @return $this
     */
    public function setOrgaoEmissor(array $dados)
    {
        $this->orgaoEmissor = $dados;

        return $this;
    }

    /**
     * Desenha, no canto direito do cabeçalho, o brasão e os dados do órgão
     * emissor — quando informados por {@see setOrgaoEmissor()}.
     *
     * @param float $x
     * @param float $y
     * @param float $limiteDireito Onde começa a área do QR Code
     * @return void
     */
    protected function renderOrgaoEmissor($x, $y, $limiteDireito)
    {
        if ([] === $this->orgaoEmissor) {
            return;
        }

        $dados  = $this->convertDataToPdfEncoding($this->orgaoEmissor);
        $brasao = $this->orgaoEmissor['brasao'] ?? '';
        $textoX = $x;

        if (is_string($brasao) && '' !== $brasao && is_file($brasao)) {
            $dimensoes = @getimagesize($brasao);

            if (false !== $dimensoes && (int) $dimensoes[1] > 0) {
                $altura  = 10;
                $largura = min(round($dimensoes[0] * ($altura / $dimensoes[1]), 0), 12);

                try {
                    $this->pdf->Image($brasao, $x, $y + 2, $largura, $altura);
                    $textoX = $x + $largura + 1;
                } catch (\Throwable $e) {
                    // Segue sem o brasão.
                }
            }
        }

        $largura = max(10, $limiteDireito - $textoX - 1);

        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($textoX, $y + 2);
        $this->pdf->cell($largura, 2.5, $dados['nome'] ?? '', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 5);

        foreach (['secretaria', 'fone', 'email'] as $campo) {
            if ('' === (string) ($dados[$campo] ?? '')) {
                continue;
            }

            $this->pdf->setX($textoX);
            $this->pdf->cell($largura, 2.2, $dados[$campo], 0, 1, 'L');
        }
    }

    /**
     * Intermediário do serviço.
     *
     * O documento oficial dedica uma faixa a este grupo mesmo quando ele não
     * existe, com o dizer "INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e"
     * — a ausência do intermediário é informação fiscal, não um vazio a omitir.
     *
     * @return void
     */
    protected function renderIntermediario()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        if ([] === $this->intermediario) {
            $this->pdf->setFont($this->fontePadrao, '', 7);
            $this->pdf->setXY($x, $y);
            $this->pdf->cell(
                $this->wPrint,
                4,
                'INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e',
                0,
                1,
                'C'
            );

            $y = $this->pdf->getY() + 0.5;
            $this->pdf->line($x, $y, $x + $this->wPrint, $y);

            return;
        }

        $w4 = $this->wPrint / 4;

        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 5, 'INTERMEDIÁRIO DO SERVIÇO', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w4, 3, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Telefone', 0, 0, 'L');
        $this->pdf->cell($w4, 3, 'Município', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $documento = '' !== ($this->intermediario['cnpj'] ?? '')
            ? $this->intermediario['cnpj']
            : ($this->intermediario['cpf'] ?? '');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->formatarCnpjCpf($documento)), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->intermediario['inscricao_municipal'] ?? ''), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->intermediario['fone'] ?? ''), 0, 0, 'L');
        $this->pdf->cell($w4, 3, $this->ouTraco($this->intermediario['municipio'] ?? ''), 0, 1, 'L');

        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 7);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 3, 'Nome / Nome Empresarial', 0, 1, 'L');

        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setX($x);
        $this->pdf->cell($this->wPrint, 3, $this->ouTraco($this->intermediario['razao_social'] ?? ''), 0, 1, 'L');

        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Totais aproximados dos tributos (Lei 12.741/2012 — "Lei da Transparência").
     *
     * Seção própria no documento oficial, com as três esferas lado a lado. Os
     * valores vêm de `totTrib/vTotTrib` do DPS e já eram parseados; só não
     * havia onde imprimi-los.
     *
     * @return void
     */
    protected function renderTotaisAproximados()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        $valores = $this->servico['valores'] ?? [];
        $w3      = $this->wPrint / 3;

        $this->pdf->setFont($this->fontePadrao, 'B', 8);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($this->wPrint, 4, 'TOTAIS APROXIMADOS DOS TRIBUTOS', 1, 1, 'L', true);

        $y = $this->pdf->getY();
        $this->pdf->setFont($this->fontePadrao, 'B', 6);
        $this->pdf->setXY($x, $y);
        $this->pdf->cell($w3, 2.5, 'Federais', 0, 0, 'C');
        $this->pdf->cell($w3, 2.5, 'Estaduais', 0, 0, 'C');
        $this->pdf->cell($w3, 2.5, 'Municipais', 0, 1, 'C');

        $this->pdf->setFont($this->fontePadrao, '', 6);
        $this->pdf->setX($x);
        $this->pdf->cell($w3, 2.5, $this->valorOuTraco($valores['total_tributos_federais'] ?? 0), 0, 0, 'C');
        $this->pdf->cell($w3, 2.5, $this->valorOuTraco($valores['total_tributos_estaduais'] ?? 0), 0, 0, 'C');
        $this->pdf->cell($w3, 2.5, $this->valorOuTraco($valores['total_tributos_municipais'] ?? 0), 0, 1, 'C');

        // Linha separadora
        $y = $this->pdf->getY() + 0.5;
        $this->pdf->line($x, $y, $x + $this->wPrint, $y);
    }

    /**
     * Valor zerado vira "-", como no oficial (que não polui o documento com
     * "R$ 0,00" em campo sem tributo).
     *
     * @param float $valor
     * @return string
     */
    protected function valorOuTraco($valor)
    {
        return (float) $valor > 0 ? 'R$ ' . $this->formatarValor($valor) : '-';
    }

    /**
     * Percentual no formato do documento: "0,90 %".
     *
     * @param float $valor
     * @return string
     */
    protected function formatarPercentual($valor)
    {
        return number_format((float) $valor, 2, ',', '.') . ' %';
    }

    /**
     * Renderiza rodapé
     *
     * @return void
     */
    protected function renderRodape()
    {
        $y = $this->pdf->getY() + 1;
        $x = $this->margesq;

        // Informações Complementares
        if (!empty($this->servico['info_complementar'])) {
            $this->pdf->setFont($this->fontePadrao, 'B', 8);
            $this->pdf->setXY($x, $y);
            $this->pdf->cell($this->wPrint, 4, 'INFORMAÇÕES COMPLEMENTARES', 1, 1, 'L', true);
            
            $this->pdf->setFont($this->fontePadrao, '', 7);
            $y = $this->pdf->getY();
            $this->pdf->setXY($x, $y);
            $this->pdf->multiCell($this->wPrint, 3, $this->servico['info_complementar'], 1, 'L');
            
            $y = $this->pdf->getY() + 1;
        }

        // Texto informativo
        $this->pdf->setFont($this->fontePadrao, 'I', 7);
        $this->pdf->setXY($x, $y);
        
        $textoRodape = 'Este documento é uma representação gráfica da NFS-e e foi impresso apenas para facilitar a consulta. ' .
                      'A NFS-e pode ser consultada através do código de verificação no site da prefeitura ou portal nacional.';
        
        $this->pdf->multiCell($this->wPrint, 3, $textoRodape, 0, 'C');

        // Adiciona créditos se configurado.
        //
        // A atribuição ao NFePHP permanece porque o motor de PDF vem mesmo do
        // sped-da (LGPL/MIT) — retirá-la seria incorreto. O que muda é que o
        // crédito de quem gerou o documento passa a ser desta biblioteca.
        if ($this->powered) {
            $y = $this->pdf->getY() + 1;
            $this->pdf->setFont($this->fontePadrao, '', 6);
            $this->pdf->setXY($x, $y);
            $credito = !empty($this->creditos) ? $this->creditos . ' - ' : '';
            $this->pdf->cell(
                $this->wPrint,
                3,
                $credito . 'Powered by Quantum Tecnologia - NFS-e Nacional (sobre NFePHP)',
                0,
                1,
                'C'
            );
        }
    }
    
    /**
     * Monta endereço de forma simplificada (uma linha)
     * 
     * @param array $endereco
     * @return string
     */
    protected function montarEnderecoSimples($endereco)
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
    protected function formatarCep($cep)
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
    /**
     * Registra a fonte acentuada do pacote como fonte padrão do documento.
     *
     * As fontes core do FPDF (times, helvetica) só cobrem o conjunto básico, e
     * por isso os rótulos saíam sem acento ("TRIBUTACAO MUNICIPAL"), diferente
     * do documento oficial. A DejaVu Sans Condensed é livre (licença em
     * storage/fonts/LICENSE-DejaVu.txt), tem métrica condensada como a do
     * oficial e é distribuída junto com o pacote.
     *
     * Falhando o registro, seguimos com a fonte core: um documento sem acento é
     * muito melhor que documento nenhum.
     *
     * @return void
     */
    protected function registraFonteAcentuada()
    {
        try {
            $this->pdf->addFont(self::FONTE_ACENTUADA, '', 'dejavusanscondensed.php');
            $this->pdf->addFont(self::FONTE_ACENTUADA, 'B', 'dejavusanscondensedb.php');
            $this->pdf->addFont(self::FONTE_ACENTUADA, 'I', 'dejavusanscondensedi.php');
            $this->pdf->addFont(self::FONTE_ACENTUADA, 'BI', 'dejavusanscondensedbi.php');

            $this->fontePadrao = self::FONTE_ACENTUADA;
        } catch (\Throwable $e) {
            // Mantém a fonte core já configurada.
        }
    }

    /**
     * Corta o texto na largura disponível, terminando em "...".
     *
     * Why: `cell()` do FPDF não recorta — o texto que não cabe simplesmente
     * transborda e se sobrepõe à coluna vizinha. As descrições da lista de
     * serviços (xTribNac/xTribMun) são longas o bastante para isso acontecer
     * sempre, e o documento oficial as trunca.
     *
     * @param string $texto
     * @param float  $largura Largura útil, em mm
     * @return string
     */
    protected function truncaNaLargura($texto, $largura)
    {
        $texto = (string) $texto;

        if ('' === $texto || $largura <= 0) {
            return $texto;
        }

        // getStringWidth mede no encoding da fonte, então a conversão precisa
        // acontecer antes da medição — não só na hora de escrever.
        if ($this->pdf->getStringWidth(PdfComFontes::paraEncodingDaFonte($texto)) <= $largura) {
            return $texto;
        }

        // Os dados vindos do XML já foram convertidos para ISO-8859-1 no parse,
        // enquanto os literais do código continuam em UTF-8. Cortar com a função
        // do encoding errado parte um caractere acentuado ao meio e produz "??".
        $encoding = mb_check_encoding($texto, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1';

        $reticencias = '...';
        $corte       = $texto;

        while ('' !== $corte
            && $this->pdf->getStringWidth(
                PdfComFontes::paraEncodingDaFonte($corte . $reticencias)
            ) > $largura
        ) {
            $corte = mb_substr($corte, 0, mb_strlen($corte, $encoding) - 1, $encoding);
        }

        return $corte . $reticencias;
    }

    /**
     * Campo vazio vira "-", como no DANFSe oficial (que nunca deixa a célula em
     * branco nem imprime "R$ 0,00" onde não há valor).
     *
     * @param string|null $valor
     * @return string
     */
    protected function ouTraco($valor)
    {
        $valor = trim((string) $valor);

        return '' === $valor ? '-' : $valor;
    }

    /**
     * Junta código e descrição no formato do oficial: "038 - 8599603 - Treinamento".
     *
     * @param string $codigo
     * @param string $descricao
     * @return string
     */
    protected function codigoComDescricao($codigo, $descricao)
    {
        $codigo    = trim((string) $codigo);
        $descricao = trim((string) $descricao);

        if ('' === $descricao) {
            return $codigo;
        }

        return '' === $codigo ? $descricao : $codigo . ' - ' . $descricao;
    }

    /**
     * Nome do país a partir do código BACEN. Só o Brasil é resolvido: é o caso
     * de mais de 99% das NFS-e e o XML omite o campo quando é nacional.
     *
     * @param string|int $codigo
     * @return string
     */
    protected function nomePais($codigo)
    {
        $codigo = trim((string) $codigo);

        if ('' === $codigo) {
            return '';
        }

        return '1058' === $codigo ? 'Brasil' : $codigo;
    }

    /**
     * Situação perante o Simples Nacional na data da competência (opSimpNac).
     *
     * ATENÇÃO: este campo NÃO compartilha domínio com `regEspTrib`. Aplicar a
     * tabela de regime especial aqui fazia uma ME/EPP (opSimpNac=3) ser impressa
     * como "Sociedade de Profissionais" — classificação fiscal errada no
     * documento. Os textos abaixo são os do DANFSe oficial.
     *
     * @param string|int $codigo
     * @return string
     */
    protected function getOptanteSimplesNacional($codigo)
    {
        $situacoes = [
            '1' => 'Não Optante',
            '2' => 'Optante - Microempreendedor Individual (MEI)',
            '3' => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
        ];

        return $situacoes[(string) $codigo] ?? '';
    }

    /**
     * Regime de apuração dos tributos pelo Simples Nacional (regApTribSN).
     *
     * @param string|int $codigo
     * @return string
     */
    protected function getRegimeApuracaoSN($codigo)
    {
        $regimes = [
            '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            '2' => 'Regime de apuração dos tributos federais pelo Simples Nacional e o ISSQN por fora do Simples Nacional',
            '3' => 'Regime de apuração dos tributos federais e municipal por fora do Simples Nacional',
        ];

        return $regimes[(string) $codigo] ?? '';
    }

    /**
     * Regime especial de tributação (regEspTrib) — domínio próprio, não confundir
     * com o Simples Nacional acima.
     *
     * @param string|int $codigo
     * @return string
     */
    protected function getRegimeEspecialTributacao($codigo)
    {
        $regimes = [
            '0' => 'Nenhum',
            '1' => 'Ato Cooperado',
            '2' => 'Estimativa',
            '3' => 'Microempresa Municipal',
            '4' => 'Notário ou Registrador',
            '5' => 'Profissional Autônomo',
            '6' => 'Sociedade de Profissionais',
        ];

        return $regimes[(string) $codigo] ?? 'Nenhum';
    }

    /**
     * Formata valor monetário
     * 
     * @param float $valor
     * @return string
     */
    protected function formatarValor($valor)
    {
        $decimals = $this->decimalPlaces ?? 2;
        return number_format((float)$valor, $decimals, ',', '.');
    }

    /**
     * Formata CNPJ/CPF
     *
     * CNPJ alfanumérico (a partir de julho/2026): letras na raiz e na ordem,
     * dígitos verificadores numéricos. A máscara é a mesma — só não se pode
     * descartar as letras ao limpar a pontuação.
     *
     * @param string $doc
     * @return string
     */
    protected function formatarCnpjCpf($doc)
    {
        $doc = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $doc));

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
    protected function formatarData($data, $formato = 'd/m/Y H:i:s')
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
    protected function montarEndereco($endereco)
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

    protected function statusNFSe()
    {
        $obj = (object) $this->nfseArray['infNFSe'];
        $resp = [
            'status' => true,
            'message' => [],
            'submessage' => ''
        ];

        //if (!empty($this->epec) && $this->tpEmis == '4') {
        if ($obj->tpEmis == '4') {
            return $resp;
        }
        if ($obj->tpEmis == '5') {
            return $resp;
        }
        // Validar onde na NFSe está o protocolo de emissão (Homologação e Produção)
        // Bruno Alvim - 28/01/2026
//        if (!isset($this->nfeProc)) {
//            $resp['status'] = false;
//            $resp['message'][] = 'NFe NÃO PROTOCOLADA';
//        }
        else {
            if ($obj->ambGer == '2') {
                $resp['status'] = false;
                $resp['message'][] =  "NFe EMITIDA EM HOMOLOGAÇÃO";
                //$resp['submessage'] = "SEM VALOR FISCAL";
            }
            // Validar o retorno do evento da NFSe
            // Bruno Alvim - 28/01/2026
            //$retEvento = $this->nfeProc->getElementsByTagName('retEvento')->item(0);
            $retEvento = [];
            $cStat = $obj->cStat;
            if (in_array($cStat, ['110', '205', '301', '302', '303'])) {
                $resp['status'] = false;
                $resp['message'][] = "NFe DENEGADA";
                //$resp['submessage'] = $this->infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue;
            } elseif (in_array($cStat, ['101', '151', '135', '155'])
            // Verificar se existe uma flag de Cancelamento
            // Bruno Alvim - 28/01/2026
            //    || $this->cancelFlag === true
            ) {
                $resp['status'] = false;
                $resp['message'][] = "NFe CANCELADA";
            }
            // Validar o retorno do Evento da NFSe
            // Bruno Alvim - 28/01/2026
            /*
            elseif (!empty($retEvento)) {
                $infEvento = $retEvento->getElementsByTagName('infEvento')->item(0);
                $cStat = $this->getTagValue($infEvento, "cStat");
                $tpEvento = $this->getTagValue($infEvento, "tpEvento");
                $dhEvento = $this->toDateTime($this->getTagValue($infEvento, "dhRegEvento"))->format("d/m/Y H:i:s");
                $nProt = $this->getTagValue($infEvento, "nProt");
                if ($tpEvento == '110111' &&
                    ($cStat == '101' ||
                        $cStat == '151' ||
                        $cStat == '135' ||
                        $cStat == '155')
                ) {
                    $resp['status'] = false;
                    $resp['message'][] = "NFe CANCELADA";
                    $resp['submessage'] = "{$dhEvento} - {$nProt}";
                }
            }
            */
        }

        return $resp;
    }

    /*
    |--------------------------------------------------------------------------
    | Pontos de variação entre as versões do DANFSe
    |--------------------------------------------------------------------------
    */

    /**
     * Desenha a marca da NFS-e no canto esquerdo do cabeçalho.
     *
     * @param float       $x    Origem horizontal do cabeçalho
     * @param float       $y    Origem vertical do cabeçalho
     * @param string|null $logo Logo informado em render($logo)
     * @return void
     */
    abstract protected function renderMarcaNfse($x, $y, $logo = null, $largura = null, $altura = null);

    /**
     * Desenha o QR Code de consulta e o texto de autenticidade ao lado.
     *
     * A moldura é desenhada aqui para que as duas versões fiquem com o mesmo
     * cabeçalho; só o conteúdo interno depende de haver gerador de QR.
     *
     * @param float $x Origem horizontal do cabeçalho
     * @param float $y Origem vertical do cabeçalho
     * @return void
     */
    protected function renderQrCode($x, $y)
    {
        // O cabeçalho tem 35mm de altura e precisa acomodar o QR MAIS as três
        // linhas do texto de autenticidade. Com o QR ocupando 30mm o texto não
        // cabia e era escrito por cima da moldura.
        $qrSize  = 22;
        $textoW  = 42;
        $qrX     = $x + $this->wPrint - $qrSize - 2;
        $textoX  = $x + $this->wPrint - $textoW - 2;

        $this->desenhaQrCode($qrX, $y + 2, $qrSize);

        // Texto de autenticidade — o documento oficial o traz sempre sob o QR.
        $this->pdf->setFont($this->fontePadrao, '', 4.5);
        $this->pdf->setXY($textoX, $y + $qrSize + 3);
        $this->pdf->cell($textoW, 2, 'A autenticidade desta NFS-e pode ser verificada', 0, 1, 'C');
        $this->pdf->setX($textoX);
        $this->pdf->cell($textoW, 2, 'pela leitura deste código QR ou pela consulta da', 0, 1, 'C');
        $this->pdf->setX($textoX);
        $this->pdf->cell($textoW, 2, 'chave de acesso no portal nacional da NFS-e', 0, 0, 'C');
    }

    /**
     * Pinta o QR Code em si. Por padrão só a moldura — as versões que sabem
     * gerar a imagem sobrescrevem.
     *
     * @param float $x    Canto esquerdo do QR
     * @param float $y    Topo do QR
     * @param float $size Lado do quadrado, em mm
     * @return void
     */
    protected function desenhaQrCode($x, $y, $size)
    {
        $this->pdf->rect($x, $y, $size, $size);
    }

    /**
     * URL de consulta pública da NFS-e, o conteúdo do QR Code.
     *
     * @return string
     */
    protected function urlConsultaPublica()
    {
        $chave = filter_var($this->infNfse['chave_acesso'] ?? '', FILTER_SANITIZE_NUMBER_INT);

        return 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . $chave;
    }

    /*
    |--------------------------------------------------------------------------
    | Municípios
    |--------------------------------------------------------------------------
    */

    /**
     * De-para IBGE carregado sob demanda (218KB — não custa nada quando o
     * documento já traz o nome pronto).
     *
     * @var array<int, array{0: string, 1: string}>|null
     */
    private static $municipios = null;

    /**
     * Formata o município como o documento oficial: "Campinas - SP".
     *
     * O XML só traz `cMun` em `enderNac`/`endNac`; o nome, quando existe, vem
     * solto em `xLocEmi`/`xLocPrestacao`/`xLocIncid`. Preferimos o nome do
     * próprio documento e usamos a tabela IBGE para completar a UF — ou para
     * resolver tudo, no caso do tomador, que não tem nome algum no XML.
     *
     * @param string|int $codigoIbge
     * @param string     $nome Nome já conhecido (xLocEmi e afins), se houver
     * @param string     $uf   UF já conhecida, se houver
     * @return string
     */
    protected function formatarMunicipio($codigoIbge, $nome = '', $uf = '')
    {
        $codigo = (int) filter_var((string) $codigoIbge, FILTER_SANITIZE_NUMBER_INT);
        $tabela = $this->municipioPorCodigo($codigo);

        if ('' === (string) $nome && null !== $tabela) {
            $nome = $tabela[0];
        }

        if ('' === (string) $uf && null !== $tabela) {
            $uf = $tabela[1];
        }

        $nome = trim((string) $nome);
        $uf   = trim((string) $uf);

        if ('' === $nome) {
            // Sem nome nem tabela, o código cru ainda é melhor que o vazio.
            return $codigo > 0 ? (string) $codigo : '';
        }

        return '' === $uf ? $nome : $nome . ' - ' . $uf;
    }

    /**
     * @param int $codigo
     * @return array{0: string, 1: string}|null
     */
    private function municipioPorCodigo($codigo)
    {
        if ($codigo <= 0) {
            return null;
        }

        if (null === self::$municipios) {
            $arquivo = __DIR__ . '/../../storage/municipios.php';

            self::$municipios = is_file($arquivo) ? (array) require $arquivo : [];
        }

        return self::$municipios[$codigo] ?? null;
    }
}
