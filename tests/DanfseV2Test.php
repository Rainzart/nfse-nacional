<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Danfse;
use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;
use QuantumTecnology\NfseNacional\DanfseSimples;
use Smalot\PdfParser\Parser;

/**
 * DANFSe v2.0 — o modelo oficial do Anexo I da Nota Técnica nº 008 v1.02.
 *
 * A referência aqui é a NT, não o comportamento da lib: cada asserção aponta o
 * item do documento que a exige. Como em {@see DanfseTest}, asseveramos o TEXTO
 * extraído do PDF — o que protege a informação fiscal. Posição e proporção dos
 * blocos exigem conferência visual contra o Anexo I; nenhum teste de texto pega
 * bloco fora de lugar.
 *
 * As duas fixtures são notas reais autorizadas:
 *  - Uberlândia/MG — COM o grupo IBS/CBS preenchido
 *  - Americana/SP  — SEM o grupo (o caso que ainda é maioria em 2026)
 */
final class DanfseV2Test extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Seleção da versão do layout
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function usaOLayoutV2PorPadrao(): void
    {
        // A API de geração do DANFSe do Ambiente Nacional foi suspensa em
        // 03/08/2026 (item 1 da NT): o render local passou a ser o documento
        // que o contribuinte entrega, então o padrão tem de ser o modelo atual.
        $texto = $this->texto('nfse_autorizada_uberlandia.xml');

        $this->assertStringContainsString('DANFSe v2.0', $texto);
        $this->assertStringNotContainsString('DANFSe v1.0', $texto);
    }

    #[Test]
    public function mantemOLayoutV1QuandoPedido(): void
    {
        $texto = $this->texto('nfse_autorizada_uberlandia.xml', AbstractDanfse::LAYOUT_V1);

        $this->assertStringContainsString('DANFSe v1.0', $texto);
        $this->assertStringNotContainsString('DANFSe v2.0', $texto);
    }

    #[Test]
    public function versaoDesconhecidaCaiNoPadraoSemLancar(): void
    {
        // setLayout() está no caminho de geração de um documento fiscal: ficar
        // sem PDF é pior que receber o layout vigente.
        $texto = $this->texto('nfse_autorizada_uberlandia.xml', '9.9');

        $this->assertStringContainsString('DANFSe v2.0', $texto);
    }

    /*
    |--------------------------------------------------------------------------
    | Formulário (item 2.2)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function oDocumentoCabeEmUmaUnicaPagina(): void
    {
        // "O DANFSe deverá ser impresso, obrigatoriamente, em uma única página"
        // (item 2.2). É o requisito mais fácil de violar sem perceber, porque
        // um campo que cresce empurra o resto para a página 2 em silêncio.
        foreach ([
            'nfse_autorizada_uberlandia.xml',
            'nfse_autorizada_americana_sem_ibscbs.xml',
        ] as $fixture) {
            $this->assertCount(
                1,
                $this->paginas($fixture),
                "A NFS-e de {$fixture} passou de uma página.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Blocos que o v2.0 introduz
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function trazOsBlocosDoModeloOficial(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        foreach ([
            'PRESTADOR / FORNECEDOR',
            'TOMADOR / ADQUIRENTE',
            'SERVIÇO PRESTADO',
            'TRIBUTAÇÃO MUNICIPAL (ISSQN)',
            'TRIBUTAÇÃO FEDERAL (EXCETO CBS)',
            'TRIBUTAÇÃO IBS / CBS',
            'VALOR TOTAL DA NFS-E',
            'INFORMAÇÕES COMPLEMENTARES',
        ] as $bloco) {
            $this->assertStringContainsString($bloco, $texto, "Bloco ausente: {$bloco}");
        }
    }

    #[Test]
    public function declaraODestinatarioDaOperacaoQueOV1NaoTinha(): void
    {
        // Bloco próprio no v2.0 (item 2.1.5). Nesta nota ele não está
        // preenchido, e a NT (nota 2) manda declarar a ausência.
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        $this->assertStringContainsString(
            'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            $texto,
        );
    }

    #[Test]
    public function imprimeOTotalComIbsCbsExigidoPeloModelo(): void
    {
        // "VALOR LÍQUIDO DA NFS-e + IBS/CBS" (item 2.1.11) não existe no v1.
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        $this->assertStringContainsString('VALOR LÍQUIDO DA NFS-e + IBS/CBS', $texto);
        $this->assertStringContainsString('Total do IBS/CBS', $texto);
        $this->assertStringContainsString('Total das Retenções', $texto);
    }

    /*
    |--------------------------------------------------------------------------
    | Tributação IBS/CBS (item 2.1.10)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function imprimeAsAliquotasEValoresDeIbsCbsDoXml(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        // Valores lidos da fixture, não do que a lib produz hoje:
        // pIBSUF 0,10 · pCBS 0,90 · vIBSTot 0,43 · vCBS 3,86 · vBC 428,35
        $this->assertStringContainsString('428,35', $texto, 'BC após exclusões e reduções (vBC).');
        $this->assertStringContainsString('0,43', $texto, 'Valor total apurado do IBS (vIBSTot).');
        $this->assertStringContainsString('3,86', $texto, 'Valor total apurado da CBS (vCBS).');
    }

    #[Test]
    public function trazOsCamposNovosDaTributacaoIbsCbs(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        foreach ([
            'CST / cClassTrib',
            'Indicador de Operação',
            'Exclusões e Reduções da Base de Cálculo',
            'Base de Cálculo Após Exclusões e Reduções',
            'Alíq. Efetiva Municipal',
            'Alíq. Efetiva Estadual',
            'Alíquota Efetiva - CBS',
        ] as $campo) {
            $this->assertStringContainsString($campo, $texto, "Campo ausente: {$campo}");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Supressões (item 2.3 e notas 2 a 4)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function usaAsFrasesDaNtQuandoOBlocoNaoSeAplica(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        // O bloco não some: a NT exige declarar a ausência (nota 2). Suprimir em
        // silêncio deixaria o leitor sem saber se o dado falta ou não se aplica.
        $this->assertStringContainsString('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $texto);
        $this->assertStringContainsString('INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $texto);
    }

    #[Test]
    public function blocosSuprimidosEmSequenciaNaoSeSobrepoem(): void
    {
        // Regressão: sem tomador (nota 2), o desconto de altura somava a faixa
        // de título DUAS vezes e o destinatário subia exatamente para cima da
        // faixa do tomador — "TOMADOR..." e "DESTINATÁRIO..." saíam uma sobre
        // a outra. Texto extraído não pega isso: aqui medimos a POSIÇÃO.
        $xml = (string) preg_replace('#<toma>.*?</toma>#s', '', $this->xml('nfse_autorizada_americana_sem_ibscbs.xml'));

        $y = $this->alturasDasFrases($xml, [
            'tomador'       => 'TOMADOR/ADQUIRENTE DA OPERA',
            'destinatario'  => 'DESTINAT',
            'intermediario' => 'INTERMEDI',
        ]);

        // A faixa suprimida tem 0,45cm (≈12,8pt). Entre uma frase e a seguinte
        // tem de caber a faixa inteira — e não mais que um bloco de sobra.
        $faixa = 4.5 * 72 / 25.4;

        $this->assertEqualsWithDelta($faixa, $y['tomador'] - $y['destinatario'], 1.0, 'Tomador e destinatário sobrepostos ou afastados.');
        $this->assertEqualsWithDelta($faixa, $y['destinatario'] - $y['intermediario'], 1.0, 'Destinatário e intermediário sobrepostos ou afastados.');
    }

    /**
     * Y (pt, origem embaixo) da primeira ocorrência de cada frase na página 1.
     *
     * @param array<string, string> $frases
     *
     * @return array<string, float>
     */
    private function alturasDasFrases(string $xml, array $frases): array
    {
        $nivelAnterior = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        try {
            $pdf = (new DanfseSimples($xml))->render();
        } finally {
            error_reporting($nivelAnterior);
        }

        // O smalot não extrai posição de PDF do FPDF (getDataTm quebra). Lemos
        // direto do fluxo de conteúdo: o FPDF escreve "BT x y Td (texto) Tj ET".
        $conteudo = '';

        preg_match_all('#stream?
(.*?)?
endstream#s', $pdf, $fluxos);

        foreach ($fluxos[1] as $fluxo) {
            $conteudo .= (@gzuncompress($fluxo) ?: $fluxo) . "
";
        }

        preg_match_all('#BT ([\d.]+) ([\d.]+) Td \((.*?)\) Tj ET#s', $conteudo, $textos, PREG_SET_ORDER);

        $y = [];

        foreach ($frases as $chave => $frase) {
            foreach ($textos as $t) {
                if (str_contains($t[3], $frase) && str_contains($t[3], 'IDENTIFICADO')) {
                    $y[$chave] = (float) $t[2];

                    break;
                }
            }

            $this->assertArrayHasKey($chave, $y, "Frase não encontrada no PDF: {$frase}");
        }

        return $y;
    }

    /*
    |--------------------------------------------------------------------------
    | Conteúdo dos campos (item 2.4.5)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function naoPerdeADescricaoDoServicoAcentuada(): void
    {
        // Regressão: a normalização de espaços com o modificador /u devolvia
        // NULL para texto em ISO-8859-1 (o encoding do parse), e a descrição
        // inteira sumia do documento — sem erro algum.
        $texto = $this->texto('nfse_autorizada_americana_sem_ibscbs.xml');

        $this->assertStringContainsString('Aviso Prévio', $texto);
        $this->assertStringContainsString('17.551,49', $texto);
    }

    #[Test]
    public function imprimeAChaveDeAcessoSemOPrefixoNfs(): void
    {
        // "Informar o id da NFS-e sem o prefixo NFS" (item 2.4.5).
        $texto = $this->texto('nfse_autorizada_uberlandia.xml');

        $this->assertStringContainsString(
            '31702062211222333000181000000000126070033869468',
            $texto,
        );
        $this->assertStringNotContainsString('NFS31702062', $texto);
    }

    #[Test]
    public function preencheCampoSemInformacaoComTraco(): void
    {
        // Nota 12: "Os campos sem informações no XML devem ser preenchidos com
        // um traço (-)" — célula em branco deixa dúvida se o dado faltou.
        $texto = $this->texto('nfse_autorizada_uberlandia.xml');

        $this->assertStringContainsString('-', $texto);
    }

    #[Test]
    public function traduzOsCodigosDoLeiauteParaAsDescricoes(): void
    {
        // O XML guarda códigos; o DANFSe imprime a descrição deles (item 2.4.5,
        // coluna "Outros Campos / Observações"). Imprimir "1" em vez de
        // "Prestador" deixaria o documento ilegível para quem o recebe.
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        $this->assertStringContainsString('Prestador', $texto, 'tpEmit.');
        $this->assertStringContainsString('NFS-e regular', $texto, 'cStat/finNFSe.');
        $this->assertStringContainsString('Operação tributável', $texto, 'tribISSQN.');
        $this->assertStringContainsString('Não Optante', $texto, 'opSimpNac.');
    }

    #[Test]
    public function distribuiALogomarcaOficialQueOFpdfConsegueDesenhar(): void
    {
        // O código procura a logo em imgs/nfse_logo.png desde a 3.3, mas o
        // arquivo não existia — e o cabeçalho caía no desenho em texto.
        $logo = __DIR__ . '/../imgs/nfse_logo.png';

        $this->assertFileExists($logo, 'A logomarca oficial da NFS-e deve vir no pacote.');

        // O FPDF lança "Alpha channel not supported" em PNG com transparência, e
        // o renderizador engole a exceção caindo no texto — sem erro nenhum para
        // investigar. Este teste é o que denuncia a troca por uma imagem com alfa.
        $info = getimagesize($logo);

        $this->assertIsArray($info);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);

        $canal = ord(file_get_contents($logo, false, null, 25, 1));

        $this->assertNotContains(
            $canal,
            [4, 6],
            'PNG com canal alfa: o FPDF não desenha, e a logo some em silêncio.',
        );
    }

    #[Test]
    public function distribuiALogomarcaJaCompostaSobreOCinzaDoCabecalho(): void
    {
        // O cabeçalho do v2 é cinza 5% (RGB 242). Com a logo de fundo branco,
        // a faixa dela destoava do resto do cabeçalho; a versão do v2 é a marca
        // oficial transparente achatada sobre esse cinza exato.
        $logo = __DIR__ . '/../imgs/nfse_logo_cinza5.png';

        $this->assertFileExists($logo);
        $this->assertNotContains(
            ord(file_get_contents($logo, false, null, 25, 1)),
            [4, 6],
            'PNG com canal alfa: o FPDF não desenha, e a logo some em silêncio.',
        );

        if (!extension_loaded('gd')) {
            $this->markTestIncomplete('Sem GD não dá para conferir a cor do fundo.');
        }

        $imagem = imagecreatefrompng($logo);
        $canto  = imagecolorsforindex($imagem, imagecolorat($imagem, imagesx($imagem) - 1, 0));

        $this->assertSame(
            [242, 242, 242],
            [$canto['red'], $canto['green'], $canto['blue']],
            'O fundo da logo tem de ser o CINZA_5 do LayoutV2, senão a faixa volta a destoar.',
        );
    }

    #[Test]
    public function soAMarcaOficialRecebeOFundoCinzaPorBaixo(): void
    {
        $xml = $this->xml('nfse_autorizada_uberlandia.xml');

        // Marca em texto não pinta fundo; a oficial tem versão no cinza.
        $this->assertTrue((new DanfseSimples($xml))->marcaAceitaFundoCinza());
        $this->assertTrue((new Danfse($xml))->marcaAceitaFundoCinza());

        // A do integrador traz fundo próprio: a faixa dela continua branca.
        $personalizada = __DIR__ . '/../imgs/nfse_logo.png';
        $this->assertFalse((new Danfse($xml))->marcaAceitaFundoCinza($personalizada));
    }

    #[Test]
    public function oV2DesenhaALogoEmImagemENaoCaiNoTexto(): void
    {
        $nivelAnterior = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        try {
            $pdf = (new Danfse($this->xml('nfse_autorizada_uberlandia.xml')))->render();
        } finally {
            error_reporting($nivelAnterior);
        }

        // A logo (1920 px de largura) só entra no PDF como imagem se o FPDF
        // conseguiu ler o arquivo; o fallback em texto não gera XObject.
        $this->assertStringContainsString('/Width 1920', $pdf);
    }

    #[Test]
    public function resolveOCodigoIbgeNoCabecalhoQuandoNaoHaNomeDoMunicipio(): void
    {
        // `xLocEmi` só existe no leiaute NACIONAL. Em XML ABRASF ele não vem, e
        // o cabeçalho saía como "Município: 3501608" — código cru, que não diz
        // nada a quem recebe a nota. A tabela do IBGE já vem no pacote.
        $danfse = new DanfseSimples($this->xml('nfse_autorizada_americana_sem_ibscbs.xml'));

        $this->assertStringContainsString(
            'Americana',
            $danfse->formatarMunicipioNoLayout('3501608'),
        );
    }

    #[Test]
    public function formataCnpjAlfanumericoSemPerderAsLetras(): void
    {
        // Antes a limpeza era [^0-9]: "12ABC34501DE35" virava "123450135" e o
        // DANFSe imprimia um documento que não é o do emitente.
        $danfse = new DanfseSimples($this->xml('nfse_autorizada_americana_sem_ibscbs.xml'));

        $this->assertSame('12.ABC.345/01DE-35', $danfse->formatarDocumentoNoLayout('12.abc.345/01de-35'));
        $this->assertSame('11.222.333/0001-81', $danfse->formatarDocumentoNoLayout('11222333000181'));
        $this->assertSame('123.456.789-09', $danfse->formatarDocumentoNoLayout('12345678909'));
    }

    #[Test]
    public function informaOAmbienteGeradorEOTipoDeAmbiente(): void
    {
        // São campos distintos no modelo (item 2.4.5): ambGer diz QUEM gerou;
        // tpAmb, se é produção ou homologação.
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        $this->assertStringContainsString('Ambiente Gerador', $texto);
        $this->assertStringContainsString('Tipo de Ambiente', $texto);
        $this->assertStringContainsString('Sistema Nacional NFS-e', $texto, 'ambGer = 2.');
    }

    /*
    |--------------------------------------------------------------------------
    | Tributação federal (item 2.1.9 e nota 6 — alterada na v1.02)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function zeraPisECofinsDeDebitoProprioQuandoHaRetencao(): void
    {
        // Regra da NT v1.02 (pág. 19): com tpRetPisCofins = 1, os campos de
        // débito de apuração própria retornam 0,00 e os valores migram para
        // "Contribuições Sociais - Retidas". Imprimir nos dois lugares contaria
        // o mesmo tributo duas vezes.
        $danfse = new DanfseSimples($this->xml('nfse_autorizada_uberlandia.xml'));
        $dados  = $danfse->dadosDaNota();

        $valores = $dados['servico']['valores'];

        if (1 !== (int) $valores['ret_pis_cofins']) {
            $this->assertSame($valores['pis'], $valores['pis_debito_proprio']);
            $this->assertSame($valores['cofins'], $valores['cofins_debito_proprio']);

            return;
        }

        $this->assertSame(0.0, $valores['pis_debito_proprio']);
        $this->assertSame(0.0, $valores['cofins_debito_proprio']);
        $this->assertSame(
            $valores['csll'] + $valores['pis'] + $valores['cofins'],
            $valores['contribuicoes_sociais_retidas'],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function xml(string $fixture): string
    {
        $xml = file_get_contents(__DIR__ . '/Fixtures/' . $fixture);

        $this->assertIsString($xml, "Fixture não encontrada: {$fixture}");

        return $xml;
    }

    private function render(string $fixture, ?string $layout = null): string
    {
        // O FPDF emite notices/deprecations em algumas células; não é o objeto
        // deste teste.
        $nivelAnterior = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        try {
            $danfse = new DanfseSimples($this->xml($fixture));

            if (null !== $layout) {
                $danfse->setLayout($layout);
            }

            return $danfse->render();
        } finally {
            error_reporting($nivelAnterior);
        }
    }

    private function texto(string $fixture, ?string $layout = null): string
    {
        return $this->documento($fixture, $layout)->getText();
    }

    /**
     * @return array<int, \Smalot\PdfParser\Page>
     */
    private function paginas(string $fixture, ?string $layout = null): array
    {
        return $this->documento($fixture, $layout)->getPages();
    }

    private function documento(string $fixture, ?string $layout = null): \Smalot\PdfParser\Document
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'danfse_') . '.pdf';
        file_put_contents($arquivo, $this->render($fixture, $layout));

        try {
            return (new Parser())->parseFile($arquivo);
        } finally {
            @unlink($arquivo);
        }
    }

    /**
     * O extrator devolve o texto com espaçamento variável entre glifos; a
     * normalização deixa a comparação sobre o conteúdo, não sobre o kerning.
     */
    private function normaliza(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }
}
