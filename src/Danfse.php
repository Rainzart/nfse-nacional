<?php

namespace QuantumTecnology\NfseNacional;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as QRCodeGenerator;
use chillerlan\QRCode\QROptions;
use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;
use Throwable;

/**
 * DANFSe completo — com a logo da NFS-e e o QR Code de consulta pública.
 *
 * Todo o conteúdo (parsing e seções) vem de {@see Danfse\AbstractDanfse}; aqui
 * ficam apenas a marca e o QR.
 *
 * O QR é gerado EM MEMÓRIA. A versão anterior dependia do Laravel
 * (`public_path()` + facade `LaravelQRCode`, nenhum dos dois declarado no
 * composer) e gravava um PNG por nota em `public/images/`, sem criar o
 * diretório nem limpar o arquivo depois — efeito colateral indesejado numa
 * biblioteca e motivo de a classe não funcionar fora de uma app Laravel.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2025 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3 or MIT
 * @author    Community Contribution
 */
class Danfse extends AbstractDanfse
{
    /**
     * Logo padrão da NFS-e, relativa à raiz do pacote.
     * @var string
     */
    protected $logoNfse = 'imgs/nfse_logo.png';

    /**
     * A mesma logo, achatada sobre o cinza 5% (RGB 242) do cabeçalho do v2.
     *
     * O FPDF não desenha PNG com transparência, então "transparente" aqui é a
     * marca oficial composta sobre a cor exata do fundo em que ela vai ficar.
     * Sem alfa no arquivo, e sem a caixa branca em volta.
     * @var string
     */
    protected $logoNfseSobreCinza = 'imgs/nfse_logo_cinza5.png';

    /**
     * Marca da NFS-e: imagem quando disponível, texto como alternativa.
     *
     * Sem caixa informada (layout v1), a marca é desenhada com a altura fixa
     * histórica, a 2mm da origem, e usa a logo de fundo branco. Com caixa
     * (layout v2), ela é ajustada para caber exatamente no espaço que a NT
     * reserva à logomarca, sem distorcer a proporção do arquivo, e usa a logo
     * de fundo cinza 5% — a cor do cabeçalho do v2.
     *
     * @param float       $x
     * @param float       $y
     * @param string|null $logo    Logo informada em render($logo)
     * @param float|null  $largura Largura da caixa, em mm
     * @param float|null  $altura  Altura da caixa, em mm
     * @return void
     */
    protected function renderMarcaNfse($x, $y, $logo = null, $largura = null, $altura = null)
    {
        $arquivo = $this->resolveLogo($logo, null !== $largura && null !== $altura);

        if (null === $arquivo) {
            // Sem imagem utilizável, cai no desenho em texto: um cabeçalho sem
            // marca alguma ficaria pior que a alternativa.
            $this->renderMarcaEmTexto($x, $y, $largura, $altura);

            return;
        }

        $dimensoes = @getimagesize($arquivo);

        if (false === $dimensoes || 0 === (int) $dimensoes[1]) {
            $this->renderMarcaEmTexto($x, $y, $largura, $altura);

            return;
        }

        $proporcao = $dimensoes[0] / $dimensoes[1];

        if (null !== $largura && null !== $altura) {
            // Encaixa na caixa preservando a proporção: limita pela altura e,
            // se ainda exceder a largura, limita pela largura.
            $destinoH = $altura;
            $destinoW = $destinoH * $proporcao;

            if ($destinoW > $largura) {
                $destinoW = $largura;
                $destinoH = $destinoW / $proporcao;
            }

            $destinoX = $x;
            $destinoY = $y + (($altura - $destinoH) / 2);
        } else {
            // Altura fixa; largura proporcional — assim a marca nunca invade o
            // título central, independentemente do arquivo informado.
            $destinoH = 12;
            $destinoW = min(round($destinoH * $proporcao, 0), 32);
            $destinoX = $x + 2;
            $destinoY = $y + 2;
        }

        try {
            $this->pdf->Image($arquivo, $destinoX, $destinoY, $destinoW, $destinoH);
        } catch (Throwable $e) {
            $this->renderMarcaEmTexto($x, $y, $largura, $altura);
        }
    }

    /**
     * Pinta o QR Code de consulta pública dentro da moldura.
     *
     * @param float $x
     * @param float $y
     * @param float $size
     * @return void
     */
    protected function desenhaQrCode($x, $y, $size)
    {
        $this->pdf->rect($x, $y, $size, $size);

        try {
            $png = (new QRCodeGenerator(new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'outputBase64'    => false,
                'scale'           => 6,
                'quietzoneSize'   => 1,
            ])))->render($this->urlConsultaPublica());
        } catch (Throwable $e) {
            // Sem QR o documento continua válido (a chave de acesso está
            // impressa); melhor a moldura vazia do que derrubar o PDF inteiro.
            return;
        }

        // O FPDF lê imagem de string via wrapper data:// — sem tocar em disco.
        $this->pdf->Image(
            'data://text/plain;base64,' . base64_encode($png),
            $x + 1,
            $y + 1,
            $size - 2,
            $size - 2,
            'png'
        );
    }

    /**
     * Caminho utilizável da logo: a informada, senão a padrão do pacote.
     *
     * @param string|null $logo
     * @param bool        $sobreCinza A marca vai sobre o cabeçalho cinza (v2)
     * @return string|null
     */
    private function resolveLogo($logo, $sobreCinza = false)
    {
        $padrao = __DIR__ . '/../' . ($sobreCinza ? $this->logoNfseSobreCinza : $this->logoNfse);

        foreach ([$this->logoPersonalizada($logo), $padrao] as $candidato) {
            if (null !== $candidato && is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Logo do integrador (render($logo) ou $logomarca), se houver arquivo.
     *
     * @param string|null $logo
     * @return string|null
     */
    private function logoPersonalizada($logo)
    {
        foreach ([$logo, $this->logomarca] as $candidato) {
            if (is_string($candidato) && '' !== $candidato && is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * A logo do integrador é um arquivo opaco de fundo desconhecido (quase
     * sempre branco): sobre o cinza viraria um retângulo destacado. A oficial
     * do pacote tem uma versão no cinza exato do cabeçalho.
     */
    public function marcaAceitaFundoCinza($logo = null)
    {
        return null === $this->logoPersonalizada($logo);
    }

    /**
     * Marca em texto, usada quando não há imagem utilizável.
     *
     * Com caixa informada (layout v2), o desenho é reduzido para caber no
     * espaço que a NT reserva à logomarca — caso contrário o texto invadiria o
     * bloco da chave de acesso, logo abaixo.
     *
     * @param float      $x
     * @param float      $y
     * @param float|null $largura Largura da caixa, em mm
     * @param float|null $altura  Altura da caixa, em mm
     * @return void
     */
    private function renderMarcaEmTexto($x, $y, $largura = null, $altura = null)
    {
        $temCaixa = null !== $largura && null !== $altura;

        $marcaX = $temCaixa ? $x : $x + 2;
        $marcaW = $temCaixa ? $largura : 30;

        $this->pdf->setFont($this->fontePadrao, 'B', $temCaixa ? 10 : 20);
        $this->pdf->setXY($marcaX, $temCaixa ? $y : $y + 3);
        $this->pdf->setTextColor(0, 128, 0); // Verde
        $this->pdf->cell($marcaW, $temCaixa ? 3.4 : 8, 'NFSe', 0, 0, 'L');

        $this->pdf->setTextColor(0, 0, 0);

        if ($temCaixa) {
            // Na caixa da NT (8,5mm de altura) só há espaço para uma linha.
            $this->pdf->setFont($this->fontePadrao, '', 4.5);
            $this->pdf->setXY($marcaX, $y + 3.6);
            $this->pdf->cell($marcaW, 2, 'Nota Fiscal de Serviço eletrônica', 0, 0, 'L');

            return;
        }

        $this->pdf->setFont($this->fontePadrao, '', 7);
        $this->pdf->setXY($marcaX, $y + 10);
        $this->pdf->cell($marcaW, 3, 'Nota Fiscal de', 0, 1, 'L');
        $this->pdf->setX($marcaX);
        $this->pdf->cell($marcaW, 3, 'Serviço eletrônico', 0, 0, 'L');
    }
}
