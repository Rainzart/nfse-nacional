<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional;

interface DpsInterface
{
    /**
     * Convert Dps::class data in XML.
     *
     * @return string
     */
    public function render();
}
