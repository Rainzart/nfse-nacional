<?php

declare(strict_types = 1);

namespace Hadder\NfseNacional;

interface DpsInterface
{
    /**
     * Convert Dps::class data in XML.
     *
     * @return string
     */
    public function render();
}
