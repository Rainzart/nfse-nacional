# Fontes do DANFSe

`DejaVu Sans Condensed` (normal, negrito, itálico e negrito-itálico) no formato
de definição do FPDF: um `.php` com as métricas e um `.z` com a TTF comprimida.

## Por que a fonte mora aqui

As fontes core do FPDF (times, helvetica, courier) cobrem apenas o conjunto
básico, então os rótulos saíam sem acento — "TRIBUTACAO MUNICIPAL" onde o
documento oficial traz "TRIBUTAÇÃO MUNICIPAL".

O FPDF do `sped-da` resolve fontes por `__DIR__`, ou seja, sempre de dentro do
`vendor/`. Instalar a fonte lá funcionaria até o próximo `composer install`
apagar tudo — por isso ela é distribuída com o pacote e
`Danfse\PdfComFontes::getFontPath()` aponta para cá.

Escolhida a variante **Condensed** por ser a mais próxima da sans condensada
que o DANFSe oficial usa nos rótulos.

## Licença

DejaVu Fonts, licença permissiva (Bitstream Vera + Arev), em
`LICENSE-DejaVu.txt`. Redistribuição permitida, inclusive comercial.

## Encoding

As fontes são geradas em **cp1252**, não Unicode — é o que este FPDF suporta.
Cobre todo o português. A conversão de UTF-8 para cp1252/ISO-8859-1 acontece em
`PdfComFontes::paraEncodingDaFonte()`, aplicada em `cell()` e `multicell()`.

## Como regerar

O `makefont.php` que acompanha o `sped-da` é a versão antiga e só aceita AFM —
não lê TTF. As definições atuais foram geradas por um script que extrai as
métricas direto da TTF (tabelas `head`/`hhea`/`hmtx`/`cmap`/`post`/`OS/2`).

Ao gerar uma fonte nova, atenção a dois detalhes que o FPDF exige e que passam
despercebidos:

- **`$diff = '';`** precisa existir no `.php`. Sem ele o FPDF emite
  "Undefined variable $diff" a cada `addFont()`.
- **`$file`** deve ser o nome do `.z`, e **`$originalsize`** o tamanho da TTF
  antes da compressão — o FPDF usa ambos ao embutir a fonte.
