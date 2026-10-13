<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

use RuntimeException;

/**
 * XML fora do schema oficial, detectado antes de assinar e transmitir.
 *
 * Distinta de erro de rede/SEFAZ de propósito: quem integra precisa saber que
 * o problema está no payload, não na comunicação.
 */
class SchemaValidationException extends RuntimeException
{
    /**
     * @var string[]
     */
    private array $errors;

    /**
     * @param string[] $errors
     */
    public function __construct(string $documento, array $errors)
    {
        $this->errors = $errors;

        parent::__construct(sprintf(
            "XML do %s não está de acordo com o schema oficial:\n- %s",
            $documento,
            implode("\n- ", $errors)
        ));
    }

    /**
     * Erros do libxml, um por item.
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
