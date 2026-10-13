<?php

namespace QuantumTecnology\NfseNacional;

use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;

/**
 * DANFSe portátil — PHP puro, sem dependências opcionais.
 *
 * Desenha a marca "NFSe" em texto (não exige arquivo de imagem) e não gera o
 * QR Code, apenas sua moldura. Use quando não houver a extensão/pacote de QR
 * disponível; caso contrário prefira {@see Danfse}, que é o documento completo.
 *
 * Todo o resto — parsing e todas as seções — vem de {@see AbstractDanfse}.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
class DanfseSimples extends AbstractDanfse
{
    /**
     * Marca da NFS-e desenhada em texto, para não depender de arquivo externo.
     *
     * Quando o layout informa uma caixa (v2), o texto é reduzido para caber no
     * espaço que a NT reserva à logomarca; sem caixa (v1), mantém o tamanho
     * histórico.
     *
     * @param float       $x
     * @param float       $y
     * @param string|null $logo    Ignorado nesta versão
     * @param float|null  $largura Largura da caixa, em mm
     * @param float|null  $altura  Altura da caixa, em mm
     * @return void
     */
    protected function renderMarcaNfse($x, $y, $logo = null, $largura = null, $altura = null)
    {
        $temCaixa = null !== $largura && null !== $altura;

        $marcaX = $temCaixa ? $x : $x + 2;
        $marcaW = $temCaixa ? $largura : 30;

        $this->pdf->setFont($this->fontePadrao, 'B', $temCaixa ? 11 : 20);
        $this->pdf->setXY($marcaX, $temCaixa ? $y : $y + 3);
        $this->pdf->setTextColor(0, 128, 0); // Verde
        $this->pdf->cell($marcaW, $temCaixa ? 4 : 8, 'NFSe', 0, 0, 'L');

        $this->pdf->setFont($this->fontePadrao, '', $temCaixa ? 5 : 7);
        $this->pdf->setTextColor(0, 0, 0);
        $this->pdf->setXY($marcaX, $temCaixa ? $y + 4 : $y + 10);
        $this->pdf->cell($marcaW, 3, 'Nota Fiscal de', 0, 1, 'L');
        $this->pdf->setX($marcaX);
        $this->pdf->cell($marcaW, 3, 'Serviço eletrônico', 0, 0, 'L');
    }
}
