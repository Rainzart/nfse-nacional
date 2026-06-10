## Descrição

Adiciona geração local do DANFSe em PDF a partir do XML autorizado da NFS-e Nacional.

## O que foi feito

* Criação de `Hadder\\NfseNacional\\Danfse\\DanfsePdf` para renderizar/salvar PDF.
* Criação de `Hadder\\NfseNacional\\Danfse\\DanfseXmlData` para extrair os principais dados do XML.
* Criação de `Hadder\\NfseNacional\\Danfse\\DanfseTemplate` com layout HTML compatível com TCPDF.
* Exemplo de uso em `exemples/GerarDanfsePdf.php`.

## Dependências

Nenhuma dependência nova foi adicionada. A implementação usa `tecnickcom/tcpdf`, que já existe no `composer.json` atual do projeto.

## Exemplo

```php
use Hadder\\NfseNacional\\Danfse\\DanfsePdf;

$xml = file\_get\_contents('nfse.xml');
$pdf = (new DanfsePdf())->renderFromXml($xml);
file\_put\_contents('danfse.pdf', $pdf);
```

## Checklist

* \[x] Usa TCPDF já presente no projeto.
* \[x] Mantém compatibilidade com PSR-4 atual: `Hadder\\NfseNacional\\`.

