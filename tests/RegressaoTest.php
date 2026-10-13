<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Dps;
use QuantumTecnology\NfseNacional\SchemaValidator;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

/**
 * Regressões de bugs corrigidos — cada teste trava um defeito que já esteve em
 * produção. Falha aqui significa que o bug voltou.
 */
final class RegressaoTest extends TestCase
{
    private const NS = 'http://www.sped.fazenda.gov.br/nfse';

    /*
    |--------------------------------------------------------------------------
    | cNBS é obrigatório no XSD
    |--------------------------------------------------------------------------
    |
    | Era emitido sob isset(), como se fosse opcional. Nota sem cNBS saía do
    | pacote e a SEFAZ rejeitava com L2103.
    */

    #[Test]
    public function emiteCnbsQuandoInformado(): void
    {
        $xpath = $this->render($this->stdBase());

        $this->assertSame(
            '122051200',
            $xpath->query('//nfse:cServ/nfse:cNBS')->item(0)->nodeValue
        );
    }

    #[Test]
    public function acusaErroQuandoCnbsEstaAusente(): void
    {
        $std = $this->stdBase();

        unset($std->infDPS->serv->cServ->cNBS);

        $dps = new Dps($std);
        $dps->render();

        $errors = (new ReflectionProperty($dps, 'dom'))->getValue($dps)->errors;

        // cNBS é obrigatório: a ausência tem que aparecer em $dom->errors, não
        // passar silenciosamente para a SEFAZ.
        $this->assertNotEmpty($errors, 'A ausência de cNBS precisa ser sinalizada.');
        $this->assertStringContainsString('cNBS', implode("\n", $errors));
    }

    #[Test]
    public function oDpsCompletoValidaNoXsd(): void
    {
        $this->assertValidoNoXsd($this->stdBase());
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibilidade com PHP 8.1 (composer declara ^8.1)
    |--------------------------------------------------------------------------
    |
    | generateId() usava str_pad() e RestCurl usava trim(), ambas do PHP
    | 8.4. Em 8.1-8.3 o pacote instalava e quebrava na primeira emissão.
    */

    #[Test]
    public function naoUsaFuncoesExclusivasDoPhp84(): void
    {
        $exclusivas84 = ['mb_str_pad', 'mb_trim', 'mb_ltrim', 'mb_rtrim', 'array_find', 'array_any', 'array_all'];

        foreach (glob(__DIR__ . '/../src/*.php') + glob(__DIR__ . '/../src/Common/*.php') as $arquivo) {
            $codigo = file_get_contents($arquivo);

            foreach ($exclusivas84 as $fn) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![\w$>])' . preg_quote($fn, '/') . '\s*\(/',
                    $codigo,
                    basename($arquivo) . " usa {$fn}(), que só existe no PHP 8.4 — o composer declara ^8.1."
                );
            }
        }
    }

    #[Test]
    public function geraOIdDoDpsCom45Caracteres(): void
    {
        $xpath = $this->render($this->stdBase());

        $id = $xpath->query('//nfse:infDPS')->item(0)->getAttribute('Id');

        // TSIdDPS: "DPS" + 42 dígitos
        $this->assertSame(45, mb_strlen($id));
        $this->assertMatchesRegularExpression('/^DPS[0-9]{42}$/', $id);
    }

    #[Test]
    public function preencheOIdComZerosAEsquerda(): void
    {
        $std = $this->stdBase();

        $std->infDPS->serie = '1';
        $std->infDPS->nDPS  = '7';

        $id = $this->render($std)->query('//nfse:infDPS')->item(0)->getAttribute('Id');

        // cLocEmi(7) + tipoInsc(1) + CNPJ(14) + serie(5) + nDPS(15)
        $this->assertSame('DPS317020621122233300018100001000000000000007', $id);
    }

    #[Test]
    public function geraOIdParaPrestadorPessoaFisica(): void
    {
        $std = $this->stdBase();

        unset($std->infDPS->prest->CNPJ);
        $std->infDPS->prest->CPF = '12345678909';

        $id = $this->render($std)->query('//nfse:infDPS')->item(0)->getAttribute('Id');

        $this->assertSame(45, mb_strlen($id));
        // Tipo de inscrição 1 = CPF, completado com zeros até 14 posições.
        $this->assertStringStartsWith('DPS3170206100012345678909', $id);
    }

    /*
    |--------------------------------------------------------------------------
    | Vigência: IBSCBS obrigatório desde 01/10/2026
    |--------------------------------------------------------------------------
    |
    | Ato Conjunto RFB/CGIBS nº 4 (era 01/08 até a NT 009 v1.01). A partir dessa
    | competência a SEFAZ rejeita DPS sem o grupo. O pacote avisa
    | (E_USER_WARNING) em vez de lançar: não sabe se o emitente é optante do
    | Simples, dispensado até 2027.
    */

    #[Test]
    public function avisaQuandoIbscbsFaltaEmCompetenciaObrigatoria(): void
    {
        $std = $this->stdBase();

        $std->infDPS->dCompet = '2026-10-01';

        $avisos = $this->capturaAvisos(fn () => (new Dps($std))->render());

        $this->assertCount(1, $avisos, 'Deveria avisar sobre o IBSCBS ausente.');
        $this->assertStringContainsString('IBSCBS', $avisos[0]);
        $this->assertStringContainsString('2026-10-01', $avisos[0]);
    }

    #[Test]
    public function naoAvisaAntesDaDataDeObrigatoriedade(): void
    {
        $std = $this->stdBase();

        // Agosto e setembro avisavam com a data antiga (01/08).
        $std->infDPS->dCompet = '2026-09-30';

        $this->assertSame([], $this->capturaAvisos(fn () => (new Dps($std))->render()));
    }

    #[Test]
    public function naoAvisaQuandoOGrupoFoiInformado(): void
    {
        $std = $this->stdBase();

        $std->infDPS->dCompet = '2027-03-01';
        $std->infDPS->IBSCBS  = (object) [
            'finNFSe'  => '0',
            'indFinal' => '0',
            'cIndOp'   => '030101',
            'indDest'  => '0',
            'valores'  => (object) [
                'trib' => (object) [
                    'gIBSCBS' => (object) ['CST' => '000', 'cClassTrib' => '000001'],
                ],
            ],
        ];

        $this->assertSame([], $this->capturaAvisos(fn () => (new Dps($std))->render()));
    }

    #[Test]
    public function oAvisoNaoImpedeAGeracaoDoXml(): void
    {
        $std = $this->stdBase();

        $std->infDPS->dCompet = '2026-12-01';

        $xml = @(new Dps($std))->render();

        // O aviso não pode virar exceção: a nota ainda precisa ser gerada.
        $this->assertStringContainsString('<infDPS', $xml);
        $this->assertStringNotContainsString('<IBSCBS', $xml);
    }

    /*
    |--------------------------------------------------------------------------
    | Dead code removido do renderEvento()
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function oEventoNaoCarregaElementoDpsOrfao(): void
    {
        $std = new stdClass();

        $std->infPedReg            = new stdClass();
        $std->infPedReg->tpAmb     = '2';
        $std->infPedReg->verAplic  = 'TSPD_1.0.1';
        $std->infPedReg->dhEvento  = '2026-07-22T10:00:00-03:00';
        $std->infPedReg->CNPJAutor = '11222333000181';
        $std->infPedReg->chNFSe    = str_repeat('1', 50);
        $std->infPedReg->e101101   = (object) ['cMotivo' => '1', 'xMotivo' => 'Erro na emissao'];

        $xml = (new Dps($std))->renderEvento();

        $this->assertStringNotContainsString('<DPS', $xml, 'renderEvento não pode emitir <DPS>.');
        $this->assertStringContainsString('<pedRegEvento', $xml);
    }

    /*
    |--------------------------------------------------------------------------
    | codigoEvento cobre os dois eventos suportados
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function derivaOCodigoDoEventoPresenteNoPayload(): void
    {
        $casos = [
            'e101101' => '101101',
            'e105102' => '105102',
        ];

        foreach ($casos as $evento => $codigo) {
            $std                       = new stdClass();
            $std->infPedReg            = new stdClass();
            $std->infPedReg->{$evento} = new stdClass();

            $dps    = new Dps($std);
            $metodo = new ReflectionMethod($dps, 'codigoEvento');

            $this->assertSame($codigo, $metodo->invoke($dps), "Evento {$evento}");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Executa o callback capturando os E_USER_WARNING emitidos.
     *
     * @return string[]
     */
    private function capturaAvisos(callable $fn): array
    {
        $avisos = [];

        set_error_handler(static function (int $nivel, string $msg) use (&$avisos): bool {
            if (E_USER_WARNING === $nivel) {
                $avisos[] = $msg;

                return true;
            }

            return false;
        });

        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $avisos;
    }

    private function render(stdClass $std): DOMXPath
    {
        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML((new Dps($std))->render());

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', self::NS);

        return $xpath;
    }

    private function assertValidoNoXsd(stdClass $std): void
    {
        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML((new Dps($std))->render());

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = $dom->schemaValidate(SchemaValidator::schemaPath('DPS', '1.01'));
        $erros  = array_map(static fn ($e) => trim($e->message), libxml_get_errors());

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $this->assertTrue($valido, "XML inválido:\n" . implode("\n", $erros));
    }

    private function stdBase(): stdClass
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
}
