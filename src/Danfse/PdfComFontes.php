<?php

namespace QuantumTecnology\NfseNacional\Danfse;

use NFePHP\DA\Legacy\Pdf;

/**
 * PDF do DANFSe com as fontes distribuídas pelo próprio pacote.
 *
 * Why: o FPDF do sped-da resolve fontes por `__DIR__` — sempre de dentro do
 * `vendor/`. Instalar uma fonte lá funcionaria até o próximo `composer install`
 * apagar tudo. Como `getFontPath()` é `protected`, apontamos para o diretório
 * do pacote quando a fonte pedida for nossa, e devolvemos o caminho original do
 * FPDF caso contrário — assim as fontes core (times, helvetica, courier)
 * continuam funcionando.
 *
 * A resolução é por EXISTÊNCIA do arquivo, não por estado guardado entre
 * chamadas: `getFontPath()` é chamado em dois momentos distintos (ao incluir o
 * `.php` da fonte e, bem depois, ao embutir o `.z` no documento) e um controle
 * baseado em "última fonte pedida" quebraria no segundo.
 */
class PdfComFontes extends Pdf
{
    /**
     * Arquivo cuja localização está sendo resolvida no momento.
     * @var string|null
     */
    private $arquivoCorrente = null;

    /**
     * Escreve uma célula convertendo o texto para o encoding da fonte.
     *
     * Why: as fontes do FPDF (core ou geradas por MakeFont) trabalham em
     * cp1252/ISO-8859-1. Os dados vindos do XML já são convertidos no parse,
     * mas os RÓTULOS são literais UTF-8 do código-fonte — sem esta conversão
     * eles apareceriam corrompidos, ou (como antes) teriam de ser escritos sem
     * acento, divergindo do documento oficial.
     *
     * @param float       $w
     * @param float       $h
     * @param string      $txt
     * @param mixed       $border
     * @param int         $ln
     * @param string      $align
     * @param bool        $fill
     * @param string      $link
     * @return void
     */
    public function cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        parent::cell($w, $h, self::paraEncodingDaFonte($txt), $border, $ln, $align, $fill, $link);
    }

    /**
     * @param float  $w
     * @param float  $h
     * @param string $txt
     * @param mixed  $border
     * @param string $align
     * @param bool   $fill
     * @return void
     */
    public function multicell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        parent::multicell($w, $h, self::paraEncodingDaFonte($txt), $border, $align, $fill);
    }

    /**
     * Converte UTF-8 para o encoding de byte único das fontes do FPDF.
     *
     * Texto que já esteja em ISO-8859-1 (caso dos dados convertidos no parse)
     * passa intacto — `mb_detect_encoding` com modo estrito evita a dupla
     * conversão, que é o que produz "UberlÃ¢ndia".
     *
     * @param string $texto
     * @return string
     */
    public static function paraEncodingDaFonte($texto)
    {
        if (!is_string($texto) || '' === $texto) {
            return $texto;
        }

        if (!mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        // ASCII puro não precisa de conversão alguma.
        if (mb_check_encoding($texto, 'ASCII')) {
            return $texto;
        }

        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * @param string $family
     * @param string $style
     * @param string $file
     * @return void
     */
    public function addFont($family, $style = '', $file = '')
    {
        $anterior = $this->arquivoCorrente;

        $this->arquivoCorrente = '' !== $file
            ? $file
            : str_replace(' ', '', strtolower($family)) . strtolower($style) . '.php';

        try {
            parent::addFont($family, $style, $file);
        } finally {
            $this->arquivoCorrente = $anterior;
        }
    }

    /**
     * Diretório das fontes deste pacote.
     *
     * @return string
     */
    public static function diretorioDeFontes()
    {
        return __DIR__ . '/../../storage/fonts/';
    }

    /**
     * @return string
     */
    protected function getFontPath()
    {
        if (null !== $this->arquivoCorrente
            && is_file(self::diretorioDeFontes() . $this->arquivoCorrente)
        ) {
            return self::diretorioDeFontes();
        }

        return parent::getFontPath();
    }

    /**
     * Embute os arquivos de fonte no PDF.
     *
     * Sobrescrito só para informar qual arquivo está sendo lido a cada volta:
     * o laço original chama `getFontPath()` sem dizer de quem é o arquivo, e
     * sem isso o `.z` das fontes do pacote seria procurado no vendor.
     *
     * @return void
     */
    protected function putFonts()
    {
        $arquivos = array_keys($this->fontFiles);

        // Um único arquivo do pacote basta para o laço inteiro apontar para cá,
        // porque getFontPath() confere a existência de cada um individualmente.
        foreach ($arquivos as $arquivo) {
            if (is_file(self::diretorioDeFontes() . $arquivo)) {
                $this->arquivoCorrente = $arquivo;

                break;
            }
        }

        try {
            parent::putFonts();
        } finally {
            $this->arquivoCorrente = null;
        }
    }
}
