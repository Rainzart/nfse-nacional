<?php

declare(strict_types = 1);

namespace Hadder\NfseNacional;

use Exception;
use Hadder\NfseNacional\Common\RestBase;
use NFePHP\Common\Certificate;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Signer;

class RestCurl extends RestBase
{
    public const URL_SEFIN_HOMOLOGACAO = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
    public const URL_SEFIN_PRODUCAO    = 'https://sefin.nfse.gov.br/sefinnacional';
    public const URL_ADN_HOMOLOGACAO   = 'https://adn.producaorestrita.nfse.gov.br';
    public const URL_ADN_PRODUCAO      = 'https://adn.nfse.gov.br';
    public const URL_NFSE_HOMOLOGACAO  = 'https://www.producaorestrita.nfse.gov.br/EmissorNacional';
    public const URL_NFSE_PRODUCAO     = 'https://www.nfse.gov.br/EmissorNacional';
    public string $soaperror;
    public int $soaperror_code;
    public array $soapinfo;
    public string $responseHead;
    public string $responseBody;

    protected $canonical    = [true, false, null, null];
    private string $cookies = '';
    private mixed $config;
    private string $url_api;
    private $connection_timeout = 30;
    private $timeout            = 30;
    private $httpver;
    private string $requestHead;
    private string $temppass = '';
    private string $security_level = '';

    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($cert);
        $this->config      = json_decode($config);
        $this->certificate = $cert;
        //        $this->wsobj = $this->loadWsobj($this->config->cmun);
    }

    /**
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
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
            curl_setopt($oCurl, CURLOPT_URL, $this->url_api . '/' . $operacao);
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
            } elseif (3 === $origem && !empty($this->cookies)) {
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
            $headsize           = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode           = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            $contentType        = curl_getinfo($oCurl, CURLINFO_CONTENT_TYPE);
            $this->responseHead = mb_trim(mb_substr($response, 0, $headsize));
            $this->responseBody = mb_trim(mb_substr($response, $headsize));

            // detecta redirect, conseguiu logar com certificado na origem 3 e pega cookies
            if (3 == $origem and 302 == $httpcode) {
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
     * @param $origem - URL de consulta 1 = Sefin (emissão), 2 = ADN (DANFSe)
     *
     * @return mixed|string
     */
    public function postData($operacao, $data, $origem = 1)
    {
        $this->resolveUrl($origem);
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
            curl_setopt($oCurl, CURLOPT_URL, $this->url_api . '/' . $operacao);
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
            $this->responseHead = mb_trim(mb_substr($response, 0, $headsize));
            $this->responseBody = mb_trim(mb_substr($response, $headsize));

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

    private function resolveUrl(int $origem = 0)
    {
        switch ($origem) {
            case 1: // SEFIN
            default:
                $this->url_api = self::URL_SEFIN_HOMOLOGACAO;

                if (1 === $this->config->tpamb) {
                    $this->url_api = self::URL_SEFIN_PRODUCAO;
                }

                break;
            case 2: // ADN
                $this->url_api = self::URL_ADN_HOMOLOGACAO;

                if (1 === $this->config->tpamb) {
                    $this->url_api = self::URL_ADN_PRODUCAO;
                }

                break;
            case 3: // NFSE
                $this->url_api = self::URL_NFSE_HOMOLOGACAO;

                if (1 === $this->config->tpamb) {
                    $this->url_api = self::URL_NFSE_PRODUCAO;
                }

                break;
        }

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
