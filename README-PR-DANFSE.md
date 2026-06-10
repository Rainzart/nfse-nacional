# PR: Geração Local do DANFSe em PDF

Esta proposta adiciona geração local do DANFSe a partir do XML autorizado da NFS-e Nacional.

## Motivo da abordagem

Geração local do PDF, sem a dependência dos serviços ora utilizados e que serão descontinuados em breve período.

## Arquivos adicionados

```txt
src/Danfse/DanfsePdf.php
src/Danfse/DanfseXmlData.php
src/Danfse/DanfseTemplate.php
src/Danfse/assets/logo\_nfse\_horizontal.png
exemples/GerarDanfsePdf.php
```

## Uso

```php
use Hadder\\NfseNacional\\Danfse\\DanfsePdf;

$xml = file\_get\_contents('nfse.xml');
$pdf = (new DanfsePdf())->renderFromXml($xml);

file\_put\_contents('danfse.pdf', $pdf);
```

Ou diretamente:

```php
(new DanfsePdf())->saveFromXml($xml, 'danfse.pdf', 'Emitido por XXX');
```

## Comando de exemplo

```bash
php exemples/GerarDanfsePdf.php storage/nfse.xml storage/danfse.pdf 'Emitido por XXX'
```

## Observações

* O parser usa `DOMXPath` com `local-name()` para tolerar namespaces diferentes do XML da NFS-e Nacional.
* O QR Code é gerado pelo próprio TCPDF quando houver URL de consulta.
* Há um pouco de "lixo" no código, devido a testes e ajustes, mas nada que impacte negativamente no uso.
* O layout é funcional e pode ser refinado visualmente depois, mas já separa prestador, tomador, serviço, valores, chave e consulta.
* Não altera os serviços existentes, então o risco da PR é baixo.

