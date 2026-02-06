<?php

namespace Hadder\NfseNacional;

use Exception;
use Hadder\NfseNacional\Common\RestBase;
use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Signer;
use RuntimeException;

class RestCurl extends RestBase
{
    const DEFAULT_URLS = [
        "sefin_homologacao" => "https://sefin.producaorestrita.nfse.gov.br/SefinNacional",
        "sefin_producao" => "https://sefin.nfse.gov.br/sefinnacional",
        "adn_homologacao" => "https://adn.producaorestrita.nfse.gov.br",
        "adn_producao" => "https://adn.nfse.gov.br",
        "nfse_homologacao" => "https://www.producaorestrita.nfse.gov.br/EmissorNacional",
        "nfse_producao" => "https://www.nfse.gov.br/EmissorNacional"
    ];
    const DEFAULT_OPERATIONS = [
        "consultar_nfse" => "nfse/{chave}",
        "consultar_dps" => "dps/{chave}",
        "consultar_eventos" => "nfse/{chave}/eventos/{tipoEvento}/{nSequencial}",
        "consultar_danfse" => "danfse/{chave}",
        "consultar_danfse_nfse_certificado" => "Certificado",
        "consultar_danfse_nfse_download" => "Notas/Download/DANFSe/{chave}",
        "emitir_nfse" => "nfse",
        "cancelar_nfse" => "nfse/{chave}/eventos"
    ];
    private $urls = [];
    private $operations = [];
    private mixed $config;
    private string $url_api;
    private $connection_timeout = 30;
    private $timeout = 30;
    private $httpver;
    public string $soaperror;
    public int $soaperror_code;
    public array $soapinfo;
    public string $responseHead;
    public string $responseBody;
    private string $requestHead;
    private string $cookies = '';
    private int $eventogovCloud = 0;

    protected $canonical = [true, false, null, null];

    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($cert);
        $this->config = json_decode($config);
        $this->certificate = $cert;
        $configFile = __DIR__ . '/../storage/prefeituras.json';

        $this->loadConfigOverrides($configFile, $this->config->prefeitura ?? null);
    }

    private function loadConfigOverrides($jsonFile, $context): void
    {
        $json = json_decode(file_get_contents($jsonFile) ?: "", true);

        if (!is_array($json)) {
            throw new RuntimeException("JSON inválido em $jsonFile");
        }

        $contextData = $json[$context] ?? [];

        $this->urls = $this->mergeDefaults(self::DEFAULT_URLS, $contextData['urls'] ?? []);

        $this->operations = $this->mergeDefaults(self::DEFAULT_OPERATIONS, $contextData['operations'] ?? []);

    }

    private function mergeDefaults(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }

    public function getOperation($operation)
    {
        return $this->operations[$operation];
    }

    /**
     * @param $operacao
     * @param $data
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
     * @return mixed|string
     */
    public function getData($operacao, $data = null, $origem = 1)
    {
        $this->resolveUrl($origem);
        $this->saveTemporarilyKeyFiles();
        try {
            $msgSize = $data ? strlen($data) : 0;
            $parameters = [
                "Content-Type: application/json;charset=utf-8;",
                "Content-length: $msgSize"
            ];
            $oCurl = curl_init();
            $api_url = $this->url_api;
            if (strlen($operacao) > 0) {
                $api_url .= '/' . $operacao;
            }
            curl_setopt($oCurl, CURLOPT_URL, $api_url);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, $this->httpver);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            if (!empty($this->security_level)) {
                curl_setopt($oCurl, CURLOPT_SSL_CIPHER_LIST, "{$this->security_level}");
            }
            //            if (!$this->disablesec) {
            //                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);
            //                if (!empty($this->casefaz)) {
            //                    if (is_file($this->casefaz)) {
            //                        curl_setopt($oCurl, CURLOPT_CAINFO, $this->casefaz);
            //                    }
            //                }
            //            }
            curl_setopt($oCurl, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
            curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
            curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
            if (!empty($this->temppass)) {
                curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
            }
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
            if (!empty($data)) {
                curl_setopt($oCurl, CURLOPT_POST, 1);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $data);
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            } elseif ($origem === 3 && !empty($this->cookies)) {
                $parameters[] = 'Cookie: ' . $this->cookies;
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);

            $this->soaperror = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($oCurl, CURLINFO_CONTENT_TYPE);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            //detecta redirect, conseguiu logar com certificado na origem 3 e pega cookies
            if ($origem == 3 and $httpcode == 302) {
                $this->captureCookies($this->responseHead, $origem);
                return ['sucesso' => true];
            }
            if ($contentType == 'application/pdf') {
                return $this->responseBody;
            } else {
                return json_decode($this->responseBody, true);
            }
        } catch (Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
    }

    /**
     * @param $operacao
     * @param $data
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
     * @return mixed|string
     */
    public function postData($operacao, $data, $origem = 1)
    {
        $this->resolveUrl($origem);
        $this->saveTemporarilyKeyFiles();
        try {
            $msgSize = $data ? strlen($data) : 0;
            $parameters = [
                //                'Accept: */*; ',
                'Content-Type: application/json',
                //                "Content-Type: application/x-www-form-urlencoded;charset=utf-8;",
                'Content-length: ' . $msgSize,
            ];
            //            $this->requestHead = implode("\n", $parameters);
            $oCurl = curl_init();
            $api_url = $this->url_api;
            if (strlen($operacao) > 0) {
                $api_url .= '/' . $operacao;
            }
            curl_setopt($oCurl, CURLOPT_URL, $api_url);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt($oCurl, CURLOPT_HTTP_VERSION, $this->httpver);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            if (!empty($this->security_level)) {
                curl_setopt($oCurl, CURLOPT_SSL_CIPHER_LIST, "{$this->security_level}");
            }

            curl_setopt($oCurl, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
            curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
            curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
            if (!empty($this->temppass)) {
                curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
            }
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
            if (!empty($data)) {
                curl_setopt($oCurl, CURLOPT_POST, 1);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $data);
                //curl_setopt($oCurl, CURLOPT_POSTFIELDS, http_build_query($data)); // Dados para enviar no POST
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);

            $this->soaperror = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            curl_close($oCurl);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            $this->responseBody = $this->refactorFormatProvider($this->responseBody, 999);

            return json_decode($this->responseBody, true);
        } catch (Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setConnectionTimeout($connection_timeout)
    {
        $this->connection_timeout = $connection_timeout;
    }

    /**
     * Sign XML passing in content
     * @param string $content
     * @param string $tagname
     * @param string $mark
     * @return string XML signed
     */
    public function sign(string $content, string $tagname, ?string $mark, $rootname)
    {
        if (empty($mark)) {
            $mark = 'Id';
        }
        $xml = Signer::sign(
            $this->certificate,
            $content,
            $tagname,
            $mark,
            OPENSSL_ALGO_SHA1,
            $this->canonical,
            $rootname
        );
        return $xml;
    }

    private function resolveUrl(int $origem = 0)
    {
        switch ($origem) {
            case 1: // SEFIN
                $this->url_api = $this->urls['sefin_homologacao'];
                if ($this->config->tpamb === 1) {
                    $this->url_api = $this->urls['sefin_producao'];
                }
                break;
            case 2: // ADN
                $this->url_api = $this->urls['adn_homologacao'];
                if ($this->config->tpamb === 1) {
                    $this->url_api = $this->urls['adn_producao'];
                }
                break;
            case 3: // NFSE
                $this->url_api = $this->urls['nfse_homologacao'];
                if ($this->config->tpamb === 1) {
                    $this->url_api = $this->urls['nfse_producao'];
                }
                break;
        }

    }

    private function captureCookies(string $headers, int $origem): void
    {
        if ($origem !== 3) {
            return;
        }
        if (!preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)/mi', $headers, $matches)) {
            return;
        }
        $cookies = array_map('trim', $matches[1]);
        if (!empty($cookies)) {
            $this->cookies = implode('; ', $cookies);
        }
    }

    /**
     * Função para refatorar a requisição para o formato correto esperado pelo provedor, ou a resposta da requisição para poder manter uma saída padrão, ou pelo menos o mais próximo para manter a compatibilidade
     * Alguns municípios com provedor próprio (govCloud é um deles) tem a requisição/resposta num formato um pouco diferente do sistema nacional
     * Esta função vai refatorar a requisição/resposta para ficar de acordo com o que o provedor espera
     * @param string|array $data requisição já comprimida e em base64 ou array de resposta
     * @param int $evento tipo de evento 1 = envio de DPS, 2 = cancelamento de DPS, 999 = resposta da requisição(até o momento é esperado que sejá usado apenas para envio de DPS e Eventos )
     * @return array|string requisição refatorada conforme o provedor
     * @author leandro-mafra
     */
    public function refactorFormatProvider(string|array $data, int $evento): array|string
    {
        $return = [];
        // busca o município pelo código ibge passado na configuração da classe Tools
        switch($this->config->prefeitura){
            case '3118601': // Contagem - MG
            case '3542404': // Regente Feijó - SP
                // govCloud
                // tando o envio quanto o cancelamento tem o mesmo formato
                $return = $this->govCloudRefactor($data, $evento);
                break;
            default:
                // sistema nacional - qualquer outro município
                    if ($evento == 999) {
                        // resposta
                        $return = $data;
                    } elseif ($evento == 1) {
                        // envio DPS
                        $return = [
                            'dpsXmlGZipB64' => $data
                        ];
                    } else {
                        // evento de cancelamento
                        $return = [
                            'pedidoRegistroEventoXmlGZipB64' => $data
                        ];
                    }
                break;
        }
        return $return;
    }

    /**
     * Função para refatorar a requisição/resposta para o formato esperado pelo provedor govCloud
     * @param string|array $data requisição já comprimida e em base64 ou array de resposta
     * @param int $evento tipo de evento 1 = envio de DPS, 2 = cancelamento de DPS, 999 = resposta da requisição
     * @return array|string requisição refatorada conforme o provedor
     * @author leandro-mafra
     */
    private function govCloudRefactor(string|array $data, int $evento): array|string
    {
        $return = [];

        if ($evento == 999) {
            // resposta
            //
            // checa se a resposta já está no formato esperado
            $jsonTemp = json_decode($data, true);
            if (
                json_last_error() === JSON_ERROR_NONE && // checa se é um json válido
                array_key_exists('lote', $jsonTemp)
            ) {
                // formata para retirar o conteúdo do nível "lote" e passar para o nível superior, mantendo o que estiver fora do "lote"
                // assim ficando mais próximo do formato padrão do sistema nacional
                $arrayTemp1 = $jsonTemp;
                $arrayTemp2 = $jsonTemp['lote'][0];
                unset($arrayTemp1['lote']);
                $return = array_merge($arrayTemp1, $arrayTemp2);

                if ($this->eventogovCloud == 1) {
                    // no sistema nacioanl a nota emitida vem nessa posição 'nfseXmlGZipB64'
                    $return['nfseXmlGZipB64'] = $return['xmlGZipB64'] ?? null;
                    if (array_key_exists('xmlGZipB64', $return)) {
                        unset($return['xmlGZipB64']);
                    }
                } else {
                    // no sistema nacioanl o retorno dos eventos (pelo menos o de cancelamento que eu sei) emitida vem nessa posição 'eventoXmlGZipB64'
                    $return['eventoXmlGZipB64'] = $return['xmlGZipB64'] ?? null;
                    if (array_key_exists('xmlGZipB64', $return)) {
                        unset($return['xmlGZipB64']);
                    }
                }

                // se tiver erro corrige a forma que está escrito a key "codigo" para "Codigo"(para ficar igual ao sistema nacional)
                if (isset($return['erros'])) {
                    if (!empty($return['erros'])) {
                        foreach($return['erros'] as $key => $item) {
                            foreach($item as $keyR => $itemR) {
                                if ($keyR === 'codigo') {
                                    unset($return['erros'][$key]['codigo']); // remove a key com texto incorreto
                                    $return['erros'][$key]['Codigo'] = $itemR; // adiciona a key com o texto correto
                                }
                            }
                        }
                    } else {
                        // caso a key "erros" exista e for vazia, será removida
                        unset($return['erros']);
                    }
                }

                $return = json_encode($return);
            } else {
                // fora do padrão esperado, nesse caso costuma ser um erro de conexão por exemplo
                // então vai retornar apenas o que foi recebido
                $return = $data;
            }
        } else {
            // tando o envio quanto o cancelamento tem o mesmo formato
            $return = [
                'LoteXmlGZipB64' => [$data]
            ];

            $this->eventogovCloud = $evento;
        }
        return $return;
    }
}
