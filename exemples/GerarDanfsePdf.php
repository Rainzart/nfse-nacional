<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Hadder\NfseNacional\Danfse\DanfsePdf;

$xmlPath = $argv[1] ?? __DIR__ . '/../storage/nfse.xml';
$pdfPath = $argv[2] ?? __DIR__ . '/../storage/danfse.pdf';

$xml = file_get_contents($xmlPath);

if ($xml === false) {
    throw new RuntimeException('Não foi possível ler o XML: ' . $xmlPath);
}

(new DanfsePdf())->saveFromXml($xml, $pdfPath, 'Emitido por XXX');

echo "DANFSe gerado em: {$pdfPath}" . PHP_EOL;
