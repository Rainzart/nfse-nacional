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
        "nfse_producao" => "https://www.nfse.gov.br/EmissorNacional",
        "soap_producao" => "https://parademinas.mg.issqn.quasar.srv.br/nfe/snissdigitalsvc",
        "soap_homologacao" => "https://parademinas.mg.des.issqn.quasar.tec.br/nfe/snissdigitalsvc",
        "soap_wsdl_producao" => "https://parademinas.mg.issqn.quasar.srv.br/nfe/snissdigitalsvc?wsdl",
        "soap_wsdl_homologacao" => "https://parademinas.mg.des.issqn.quasar.tec.br/nfe/snissdigitalsvc?wsdl"
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
    private string $url_api_wsdl;
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
            case 4: // SOAP
                $this->url_api = $this->urls['soap_homologacao'];
                if ($this->config->tpamb === 1) {
                    $this->url_api = $this->urls['soap_producao'];
                }

                $this->url_api_wsdl = $this->urls['soap_wsdl_homologacao'];
                if ($this->config->tpamb === 1) {
                    $this->url_api_wsdl = $this->urls['soap_wsdl_producao'];
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

    public function postSoap (string $operation, string $content, int $origin = 4) : array
    {
        $this->resolveUrl($origin);

        $client = new \SoapClient($this->url_api_wsdl, [
            'trace'         => 1,
            'exceptions'    => true,
            'cache_wsdl'    => WSDL_CACHE_NONE,
            'location'      => $this->url_api,   // força envio no endpoint
        ]);

        try {
            $resp = $client->__soapCall($operation, [[ 'xml' => $content ]]);
            return ['response' => $resp];
        } catch (\SoapFault $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getSoap (string $operation, string $content, int $origin = 4) : array
    {
        $this->resolveUrl($origin);

        $client = new \SoapClient($this->url_api_wsdl, [
            'trace'         => 1,
            'exceptions'    => true,
            'cache_wsdl'    => WSDL_CACHE_NONE,
            'location'      => $this->url_api,   // força envio no endpoint
        ]);

        $resp = $client->__soapCall($operation, [[ 'xml' => $content ]]);

        try {
            $resp = $client->__soapCall($operation, [[ 'xml' => $content ]]);
            return ['response' => $resp];
        } catch (\SoapFault $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
