<?php

namespace Hadder\NfseNacional;

use DOMDocument;
use NFePHP\Common\Certificate;

class Tools extends RestCurl
{
    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }

    public function consultarNfseChave($chave, $encoding = true)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_nfse'));
        $retorno = $this->getData($operacao);

        if (
            isset($retorno['erro']) ||
            is_null($retorno) // culpa da pronim que retorna null quando a nota não é encontrada, ao invés de um erro
        ) {
            return $retorno;
        }
        if ($retorno) {
            $retornoR = null;
            if (
                isset($retorno['notas']) &&
                isset($retorno['notas'][0]) &&
                isset($retorno['notas'][0]['xmlGZipB64'])
            ) {
                // retorno padrão da pronim
                $retornoR = $retorno['notas'][0]['xmlGZipB64'];
            } else {
                // retorno padrão do sistema nacional
                $retornoR = $retorno['nfseXmlGZipB64'];
            }
            $base_decode = base64_decode($retorno['nfseXmlGZipB64']);
            $gz_decode = gzdecode($base_decode);
            return $encoding ? mb_convert_encoding($gz_decode, 'ISO-8859-1') : $gz_decode;
        }
        return null;
    }

    public function consultarDpsChave($chave)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_dps'));
        $retorno = $this->getData($operacao);

        return $retorno;
    }

    public function consultarNfseEventos($chave, $tipoEvento = null, $nSequencial = null)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_eventos'));
        if (!$tipoEvento) {
            $operacao = str_replace("/{tipoEvento}/{nSequencial}", "", $operacao);
        }
        $operacao = str_replace("{tipoEvento}", $tipoEvento, $operacao);

        if (!$nSequencial) {
            $operacao = str_replace("/{nSequencial}", "", $operacao);
        }
        $operacao = str_replace("{nSequencial}", $nSequencial, $operacao);

        $retornoTemp = $this->getData($operacao);

        /**
         * não sei se isso é um erro de homologação ou se via acontecer tbm na produção
         * mas durante os meus testes, notas que foram canceladas com o evento 101101 json
         * já notas que foram canceladas por substituição, retornam uma array vazia
         */
        $retorno = null;
        if (is_null($retornoTemp)) {
            // até agora só a pronim retornou null quando não encontra a nota
            $retorno = $retornoTemp;
        } elseif(
            !isset($retornoTemp['eventos']) &&
            (   // identifica o retorno padrão da pronim
                isset($retornoTemp[0]) &&
                isset($retornoTemp[0]['xmlGZipB64'])
            )
        ) {
            // como a rota de eventos da pronim devolve todos os eventos dentro dele
            $tempArray = [];
            foreach ($retornoTemp as $evento) {
                if (
                    is_null($tipoEvento) || // adiciona o evento se não for passado nenhum tipo de evento do parãmetro da função consultarNfseEventos()
                    $tipoEvento == $evento['tipo'] // ou se o tipo de evento for passado for igual a um dos eventos recuperado
                ) {
                    $tempArray[] = [
                        'chaveAcesso'                => $chave,
                        'tipoEvento'                 => $evento['tipo'],
                        'numeroPedidoRegistroEvento' => null, // o unico que não consegui recuperar
                        'dataHoraRecebimento'        => $evento['dataInclusao'],
                        'arquivoXml'                 => $evento['xmlGZipB64'],
                    ];
                }
            }
            // adiciona para que o retorno do evento fique o mais parecido com o do sistema nacional
            // mas não consegue recuperar os campos, passarei eles como null
            // "dataHoraProcessamento"
            // "tipoAmbiente"
            // "versaoAplicativo"
            /**
             * exemplo de requisição do sistema nacional
             *
             *   "dataHoraProcessamento" => "2026-02-05T16:42:08.0252693-03:00"
             *   "tipoAmbiente" => 2
             *   "versaoAplicativo" => "SefinNacional_1.6.0"
             *   "eventos" => array:1 [
             *       0 => array:5 [
             *       "chaveAcesso" => "3106..."
             *       "tipoEvento" => 105102
             *       "numeroPedidoRegistroEvento" => 1
             *       "dataHoraRecebimento" => "2026-02-02T17:32:31"
             *       "arquivoXml" => "SDRzSUFBQUFBQUFFQUsxWTJaTHFTSE4rbFk2ZVMrSzB
             */

            $retorno = [
                'dataHoraProcessamento' => null,
                'tipoAmbiente' => null,
                'versaoAplicativo' => null,
            ];
            // no sistema nacional quando não é encontrado nenhum evento a posição "eventos" não é passado
            // farei o mesmo aqui
            if (!empty($tempArray)) {
                $retorno['eventos'] = $tempArray;
            }
        } else {
            $retorno = $retornoTemp;
        }

        return $retorno;
    }

    public function consultarDanfse($chave)
    {
        $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_danfse'));
        $retorno = $this->getData($operacao, null, 2);
        if (isset($retorno['erro'])) {
            return $retorno;
        }
        if ($retorno) {
            return $retorno;
        }
        if(empty($retorno)){
            return $this->consultarDanfseNfse($chave);
        }
        return null;
    }

    /**
     * Consulta o DANFSe via NFSe caso o serviço direto falhe
     *
     * @param string $chave
     * @return array|binary|null
     */
    public function consultarDanfseNfse($chave)
    {
        $operacao = $this->getOperation('consultar_danfse_nfse_certificado');
        $retorno = $this->getData($operacao, null, 3);
        if(isset($retorno) and isset($retorno['sucesso']) and $retorno['sucesso']==true){
            $operacao = str_replace("{chave}", $chave, $this->getOperation('consultar_danfse_nfse_download'));
            $retorno = $this->getData($operacao, null, 3);
        }
        if (isset($retorno['erro'])) {
            return $retorno;
        }
        if ($retorno) {
            return $retorno;
        }
        return null;
    }

    public function enviaDps($content)
    {
        //$content = $this->canonize($content);
        $content = $this->sign($content, 'infDPS', '', 'DPS');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz = gzencode($content);
        $data = base64_encode($gz);
        $dados = $this->refactorFormatProvider($data, 1);
        $operacao = $this->getOperation('emitir_nfse');
        $retorno = $this->postData($operacao, json_encode($dados));
        return $retorno;
    }

    public function cancelaNfse($std)
    {
        $dps = new \Hadder\NfseNacional\Dps($std);
        $content = $dps->renderEvento($std);
        //$content = $this->canonize($content);
        $content = $this->sign($content, 'infPedReg', '', 'pedRegEvento');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz = gzencode($content);
        $data = base64_encode($gz);
        $dados = $this->refactorFormatProvider($data, 2);
        $operacao = str_replace("{chave}", $std->infPedReg->chNFSe, $this->getOperation('cancelar_nfse'));
        $retorno = $this->postData($operacao, json_encode($dados));
        return $retorno;
    }

    protected function canonize($content)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($content);
        dump($dom->saveXML());
        return $dom->C14N(false, false, null, null);
    }
}
