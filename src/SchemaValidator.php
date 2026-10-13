<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

use DOMDocument;
use InvalidArgumentException;

/**
 * Validação do XML contra os XSDs oficiais da NFS-e nacional.
 *
 * Espelha o que o sped-nfe faz em NFePHP\Common\Validator: valida antes de
 * assinar e transmitir, para que um XML fora do schema seja apontado com o
 * elemento exato — em vez de virar uma rejeição genérica da SEFAZ (L2103).
 *
 * Degrada com elegância: se o XSD da versão não estiver presente, considera
 * válido em vez de travar a emissão (mesma decisão do sped-nfe).
 */
class SchemaValidator
{
    /**
     * Diretório dos XSDs oficiais.
     */
    public static function pathSchemes(): string
    {
        return __DIR__ . '/../storage/schemes/';
    }

    /**
     * Caminho do XSD de um documento numa dada versão.
     *
     * Uma pasta por versão, como no pacote oficial (Schemas/1.00, Schemas/1.01):
     * as duas versões trazem arquivos de mesmo nome e conteúdo diferente
     * (CNC_v1.00, tiposCnc_v1.00, xmldsig-core-schema), que numa pasta só se
     * sobrescreveriam.
     *
     * @param string $documento Nome do XSD sem versão: 'DPS', 'pedRegEvento', 'NFSe'
     */
    public static function schemaPath(string $documento, string $versao = '1.01'): string
    {
        return self::pathSchemes() . $versao . '/' . $documento . '_v' . $versao . '.xsd';
    }

    /**
     * Valida um XML contra o XSD e devolve os erros encontrados.
     *
     * Retorna array vazio quando o XML é válido — ou quando o XSD da versão não
     * existe no pacote, caso em que não há como afirmar que é inválido.
     *
     * @return string[] mensagens do libxml, já limpas
     */
    public static function errors(string $xml, string $documento, string $versao = '1.01'): array
    {
        if ('' === trim($xml)) {
            throw new InvalidArgumentException('XML vazio: nada a validar.');
        }

        $schema = self::schemaPath($documento, $versao);

        // Sem o XSD da versão, não trava a emissão.
        if (!is_file($schema)) {
            return [];
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom                     = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;

        if (!$dom->loadXML($xml)) {
            $erros = self::coleta();
            libxml_use_internal_errors($anterior);

            return $erros ?: ['XML mal formado.'];
        }

        $dom->schemaValidate($schema);
        $erros = self::coleta();

        libxml_use_internal_errors($anterior);

        return $erros;
    }

    /**
     * @return string[]
     */
    private static function coleta(): array
    {
        $erros = [];

        foreach (libxml_get_errors() as $erro) {
            $msg = trim($erro->message);

            // As mensagens do libxml vêm com o namespace completo repetido em
            // cada elemento, o que polui o log sem acrescentar informação.
            $msg = str_replace('{http://www.sped.fazenda.gov.br/nfse}', '', $msg);

            if ('' !== $msg) {
                $erros[] = 0 === $erro->line
                    ? $msg
                    : sprintf('%s (linha %d)', $msg, $erro->line);
            }
        }

        libxml_clear_errors();

        return $erros;
    }
}
