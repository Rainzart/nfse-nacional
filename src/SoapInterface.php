<?php

namespace Hadder\NfseNacional;

interface SoapInterface
{
    /**
     * Convert Soap::class data in XML
     * @return string
     */
    public function render();
}
