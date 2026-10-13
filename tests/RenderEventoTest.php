<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Dps;
use QuantumTecnology\NfseNacional\SchemaValidator;
use ReflectionProperty;
use stdClass;

/**
 * Eventos (pedRegEvento) — cancelamento e cancelamento por substituição.
 *
 * REGRESSÃO (ida e volta): a 3.x validava contra uma edição ANTIGA do XSD
 * v1.01, que exigia <nPedRegEvento> e Id de 62 chars — e o pacote passou a
 * gerá-los para "passar na validação". O XSD oficial (produção 09/02/2026 e
 * homologação 27/07/2026) removeu o elemento e fixou o Id em 59 chars
 * ("PRE" + chave 50 + evento 6). O XML passava no validador local e era
 * recusado pela Sefin Nacional. Os testes abaixo prendem o layout oficial.
 */
final class RenderEventoTest extends TestCase
{
    private const NS = 'http://www.sped.fazenda.gov.br/nfse';

    /*
    |--------------------------------------------------------------------------
    | Regressão: o XML do evento precisa validar no XSD
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function oCancelamentoValidaContraOXsd(): void
    {
        $this->assertEventoValidoNoXsd($this->stdCancelamento());
    }

    #[Test]
    public function oEventoVemLogoDepoisDoChnfseSemNpedregevento(): void
    {
        $xpath = $this->renderEvento($this->stdCancelamento());

        $filhos = [];

        foreach ($xpath->query('//nfse:infPedReg/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(
            ['tpAmb', 'verAplic', 'dhEvento', 'CNPJAutor', 'chNFSe', 'e101101'],
            $filhos
        );
    }

    #[Test]
    public function oIdDoPedidoTem59Caracteres(): void
    {
        $xpath = $this->renderEvento($this->stdCancelamento());

        $id = $xpath->query('//nfse:infPedReg')->item(0)->getAttribute('Id');

        // TSIdPedRegEvt: PRE + chave(50) + evento(6), maxLength 59
        $this->assertSame(59, mb_strlen($id), 'Id deve ter 59 chars, tem ' . mb_strlen($id) . ": {$id}");
        $this->assertSame('PRE' . str_repeat('1', 50) . '101101', $id);
    }

    #[Test]
    public function npedregeventoDoPayloadNaoVazaParaOXml(): void
    {
        $std = $this->stdCancelamento();

        // Integrações da 3.x ainda podem mandar o campo; não pode reaparecer.
        $std->infPedReg->nPedRegEvento = 7;

        $xpath = $this->renderEvento($std);

        $this->assertSame(0, $xpath->query('//nfse:nPedRegEvento')->length);
        $this->assertSame(59, mb_strlen($xpath->query('//nfse:infPedReg')->item(0)->getAttribute('Id')));

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function oCancelamentoDeNotaComCnpjAlfanumericoValida(): void
    {
        $std = $this->stdCancelamento();

        // Chave = cMun(7) + ambGer(1) + tpInsc(1) + inscrição(14) + 27 dígitos.
        // Letras só na raiz: o XSD oficial se contradiz sobre onde elas cabem —
        // TSChaveNFSe libera as posições 7–20 da chave, o Id do evento libera a
        // inscrição (10–23). Só a interseção passa nos dois.
        $cnpj = '12ABC345000190';

        $std->infPedReg->CNPJAutor = $cnpj;
        $std->infPedReg->chNFSe    = '3170206' . '1' . '2' . $cnpj . str_repeat('1', 27);

        $this->assertEventoValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | xDesc é enumeração fixa no XSD
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function derivaOXdescDoCancelamentoIgnorandoOPayload(): void
    {
        $std = $this->stdCancelamento();

        // Payload com grafia errada: não pode vazar para o XML.
        $std->infPedReg->e101101->xDesc = 'texto qualquer que o XSD recusa';

        $xpath = $this->renderEvento($std);

        $this->assertSame(
            'Cancelamento de NFS-e',
            $xpath->query('//nfse:e101101/nfse:xDesc')->item(0)->nodeValue
        );

        $this->assertEventoValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | e105102 — cancelamento por substituição
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function emiteOCorpoDoCancelamentoPorSubstituicao(): void
    {
        $std = $this->stdSubstituicao();

        $xpath = $this->renderEvento($std);

        $this->assertSame(1, $xpath->query('//nfse:infPedReg/nfse:e105102')->length);
        $this->assertSame(0, $xpath->query('//nfse:infPedReg/nfse:e101101')->length);

        $filhos = [];

        foreach ($xpath->query('//nfse:e105102/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(['xDesc', 'cMotivo', 'xMotivo', 'chSubstituta'], $filhos);

        // Grafia exata do XSD v1.01 oficial — acentuada (a v1.00 não era).
        $this->assertSame(
            'Cancelamento de NFS-e por Substituição',
            $xpath->query('//nfse:e105102/nfse:xDesc')->item(0)->nodeValue
        );

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function oXmotivoDaSubstituicaoEOpcional(): void
    {
        $std = $this->stdSubstituicao();

        unset($std->infPedReg->e105102->xMotivo);

        $xpath = $this->renderEvento($std);

        $this->assertSame(0, $xpath->query('//nfse:e105102/nfse:xMotivo')->length);

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function oIdDaSubstituicaoUsaOCodigo105102(): void
    {
        $id = $this->renderEvento($this->stdSubstituicao())
            ->query('//nfse:infPedReg')->item(0)->getAttribute('Id');

        $this->assertSame(59, mb_strlen($id));
        $this->assertStringEndsWith('105102', $id);
    }

    /*
    |--------------------------------------------------------------------------
    | Autor: choice CNPJAutor | CPFAutor
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function aceitaCpfDoAutor(): void
    {
        $std = $this->stdCancelamento();

        unset($std->infPedReg->CNPJAutor);
        $std->infPedReg->CPFAutor = '12345678909';

        $xpath = $this->renderEvento($std);

        $this->assertSame(1, $xpath->query('//nfse:infPedReg/nfse:CPFAutor')->length);
        $this->assertSame(0, $xpath->query('//nfse:infPedReg/nfse:CNPJAutor')->length);

        $this->assertEventoValidoNoXsd($std);
    }

    #[Test]
    public function naoRegistraErrosDePreenchimento(): void
    {
        $dps = new Dps($this->stdCancelamento());
        $dps->renderEvento();

        $errors = (new ReflectionProperty($dps, 'dom'))->getValue($dps)->errors;

        $this->assertSame([], $errors);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function renderEvento(stdClass $std): DOMXPath
    {
        $xml = (new Dps($std))->renderEvento();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', self::NS);

        return $xpath;
    }

    private function assertEventoValidoNoXsd(stdClass $std): void
    {
        $xml = (new Dps($std))->renderEvento();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = $dom->schemaValidate(SchemaValidator::schemaPath('pedRegEvento', '1.01'));
        $erros  = array_map(
            static fn ($erro) => trim($erro->message),
            libxml_get_errors()
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $this->assertTrue($valido, "Evento inválido no XSD v1.01:\n" . implode("\n", $erros));
    }

    private function stdEventoBase(): stdClass
    {
        $std = new stdClass();

        $std->infPedReg            = new stdClass();
        $std->infPedReg->tpAmb     = '2';
        $std->infPedReg->verAplic  = 'TSPD_1.0.1';
        $std->infPedReg->dhEvento  = '2026-07-22T10:00:00-03:00';
        $std->infPedReg->CNPJAutor = '11222333000181';
        $std->infPedReg->chNFSe    = str_repeat('1', 50);

        return $std;
    }

    private function stdCancelamento(): stdClass
    {
        $std = $this->stdEventoBase();

        $std->infPedReg->e101101 = (object) [
            'cMotivo' => '1',
            'xMotivo' => 'Erro na emissao da nota fiscal',
        ];

        return $std;
    }

    private function stdSubstituicao(): stdClass
    {
        $std = $this->stdEventoBase();

        // cMotivo da substituição é TSCodJustSubst (01..05, 99) — com zero à
        // esquerda, diferente do cancelamento, que é TSCodJustCanc (1, 2, 9).
        $std->infPedReg->e105102 = (object) [
            'cMotivo'      => '01',
            'xMotivo'      => 'Substituicao por erro de valor',
            'chSubstituta' => str_repeat('2', 50),
        ];

        return $std;
    }
}
