<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use DOMDocument;
use DOMNode;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\Dps;
use QuantumTecnology\NfseNacional\SchemaValidator;
use ReflectionProperty;
use stdClass;

/**
 * Grupo IBSCBS do DPS (Reforma Tributária — EC 132/2023).
 *
 * A referência desta suíte é uma NFS-e REAL autorizada pelo Ambiente de Dados
 * Nacional (Uberlândia/MG, cStat 100, 2026-07-29), em tests/Fixtures. O teste
 * golden compara o <IBSCBS> gerado com o que o ADN de fato aceitou.
 */
final class DpsIbsCbsTest extends TestCase
{
    private const NS = 'http://www.sped.fazenda.gov.br/nfse';

    /*
    |--------------------------------------------------------------------------
    | Golden test — paridade com a nota autorizada
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function geraOGrupoIbscbsIdenticoAoDaNotaAutorizada(): void
    {
        $esperado = $this->ibsCbsDaNotaAutorizada();

        $xpath  = $this->render($this->stdComIbsCbsMinimo());
        $gerado = $xpath->query('//nfse:infDPS/nfse:IBSCBS')->item(0);

        $this->assertNotNull($gerado, 'O grupo <IBSCBS> não foi gerado.');

        $this->assertSame(
            $this->canonicalizar($esperado),
            $this->canonicalizar($gerado),
            'O <IBSCBS> gerado diverge do XML autorizado pelo ADN.'
        );
    }

    #[Test]
    public function oGrupoMinimoValidaContraOXsd(): void
    {
        $this->assertValidoNoXsd($this->stdComIbsCbsMinimo());
    }

    /*
    |--------------------------------------------------------------------------
    | Não-regressão: o grupo é opcional
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function omiteOGrupoQuandoOPayloadNaoTrazIbscbs(): void
    {
        $std = $this->stdBase();

        $xpath = $this->render($std);

        $this->assertSame(
            0,
            $xpath->query('//nfse:IBSCBS')->length,
            'Sem IBSCBS no payload, a tag não pode aparecer no XML.'
        );
    }

    #[Test]
    public function oDpsSemIbscbsContinuaValidoNoXsd(): void
    {
        $this->assertValidoNoXsd($this->stdBase());
    }

    /*
    |--------------------------------------------------------------------------
    | Guardas dos grupos opcionais (defeito: tags vazias silenciosas)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function naoEmiteGruposOpcionaisAusentesNoPayload(): void
    {
        $xpath = $this->render($this->stdComIbsCbsMinimo());

        // DOMImproved::addChild com obrigatorio=true CRIA a tag mesmo vazia e só
        // registra em $dom->errors — que ninguém lê. Sem guarda isset, todos estes
        // sairiam vazios em toda nota.
        foreach (['gTribRegular', 'gDif', 'dest', 'imovel', 'gRefNFSe', 'gReeRepRes'] as $tag) {
            $this->assertSame(
                0,
                $xpath->query("//nfse:IBSCBS//nfse:{$tag}")->length,
                "<{$tag}> não foi informado no payload e não pode ser emitido."
            );
        }

        foreach (['tpOper', 'tpEnteGov', 'cCredPres'] as $tag) {
            $this->assertSame(
                0,
                $xpath->query("//nfse:IBSCBS/nfse:{$tag}")->length,
                "<{$tag}> é opcional e não foi informado."
            );
        }
    }

    #[Test]
    public function naoRegistraErrosDePreenchimentoNoCasoMinimo(): void
    {
        $dps = new Dps($this->stdComIbsCbsMinimo());
        $dps->render();

        $errors = (new ReflectionProperty($dps, 'dom'))->getValue($dps)->errors;

        $this->assertSame([], $errors, 'O DOM acumulou erros de preenchimento obrigatório.');
    }

    /*
    |--------------------------------------------------------------------------
    | Estrutura: gTribRegular e gDif são filhos de gIBSCBS
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function aninhaGtribregularEGdifDentroDeGibscbs(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $gibscbs               = $std->infDPS->IBSCBS->valores->trib->gIBSCBS;
        $gibscbs->gTribRegular = (object) ['CSTReg' => '000', 'cClassTribReg' => '000001'];
        $gibscbs->gDif         = (object) ['pDifUF' => '10.00', 'pDifMun' => '5.00', 'pDifCBS' => '2.50'];

        $xpath = $this->render($std);

        // Devem estar sob gIBSCBS — não como irmãos, direto em <trib>.
        $this->assertSame(1, $xpath->query('//nfse:trib/nfse:gIBSCBS/nfse:gTribRegular')->length);
        $this->assertSame(1, $xpath->query('//nfse:trib/nfse:gIBSCBS/nfse:gDif')->length);
        $this->assertSame(0, $xpath->query('//nfse:trib/nfse:gTribRegular')->length);
        $this->assertSame(0, $xpath->query('//nfse:trib/nfse:gDif')->length);

        $this->assertSame('10.00', $xpath->query('//nfse:gDif/nfse:pDifUF')->item(0)->nodeValue);

        $this->assertValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | dest: ordem de fone/email e exclusividade do choice
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function emiteFoneEEmailUmaVezSoEDepoisDoEnd(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $std->infDPS->IBSCBS->dest = (object) [
            'CPF'   => '12345678909',
            'xNome' => 'TOMADOR DE EXEMPLO',
            'fone'  => '34999999999',
            'email' => 'tomador@exemplo.com.br',
            'end'   => (object) [
                'endNac'  => (object) ['cMun' => '3170206', 'CEP' => '38400000'],
                'xLgr'    => 'RUA DO TOMADOR',
                'nro'     => '80',
                'xCpl'    => 'APT',
                'xBairro' => 'BAIRRO EXEMPLO',
            ],
        ];

        $xpath = $this->render($std);

        $this->assertSame(1, $xpath->query('//nfse:IBSCBS/nfse:dest/nfse:fone')->length);
        $this->assertSame(1, $xpath->query('//nfse:IBSCBS/nfse:dest/nfse:email')->length);

        $filhos = [];

        foreach ($xpath->query('//nfse:IBSCBS/nfse:dest/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(['CPF', 'xNome', 'end', 'fone', 'email'], $filhos);

        $this->assertValidoNoXsd($std);
    }

    #[Test]
    public function respeitaOChoiceDeIdentificacaoDoDestinatario(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        // Payload ambíguo de propósito: CNPJ e CPF juntos. Só o primeiro sai.
        $std->infDPS->IBSCBS->dest = (object) [
            'CNPJ'  => '11222333000181',
            'CPF'   => '12345678909',
            'xNome' => 'DESTINATARIO TESTE',
        ];

        $xpath = $this->render($std);

        $this->assertSame(1, $xpath->query('//nfse:IBSCBS/nfse:dest/nfse:CNPJ')->length);
        $this->assertSame(0, $xpath->query('//nfse:IBSCBS/nfse:dest/nfse:CPF')->length);

        $this->assertValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | Grupos repetíveis e ordem na sequence
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function posicionaGrefnfseEntreTpoperETpentegov(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $std->infDPS->IBSCBS->tpOper    = '1';
        $std->infDPS->IBSCBS->tpEnteGov = '4';
        $std->infDPS->IBSCBS->gRefNFSe  = (object) [
            'refNFSe' => [str_repeat('1', 50), str_repeat('2', 50)],
        ];

        $xpath = $this->render($std);

        $this->assertSame(2, $xpath->query('//nfse:gRefNFSe/nfse:refNFSe')->length);

        $filhos = [];

        foreach ($xpath->query('//nfse:IBSCBS/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(
            ['finNFSe', 'indFinal', 'cIndOp', 'tpOper', 'gRefNFSe', 'tpEnteGov', 'indDest', 'valores'],
            $filhos
        );

        $this->assertValidoNoXsd($std);
    }

    #[Test]
    public function aceitaUmUnicoRefnfseForaDeArray(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $std->infDPS->IBSCBS->gRefNFSe = (object) ['refNFSe' => str_repeat('7', 50)];

        $xpath = $this->render($std);

        $this->assertSame(1, $xpath->query('//nfse:gRefNFSe/nfse:refNFSe')->length);
        $this->assertValidoNoXsd($std);
    }

    #[Test]
    public function emiteGreereprresAntesDeTrib(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $std->infDPS->IBSCBS->valores->gReeRepRes = (object) [
            'documentos' => [
                (object) [
                    'dFeNacional'  => (object) ['tipoChaveDFe' => '1', 'chaveDFe' => str_repeat('3', 50)],
                    'fornec'       => (object) ['CNPJ' => '11222333000181', 'xNome' => 'FORNECEDOR LTDA'],
                    'dtEmiDoc'     => '2026-07-01',
                    'dtCompDoc'    => '2026-07-22',
                    'tpReeRepRes'  => '01',
                    'vlrReeRepRes' => '150.00',
                ],
                (object) [
                    'docOutro'     => (object) ['nDoc' => '12345', 'xDoc' => 'Recibo avulso'],
                    'dtEmiDoc'     => '2026-07-02',
                    'dtCompDoc'    => '2026-07-22',
                    'tpReeRepRes'  => '99',
                    'xTpReeRepRes' => 'Outros reembolsos',
                    'vlrReeRepRes' => '75.50',
                ],
            ],
        ];

        $xpath = $this->render($std);

        $this->assertSame(2, $xpath->query('//nfse:gReeRepRes/nfse:documentos')->length);

        $filhos = [];

        foreach ($xpath->query('//nfse:IBSCBS/nfse:valores/*') as $filho) {
            $filhos[] = $filho->localName;
        }

        $this->assertSame(['gReeRepRes', 'trib'], $filhos, 'gReeRepRes precede trib na sequence.');

        // xTpReeRepRes só no documento com tpReeRepRes=99.
        $this->assertSame(1, $xpath->query('//nfse:documentos/nfse:xTpReeRepRes')->length);

        $this->assertValidoNoXsd($std);
    }

    #[Test]
    public function emiteOGrupoImovelComCcib(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $std->infDPS->IBSCBS->imovel = (object) [
            'inscImobFisc' => 'ABC123',
            'cCIB'         => '12345678',
        ];

        $xpath = $this->render($std);

        $this->assertSame('12345678', $xpath->query('//nfse:imovel/nfse:cCIB')->item(0)->nodeValue);
        $this->assertSame(0, $xpath->query('//nfse:imovel/nfse:end')->length);

        $this->assertValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | Códigos são string — zeros à esquerda não podem se perder
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function preservaZerosAEsquerdaDosCodigos(): void
    {
        $xpath = $this->render($this->stdComIbsCbsMinimo());

        $this->assertSame('0', $xpath->query('//nfse:IBSCBS/nfse:finNFSe')->item(0)->nodeValue);
        $this->assertSame('0', $xpath->query('//nfse:IBSCBS/nfse:indFinal')->item(0)->nodeValue);
        $this->assertSame('030101', $xpath->query('//nfse:IBSCBS/nfse:cIndOp')->item(0)->nodeValue);
        $this->assertSame('0', $xpath->query('//nfse:IBSCBS/nfse:indDest')->item(0)->nodeValue);
        $this->assertSame('000', $xpath->query('//nfse:gIBSCBS/nfse:CST')->item(0)->nodeValue);
        $this->assertSame('000001', $xpath->query('//nfse:gIBSCBS/nfse:cClassTrib')->item(0)->nodeValue);
    }

    /*
    |--------------------------------------------------------------------------
    | propertiesToLower com arrays (pré-requisito dos grupos repetíveis)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function rebaixaChavesDeObjetosDentroDeArrays(): void
    {
        $entrada = (object) [
            'Grupo' => (object) [
                'Itens' => [
                    (object) ['ChaveUm' => 'a'],
                    (object) ['ChaveDois' => 'b'],
                ],
            ],
        ];

        $saida = Dps::propertiesToLower($entrada);

        $this->assertObjectHasProperty('grupo', $saida);
        $this->assertObjectHasProperty('chaveum', $saida->grupo->itens[0]);
        $this->assertObjectHasProperty('chavedois', $saida->grupo->itens[1]);
    }

    #[Test]
    public function preservaArraysDeEscalares(): void
    {
        $saida = Dps::propertiesToLower((object) ['Lista' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $saida->lista);
    }

    /*
    |--------------------------------------------------------------------------
    | Kitchen sink
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function validaNoXsdComTodosOsGruposOpcionaisPreenchidos(): void
    {
        $std = $this->stdComIbsCbsMinimo();

        $ibscbs            = $std->infDPS->IBSCBS;
        $ibscbs->tpOper    = '5';
        $ibscbs->tpEnteGov = '4';
        $ibscbs->gRefNFSe  = (object) ['refNFSe' => [str_repeat('9', 50)]];
        $ibscbs->dest      = (object) [
            'NIF'   => 'NIF-123456',
            'xNome' => 'CLIENTE EXTERIOR',
            'end'   => (object) [
                'endExt' => (object) [
                    'cPais'       => 'PT',
                    'cEndPost'    => '1000-001',
                    'xCidade'     => 'Lisboa',
                    'xEstProvReg' => 'Lisboa',
                ],
                'xLgr'    => 'Rua Augusta',
                'nro'     => '100',
                'xCpl'    => '2 Esq',
                'xBairro' => 'Baixa',
            ],
            'fone'  => '351210000000',
            'email' => 'cliente@exterior.pt',
        ];
        // No endereço da obra (TCEnderObraEvento) o choice nacional é CEP, não cMun.
        $ibscbs->imovel = (object) [
            'inscImobFisc' => 'IM-99',
            'end'          => (object) [
                'CEP'     => '38400000',
                'xLgr'    => 'Rua da Obra',
                'nro'     => '500',
                'xBairro' => 'Centro',
            ],
        ];

        $gibscbs               = $ibscbs->valores->trib->gIBSCBS;
        $gibscbs->cCredPres    = '01';
        $gibscbs->gTribRegular = (object) ['CSTReg' => '000', 'cClassTribReg' => '000001'];
        $gibscbs->gDif         = (object) ['pDifUF' => '1.00', 'pDifMun' => '2.00', 'pDifCBS' => '3.00'];

        $ibscbs->valores->gReeRepRes = (object) [
            'documentos' => [
                (object) [
                    'docFiscalOutro' => (object) [
                        'cMunDocFiscal' => '3170206',
                        'nDocFiscal'    => '555',
                        'xDocFiscal'    => 'Nota municipal',
                    ],
                    'dtEmiDoc'     => '2026-07-03',
                    'dtCompDoc'    => '2026-07-22',
                    'tpReeRepRes'  => '02',
                    'vlrReeRepRes' => '10.00',
                ],
            ],
        ];

        $this->assertValidoNoXsd($std);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function render(stdClass $std): DOMXPath
    {
        $xml = (new Dps($std))->render();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', self::NS);

        return $xpath;
    }

    /**
     * Valida o <DPS> gerado contra o XSD oficial v1.01.
     *
     * A validação roda ANTES da assinatura — a <Signature> só é acrescentada
     * depois, em Tools::enviaDps().
     */
    private function assertValidoNoXsd(stdClass $std): void
    {
        $xml = (new Dps($std))->render();

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = $dom->schemaValidate(SchemaValidator::schemaPath('DPS', '1.01'));
        $erros  = array_map(
            static fn ($erro) => trim($erro->message),
            libxml_get_errors()
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $this->assertTrue($valido, "XML inválido no XSD v1.01:\n" . implode("\n", $erros));
    }

    /**
     * Serializa um nó de forma comparável (sem depender de espaços/prefixos).
     */
    private function canonicalizar(DOMNode $node): string
    {
        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->appendChild($dom->importNode($node, true));

        return trim($dom->saveXML($dom->documentElement));
    }

    private function ibsCbsDaNotaAutorizada(): DOMNode
    {
        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->load(__DIR__ . '/Fixtures/nfse_autorizada_uberlandia.xml');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfse', self::NS);

        // A NFS-e tem DOIS grupos IBSCBS: o de retorno (infNFSe, calculado pelo
        // ADN) e o declarado no DPS. Aqui interessa o do DPS.
        $node = $xpath->query('//nfse:DPS/nfse:infDPS/nfse:IBSCBS')->item(0);

        $this->assertNotNull($node, 'Fixture sem <IBSCBS> no DPS.');

        return $node;
    }

    /**
     * DPS mínimo válido, espelhando a nota autorizada — sem o grupo IBSCBS.
     */
    private function stdBase(): stdClass
    {
        $std = new stdClass();

        $std->infDPS           = new stdClass();
        $std->infDPS->tpAmb    = '1';
        $std->infDPS->dhEmi    = '2026-07-22T00:00:00-03:00';
        $std->infDPS->verAplic = 'TSPD_1.0.1';
        $std->infDPS->serie    = '1';
        $std->infDPS->nDPS     = '1';
        $std->infDPS->dCompet  = '2026-07-22';
        $std->infDPS->tpEmit   = '1';
        $std->infDPS->cLocEmi  = '3170206';

        $std->infDPS->prest = (object) [
            'CNPJ'    => '11222333000181',
            'fone'    => '34999999999',
            'email'   => 'contato@exemplo.com.br',
            'regTrib' => (object) ['opSimpNac' => '1', 'regEspTrib' => '0'],
        ];

        $std->infDPS->toma = (object) [
            'CPF'   => '12345678909',
            'xNome' => 'TOMADOR DE EXEMPLO',
            'end'   => (object) [
                'endNac'  => (object) ['cMun' => '3170206', 'CEP' => '38400000'],
                'xLgr'    => 'RUA DO TOMADOR',
                'nro'     => '80',
                'xCpl'    => 'APT',
                'xBairro' => 'BAIRRO EXEMPLO',
            ],
            'email' => 'tomador@exemplo.com.br',
        ];

        $std->infDPS->serv = (object) [
            'locPrest' => (object) ['cLocPrestacao' => '3170206'],
            'cServ'    => (object) [
                'cTribNac'  => '060401',
                'xDescServ' => 'NF referente ao pagamento da parcela mensal',
                'cNBS'      => '122051200',
            ],
        ];

        $std->infDPS->valores = (object) [
            'vServPrest' => (object) ['vServ' => '441.60'],
            'trib'       => (object) [
                'tribMun' => (object) ['tribISSQN' => '1', 'tpRetISSQN' => '1'],
                'totTrib' => (object) [
                    'vTotTrib' => (object) [
                        'vTotTribFed' => '0.00',
                        'vTotTribEst' => '0.00',
                        'vTotTribMun' => '0.00',
                    ],
                ],
            ],
        ];

        return $std;
    }

    /**
     * O grupo IBSCBS exatamente como saiu na nota autorizada: só o mínimo.
     */
    private function stdComIbsCbsMinimo(): stdClass
    {
        $std = $this->stdBase();

        $std->infDPS->IBSCBS = (object) [
            'finNFSe'  => '0',
            'indFinal' => '0',
            'cIndOp'   => '030101',
            'indDest'  => '0',
            'valores'  => (object) [
                'trib' => (object) [
                    'gIBSCBS' => (object) [
                        'CST'        => '000',
                        'cClassTrib' => '000001',
                    ],
                ],
            ],
        ];

        return $std;
    }
}
