<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Danfse\Layout;

use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;

/**
 * Estratégia de desenho do DANFSe.
 *
 * O documento tem HOJE duas representações gráficas válidas, e elas não são
 * variações estéticas do mesmo desenho — mudam blocos, ordem e campos:
 *
 *  - {@see LayoutV1} o layout histórico desta biblioteca (herdado do sped-da);
 *  - {@see LayoutV2} o modelo oficial do Anexo I da NT nº 008 v1.02.
 *
 * Why: separar "desenhar" de "ser um DANFSe" era o único jeito de ter as duas
 * versões sem multiplicar a hierarquia. As subclasses concretas
 * ({@see \QuantumTecnology\NfseNacional\Danfse} e
 * {@see \QuantumTecnology\NfseNacional\DanfseSimples}) já usam a herança para
 * outro eixo — com ou sem QR/logo —, e cruzar os dois eixos por herança daria
 * quatro classes para duas decisões independentes.
 *
 * O parsing do XML continua inteiro em {@see AbstractDanfse}: um layout recebe
 * a nota já interpretada e só decide onde cada dado aparece na página.
 */
interface LayoutDanfseInterface
{
    /**
     * Desenha o documento inteiro no PDF já inicializado pela AbstractDanfse.
     *
     * @param AbstractDanfse $danfse Fonte dos dados e dos helpers de formatação
     * @param string|null    $logo   Logo informada em render($logo)
     *
     * @return void
     */
    public function desenha(AbstractDanfse $danfse, $logo = null);
}
