<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use QuantumTecnology\NfseNacional\Tools;

/**
 * Duplo de Tools que exercita a validação de schema sem certificado nem rede.
 *
 * Sobrescreve o construtor (que exigiria um Certificate real), a assinatura e o
 * POST, registrando apenas se o fluxo chegou até lá. O que interessa testar é o
 * ponto de corte: XML inválido não pode ser assinado nem transmitido.
 */
final class ToolsSpy extends Tools
{
    public bool $assinou = false;

    public bool $transmitiu = false;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        // Sem parent::__construct: aqui não há certificado nem config.
    }

    public function versao(string $xml): ?string
    {
        return $this->versaoDoXml($xml);
    }

    public function sign(string $content, string $tagname, ?string $mark, $rootname)
    {
        $this->assinou = true;

        return $content;
    }

    public function getOperation($operation)
    {
        return 'https://exemplo.invalido/' . $operation;
    }

    public function postData($operacao, $data, $origem = 1)
    {
        $this->transmitiu = true;

        return ['ok' => true];
    }
}
