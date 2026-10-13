<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

use Exception;
use QuantumTecnology\NfseNacional\Common\RestBase;
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

    /**
     * URLs nacionais (sem overrides da prefeitura). As CONSULTAS por chave usam
     * estas — só a EMISSÃO/CANCELAMENTO podem ir ao endpoint próprio da
     * prefeitura. Municípios como Americana-SP têm emissor próprio que aceita o
     * leiaute nacional só na emissão; a consulta segue no Ambiente Nacional.
     */
    private array $nationalUrls = [];
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

    protected $canonical    = [true, false, null, null];
    private string $cookies = '';
    private string $temppass = '';
    private string $security_level = '';

    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($cert);
        $this->config      = json_decode($config);
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

        // URLs nacionais puras (consultas) x URLs com override da prefeitura (emissão).
        $this->nationalUrls = self::DEFAULT_URLS;
        $this->urls         = $this->mergeDefaults(self::DEFAULT_URLS, $contextData['urls'] ?? []);

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
        if (!array_key_exists($operation, $this->operations)) {
            throw new RuntimeException("Operação desconhecida: {$operation}");
        }

        return $this->operations[$operation];
    }

    /**
     * Busca dados (CONSULTAS por chave). Usa sempre as URLs do Ambiente Nacional
     * — nunca o override da prefeitura — pois mesmo municípios com emissor
     * próprio (ex.: Americana-SP) mantêm as consultas no ambiente nacional.
     *
     * @param $origem - 1 = Sefin, 2 = ADN (DANFSe), 3 = NFSE (emissor público)
     *
     * @return mixed|string
     */
    public function getData($operacao, $data = null, $origem = 1)
    {
        $this->resolveUrl($origem);
        $this->saveTemporarilyKeyFiles();

        try {
            $msgSize    = $data ? mb_strlen($data) : 0;
            $parameters = [
                'Content-Type: application/json;charset=utf-8;',
                "Content-length: $msgSize",
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

            $this->soaperror      = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo                = curl_getinfo($oCurl);

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

            if ('application/pdf' == $contentType) {
                return $this->responseBody;
            }

            return json_decode($this->responseBody, true);

        } catch (Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
    }

    /**
     * Envia dados (EMISSÃO/CANCELAMENTO). Usa as URLs com override da prefeitura
     * quando o município tem endpoint próprio (ex.: Americana-SP).
     *
     * @param $origem - 1 = Sefin (emissão), 2 = ADN, 3 = NFSE
     *
     * @return mixed|string
     */
    public function postData($operacao, $data, $origem = 1)
    {
        $this->resolveUrl($origem, usarOverridePrefeitura: true);
        $this->saveTemporarilyKeyFiles();

        try {
            $msgSize    = $data ? mb_strlen($data) : 0;
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
                // curl_setopt($oCurl, CURLOPT_POSTFIELDS, http_build_query($data)); // Dados para enviar no POST
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);

            $this->soaperror      = curl_error($oCurl);
            $this->soaperror_code = curl_errno($oCurl);
            $ainfo                = curl_getinfo($oCurl);

            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            curl_close($oCurl);
            // trim e não mb_trim: cabeçalhos HTTP são ASCII, e mb_trim só existe
            // no PHP 8.4 (o pacote declara ^8.1 no composer).
            $this->responseHead = trim(mb_substr($response, 0, $headsize));
            $this->responseBody = trim(mb_substr($response, $headsize));

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
     * Sign XML passing in content.
     *
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

    /**
     * Resolve a URL base do request.
     *
     * @param int  $origem                 1 = SEFIN, 2 = ADN, 3 = NFSE (emissor)
     * @param bool $usarOverridePrefeitura quando true usa as URLs com override
     *                                      da prefeitura (EMISSÃO/CANCELAMENTO);
     *                                      quando false usa as URLs nacionais
     *                                      (CONSULTAS por chave). Ver
     *                                      loadConfigOverrides().
     */
    private function resolveUrl(int $origem = 0, bool $usarOverridePrefeitura = false): void
    {
        $urls = $usarOverridePrefeitura ? $this->urls : $this->nationalUrls;
        $prod = 1 === $this->config->tpamb;

        $this->url_api = match ($origem) {
            1 => $prod ? $urls['sefin_producao'] : $urls['sefin_homologacao'], // SEFIN
            2 => $prod ? $urls['adn_producao'] : $urls['adn_homologacao'],     // ADN
            3 => $prod ? $urls['nfse_producao'] : $urls['nfse_homologacao'],   // NFSE
            default => throw new RuntimeException("Origem de URL inválida: {$origem}"),
        };
    }

    private function captureCookies(string $headers, int $origem): void
    {
        if (3 !== $origem) {
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
}
