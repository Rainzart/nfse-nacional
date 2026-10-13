<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Dps;
use QuantumTecnology\NfseNacional\SchemaValidationException;
use QuantumTecnology\NfseNacional\SchemaValidator;
use stdClass;

/**
 * Validação contra os XSDs oficiais.
 *
 * Antes desta funcionalidade os XSDs em storage/schemes/ eram arquivos órfãos:
 * o pacote assinava e transmitia XML fora do schema, e o erro só aparecia como
 * rejeição genérica da SEFAZ (L2103). O sped-nfe valida em toda transmissão
 * (NFePHP\Common\Validator); aqui seguimos o mesmo padrão.
 */
final class SchemaValidatorTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | SchemaValidator
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function naoAcusaErroEmXmlValido(): void
    {
        $xml = (new Dps($this->stdValido()))->render();

        $this->assertSame([], SchemaValidator::errors($xml, 'DPS'));
    }

    #[Test]
    public function apontaOElementoExatoQuandoFaltaCampoObrigatorio(): void
    {
        $std = $this->stdValido();

        // cNBS é obrigatório no XSD; sem ele o DOMImproved gera a tag vazia.
        unset($std->infDPS->serv->cServ->cNBS);

        $erros = SchemaValidator::errors((new Dps($std))->render(), 'DPS');

        $this->assertNotEmpty($erros, 'Deveria acusar o cNBS vazio.');
        $this->assertStringContainsString('cNBS', implode("\n", $erros));
    }

    #[Test]
    public function limpaONamespaceDasMensagensDoLibxml(): void
    {
        $std = $this->stdValido();

        unset($std->infDPS->serv->cServ->cNBS);

        $erros = implode("\n", SchemaValidator::errors((new Dps($std))->render(), 'DPS'));

        // O libxml repete o namespace em cada elemento, o que polui o log.
        $this->assertStringNotContainsString('{http://www.sped.fazenda.gov.br/nfse}', $erros);
    }

    #[Test]
    public function acusaXmlMalFormado(): void
    {
        $erros = SchemaValidator::errors('<DPS><infDPS></DPS>', 'DPS');

        $this->assertNotEmpty($erros);
    }

    #[Test]
    public function recusaXmlVazio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SchemaValidator::errors('   ', 'DPS');
    }

    #[Test]
    public function consideraValidoQuandoOXsdDaVersaoNaoExiste(): void
    {
        $xml = (new Dps($this->stdValido()))->render();

        // Mesma decisão do sped-nfe: sem o XSD, não trava a emissão.
        $this->assertSame([], SchemaValidator::errors($xml, 'DPS', '9.99'));
    }

    #[Test]
    public function oDpsDaNotaRealAutorizadaValidaNoXsd(): void
    {
        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->load(__DIR__ . '/Fixtures/nfse_autorizada_uberlandia.xml');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', 'http://www.sped.fazenda.gov.br/nfse');

        $extraido = new DOMDocument('1.0', 'UTF-8');
        $extraido->appendChild($extraido->importNode($xpath->query('//nfse:DPS')->item(0), true));

        // O ADN autorizou este DPS (cStat 100). Se o validador o recusa, é o
        // validador que está errado — não a nota.
        $this->assertSame([], SchemaValidator::errors($extraido->saveXML(), 'DPS'));
    }

    #[Test]
    public function aceitaCnpjAlfanumericoNoPrestadorENoId(): void
    {
        $std = $this->stdValido();

        $std->infDPS->prest->CNPJ = '12ABC34501DE35';

        $dps = new Dps($std);
        $xml = $dps->render();

        $this->assertSame([], SchemaValidator::errors($xml, 'DPS'));
        $this->assertStringContainsString('Id="DPS317020621' . '2ABC34501DE35', $xml);
    }

    #[Test]
    public function validaOEventoContraOSchemaProprio(): void
    {
        $xml = (new Dps($this->stdEvento()))->renderEvento();

        $this->assertSame([], SchemaValidator::errors($xml, 'pedRegEvento'));
    }

    /*
    |--------------------------------------------------------------------------
    | Dps::validate() e getErrors()
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function oDpsValidaOProprioXml(): void
    {
        $dps = new Dps($this->stdValido());

        $this->assertSame([], $dps->validate($dps->render()));
    }

    #[Test]
    public function oDpsExpoeOsErrosDePreenchimento(): void
    {
        $std = $this->stdValido();

        unset($std->infDPS->serv->cServ->cNBS);

        $dps = new Dps($std);
        $dps->render();

        // $dom->errors era acumulado e nunca lido — falha silenciosa.
        $this->assertNotEmpty($dps->getErrors());
        $this->assertStringContainsString('cNBS', implode("\n", $dps->getErrors()));
    }

    #[Test]
    public function getErrorsFicaVazioQuandoTudoEstaPreenchido(): void
    {
        $dps = new Dps($this->stdValido());
        $dps->render();

        $this->assertSame([], $dps->getErrors());
    }

    /*
    |--------------------------------------------------------------------------
    | Integração com Tools (sem rede)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function toolsBloqueiaTransmissaoDeXmlInvalido(): void
    {
        $std = $this->stdValido();

        unset($std->infDPS->serv->cServ->cNBS);

        $xml   = (new Dps($std))->render();
        $tools = new ToolsSpy();

        try {
            $tools->enviaDps($xml);
            $this->fail('Deveria ter lançado SchemaValidationException.');
        } catch (SchemaValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
            $this->assertStringContainsString('cNBS', $e->getMessage());
        }

        $this->assertFalse($tools->assinou, 'Não pode assinar um XML inválido.');
    }

    #[Test]
    public function toolsDeixaPassarXmlValido(): void
    {
        $xml   = (new Dps($this->stdValido()))->render();
        $tools = new ToolsSpy();

        $tools->enviaDps($xml);

        $this->assertTrue($tools->assinou, 'XML válido deve seguir para a assinatura.');
    }

    #[Test]
    public function aValidacaoPodeSerDesligada(): void
    {
        $std = $this->stdValido();

        unset($std->infDPS->serv->cServ->cNBS);

        $tools = new ToolsSpy();
        $tools->setValidateSchema(false);

        $tools->enviaDps((new Dps($std))->render());

        $this->assertTrue($tools->assinou, 'Com a validação desligada, segue adiante.');
    }

    #[Test]
    public function toolsBloqueiaCancelamentoDeEventoInvalido(): void
    {
        $std = $this->stdEvento();

        // cMotivo é obrigatório no TE101101; sem ele o evento sai fora do schema.
        unset($std->infPedReg->e101101->cMotivo);

        $tools = new ToolsSpy();

        try {
            $tools->cancelaNfse($std);
            $this->fail('Deveria ter lançado SchemaValidationException.');
        } catch (SchemaValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
        }

        $this->assertFalse($tools->assinou, 'Não pode assinar um evento inválido.');
        $this->assertFalse($tools->transmitiu, 'Não pode transmitir um evento inválido.');
    }

    #[Test]
    public function toolsDeixaPassarCancelamentoValido(): void
    {
        $tools = new ToolsSpy();

        $tools->cancelaNfse($this->stdEvento());

        $this->assertTrue($tools->assinou);
        $this->assertTrue($tools->transmitiu);
    }

    #[Test]
    public function escolheOSchemaPelaVersaoDeclaradaNoXml(): void
    {
        $tools = new ToolsSpy();

        $this->assertSame('1.01', $tools->versao('<DPS versao="1.01" xmlns="x"><a/></DPS>'));
        $this->assertSame('1.04', $tools->versao('<DPS versao="1.04" xmlns="x"><a/></DPS>'));
        $this->assertSame('1.01', $tools->versao('<pedRegEvento versao="1.01"><a/></pedRegEvento>'));
        $this->assertNull($tools->versao('<DPS><a/></DPS>'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function stdValido(): stdClass
    {
        $std = new stdClass();

        $std->infDPS           = new stdClass();
        $std->infDPS->tpAmb    = '2';
        $std->infDPS->dhEmi    = '2026-07-22T00:00:00-03:00';
        $std->infDPS->verAplic = 'TSPD_1.0.1';
        $std->infDPS->serie    = '1';
        $std->infDPS->nDPS     = '3180';
        $std->infDPS->dCompet  = '2026-07-22';
        $std->infDPS->tpEmit   = '1';
        $std->infDPS->cLocEmi  = '3170206';

        $std->infDPS->prest = (object) [
            'CNPJ'    => '11222333000181',
            'regTrib' => (object) ['opSimpNac' => '1', 'regEspTrib' => '0'],
        ];

        $std->infDPS->serv = (object) [
            'locPrest' => (object) ['cLocPrestacao' => '3170206'],
            'cServ'    => (object) [
                'cTribNac'  => '060401',
                'xDescServ' => 'Servico de exemplo',
                'cNBS'      => '122051200',
            ],
        ];

        $std->infDPS->valores = (object) [
            'vServPrest' => (object) ['vServ' => '441.60'],
            'trib'       => (object) [
                'tribMun' => (object) ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
                'totTrib' => (object) ['indTotTrib' => '0'],
            ],
        ];

        return $std;
    }

    private function stdEvento(): stdClass
    {
        $std = new stdClass();

        $std->infPedReg            = new stdClass();
        $std->infPedReg->tpAmb     = '2';
        $std->infPedReg->verAplic  = 'TSPD_1.0.1';
        $std->infPedReg->dhEvento  = '2026-07-22T10:00:00-03:00';
        $std->infPedReg->CNPJAutor = '11222333000181';
        $std->infPedReg->chNFSe    = str_repeat('1', 50);
        $std->infPedReg->e101101   = (object) [
            'cMotivo' => '1',
            'xMotivo' => 'Erro na emissao da nota fiscal',
        ];

        return $std;
    }
}
