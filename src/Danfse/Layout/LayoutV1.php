<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Danfse\Layout;

use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;

/**
 * Layout histórico desta biblioteca.
 *
 * ATENÇÃO: este desenho NÃO é o modelo oficial de nenhuma versão do DANFSe. É o
 * layout que a lib produz desde que herdou o motor do sped-da, preservado
 * apenas para quem já depende dele (a aparência do documento muda, e mudar a
 * aparência de um documento fiscal sem aviso confunde quem o recebe).
 *
 * Para emitir hoje, use {@see LayoutV2} — o modelo do Anexo I da NT nº 008,
 * que é o padrão da biblioteca.
 *
 * As seções continuam implementadas na {@see AbstractDanfse}: este layout é o
 * recorte que preserva a ordem original de chamada, sem alterá-las.
 */
class LayoutV1 implements LayoutDanfseInterface
{
    /**
     * Desenha o documento no layout histórico.
     *
     * @param string|null $logo
     *
     * @return void
     */
    public function desenha(AbstractDanfse $danfse, $logo = null)
    {
        $danfse->desenhaSecoesV1($logo);
    }
}
