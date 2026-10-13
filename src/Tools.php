<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

use NFePHP\Common\Certificate;

class Tools extends RestCurl
{
    /**
     * Valida o XML contra o XSD oficial antes de transmitir.
     */
    protected bool $validateSchema = true;

    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }

    /**
     * Liga/desliga a validação de schema antes da transmissão.
     *
     * Desligue apenas em cenários excepcionais (ex.: schema desatualizado no
     * pacote impedindo uma emissão que a SEFAZ aceita). Sem ela, o XML fora do
     * schema só é detectado pela rejeição da SEFAZ.
     */
    public function setValidateSchema(bool $flag): void
    {
        $this->validateSchema = $flag;
    }

    public function consultarNfseChave($chave, $encoding = true)
    {
        $operacao = str_replace('{chave}', $chave, $this->getOperation('consultar_nfse'));
        $retorno  = $this->getData($operacao);

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            $base_decode = base64_decode($retorno['nfseXmlGZipB64']);
            $gz_decode   = gzdecode($base_decode);

            return $encoding ? mb_convert_encoding($gz_decode, 'ISO-8859-1') : $gz_decode;
        }

        return null;
    }

    public function consultarDpsChave($chave)
    {
        $operacao = str_replace('{chave}', $chave, $this->getOperation('consultar_dps'));
        $retorno  = $this->getData($operacao);

        return $retorno;
    }

    public function consultarNfseEventos($chave, $tipoEvento = null, $nSequencial = null)
    {
        $operacao = str_replace('{chave}', $chave, $this->getOperation('consultar_eventos'));

        if ($tipoEvento) {
            $operacao = str_replace('{tipoEvento}', (string) $tipoEvento, $operacao);
        } else {
            // Sem tipo de evento: remove os dois segmentos opcionais da URL.
            $operacao = str_replace('/{tipoEvento}/{nSequencial}', '', $operacao);
        }

        if ($nSequencial) {
            $operacao = str_replace('{nSequencial}', (string) $nSequencial, $operacao);
        } else {
            $operacao = str_replace('/{nSequencial}', '', $operacao);
        }

        $retorno = $this->getData($operacao);

        return $retorno;
    }

    public function consultarDanfse(string $chave)
    {
        $operacao = str_replace('{chave}', $chave, $this->getOperation('consultar_danfse'));
        $retorno  = $this->getData($operacao, null, 2);

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            return $retorno;
        }

        if (empty($retorno)) {
            return $this->consultarDanfseNfse($chave);
        }

        return null;
    }

    /**
     * Consulta o DANFSe via NFSe caso o serviço direto falhe.
     *
     * @param string $chave
     *
     * @return array|binary|null
     */
    public function consultarDanfseNfse($chave)
    {
        $operacao = $this->getOperation('consultar_danfse_nfse_certificado');
        $retorno  = $this->getData($operacao, null, 3);

        if (isset($retorno) and isset($retorno['sucesso']) and true == $retorno['sucesso']) {
            $operacao = str_replace('{chave}', $chave, $this->getOperation('consultar_danfse_nfse_download'));
            $retorno  = $this->getData($operacao, null, 3);
        }

        if (isset($retorno['erro'])) {
            return $retorno;
        }

        if ($retorno) {
            return $retorno;
        }

        return null;
    }

    public function enviaDps($content): array
    {
        // Valida ANTES de assinar (a Signature entra depois e não faz parte do
        // que o XSD do infDPS descreve). Assim um XML fora do schema é apontado
        // com o elemento exato, em vez de virar uma rejeição L2103 genérica.
        $this->assertValidSchema($content, 'DPS');

        $content = $this->sign($content, 'infDPS', '', 'DPS');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz      = gzencode($content);
        $data    = base64_encode($gz);
        $dados   = [
            'dpsXmlGZipB64' => $data,
        ];
        $operacao = $this->getOperation('emitir_nfse');
        $retorno  = $this->postData($operacao, json_encode($dados));

        return $retorno;
    }

    public function cancelaNfse($std)
    {
        $dps     = new Dps($std);
        $content = $dps->renderEvento($std);

        $this->assertValidSchema($content, 'pedRegEvento', $dps->std->version ?? '1.01');

        $content = $this->sign($content, 'infPedReg', '', 'pedRegEvento');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz      = gzencode($content);
        $data    = base64_encode($gz);
        $dados   = [
            'pedidoRegistroEventoXmlGZipB64' => $data,
        ];
        $operacao = str_replace('{chave}', $std->infPedReg->chNFSe, $this->getOperation('cancelar_nfse'));
        $retorno  = $this->postData($operacao, json_encode($dados));

        return $retorno;
    }

    /**
     * Falha cedo quando o XML não bate com o XSD oficial.
     *
     * @throws SchemaValidationException
     */
    protected function assertValidSchema(string $xml, string $documento, string $versao = '1.01'): void
    {
        if (!$this->validateSchema) {
            return;
        }

        $erros = SchemaValidator::errors($xml, $documento, $this->versaoDoXml($xml) ?? $versao);

        if ([] !== $erros) {
            throw new SchemaValidationException($documento, $erros);
        }
    }

    /**
     * Lê o atributo versao do elemento raiz, para escolher o XSD certo.
     *
     * O XML chega aqui já renderizado, então a versão vem dele — não de config.
     */
    protected function versaoDoXml(string $xml): ?string
    {
        if (preg_match('/<(?:DPS|pedRegEvento)[^>]*\sversao="([^"]+)"/', $xml, $m)) {
            return $m[1];
        }

        return null;
    }
}
