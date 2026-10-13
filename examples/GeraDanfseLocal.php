<?php

declare(strict_types = 1);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

use QuantumTecnology\NfseNacional\DanfseSimples;
use QuantumTecnology\NfseNacional\Tools;

/*
 * DANFSe com FALLBACK: oficial do ADN e, se falhar, gerada localmente.
 *
 * É assim que se usa em produção. O endpoint `danfse/{chave}` do Ambiente
 * Nacional é intermitente: com a MESMA chave válida ele ora devolve o PDF,
 * ora volta vazio. Esse vazio é indisponibilidade temporária — NÃO significa
 * "nota inexistente" — então vale tentar de novo antes de renderizar local.
 *
 * Estratégia:
 *   1. Tenta o PDF oficial via consultarDanfse(), com algumas tentativas.
 *   2. Falhou? Obtém o XML autorizado e gera o PDF localmente.
 *
 * O PDF local é visualmente equivalente, mas não é o documento oficial —
 * convém registrar qual origem foi usada.
 */

/**
 * Tenta o canal oficial. Devolve null em qualquer falha: o caller decide o
 * fallback.
 */
function baixarDanfseOficial(Tools $tools, string $chave, int $tentativas = 3): ?string
{
    // O endpoint exige a chave de 50 posições, sem prefixo. A SEFAZ devolve
    // `chaveAcesso` com o prefixo "NFS" (decoração do Id do XML) e, com ele,
    // a consulta volta vazia — um erro silencioso clássico. Não usar \D: com
    // CNPJ alfanumérico a chave tem letras, e \D as apagaria.
    $limpa = preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/^NFS/i', '', $chave) ?? '')) ?? '';
    $chave = 50 === mb_strlen($limpa) ? $limpa : $chave;

    for ($tentativa = 1; $tentativa <= $tentativas; ++$tentativa) {
        try {
            $retorno = $tools->consultarDanfse($chave);
        } catch (Exception $e) {
            // Falha dura (config, certificado, rede): não adianta repetir.
            echo '  ADN falhou: ', $e->getMessage(), PHP_EOL;

            return null;
        }

        // Confere a assinatura do PDF: o endpoint pode devolver JSON de erro.
        if (is_string($retorno) && str_starts_with($retorno, '%PDF')) {
            return $retorno;
        }

        // Vazio = instabilidade. Espera progressiva e tenta de novo.
        if ($tentativa < $tentativas) {
            usleep(300_000 * $tentativa);
        }
    }

    echo '  ADN indisponível após ', $tentativas, ' tentativa(s).', PHP_EOL;

    return null;
}

try {
    $config        = new stdClass();
    $config->tpamb = 2; // 1 - Produção, 2 - Homologação
    $configJson    = json_encode($config);

    $cert  = NFePHP\Common\Certificate::readPfx(file_get_contents('certificado.pfx'), 'senha_certificado');
    $tools = new Tools($configJson, $cert);

    $chave = '00000000000000000000000000000000000000000000000000';

    // --- 1. Canal oficial --------------------------------------------------
    echo 'Tentando DANFSe oficial no ADN...', PHP_EOL;
    $pdf    = baixarDanfseOficial($tools, $chave);
    $origem = 'oficial';

    // --- 2. Fallback local -------------------------------------------------
    if (null === $pdf) {
        echo 'Gerando DANFSe localmente a partir do XML...', PHP_EOL;
        $origem = 'local';

        // O XML autorizado: do seu storage, ou reconsultando a SEFAZ.
        $xml = $tools->consultarNfseChave($chave, encoding: false);

        if (!is_string($xml) || !str_contains($xml, '<NFSe')) {
            throw new RuntimeException('XML da NFS-e indisponível para o render local.');
        }

        $danfse = new DanfseSimples($xml, 'P'); // 'P' retrato, 'L' paisagem

        if ($danfse->hasErrors()) {
            // Aqui getErrors() devolve uma string (diferente do Dps::getErrors()).
            throw new RuntimeException('XML inválido para a DANFSe: ' . $danfse->getErrors());
        }

        // O renderer (sped-da/Fpdf) emite warnings em campos opcionais ausentes.
        // Em frameworks que convertem warning em exception (Laravel, por ex.)
        // isso aborta o render — suprima apenas durante a chamada.
        set_error_handler(static fn (): bool => true, E_WARNING | E_NOTICE | E_DEPRECATED);

        try {
            // render() vem do DaCommon (sped-da) e devolve o binário do PDF.
            // Aceita o caminho de um logo como argumento opcional.
            $pdf = $danfse->render();
        } finally {
            restore_error_handler();
        }
    }

    file_put_contents('danfse.pdf', $pdf);
    echo 'Gerado: danfse.pdf (origem: ', $origem, ')', PHP_EOL;

    /*
    | Sobre a classe Danfse (completa, com logo e QR Code):
    |
    |   new Danfse($xml)->render($caminhoDoLogo);
    |
    | ATENÇÃO: hoje ela depende de helpers do Laravel (public_path(), Str) e de
    | um pacote de QR Code que NÃO estão declarados no composer.json — só
    | funciona dentro de uma aplicação Laravel. Em PHP puro, use DanfseSimples.
    */
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
