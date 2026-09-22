<?php

namespace Hadder\NfseNacional\Danfse;

/**
 * Classe base abstrata para impressão de documentos auxiliares da NFS-e.
 *
 * Equivalente standalone ao NFePHP\DA\Common\DaCommon, sem depender de
 * nfephp-org/sped-da. Provê o ciclo printParameters → logoParameters →
 * render → monta usado por todos os DA-* do ecossistema NFePHP.
 */
abstract class DanfseCommon
{
    protected ?Pdf $pdf = null;
    protected string $orientacao = 'P';
    protected string $papel = 'A4';
    protected float $margsup = 1.5;
    protected float $margesq = 1.5;
    protected float $padInterno = 1.5;   // padding lateral entre moldura e conteúdo
    protected float $maxW = 210;
    protected float $maxH = 297;
    protected bool $debug = false;
    protected ?string $logo = null;
    protected ?string $logoAlign = 'L';
    protected ?string $creditMessage = null;
    protected bool $creditPowered = false;

    public function printParameters(
        string $orientacao = 'P',
        string $papel = 'A4',
        float $margSup = 1.5,
        float $margEsq = 1.5
    ): self {
        $this->orientacao = $orientacao;
        $this->papel = $papel;
        $this->margsup = $margSup;
        $this->margesq = $margEsq;
        return $this;
    }

    public function logoParameters(?string $logo, ?string $align = 'L'): self
    {
        $this->logo = $logo;
        $this->logoAlign = $align;
        return $this;
    }

    /** Define o padding lateral (mm) entre a moldura externa e o conteúdo. */
    public function margemInternaParameters(float $mm): self
    {
        $this->padInterno = max(0.0, $mm);
        return $this;
    }

    public function debugMode(bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function creditsIntegratorFooter(string $message, bool $powered = false): self
    {
        $this->creditMessage = $message;
        $this->creditPowered = $powered;
        return $this;
    }

    /**
     * Retorna o binário do PDF gerado.
     */
    public function render(): string
    {
        if ($this->pdf === null) {
            $this->monta($this->logo ?? '');
        }
        return $this->pdf->Output('S');
    }

    /**
     * Implementação concreta nas subclasses (orquestra blocos do documento).
     */
    abstract protected function monta($logo = ''): void;
}
