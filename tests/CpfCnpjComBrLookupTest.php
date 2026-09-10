<?php

namespace Hadder\NfseNacional\Tests;

use Hadder\NfseNacional\Tomador\CpfCnpjComBrLookup;
use Hadder\NfseNacional\Tomador\TomadorLookupException;
use PHPUnit\Framework\TestCase;

class CpfCnpjComBrLookupTest extends TestCase
{
    /**
     * @param string $resposta Corpo JSON a devolver pelo transporte
     * @param array<int,string> $urlsChamadas Referencia que coleta as URLs acessadas
     */
    private function lookupComResposta(string $resposta, array &$urlsChamadas, array $opcoes = []): CpfCnpjComBrLookup
    {
        $opcoes['transporte'] = function (string $url) use ($resposta, &$urlsChamadas): string {
            $urlsChamadas[] = $url;
            return $resposta;
        };

        return new CpfCnpjComBrLookup('5ae973d7a997af13f0aaf2bf60e65803', $opcoes);
    }

    public function testConsultarCpfMapeiaEnderecoEIbge(): void
    {
        $json = json_encode([
            'status' => 1,
            'cpf' => '11144477735',
            'nome' => 'Fulano de Tal',
            'endereco' => 'Rua das Flores',
            'numero' => '100',
            'complemento' => 'Apto 2',
            'bairro' => 'Centro',
            'cep' => '30110-000',
            'cidade' => 'Belo Horizonte',
            'uf' => 'MG',
            'ibge' => '3106200',
        ]);

        $urls = [];
        $lookup = $this->lookupComResposta($json, $urls, ['pacoteCpf' => 3]);
        $pessoa = $lookup->consultarCpf('111.444.777-35');

        $this->assertSame('cpf', $pessoa->tipo);
        $this->assertSame('Fulano de Tal', $pessoa->nome);
        $this->assertSame('Rua das Flores', $pessoa->logradouro);
        $this->assertSame('30110000', $pessoa->cep);
        $this->assertSame('3106200', $pessoa->codigoMunicipioIbge);
        $this->assertStringContainsString('/3/11144477735', $urls[0]);
    }

    public function testConsultarCnpjMapeiaMatrizEIbgeAninhado(): void
    {
        $json = json_encode([
            'status' => 1,
            'cnpj' => '27272134000118',
            'tipo' => 'MATRIZ',
            'razao' => 'ALAS Tecnologia LTDA',
            'fantasia' => 'ALAS',
            'matrizEndereco' => [
                'cep' => '30140-071',
                'logradouro' => 'Avenida Brasil',
                'numero' => '1500',
                'complemento' => 'Sala 3',
                'bairro' => 'Savassi',
                'cidade' => 'Belo Horizonte',
                'uf' => 'MG',
            ],
            'ibge' => ['cidade' => ['ibge_id' => '3106200']],
            'simplesNacional' => ['optante' => 'Sim'],
            'situacao' => ['nome' => 'Ativa'],
            'porte' => ['descricao' => 'Demais'],
        ]);

        $urls = [];
        $lookup = $this->lookupComResposta($json, $urls, ['pacoteCnpj' => 6]);
        $pessoa = $lookup->consultarCnpj('27.272.134/0001-18');

        $this->assertSame('cnpj', $pessoa->tipo);
        $this->assertSame('ALAS Tecnologia LTDA', $pessoa->nome);
        $this->assertSame('ALAS', $pessoa->fantasia);
        $this->assertSame('Avenida Brasil', $pessoa->logradouro);
        $this->assertSame('1500', $pessoa->numero);
        $this->assertSame('30140071', $pessoa->cep);
        $this->assertSame('3106200', $pessoa->codigoMunicipioIbge);
        $this->assertTrue($pessoa->simplesNacional);
        $this->assertSame('Ativa', $pessoa->situacao);
        $this->assertSame('Demais', $pessoa->porte);
        $this->assertStringContainsString('/6/27272134000118', $urls[0]);
    }

    public function testStatusZeroLancaExcecaoComMensagem(): void
    {
        $json = json_encode([
            'status' => 0,
            'erro' => 'Token invalido',
            'erroCodigo' => '401',
        ]);

        $urls = [];
        $lookup = $this->lookupComResposta($json, $urls);

        $this->expectException(TomadorLookupException::class);
        $this->expectExceptionMessageMatches('/Token invalido/');
        $lookup->consultarCpf('11144477735');
    }

    public function testTimeoutPadraoSegueRecomendacaoDaApi(): void
    {
        $lookup = new CpfCnpjComBrLookup('5ae973d7a997af13f0aaf2bf60e65803');
        $propriedade = new \ReflectionProperty(CpfCnpjComBrLookup::class, 'timeout');
        $propriedade->setAccessible(true);

        $this->assertSame(60, $propriedade->getValue($lookup));

        $personalizado = new CpfCnpjComBrLookup('5ae973d7a997af13f0aaf2bf60e65803', ['timeout' => 20]);
        $this->assertSame(20, $propriedade->getValue($personalizado));
    }

    public function testTokenVazioLancaExcecao(): void
    {
        $this->expectException(TomadorLookupException::class);
        new CpfCnpjComBrLookup('   ');
    }

    public function testCpfComTamanhoInvalidoLancaExcecao(): void
    {
        $urls = [];
        $lookup = $this->lookupComResposta('{}', $urls);

        $this->expectException(TomadorLookupException::class);
        $lookup->consultarCpf('123');
    }
}
