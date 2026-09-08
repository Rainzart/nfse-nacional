<?php

namespace Hadder\NfseNacional\Tests;

use Hadder\NfseNacional\Tomador\PessoaLookup;
use Hadder\NfseNacional\Tomador\PessoaTomador;
use Hadder\NfseNacional\Tomador\TomadorLookupException;
use Hadder\NfseNacional\Tomador\TomadorResolver;
use PHPUnit\Framework\TestCase;

class TomadorResolverTest extends TestCase
{
    private function lookupFalso(): PessoaLookup
    {
        return new class implements PessoaLookup {
            public function consultarCpf(string $cpf): PessoaTomador
            {
                return new PessoaTomador(
                    tipo: 'cpf',
                    documento: '11144477735',
                    nome: 'Fulano de Tal',
                    logradouro: 'Rua das Flores',
                    numero: '100',
                    complemento: 'Apto 2',
                    bairro: 'Centro',
                    cep: '30110000',
                    cidade: 'Belo Horizonte',
                    uf: 'MG',
                    codigoMunicipioIbge: '3106200'
                );
            }

            public function consultarCnpj(string $cnpj): PessoaTomador
            {
                return new PessoaTomador(
                    tipo: 'cnpj',
                    documento: '27272134000118',
                    nome: 'ALAS Tecnologia LTDA',
                    logradouro: 'Avenida Brasil',
                    numero: '',
                    complemento: '',
                    bairro: 'Savassi',
                    cep: '30140071',
                    cidade: 'Belo Horizonte',
                    uf: 'MG',
                    codigoMunicipioIbge: '3106200',
                    fantasia: 'ALAS',
                    simplesNacional: true,
                    situacao: 'Ativa',
                    porte: 'Demais'
                );
            }
        };
    }

    public function testPorCpfMontaGrupoTomaComEndereco(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porCpf('111.444.777-35');

        $this->assertObjectHasProperty('CPF', $toma);
        $this->assertObjectNotHasProperty('CNPJ', $toma);
        $this->assertSame('11144477735', $toma->CPF);
        $this->assertSame('Fulano de Tal', $toma->xNome);
        $this->assertSame('Rua das Flores', $toma->end->xLgr);
        $this->assertSame('100', $toma->end->nro);
        $this->assertSame('Apto 2', $toma->end->xCpl);
        $this->assertSame('Centro', $toma->end->xBairro);
        $this->assertSame('3106200', $toma->end->endNac->cMun);
        $this->assertSame('30110000', $toma->end->endNac->CEP);
    }

    public function testPorCnpjUsaCnpjEDadosDaMatriz(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porCnpj('27.272.134/0001-18');

        $this->assertObjectHasProperty('CNPJ', $toma);
        $this->assertObjectNotHasProperty('CPF', $toma);
        $this->assertSame('27272134000118', $toma->CNPJ);
        $this->assertSame('ALAS Tecnologia LTDA', $toma->xNome);
        $this->assertSame('Avenida Brasil', $toma->end->xLgr);
        $this->assertSame('Savassi', $toma->end->xBairro);
        $this->assertSame('3106200', $toma->end->endNac->cMun);
    }

    public function testNumeroVazioViraSemNumero(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porCnpj('27272134000118');

        $this->assertSame('S/N', $toma->end->nro);
    }

    public function testComplementoVazioNaoEIncluido(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porCnpj('27272134000118');

        $this->assertObjectNotHasProperty('xCpl', $toma->end);
    }

    public function testPorDocumentoDetectaCpf(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porDocumento('111.444.777-35');

        $this->assertObjectHasProperty('CPF', $toma);
    }

    public function testPorDocumentoDetectaCnpj(): void
    {
        $resolver = new TomadorResolver($this->lookupFalso());
        $toma = $resolver->porDocumento('27.272.134/0001-18');

        $this->assertObjectHasProperty('CNPJ', $toma);
    }

    public function testPorDocumentoInvalidoLancaExcecao(): void
    {
        $this->expectException(TomadorLookupException::class);
        $resolver = new TomadorResolver($this->lookupFalso());
        $resolver->porDocumento('123');
    }
}
