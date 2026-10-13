<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QuantumTecnology\NfseNacional\DanfseSimples;
use Smalot\PdfParser\Parser;

/**
 * DANFSe — representação gráfica da NFS-e.
 *
 * A referência é o par XML + PDF OFICIAL de duas notas reais autorizadas:
 *
 *  - Americana/SP nº 62  — sem IBS/CBS (fixture `..._americana_sem_ibscbs.xml`)
 *  - Uberlândia/MG       — com IBS/CBS real (fixture `..._uberlandia.xml`)
 *
 * IMPORTANTE: todo valor esperado aqui é lido do XML, NUNCA do que a lib produz
 * hoje. Vários destes testes nascem VERMELHOS de propósito — eles descrevem o
 * DANFSe correto (o que o documento oficial mostra), não o atual. Congelar o
 * comportamento atual seria congelar os bugs.
 *
 * Asseveramos CONTEÚDO (texto extraído do PDF), não pixels: o objetivo é
 * proteger a informação fiscal enquanto o layout é reescrito.
 */
final class DanfseTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Sanidade — o PDF é gerado
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function geraUmPdfValidoAPartirDoXmlAutorizado(): void
    {
        $pdf = $this->render('nfse_autorizada_americana_sem_ibscbs.xml');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, mb_strlen($pdf, '8bit'));
    }

    #[Test]
    public function imprimeAChaveDeAcessoEOsNumerosDaNota(): void
    {
        $texto = $this->texto('nfse_autorizada_americana_sem_ibscbs.xml');

        $this->assertStringContainsString(
            '35016081251052008000132000000000006226061409079490',
            $this->semEspacos($texto),
            'A chave de acesso precisa sair íntegra no DANFSe.',
        );

        $this->assertStringContainsString('62', $texto, 'Número da NFS-e.');
        $this->assertStringContainsString('61', $texto, 'Número da DPS.');
    }

    /*
    |--------------------------------------------------------------------------
    | Valores tributários — devem vir de infNFSe/valores (calculado pela SEFAZ)
    |--------------------------------------------------------------------------
    |
    | O parser lê os valores do DPS enviado, onde tribMun só tem tribISSQN e
    | tpRetISSQN — sem vBC/pAliq/vISSQN. Por isso BC e alíquota saem zerados.
    | Os valores corretos vivem em infNFSe/valores.
    */

    #[Test]
    public function imprimeABaseDeCalculoCalculadaPelaSefaz(): void
    {
        $texto = $this->texto('nfse_autorizada_americana_sem_ibscbs.xml');

        // infNFSe/valores/vBC = 17551.49
        $this->assertStringContainsString(
            '17.551,49',
            $texto,
            'A BC do ISSQN vem de infNFSe/valores/vBC, não do DPS.',
        );
    }

    #[Test]
    public function imprimeOsValoresDeIssApuradoPelaSefaz(): void
    {
        $texto = $this->texto('nfse_autorizada_uberlandia.xml');

        // infNFSe/valores: vBC=441.60, pAliqAplic=3.00, vISSQN=13.25
        $this->assertStringContainsString('441,60', $texto, 'BC do ISSQN (vBC).');
        $this->assertStringContainsString('13,25', $texto, 'ISSQN apurado (vISSQN).');
        $this->assertMatchesRegularExpression(
            '/3,00\s*%/u',
            $texto,
            'Alíquota aplicada (pAliqAplic) — hoje sai 0,00% porque é lida do DPS.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Regime tributário — opSimpNac ≠ regEspTrib
    |--------------------------------------------------------------------------
    |
    | opSimpNac tem domínio próprio (1=não optante, 2=MEI, 3=ME/EPP) e NÃO pode
    | ser traduzido pela tabela de regEspTrib. Com o de-para trocado, opSimpNac=3
    | vira "Sociedade de Profissionais" — o oficial diz "Optante ... (ME/EPP)".
    */

    #[Test]
    public function classificaOSimplesNacionalPelaTabelaDeOpSimpNac(): void
    {
        $texto = $this->texto('nfse_autorizada_americana_sem_ibscbs.xml');

        // opSimpNac = 3 → Optante - Microempresa ou Empresa de Pequeno Porte
        $this->assertMatchesRegularExpression(
            '/ME\s*\/\s*EPP|Pequeno Porte/ui',
            $texto,
            'opSimpNac=3 é ME/EPP.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/Sociedade de Profissionais/ui',
            $texto,
            'Sintoma do de-para trocado: a tabela de regEspTrib aplicada a opSimpNac.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Município — o XML entrega o nome pronto; não é preciso tabela IBGE
    |--------------------------------------------------------------------------
    |
    | O parser lê `xMun`, que NÃO existe em enderNac (só cMun). Os nomes já vêm
    | em xLocEmi / xLocPrestacao / xLocIncid.
    */

    #[Test]
    public function imprimeONomeDoMunicipioEmVezDeDeixarEmBranco(): void
    {
        $texto = $this->texto('nfse_autorizada_americana_sem_ibscbs.xml');

        $this->assertStringContainsString(
            'Americana',
            $texto,
            'xLocEmi/xLocPrestacao/xLocIncid trazem o nome do município.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Códigos de tributação — o XML traz a descrição, não só o código
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function imprimeADescricaoDosCodigosDeTributacao(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        // xTribNac = "08.02 - Instrução, treinamento, orientação pedagógica..."
        $this->assertMatchesRegularExpression(
            '/Instru[çc][ãa]o|treinamento/ui',
            $texto,
            'xTribNac traz a descrição do código nacional.',
        );

        // xTribMun = "8599603 - Treinamento em informática"
        $this->assertStringContainsString(
            '8599603',
            $texto,
            'xTribMun traz o código municipal, hoje impresso vazio.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IBS/CBS — Reforma Tributária (obrigatório a partir de 01/08/2026)
    |--------------------------------------------------------------------------
    |
    | Decisão de produto: o bloco é SEMPRE visível — com zeros quando a nota não
    | tiver o grupo.
    */

    #[Test]
    public function imprimeOsValoresDeIbsCbsQuandoANotaTemOGrupo(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_uberlandia.xml'));

        $this->assertMatchesRegularExpression('/IBS/u', $texto, 'Rótulo do IBS.');
        $this->assertMatchesRegularExpression('/CBS/u', $texto, 'Rótulo da CBS.');

        // totCIBS: vIBSTot=0.43, vCBS=3.86, vTotNF=441.60
        $this->assertStringContainsString('0,43', $texto, 'vIBSTot.');
        $this->assertStringContainsString('3,86', $texto, 'vCBS.');
    }

    #[Test]
    public function mostraOBlocoIbsCbsZeradoQuandoANotaNaoTemOGrupo(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        $this->assertMatchesRegularExpression(
            '/IBS/u',
            $texto,
            'O bloco IBS/CBS é sempre exibido, mesmo sem o grupo no XML.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Acentuação dos rótulos
    |--------------------------------------------------------------------------
    |
    | As fontes core do FPDF são ISO-8859-1 e não cobrem acentuação; o pacote
    | distribui uma TTF própria (storage/fonts/). Se o registro da fonte falhar,
    | o documento continua sendo gerado — porém sem acento, silenciosamente.
    | Este teste é o que denuncia essa regressão.
    */

    #[Test]
    public function imprimeOsRotulosAcentuadosComoODocumentoOficial(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        foreach ([
            'TRIBUTAÇÃO MUNICIPAL',
            'Município',
            'Código de Tributação Nacional',
            'SERVIÇO PRESTADO',
            // O v2.0 usa o rótulo da NT ("Indicador Municipal (Inscrição)") no
            // lugar de "Inscrição Municipal"; o que este teste protege é o
            // acento, então basta a palavra acentuada comum às duas versões.
            'Inscrição',
            'Endereço',
            'NÚMERO DA NFS-E',
            'COMPETÊNCIA DA NFS-E',
        ] as $rotulo) {
            $this->assertStringContainsString(
                $rotulo,
                $texto,
                "Rótulo sem acento — a fonte do pacote não foi aplicada: {$rotulo}",
            );
        }
    }

    #[Test]
    public function naoCorrompeAcentosVindosDoXml(): void
    {
        $texto = $this->texto('nfse_autorizada_uberlandia.xml');

        $this->assertStringContainsString('Uberlândia', $texto);
        // Dupla conversão de encoding produziria "UberlÃ¢ndia".
        $this->assertStringNotContainsString('Ã¢', $texto, 'Sinal de dupla conversão UTF-8.');
    }

    #[Test]
    public function truncaTextoLongoSemQuebrarOsAcentos(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        // O corte de texto longo não pode partir um caractere acentuado ao meio
        // (sintoma: "Instru??o"). No v2.0 a descrição impressa é a MUNICIPAL
        // quando existe (regra do item 2.4.5) — nesta nota, "Treinamento em
        // informática" —, e a descrição do serviço vem do XML com acento.
        $this->assertStringContainsString('informática', $texto);
        $this->assertStringContainsString('Aviso Prévio', $texto, 'xDescServ não pode sumir.');
        $this->assertStringNotContainsString('??', $texto, 'Corte no meio de caractere multibyte.');
    }

    /*
    |--------------------------------------------------------------------------
    | Seções que o documento oficial traz
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function declaraQueNaoHaIntermediarioQuandoANotaNaoTem(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        // O oficial dedica uma faixa ao grupo mesmo quando ele não existe: a
        // ausência do intermediário é informação fiscal, não um vazio a omitir.
        // A frase é a da nota 2 do item 2.4.5 da NT nº 008.
        $this->assertStringContainsString(
            'INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            $texto,
        );
    }

    #[Test]
    public function imprimeASecaoDeTotaisAproximadosDosTributos(): void
    {
        $texto = $this->normaliza($this->texto('nfse_autorizada_americana_sem_ibscbs.xml'));

        // Lei 12.741/2012 — informação obrigatória. No v2.0 ela deixa de ser um
        // bloco próprio e passa a integrar "Informações Complementares", na
        // frase fixa da nota 10 do item 2.4.5.
        $this->assertStringContainsString('Totais Aproximados dos Tributos', $texto);
        $this->assertStringContainsString('Lei nº 12.741/2012', $texto);
        $this->assertStringContainsString('Federais', $texto);
        $this->assertStringContainsString('Estaduais', $texto);
        $this->assertStringContainsString('Municipais', $texto);
    }

    #[Test]
    public function mostraOOrgaoEmissorNoCabecalhoQuandoInformado(): void
    {
        // Estes dados NÃO vêm no XML — o portal os tem em cadastro próprio.
        // Quem os tiver informa por setOrgaoEmissor(); sem isso o espaço fica
        // livre, em vez de exibir dado inventado.
        $xml = file_get_contents(__DIR__ . '/Fixtures/nfse_autorizada_americana_sem_ibscbs.xml');

        $nivel = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        try {
            $pdf = (new DanfseSimples($xml))
                ->setOrgaoEmissor([
                    'nome'       => 'Prefeitura Municipal de Americana',
                    'secretaria' => 'Secretaria de Fazenda',
                    'fone'       => '(19)3475-9049',
                    'email'      => 'iss@americana.sp.gov.br',
                ])
                ->render();
        } finally {
            error_reporting($nivel);
        }

        $arquivo = tempnam(sys_get_temp_dir(), 'danfse_') . '.pdf';
        file_put_contents($arquivo, $pdf);

        try {
            $texto = $this->normaliza((new Parser())->parseFile($arquivo)->getText());
        } finally {
            @unlink($arquivo);
        }

        $this->assertStringContainsString('Prefeitura Municipal de Americana', $texto);
        $this->assertStringContainsString('Secretaria de Fazenda', $texto);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function render(string $fixture): string
    {
        $xml = file_get_contents(__DIR__ . '/Fixtures/' . $fixture);

        $this->assertIsString($xml, "Fixture não encontrada: {$fixture}");

        // O FPDF emite notices/deprecations em algumas células; não é o objeto
        // deste teste. Silenciamos via error_reporting (sem mexer na pilha de
        // handlers, que a lib também usa e nem sempre restaura).
        $nivelAnterior = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        try {
            return (new DanfseSimples($xml))->render();
        } finally {
            error_reporting($nivelAnterior);
        }
    }

    private function texto(string $fixture): string
    {
        $pdf = $this->render($fixture);

        $arquivo = tempnam(sys_get_temp_dir(), 'danfse_') . '.pdf';
        file_put_contents($arquivo, $pdf);

        try {
            return (new Parser())->parseFile($arquivo)->getText();
        } finally {
            @unlink($arquivo);
        }
    }

    /**
     * O PDF quebra células em posições arbitrárias; para procurar a chave de
     * acesso (50 dígitos) é preciso remover os espaços intercalados.
     */
    private function semEspacos(string $texto): string
    {
        return preg_replace('/\s+/u', '', $texto) ?? $texto;
    }

    /**
     * Colapsa espaços/quebras para que asserções de frase não dependam de onde
     * o renderer quebrou a linha.
     */
    private function normaliza(string $texto): string
    {
        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }
}
