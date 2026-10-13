<?php

declare(strict_types = 1);

namespace QuantumTecnology\NfseNacional\Danfse\Layout;

use QuantumTecnology\NfseNacional\Danfse\AbstractDanfse;

/**
 * DANFSe v2.0 — modelo oficial do Anexo I da Nota Técnica nº 008 v1.02.
 *
 * Diferente do {@see LayoutV1}, que desenha em fluxo (cada seção continua de
 * onde a anterior parou), aqui cada campo tem COORDENADA ABSOLUTA em cm, lida
 * da tabela do item 2.4.5 da NT. É o que permite conferir o resultado contra o
 * modelo oficial campo a campo — e o que faz o documento caber na única página
 * que a NT exige (item 2.2).
 *
 * As medidas ficam reunidas em {@see self::Y} e nas constantes de coluna, em vez
 * de espalhadas como somas ao longo do desenho: assim a página inteira é
 * conferível contra a NT sem executar nada.
 *
 * Blocos suprimíveis (itens 2.3 e notas 2/3/4) deslocam o que vem abaixo. Por
 * isso o desenho acumula um deslocamento vertical ($this->deslocamento) em vez
 * de usar o Y da tabela cru: a NT diz, na nota 2, que "as coordenadas (X/Y) dos
 * blocos devem ser ajustadas conforme as informações preenchidas na NFS-e".
 */
class LayoutV2 implements LayoutDanfseInterface
{
    /**
     * Margem esquerda do corpo impresso (cm) — item 2.4.5.
     */
    public const X0 = 0.30;

    /**
     * Colunas do formulário (cm). Quatro colunas de 5,09 de largura.
     */
    public const X1 = 5.41;
    public const X2 = 10.51;
    public const X3 = 15.62;

    /**
     * Largura do corpo impresso e das colunas (cm).
     */
    public const LARGURA_TOTAL = 20.40;
    public const LARGURA_COL   = 5.09;
    public const LARGURA_COL2  = 10.19;

    /**
     * Altura padrão de uma linha de campo (rótulo + conteúdo), em cm.
     */
    public const ALTURA_CAMPO = 0.63;

    /**
     * Altura da faixa de título de bloco, em cm.
     */
    public const ALTURA_TITULO = 0.45;

    /**
     * Altura da faixa de um bloco suprimido (notas 2, 3 e 4 do item 2.4.5).
     *
     * A NT fixa o mínimo em 0,32cm; usamos 0,45 — a mesma altura de um título
     * de bloco — para o texto não ficar espremido contra as divisórias.
     */
    public const ALTURA_SUPRIMIDO = 0.45;

    /**
     * Y de cada bloco conforme o item 2.4.5 (cm, a partir da margem superior).
     *
     * São os valores da NT. O desenho aplica o deslocamento acumulado por cima
     * destes, para respeitar a nota 2 quando um bloco é suprimido.
     */
    public const Y = [
        'cabecalho'      => 0.30,
        'dados_nfse'     => 1.48,
        'prestador'      => 4.34,
        'tomador'        => 6.92,
        'destinatario'   => 8.86,
        'intermediario'  => 10.80,
        'servico'        => 12.74,
        'issqn'          => 14.43,
        'federal'        => 17.02,
        'ibscbs'         => 18.32,
        'total'          => 20.90,
        'complementares' => 22.27,
        'canhoto'        => 28.10,
    ];

    /**
     * Tamanhos de fonte exigidos pelo item 2.4 (pontos).
     */
    public const FONTE_TITULO_DOC   = 9;  // "DANFSe v2.0" e subtítulo
    public const FONTE_MUNICIPIO    = 8;  // município do emitente
    public const FONTE_AMBIENTE     = 6;  // ambGer / tpAmb / texto do QR
    public const FONTE_CONTEUDO     = 7;  // conteúdo dos campos
    public const FONTE_ROTULO       = 6;  // rótulos (labels) dos campos
    public const FONTE_TITULO_BLOCO = 7;  // títulos dos blocos

    /**
     * Cinza claro (5% de densidade) do item 2.2.3, em RGB.
     */
    public const CINZA_5 = 242;

    /**
     * Cinza K35 das marcas d'água (item 2.5), em RGB.
     */
    public const CINZA_K35 = 166;

    /**
     * @var AbstractDanfse
     */
    private $danfse;

    /**
     * @var \NFePHP\DA\Legacy\Pdf
     */
    private $pdf;

    /**
     * Dados já interpretados da nota.
     *
     * @var array
     */
    private $dados = [];

    /**
     * Fonte ativa.
     *
     * @var string
     */
    private $fonte = 'times';

    /**
     * Deslocamento vertical acumulado pelas supressões (cm).
     *
     * @var float
     */
    private $deslocamento = 0.0;

    /**
     * Espaço extra (cm) que as supressões dos blocos de partes liberaram e que
     * é devolvido à descrição do serviço.
     *
     * @var float
     */
    private $folgaParaServico = 0.0;

    /**
     * Desenha o documento no modelo oficial da NT nº 008.
     *
     * A ordem dos blocos é a do Anexo I e não pode ser alterada: o item 2.2.4
     * diz que "a disposição de campos deve obrigatoriamente obedecer ao
     * disposto no respectivo anexo".
     *
     * @param string|null $logo
     *
     * @return void
     */
    public function desenha(AbstractDanfse $danfse, $logo = null)
    {
        $this->danfse = $danfse;
        $this->pdf    = $danfse->pdf();
        $this->dados  = $danfse->dadosDaNota();
        $this->fonte  = $danfse->fonte();

        $this->deslocamento = 0.0;

        // Linhas de 0,5pt; borda da página de 1pt (item 2.2.3).
        $this->pdf->setLineWidth(0.5 / 2.83465);

        $this->desenhaCabecalho($logo);
        $this->desenhaDadosNfse();
        $this->desenhaPrestador();
        $this->desenhaTomador();
        $this->desenhaDestinatario();
        $this->desenhaIntermediario();

        // O espaço liberado pelos blocos suprimidos acima é devolvido à
        // descrição do serviço — o campo de conteúdo livre, que é o que mais
        // sofre com truncagem. Mas só o que ela precisa: devolver tudo abriria
        // um vão no meio da página, e a NT manda que a sobra fique nas
        // informações complementares (item 2.5.3).
        //
        // O teto é o espaço efetivamente liberado: pedir mais que isso empurraria
        // o resto do documento para fora da página única exigida pelo item 2.2.
        $this->folgaParaServico = max(0.0, min(
            -$this->deslocamento,
            $this->folgaQueADescricaoAproveita()
        ));

        // O bloco de serviço sobe com todo o deslocamento das supressões,
        // fechando o vão que sobraria acima dele.
        $this->desenhaServico();

        // Os blocos seguintes sobem junto, MENOS a folga reservada à descrição:
        // é essa diferença que abre espaço para o texto entre a descrição e o
        // ISSQN. Somar a folga ao deslocamento (movendo os dois) não adiantava —
        // a distância entre eles ficava igual. O que sobra desce para o fim da
        // página, com as informações complementares (item 2.5.3).
        $this->deslocamento += $this->folgaParaServico;

        $this->desenhaIssqn();
        $this->desenhaTributacaoFederal();
        $this->desenhaIbsCbs();
        $this->desenhaValorTotal();
        $this->desenhaInformacoesComplementares();

        $this->desenhaBordaDaPagina();
        $this->desenhaMarcaDagua();
    }

    /*
    |--------------------------------------------------------------------------
    | Cabeçalho e identificação (itens 2.1.1, 2.1.2 e 2.4.3)
    |--------------------------------------------------------------------------
    */

    /**
     * Cabeçalho: logo à esquerda, título ao centro, município/ambiente à direita.
     *
     * @param string|null $logo
     *
     * @return void
     */
    private function desenhaCabecalho($logo = null)
    {
        $infNfse = $this->dados['infNfse'];
        $y       = self::Y['cabecalho'];

        // Fundo cinza 5% do cabeçalho (item 2.2.3). Com a marca oficial (ou em
        // texto), o cinza cobre o cabeçalho inteiro: o pacote traz a logo já
        // composta sobre esse cinza, porque o FPDF não desenha PNG com
        // transparência. Com a logo do integrador, que tem fundo próprio, o
        // cinza começa À DIREITA dela — pintar por baixo deixaria um retângulo
        // destacado em volta.
        $inicioDoCinza = $this->danfse->marcaAceitaFundoCinza($logo)
            ? self::X0
            : 4.60; // fim da caixa da logomarca (0,49 + 4,00) + folga

        $this->pdf->setFillColor(self::CINZA_5, self::CINZA_5, self::CINZA_5);
        $this->pdf->rect(
            $this->mm($inicioDoCinza),
            $this->mm($y),
            $this->mm(self::X0 + self::LARGURA_TOTAL - $inicioDoCinza),
            $this->mm(1.16),
            'F'
        );
        $this->pdf->setFillColor(255, 255, 255);

        // Moldura do cabeçalho, por cima do preenchimento.
        $this->pdf->rect(
            $this->mm(self::X0),
            $this->mm($y),
            $this->mm(self::LARGURA_TOTAL),
            $this->mm(1.16)
        );

        // Logomarca da NFS-e — 0,85 × 4,00 @ 0,49/0,44.
        $this->danfse->desenhaMarcaNoLayout($this->mm(0.49), $this->mm(0.44), $this->mm(4.00), $this->mm(0.85), $logo);

        // Título centralizado no quadro de 10,19 a partir de X 5,41.
        $this->pdf->setFont($this->fonte, 'B', self::FONTE_TITULO_DOC);
        $this->pdf->setXY($this->mm(self::X1), $this->mm($y) + 1);
        $this->pdf->cell($this->mm(self::LARGURA_COL2), 4, 'DANFSe v2.0', 0, 0, 'C');

        $this->pdf->setXY($this->mm(self::X1), $this->mm($y) + 4.6);
        $this->pdf->cell($this->mm(self::LARGURA_COL2), 4, 'Documento Auxiliar da NFS-e', 0, 0, 'C');

        // Homologação: expressão obrigatória em vermelho sólido (item 2.4.3).
        if (2 === (int) ($infNfse['tipo_ambiente'] ?? 2)) {
            $this->pdf->setTextColor(237, 28, 36); // M100/Y100
            $this->pdf->setXY($this->mm(self::X1), $this->mm($y) + 8.2);
            $this->pdf->cell($this->mm(self::LARGURA_COL2), 3, 'NFS-e SEM VALIDADE JURÍDICA', 0, 0, 'C');
            $this->pdf->setTextColor(0, 0, 0);
        }

        // Município do emitente (8pt) e ambiente (6pt), à direita.
        $this->pdf->setFont($this->fonte, '', self::FONTE_MUNICIPIO);
        $this->pdf->setXY($this->mm(self::X3) + 0.6, $this->mm(0.30) + 0.4);
        $this->pdf->cell(
            $this->mm(self::LARGURA_COL) - 1.2,
            3,
            $this->trunca(
                $this->juntaTexto('Município: ', $this->municipioDoEmitente()),
                $this->mm(self::LARGURA_COL) - 1.6,
                '',
                self::FONTE_MUNICIPIO
            ),
            0,
            0,
            'L'
        );

        // Órgão emissor (Prefeitura/Secretaria), quando informado por
        // setOrgaoEmissor(). Não vem do XML, então só aparece se o integrador
        // tiver o dado — fica abaixo do subtítulo, sem disputar espaço com o
        // quadro de município/ambiente nem com o QR.
        $this->desenhaOrgaoEmissor();

        $this->pdf->setFont($this->fonte, '', self::FONTE_AMBIENTE);
        $this->pdf->setXY($this->mm(self::X3) + 0.6, $this->mm(0.97));
        $this->pdf->cell($this->mm(self::LARGURA_COL) - 1.2, 2.4, 'Ambiente Gerador: ' . $this->descricaoAmbienteGerador(), 0, 0, 'L');

        $this->pdf->setXY($this->mm(self::X3) + 0.6, $this->mm(1.22));
        $this->pdf->cell($this->mm(self::LARGURA_COL) - 1.2, 2.4, 'Tipo de Ambiente: ' . $this->descricaoTipoAmbiente(), 0, 0, 'L');
    }

    /**
     * Espaço extra (cm) que a descrição do serviço consegue aproveitar.
     *
     * Mede quantas linhas a descrição realmente ocupa e devolve só o que falta
     * para elas caberem. Sem esse limite, uma nota com muitos blocos suprimidos
     * ganharia um vão em branco no meio da página.
     *
     * @return float
     */
    private function folgaQueADescricaoAproveita()
    {
        $descricao = trim((string) ($this->dados['servico']['descricao'] ?? ''));

        if ('' === $descricao) {
            return 0.0;
        }

        $this->pdf->setFont($this->fonte, '', self::FONTE_CONTEUDO);

        $larguraMm = $this->mm(self::LARGURA_TOTAL) - 1.2;

        // Quantas linhas o texto ocupa nesta largura.
        $linhas = max(1, (int) ceil(
            $this->pdf->getStringWidth($this->paraFonte($descricao)) / $larguraMm
        ));

        // Altura que a descrição precisa, em cm, com uma linha de respiro antes
        // do bloco seguinte. Como os blocos abaixo sobem com o mesmo
        // deslocamento deste, o espaço entre a descrição e o ISSQN é EXATAMENTE
        // esta reserva — não sobra nem falta.
        $necessario = (($linhas + 1) * 2.8) / 10;

        // Espaço que o bloco já tem pelas coordenadas da NT (1,33 é o offset da
        // descrição dentro do bloco de serviço).
        $disponivel = self::Y['issqn'] - (self::Y['servico'] + 1.33);

        return max(0.0, $necessario - $disponivel);
    }

    /**
     * Identificação do órgão emissor no cabeçalho, quando informada.
     *
     * Esses dados (Prefeitura, Secretaria, contato) NÃO estão no XML — o portal
     * os obtém de cadastro próprio por município. Quem os tiver informa por
     * {@see AbstractDanfse::setOrgaoEmissor()}; sem eles o espaço fica livre,
     * em vez de o documento exibir dado inventado.
     *
     * @return void
     */
    private function desenhaOrgaoEmissor()
    {
        $orgao = $this->dados['orgaoEmissor'];

        if ([] === $orgao) {
            return;
        }

        $linhas = array_values(array_filter([
            $orgao['nome'] ?? '',
            $orgao['secretaria'] ?? '',
        ], static fn ($v) => '' !== trim((string) $v)));

        if ([] === $linhas) {
            return;
        }

        $this->pdf->setFont($this->fonte, '', self::FONTE_AMBIENTE);

        foreach ($linhas as $i => $linha) {
            $this->pdf->setXY($this->mm(self::X1), $this->mm(0.86) + ($i * 2.2));
            $this->pdf->cell(
                $this->mm(self::LARGURA_COL2),
                2.2,
                $this->trunca($linha, $this->mm(self::LARGURA_COL2), '', self::FONTE_AMBIENTE),
                0,
                0,
                'C'
            );
        }
    }

    /**
     * Bloco "DADOS DA NFS-e" + QR Code (itens 2.1.1, 2.1.2 e 2.4.3).
     *
     * @return void
     */
    private function desenhaDadosNfse()
    {
        $infNfse = $this->dados['infNfse'];
        $y       = self::Y['dados_nfse'];

        $this->pdf->rect(
            $this->mm(self::X0),
            $this->mm($y),
            $this->mm(self::LARGURA_TOTAL),
            $this->mm(2.84)
        );

        // Chave de acesso: bloco único de 50 dígitos, sem o prefixo "NFS"
        // (0,77 × 15,30 @ 0,30/1,48). Tem altura própria na tabela da NT — o
        // campo padrão de 0,63 apertaria rótulo e número um sobre o outro.
        $this->pdf->setFont($this->fonte, 'B', self::FONTE_ROTULO);
        $this->pdf->setXY($this->mm(self::X0) + 0.6, $this->mm($y) + 0.4);
        $this->pdf->cell($this->mm(15.30) - 1.2, 2.4, 'CHAVE DE ACESSO DA NFS-E', 0, 0, 'L');

        $this->pdf->setFont($this->fonte, '', self::FONTE_CONTEUDO);
        $this->pdf->setXY($this->mm(self::X0) + 0.6, $this->mm($y) + 3.4);
        $this->pdf->cell($this->mm(15.30) - 1.2, 3, $this->chaveDeAcesso(), 0, 0, 'L');

        $this->campo('NÚMERO DA NFS-E', $infNfse['numero'] ?? '', self::X0, 2.27);
        $this->campo('COMPETÊNCIA DA NFS-E', $this->data($infNfse['competencia'] ?? '', 'd/m/Y'), self::X1, 2.27);
        $this->campo('DATA E HORA DA EMISSÃO DA NFS-E', $this->data($infNfse['data_processamento'] ?? $infNfse['data_emissao'] ?? ''), self::X2, 2.27);

        $this->campo('NÚMERO DA DPS', $infNfse['numero_dps'] ?? '', self::X0, 2.96);
        $this->campo('SÉRIE DA DPS', $infNfse['serie_dps'] ?? '', self::X1, 2.96);
        $this->campo('DATA E HORA DA EMISSÃO DA DPS', $this->data($infNfse['data_emissao_dps'] ?? ''), self::X2, 2.96);

        $this->campo('EMITENTE DA NFS-E', $this->descricaoEmitente(), self::X0, 3.65, self::LARGURA_COL, true);
        $this->campo('SITUAÇÃO DA NFS-E', $this->descricaoSituacao(), self::X1, 3.65);
        $this->campo('FINALIDADE', $this->descricaoFinalidade(), self::X2, 3.65);

        // QR Code em 1,52 × 1,52 @ X 17,48 / Y 1,67 (item 2.4.3).
        $this->danfse->desenhaQrCodeNoLayout($this->mm(17.48), $this->mm(1.67), $this->mm(1.52));

        // Texto de autenticidade em 3 linhas, 6pt, abaixo do QR.
        $this->pdf->setFont($this->fonte, '', self::FONTE_AMBIENTE);
        $linhas = [
            'A autenticidade desta NFS-e pode ser verificada',
            'pela leitura deste código QR ou pela consulta da',
            'chave de acesso no portal nacional da NFS-e',
        ];

        foreach ($linhas as $i => $linha) {
            $this->pdf->setXY($this->mm(15.80), $this->mm(3.36) + ($i * 2.2));
            $this->pdf->cell($this->mm(4.72), 2.2, $linha, 0, 0, 'L');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Partes da operação (itens 2.1.3 a 2.1.6)
    |--------------------------------------------------------------------------
    */

    /**
     * Bloco "PRESTADOR / FORNECEDOR" (item 2.1.3).
     *
     * Único bloco de parte que nunca é suprimido — sem prestador não há nota.
     *
     * @return void
     */
    private function desenhaPrestador()
    {
        $p   = $this->dados['prestador'];
        $end = $p['endereco'] ?? [];
        $y   = $this->y('prestador');

        $this->tituloBloco('PRESTADOR / FORNECEDOR', $y, self::ALTURA_TITULO, self::LARGURA_COL, 2.58);

        $this->campo('CNPJ / CPF / NIF', $this->documento($p), self::X1, $y);
        $this->campo('Indicador Municipal (Inscrição)', $p['inscricao_municipal'] ?? '', self::X2, $y);
        $this->campo('Telefone', $p['fone'] ?? '', self::X3, $y);
        $this->divisoriasDaLinha($y);

        $this->campo('Nome / Nome Empresarial', $p['razao_social'] ?? '', self::X0, $y + 0.64, self::LARGURA_COL2);
        $this->campo('Município / Sigla UF', $end['municipio'] ?? '', self::X2, $y + 0.64);
        $this->campo('Código IBGE / CEP', $this->ibgeComCep($end), self::X3, $y + 0.64);
        $this->divisoriasDaLinha($y + 0.64, [self::X3]);

        $this->campo('Endereço', $this->enderecoEmLinha($end), self::X0, $y + 1.28, self::LARGURA_COL2);
        $this->campo('E-mail', $p['email'] ?? '', self::X2, $y + 1.28, self::LARGURA_COL2);

        $this->campo('Simples Nacional na Data de Competência', $p['optante_simples_descricao'] ?? $this->descricaoSimplesNacional(), self::X0, $y + 1.94);
        $this->campo('Regime de Apuração Tributária pelo SN', $this->descricaoRegimeApuracao(), self::X2, $y + 1.94, self::LARGURA_COL2);
    }

    /**
     * Bloco "TOMADOR / ADQUIRENTE" (item 2.1.4), suprimível pela nota 2.
     *
     * @return void
     */
    private function desenhaTomador()
    {
        $t   = $this->dados['tomador'];
        $end = $t['endereco'] ?? [];

        if ($this->parteVazia($t)) {
            $this->suprimeBloco('TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', 'tomador');

            return;
        }

        $y = $this->y('tomador');

        $this->tituloBloco('TOMADOR / ADQUIRENTE', $y, self::ALTURA_TITULO, self::LARGURA_COL, 1.94);

        $this->campo('CNPJ / CPF / NIF', $this->documento($t), self::X1, $y);
        $this->campo('Indicador Municipal (Inscrição)', $t['inscricao_municipal'] ?? '', self::X2, $y);
        $this->campo('Telefone', $t['fone'] ?? '', self::X3, $y);
        $this->divisoriasDaLinha($y);

        $this->campo('Nome / Nome Empresarial', $t['razao_social'] ?? '', self::X0, $y + 0.64, self::LARGURA_COL2);
        $this->campo('Município / Sigla UF', $end['municipio'] ?? '', self::X2, $y + 0.64);
        $this->campo('Código IBGE / CEP', $this->ibgeComCep($end), self::X3, $y + 0.64);

        $this->campo('Endereço', $this->enderecoEmLinha($end), self::X0, $y + 1.30, self::LARGURA_COL2);
        $this->campo('E-mail', $t['email'] ?? '', self::X2, $y + 1.30, self::LARGURA_COL2);
    }

    /**
     * Bloco "DESTINATÁRIO DA OPERAÇÃO" (item 2.1.5) — novo no v2.0.
     *
     * Tem duas supressões próprias: sem dados (nota 2) e, quando o destinatário
     * é o próprio tomador, a frase da nota 3. Repare que este bloco não tem
     * Indicador Municipal, ao contrário dos demais.
     *
     * @return void
     */
    private function desenhaDestinatario()
    {
        $d   = $this->dados['destinatario'];
        $end = $d['endereco'] ?? [];

        if ($this->parteVazia($d)) {
            $this->suprimeBloco('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', 'destinatario');

            return;
        }

        if ($this->destinatarioEhOTomador()) {
            $this->suprimeBloco('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO', 'destinatario');

            return;
        }

        $y = $this->y('destinatario');

        $this->tituloBloco('DESTINATÁRIO DA OPERAÇÃO', $y, self::ALTURA_TITULO, self::LARGURA_COL, 1.94);

        $this->campo('CNPJ / CPF / NIF', $this->documento($d), self::X1, $y);
        $this->campo('Telefone', $d['fone'] ?? '', self::X3, $y);
        $this->divisoriasDaLinha($y, [self::X3]);

        $this->campo('Nome / Nome Empresarial', $d['razao_social'] ?? '', self::X0, $y + 0.64, self::LARGURA_COL2);
        $this->campo('Município / Sigla UF', $d['municipio'] ?? '', self::X2, $y + 0.64);
        $this->campo('Código IBGE / CEP', $this->ibgeComCep($d), self::X3, $y + 0.64);

        $this->campo('Endereço', $this->enderecoEmLinha($end), self::X0, $y + 1.30, self::LARGURA_COL2);
        $this->campo('E-mail', $d['email'] ?? '', self::X2, $y + 1.30, self::LARGURA_COL2);
    }

    /**
     * Bloco "INTERMEDIÁRIO DA OPERAÇÃO" (item 2.1.6), suprimível pela nota 2.
     *
     * @return void
     */
    private function desenhaIntermediario()
    {
        $i = $this->dados['intermediario'];

        if ($this->parteVazia($i)) {
            $this->suprimeBloco('INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', 'intermediario');

            return;
        }

        $y = $this->y('intermediario');

        $this->tituloBloco('INTERMEDIÁRIO DA OPERAÇÃO', $y, self::ALTURA_TITULO, self::LARGURA_COL, 1.94);

        $this->campo('CNPJ / CPF / NIF', $this->documento($i), self::X1, $y);
        $this->campo('Indicador Municipal (Inscrição)', $i['inscricao_municipal'] ?? '', self::X2, $y);
        $this->campo('Telefone', $i['fone'] ?? '', self::X3, $y);
        $this->divisoriasDaLinha($y);

        $this->campo('Nome / Nome Empresarial', $i['razao_social'] ?? '', self::X0, $y + 0.64, self::LARGURA_COL2);
        $this->campo('Município / Sigla UF', $i['municipio'] ?? '', self::X2, $y + 0.64);
        $this->campo('Código IBGE / CEP', $this->ibgeComCep($i), self::X3, $y + 0.64);

        $this->campo('Endereço', $this->enderecoEmLinha($i['endereco'] ?? []), self::X0, $y + 1.30, self::LARGURA_COL2);
        $this->campo('E-mail', $i['email'] ?? '', self::X2, $y + 1.30, self::LARGURA_COL2);
    }

    /*
    |--------------------------------------------------------------------------
    | Serviço e tributação (itens 2.1.7 a 2.1.11)
    |--------------------------------------------------------------------------
    */

    /**
     * Bloco "SERVIÇO PRESTADO" (item 2.1.7).
     *
     * @return void
     */
    private function desenhaServico()
    {
        $s = $this->dados['servico'];

        // O deslocamento já inclui a folga reservada à descrição: o bloco sobe e
        // o espaço extra fica entre a descrição e o ISSQN.
        $y = $this->y('servico');

        $this->tituloBloco('SERVIÇO PRESTADO', $y);

        $this->campo('Código de Tributação Nacional / Municipal', $this->codigosDeTributacao(), self::X1, $y);
        $this->campo('Código da NBS', $s['codigo_nbs'] ?? '', self::X2, $y);
        $this->campo('Local da Prestação / Sigla UF / País', $this->localDaPrestacao(), self::X3, $y);
        $this->divisoriasDaLinha($y);

        // Descrição do código de tributação: a NT diz explicitamente que este
        // campo NÃO tem rótulo no DANFSe (item 2.4.5).
        $this->pdf->setFont($this->fonte, '', self::FONTE_ROTULO);
        $this->pdf->setXY($this->mm(self::X0) + 0.6, $this->mm($y + 0.65));
        $this->pdf->cell(
            $this->mm(self::LARGURA_TOTAL) - 1.2,
            2.8,
            $this->trunca(
                $this->descricaoDoCodigoDeTributacao(),
                $this->mm(self::LARGURA_TOTAL) - 1.6,
                '',
                self::FONTE_ROTULO
            ),
            0,
            0,
            'L'
        );

        // Descrição do serviço: 0,63 × 20,40 @ 0,30/13,79 na tabela da NT
        // (offset 1,05 sobre o início do bloco).
        $this->pdf->setFont($this->fonte, 'B', self::FONTE_ROTULO);
        $this->pdf->setXY($this->mm(self::X0) + 0.6, $this->mm($y + 1.00));
        $this->pdf->cell($this->mm(self::LARGURA_TOTAL) - 1.2, 2.4, 'Descrição do Serviço', 0, 0, 'L');

        // A descrição é livre (até 1300 caracteres) e o espaço até o bloco
        // seguinte é finito: sem limitar as linhas, um texto longo empurraria o
        // ISSQN para fora da página — e a NT exige página única (item 2.2).
        // O espaço aproveitável cresce quando blocos acima foram suprimidos.
        $inicio = $y + 1.33;

        $this->pdf->setFont($this->fonte, '', self::FONTE_CONTEUDO);
        $this->escreveEmLinhas(
            (string) ($s['descricao'] ?? ''),
            self::X0,
            $inicio,
            self::LARGURA_TOTAL,
            // O ISSQN é desenhado com a folga reservada SOMADA ao deslocamento
            // (ver desenha()), então o limite real da descrição é o Y deslocado
            // mais essa folga — é justamente o espaço aberto para ela.
            $this->linhasCabemAte($inicio, $this->y('issqn') + $this->folgaParaServico),
            2.8
        );
    }

    /**
     * Bloco "TRIBUTAÇÃO MUNICIPAL (ISSQN)" (item 2.1.8), suprimível pela nota 4.
     *
     * @return void
     */
    private function desenhaIssqn()
    {
        $s       = $this->dados['servico'];
        $valores = $s['valores'] ?? [];

        // tribISSQN = 2 (não incidência) / 4 (imune) descrevem operação fora do
        // campo do ISSQN — é o caso da supressão da nota 4.
        if (!$this->sujeitaAoIssqn()) {
            $this->suprimeBloco('TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN', 'issqn');

            return;
        }

        $y = $this->y('issqn');

        $this->tituloBloco('TRIBUTAÇÃO MUNICIPAL (ISSQN)', $y, self::ALTURA_TITULO, self::LARGURA_COL, 2.59);

        // Linhas em 14,43 / 15,08 / 15,73 / 16,37 na tabela da NT — a primeira
        // divide a faixa com o título do bloco, como no Anexo I.
        $this->campo('Tipo de Tributação do ISSQN', $this->descricaoTipoTributacao(), self::X1, $y);
        $this->campo('Município / Sigla UF / País de Incidência do ISSQN', $this->localDaIncidencia(), self::X2, $y, self::LARGURA_COL2);

        $this->campo('Regime Especial de Tributação do ISSQN', $this->descricaoRegimeEspecial(), self::X0, $y + 0.65);
        $this->campo('Tipo de Imunidade do ISSQN', $s['tipo_imunidade'] ?? '', self::X1, $y + 0.65);
        $this->campo('Suspensão da Exigibilidade do ISSQN', $s['suspensao_exigibilidade'] ?? '', self::X2, $y + 0.65);
        $this->campo('Número Processo Suspensão', $s['numero_processo_suspensao'] ?? '', self::X3, $y + 0.65);
        $this->divisoriasDaLinha($y + 0.65);

        $this->campo('Benefício Municipal', $s['beneficio_municipal'] ?? '', self::X0, $y + 1.30);
        $this->campo('Cálculo do BM', $this->valorOuVazio($valores['calculo_bm'] ?? 0), self::X1, $y + 1.30);
        $this->campo('Total Deduções/Reduções', $this->dinheiro($valores['deducoes'] ?? 0), self::X2, $y + 1.30);
        $this->campo('Desconto Incondicionado', $this->dinheiro($valores['desconto_incondicionado'] ?? 0), self::X3, $y + 1.30);
        $this->divisoriasDaLinha($y + 1.30);

        $this->campo('BC ISSQN', $this->dinheiro($valores['base_calculo'] ?? 0), self::X0, $y + 1.94);
        $this->campo('Alíquota Aplicada', $this->percentual($valores['aliquota'] ?? 0), self::X1, $y + 1.94);
        $this->campo('Retenção do ISSQN', $this->descricaoRetencaoIssqn(), self::X2, $y + 1.94);
        $this->campo('ISSQN Apurado', $this->dinheiro($valores['iss'] ?? 0), self::X3, $y + 1.94);
        $this->divisoriasDaLinha($y + 1.94);
    }

    /**
     * Bloco "TRIBUTAÇÃO FEDERAL (EXCETO CBS)" (item 2.1.9).
     *
     * A linha de PIS/COFINS de débito próprio só é impressa para competência
     * até o fim de 2026 (nota 6).
     *
     * @return void
     */
    private function desenhaTributacaoFederal()
    {
        $valores = $this->dados['servico']['valores'] ?? [];
        $y       = $this->y('federal');

        $this->tituloBloco('TRIBUTAÇÃO FEDERAL (EXCETO CBS)', $y, self::ALTURA_TITULO, self::LARGURA_COL, 1.30);

        // Linhas em 17,02 e 17,67 na tabela da NT (offset 0 e 0,65).
        $this->campo('IRRF', $this->dinheiro($valores['ir'] ?? 0), self::X1, $y);
        $this->campo('Contribuição Previdenciária - Retida', $this->dinheiro($valores['inss'] ?? 0), self::X2, $y);
        $this->campo('Contribuições Sociais - Retidas', $this->dinheiro($valores['contribuicoes_sociais_retidas'] ?? 0), self::X3, $y);
        $this->divisoriasDaLinha($y);

        // A linha de PIS/COFINS de débito próprio só vale até o fim de 2026
        // (nota 6); depois disso, os dois tributos são substituídos pela CBS.
        if (!$this->competenciaAte2026()) {
            return;
        }

        $this->campo('PIS - Débito Apuração Própria', $this->dinheiro($valores['pis_debito_proprio'] ?? 0), self::X0, $y + 0.65);
        $this->campo('COFINS - Débito Apuração Própria', $this->dinheiro($valores['cofins_debito_proprio'] ?? 0), self::X1, $y + 0.65);
        $this->campo('Descrição Contrib. Sociais - Retidas', $this->descricaoRetencaoPisCofins(), self::X2, $y + 0.65, self::LARGURA_COL2);
    }

    /**
     * Bloco "TRIBUTAÇÃO IBS / CBS" (item 2.1.10).
     *
     * @return void
     */
    private function desenhaIbsCbs()
    {
        $ibs = $this->dados['ibsCbs'];
        $y   = $this->y('ibscbs');

        $this->tituloBloco('TRIBUTAÇÃO IBS / CBS', $y, self::ALTURA_TITULO, self::LARGURA_COL, 2.58);

        $this->campo('CST / cClassTrib', $this->cstComClassificacao(), self::X1, $y);
        $this->campo(
            'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF',
            $this->indicadorDeOperacao(),
            self::X2,
            $y,
            self::LARGURA_COL2
        );

        $this->campo('Exclusões e Reduções da Base de Cálculo', $this->dinheiro($ibs['exclusoes_reducoes_bc'] ?? 0), self::X0, $y + 0.64);
        $this->campo('Base de Cálculo Após Exclusões e Reduções', $this->dinheiro($ibs['base_calculo'] ?? 0), self::X1, $y + 0.64);
        $this->campo('Red. Alíquota IBS / Red. Alíquota CBS', $this->reducoesDeAliquota(), self::X2, $y + 0.64);
        $this->campo('Alíquota - IBS UF / IBS Mun', $this->aliquotasIbs(), self::X3, $y + 0.64);
        $this->divisoriasDaLinha($y + 0.64);

        $this->campo('Alíq. Efetiva Municipal - IBS', $this->percentual($ibs['aliquota_efetiva_ibs_mun'] ?? 0), self::X0, $y + 1.29);
        $this->campo('Valor Apurado Municipal - IBS', $this->dinheiro($ibs['valor_ibs_mun'] ?? 0), self::X1, $y + 1.29);
        $this->campo('Alíq. Efetiva Estadual - IBS', $this->percentual($ibs['aliquota_efetiva_ibs_uf'] ?? 0), self::X2, $y + 1.29);
        $this->campo('Valor Apurado Estadual - IBS', $this->dinheiro($ibs['valor_ibs_uf'] ?? 0), self::X3, $y + 1.29);
        $this->divisoriasDaLinha($y + 1.29);

        $this->campo('Valor Total Apurado - IBS', $this->dinheiro($ibs['valor_ibs'] ?? 0), self::X0, $y + 1.94);
        $this->campo('Alíquota - CBS', $this->percentual($ibs['aliquota_cbs'] ?? 0), self::X1, $y + 1.94);
        $this->campo('Alíquota Efetiva - CBS', $this->percentual($ibs['aliquota_efetiva_cbs'] ?? 0), self::X2, $y + 1.94);
        $this->campo('Valor Total Apurado - CBS', $this->dinheiro($ibs['valor_cbs'] ?? 0), self::X3, $y + 1.94);
        $this->divisoriasDaLinha($y + 1.94);
    }

    /**
     * Bloco "VALOR TOTAL DA NFS-E" (item 2.1.11).
     *
     * @return void
     */
    private function desenhaValorTotal()
    {
        $valores = $this->dados['servico']['valores'] ?? [];
        $ibs     = $this->dados['ibsCbs'];
        $y       = $this->y('total');

        $this->tituloBloco('VALOR TOTAL DA NFS-E', $y, self::ALTURA_TITULO, self::LARGURA_COL, 1.37);

        $this->campo('VALOR DA OPERAÇÃO / SERVIÇO', $this->dinheiro($valores['servicos'] ?? 0), self::X1, $y);
        $this->campo('Desconto Incondicionado', $this->dinheiro($valores['desconto_incondicionado'] ?? 0), self::X2, $y);
        $this->campo('Desconto Condicionado', $this->dinheiro($valores['desconto_condicionado'] ?? 0), self::X3, $y);
        $this->divisoriasDaLinha($y);

        $this->campo('Total das Retenções (ISSQN / Federais)', $this->dinheiro($valores['total_retencoes'] ?? 0), self::X0, $y + 0.69);
        $this->campo('VALOR LÍQUIDO DA NFS-e', $this->dinheiro($valores['valor_liquido'] ?? 0), self::X1, $y + 0.69);
        $this->campo('Total do IBS/CBS', $this->dinheiro(($ibs['valor_ibs'] ?? 0) + ($ibs['valor_cbs'] ?? 0)), self::X2, $y + 0.69);

        // Único campo de valor com sombreamento obrigatório (item 2.2.3).
        $this->campo(
            'VALOR LÍQUIDO DA NFS-e + IBS/CBS',
            $this->dinheiro($ibs['valor_total'] ?? 0),
            self::X3,
            $y + 0.69,
            self::LARGURA_COL,
            true
        );
    }

    /**
     * Bloco "INFORMAÇÕES COMPLEMENTARES" (item 2.1.12).
     *
     * A linha de Totais Aproximados dos Tributos (Lei 12.741/2012) é
     * OBRIGATÓRIA aqui — não é um bloco à parte, como no layout v1.
     *
     * @return void
     */
    private function desenhaInformacoesComplementares()
    {
        $y = $this->y('complementares');

        // Título ocupa a linha inteira neste bloco, como no Anexo I.
        $this->tituloBloco('INFORMAÇÕES COMPLEMENTARES', $y, 0.39, self::LARGURA_TOTAL);

        // O bloco vai até o pé da página — é ele que absorve o espaço livre, e
        // o único cuja altura a NT permite ajustar (item 2.5.3).
        $inicio = $y + 0.45;

        $this->pdf->setFont($this->fonte, '', self::FONTE_CONTEUDO);
        $this->escreveEmLinhas(
            $this->textoComplementar(),
            self::X0,
            $inicio,
            self::LARGURA_TOTAL,
            $this->linhasCabemAte($inicio, self::Y['cabecalho'] + 28.40, 2.9),
            2.9
        );
    }

    /**
     * Imprime a frase de bloco não identificado e encolhe o espaço restante.
     *
     * A NT (nota 2) fixa a altura mínima do bloco suprimido em 0,32cm e manda
     * ajustar o Y dos blocos seguintes — é o que abre espaço para o documento
     * caber numa página só.
     *
     * @param string $frase
     * @param string $bloco Chave de {@see self::Y}
     *
     * @return void
     */
    private function suprimeBloco($frase, $bloco)
    {
        $this->faixaTexto($frase, $this->y($bloco), self::ALTURA_SUPRIMIDO);

        // O bloco ocuparia do seu Y até o Y do bloco seguinte (a faixa de título
        // já está DENTRO dessa distância); passa a ocupar só a faixa da nota 2.
        // A altura vem da própria tabela Y: antes era passada à mão e somava o
        // título de novo — com o tomador suprimido, o destinatário subia para
        // cima da faixa do tomador e as duas frases saíam sobrepostas.
        $this->deslocamento -= $this->alturaDoBloco($bloco) - self::ALTURA_SUPRIMIDO;
    }

    /**
     * Altura cheia de um bloco (cm): a distância até o bloco seguinte em
     * {@see self::Y}.
     *
     * @param string $bloco
     *
     * @return float
     */
    private function alturaDoBloco($bloco)
    {
        $chaves = array_keys(self::Y);
        $indice = array_search($bloco, $chaves, true);

        return self::Y[$chaves[$indice + 1]] - self::Y[$bloco];
    }

    /*
    |--------------------------------------------------------------------------
    | Primitivas de desenho
    |--------------------------------------------------------------------------
    |
    | A NT especifica tudo em centímetros; o FPDF trabalha em milímetros. A
    | conversão acontece num lugar só (mm()), para que as constantes acima
    | possam ser comparadas diretamente com a tabela do item 2.4.5.
    */

    /**
     * Converte centímetros (a unidade da NT) em milímetros (a do PDF).
     *
     * @param float $cm
     *
     * @return float
     */
    private function mm($cm)
    {
        return $cm * 10;
    }

    /**
     * Y absoluto de um bloco, já com o deslocamento das supressões.
     *
     * @param string $bloco Chave de {@see self::Y}
     *
     * @return float Em centímetros
     */
    private function y($bloco)
    {
        return self::Y[$bloco] + $this->deslocamento;
    }

    /**
     * Título de bloco: faixa cinza 5% com o nome em caixa alta (item 2.2.3).
     *
     * No modelo oficial o título ocupa apenas a PRIMEIRA COLUNA — os campos da
     * mesma linha seguem à direita dele (veja o Anexo I: "PRESTADOR /
     * FORNECEDOR" divide a linha com "CNPJ / CPF / NIF"). Só os blocos que
     * abrem uma linha inteira (informações complementares, canhoto) usam a
     * largura toda.
     *
     * @param string $texto
     * @param float  $y       Em cm
     * @param float  $altura  Em cm
     * @param float  $largura Em cm
     *
     * @return void
     */
    private function tituloBloco($texto, $y, $altura = self::ALTURA_TITULO, $largura = self::LARGURA_COL, $alturaBloco = 0.0)
    {
        $larguraMm = $this->mm($largura);

        // Grade do bloco: divisórias entre as colunas (item 2.2.3).
        //
        // Apenas a FAIXA DO TÍTULO recebe a divisória de X1 descendo inteira —
        // ali as quatro colunas sempre existem. Nas linhas seguintes cada bloco
        // desenha as suas ({@see divisoriasDaLinha}), porque campos de coluna
        // dupla (Nome, Endereço, E-mail) atravessam X1 e X2: uma divisória
        // descendo o bloco todo cortaria o texto ao meio e invadiria a coluna
        // vizinha visualmente.
        if ($alturaBloco > 0) {
            $this->divisoriasVerticais($y, min($alturaBloco, self::ALTURA_CAMPO), [self::X1]);
        }

        $this->pdf->setFillColor(self::CINZA_5, self::CINZA_5, self::CINZA_5);
        $this->pdf->rect($this->mm(self::X0), $this->mm($y), $larguraMm, $this->mm($altura), 'F');
        $this->pdf->setFillColor(255, 255, 255);

        $this->pdf->setFont($this->fonte, 'B', self::FONTE_TITULO_BLOCO);
        $this->pdf->setXY($this->mm(self::X0) + 0.6, $this->mm($y) + 0.5);
        $this->pdf->cell(
            $larguraMm - 1.2,
            $this->mm($altura) - 1,
            $this->trunca($texto, $larguraMm - 1.6, 'B', self::FONTE_TITULO_BLOCO),
            0,
            0,
            'L'
        );

        // Linha divisória acima do bloco (0,5pt, item 2.2.3).
        $this->pdf->line(
            $this->mm(self::X0),
            $this->mm($y),
            $this->mm(self::X0 + self::LARGURA_TOTAL),
            $this->mm($y)
        );
    }

    /**
     * Divisórias de uma LINHA de quatro colunas.
     *
     * Use nas linhas em que as quatro colunas existem de fato; linhas com campo
     * de coluna dupla recebem só as divisórias que não o cortam.
     *
     * @param float $y  Topo da linha, em cm
     * @param array $xs Coordenadas X das divisórias, em cm
     *
     * @return void
     */
    private function divisoriasDaLinha($y, array $xs = [self::X2, self::X3])
    {
        $this->divisoriasVerticais($y, self::ALTURA_CAMPO, $xs);
    }

    /**
     * Separadores verticais entre as colunas de um bloco (item 2.2.3).
     *
     * No Anexo I cada bloco é uma grade: sem as divisórias, as colunas "flutuam"
     * e o documento deixa de parecer o oficial.
     *
     * @param float $y      Topo do bloco, em cm
     * @param float $altura Altura do bloco, em cm
     * @param array $xs     Coordenadas X das divisórias, em cm
     *
     * @return void
     */
    private function divisoriasVerticais($y, $altura, array $xs)
    {
        foreach ($xs as $x) {
            $this->pdf->line(
                $this->mm($x),
                $this->mm($y),
                $this->mm($x),
                $this->mm($y + $altura)
            );
        }
    }

    /**
     * Campo do formulário: rótulo pequeno em cima, conteúdo embaixo.
     *
     * É a unidade de repetição do modelo oficial — todo bloco é uma grade
     * destes. Campo sem informação no XML sai com traço (nota 12).
     *
     * @param string $rotulo
     * @param string $conteudo
     * @param float  $x        Em cm
     * @param float  $y        Em cm
     * @param float  $largura  Em cm
     * @param bool   $destaque Fundo cinza (item 2.2.3)
     *
     * @return void
     */
    private function campo($rotulo, $conteudo, $x, $y, $largura = self::LARGURA_COL, $destaque = false)
    {
        $larguraMm = $this->mm($largura);

        if ($destaque) {
            $this->pdf->setFillColor(self::CINZA_5, self::CINZA_5, self::CINZA_5);
            $this->pdf->rect($this->mm($x), $this->mm($y), $larguraMm, $this->mm(self::ALTURA_CAMPO), 'F');
            $this->pdf->setFillColor(255, 255, 255);
        }

        // Rótulo
        $this->pdf->setFont($this->fonte, 'B', self::FONTE_ROTULO);
        $this->pdf->setXY($this->mm($x) + 0.6, $this->mm($y) + 0.2);
        $this->pdf->cell($larguraMm - 1.2, 2.4, $this->trunca($rotulo, $larguraMm - 1.6, 'B', self::FONTE_ROTULO), 0, 0, 'L');

        // Conteúdo
        $this->pdf->setFont($this->fonte, '', self::FONTE_CONTEUDO);
        $this->pdf->setXY($this->mm($x) + 0.6, $this->mm($y) + 2.6);
        $this->pdf->cell(
            $larguraMm - 1.2,
            3,
            $this->trunca($this->ouTraco($conteudo), $larguraMm - 1.6, '', self::FONTE_CONTEUDO),
            0,
            0,
            'L'
        );
    }

    /**
     * Linha de texto simples, sem rótulo (usada nas frases de supressão).
     *
     * @param string $texto
     * @param float  $y      Em cm
     * @param float  $altura Em cm
     *
     * @return void
     */
    private function faixaTexto($texto, $y, $altura = 0.32)
    {
        $alturaMm = $this->mm($altura);

        $this->pdf->setFont($this->fonte, 'B', self::FONTE_TITULO_BLOCO);
        $this->pdf->setXY($this->mm(self::X0), $this->mm($y));
        $this->pdf->cell($this->mm(self::LARGURA_TOTAL), $alturaMm, $texto, 0, 0, 'L');

        // Só as linhas de topo e base, sem as laterais: a borda da página já
        // fecha os lados, e `cell(..., 1, ...)` desenharia um retângulo isolado
        // no meio do formulário.
        $x1 = $this->mm(self::X0);
        $x2 = $this->mm(self::X0 + self::LARGURA_TOTAL);

        $this->pdf->line($x1, $this->mm($y), $x2, $this->mm($y));
        $this->pdf->line($x1, $this->mm($y) + $alturaMm, $x2, $this->mm($y) + $alturaMm);
    }

    /**
     * Corta o texto que não cabe na largura, terminando em reticências.
     *
     * O `cell()` do FPDF não recorta: o que não cabe invade a coluna vizinha.
     *
     * @param string $texto
     * @param float  $larguraMm
     * @param string $estilo
     * @param int    $tamanho
     *
     * @return string
     */
    private function trunca($texto, $larguraMm, $estilo, $tamanho)
    {
        $texto = (string) $texto;

        if ('' === $texto || $larguraMm <= 0) {
            return $texto;
        }

        $this->pdf->setFont($this->fonte, $estilo, $tamanho);

        return $this->danfse->truncaNoLayout($texto, $larguraMm);
    }

    /**
     * Campo vazio vira traço (nota 12 do item 2.4.5).
     *
     * @param string|null $valor
     *
     * @return string
     */
    private function ouTraco($valor)
    {
        $valor = trim((string) $valor);

        return '' === $valor ? '-' : $valor;
    }

    /**
     * Escreve texto longo em N linhas no máximo, truncando a última.
     *
     * Why: `multiCell()` cresce conforme o conteúdo, e num layout de coordenadas
     * absolutas isso invade o bloco seguinte — a NT exige página única, então
     * nenhum campo pode empurrar o que vem abaixo.
     *
     * @param string $texto
     * @param float  $x           Em cm
     * @param float  $y           Em cm
     * @param float  $largura     Em cm
     * @param int    $maxLinhas
     * @param float  $alturaLinha Em mm
     *
     * @return void
     */
    private function escreveEmLinhas($texto, $x, $y, $largura, $maxLinhas, $alturaLinha)
    {
        // Sem o modificador /u de propósito: os dados vindos do parse já estão
        // em ISO-8859-1, e `preg_replace` com /u devolve NULL em string que não
        // é UTF-8 válida — o texto acentuado sumia inteiro do documento, em
        // silêncio. \s basta aqui porque só normalizamos espaço em branco ASCII.
        $texto = trim(preg_replace('/\s+/', ' ', (string) $texto) ?? '');

        if ('' === $texto || $maxLinhas < 1) {
            return;
        }

        $larguraMm = $this->mm($largura) - 1.2;
        $palavras  = explode(' ', $texto);
        $linhas    = [];
        $atual     = '';

        foreach ($palavras as $palavra) {
            $tentativa = '' === $atual ? $palavra : $atual . ' ' . $palavra;

            if ($this->pdf->getStringWidth($this->paraFonte($tentativa)) <= $larguraMm) {
                $atual = $tentativa;

                continue;
            }

            if ('' !== $atual) {
                $linhas[] = $atual;
            }

            $atual = $palavra;

            if (count($linhas) >= $maxLinhas) {
                break;
            }
        }

        if ('' !== $atual && count($linhas) < $maxLinhas) {
            $linhas[] = $atual;
        }

        $linhas = array_slice($linhas, 0, $maxLinhas);

        // Sobrou texto? A última linha recebe as reticências (item 2.1).
        $escrito = implode(' ', $linhas);

        if (mb_strlen($escrito) < mb_strlen($texto) && [] !== $linhas) {
            $ultima   = array_pop($linhas);
            $linhas[] = $this->danfse->truncaNoLayout($ultima . ' ...', $larguraMm);
        }

        foreach ($linhas as $i => $linha) {
            $this->pdf->setXY($this->mm($x) + 0.6, $this->mm($y) + ($i * $alturaLinha));
            $this->pdf->cell($larguraMm, $alturaLinha, $linha, 0, 0, 'L');
        }
    }

    /**
     * Quantas linhas cabem entre duas coordenadas verticais.
     *
     * @param float $de          Em cm
     * @param float $ate         Em cm
     * @param float $alturaLinha Em mm
     *
     * @return int
     */
    private function linhasCabemAte($de, $ate, $alturaLinha = 2.8)
    {
        $disponivelMm = $this->mm($ate - $de) - 1;

        return max(0, (int) floor($disponivelMm / $alturaLinha));
    }

    /**
     * Converte um texto para o encoding da fonte (para medição).
     *
     * @param string $texto
     *
     * @return string
     */
    private function paraFonte($texto)
    {
        return \QuantumTecnology\NfseNacional\Danfse\PdfComFontes::paraEncodingDaFonte($texto);
    }

    /**
     * Concatena literais do código (UTF-8) com dados do XML (já em ISO-8859-1).
     *
     * Why: juntar os dois direto produz uma string que não é válida em nenhum
     * dos dois encodings, e a conversão do FPDF passa a tratá-la como ISO — o
     * "í" de "Município" (2 bytes UTF-8) vira "Ã­" no PDF. Converter o literal
     * ANTES da concatenação mantém a string inteira num encoding só.
     *
     * @param string $literal Texto UTF-8 escrito no código
     * @param string $dado    Texto vindo do parse do XML
     *
     * @return string
     */
    private function juntaTexto($literal, $dado)
    {
        return \QuantumTecnology\NfseNacional\Danfse\PdfComFontes::paraEncodingDaFonte($literal)
            . \QuantumTecnology\NfseNacional\Danfse\PdfComFontes::paraEncodingDaFonte((string) $dado);
    }

    /**
     * Valor monetário no formato do documento.
     *
     * @param float $valor
     *
     * @return string
     */
    private function dinheiro($valor)
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    /**
     * Percentual no formato do documento.
     *
     * @param float $valor
     *
     * @return string
     */
    private function percentual($valor)
    {
        return number_format((float) $valor, 2, ',', '.') . ' %';
    }

    /**
     * Valor monetário, ou vazio quando zerado (para campos da nota 5).
     *
     * @param float $valor
     *
     * @return string
     */
    private function valorOuVazio($valor)
    {
        return (float) $valor > 0 ? $this->dinheiro($valor) : '';
    }

    /*
    |--------------------------------------------------------------------------
    | Acabamento da página (itens 2.2.3 e 2.5)
    |--------------------------------------------------------------------------
    */

    /**
     * Borda da página, de 1 ponto (item 2.2.3).
     *
     * @return void
     */
    private function desenhaBordaDaPagina()
    {
        $this->pdf->setLineWidth(1 / 2.83465);
        $this->pdf->rect(
            $this->mm(self::X0),
            $this->mm(self::Y['cabecalho']),
            $this->mm(self::LARGURA_TOTAL),
            $this->mm(28.40)
        );
        $this->pdf->setLineWidth(0.5 / 2.83465);
    }

    /**
     * Marca d'água de nota cancelada ou substituída (item 2.5).
     *
     * A NT exige diagonal, mínimo 50pt e cinza K35. O FPDF não rotaciona texto,
     * então a diagonal é aproximada por linhas deslocadas — a exigência de
     * legibilidade e contraste é preservada.
     *
     * @return void
     */
    private function desenhaMarcaDagua()
    {
        $situacao = $this->situacaoParaMarcaDagua();

        if ('' === $situacao) {
            return;
        }

        $this->pdf->setTextColor(self::CINZA_K35, self::CINZA_K35, self::CINZA_K35);
        $this->pdf->setFont($this->fonte, 'B', 50);
        $this->pdf->setXY($this->mm(self::X0), $this->mm(13.00));
        $this->pdf->cell($this->mm(self::LARGURA_TOTAL), 20, $situacao, 0, 0, 'C');
        $this->pdf->setTextColor(0, 0, 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Conteúdo dos campos
    |--------------------------------------------------------------------------
    |
    | O leiaute guarda códigos; o DANFSe imprime a DESCRIÇÃO deles (item 2.4.5,
    | coluna "Outros Campos / Observações"). Imprimir o código cru deixaria o
    | documento ilegível para quem o recebe.
    */

    /**
     * Município do emitente para o cabeçalho, no formato "Município / UF".
     *
     * `xLocEmi` só existe no leiaute NACIONAL. Num XML ABRASF o campo não vem,
     * e sem o fallback pela tabela do IBGE o cabeçalho exibiria o código cru
     * ("Município: 3501608") — que não diz nada a quem recebe a nota.
     *
     * @return string
     */
    private function municipioDoEmitente()
    {
        $infNfse = $this->dados['infNfse'];

        $nome = trim((string) ($infNfse['local_emissao'] ?? ''));

        // Nome já resolvido: nada a fazer.
        if ('' !== $nome && !ctype_digit($nome)) {
            return $nome;
        }

        $codigo = '' !== $nome
            ? $nome
            : (string) ($this->dados['prestador']['endereco']['codigo_municipio'] ?? '');

        $resolvido = $this->danfse->formatarMunicipioNoLayout($codigo);

        return $this->ouTraco('' !== $resolvido ? $resolvido : $nome);
    }

    /**
     * Chave de acesso sem o prefixo "NFS" (item 2.4.5).
     *
     * @return string
     */
    private function chaveDeAcesso()
    {
        $chave = (string) ($this->dados['infNfse']['chave_acesso'] ?? '');

        return preg_replace('/^NFS/', '', $chave) ?? $chave;
    }

    /**
     * Documento da parte: CNPJ, CPF ou NIF, o que estiver preenchido.
     *
     * @return string
     */
    private function documento(array $parte)
    {
        foreach (['cnpj', 'cpf', 'nif'] as $chave) {
            $valor = trim((string) ($parte[$chave] ?? ''));

            if ('' !== $valor) {
                return $this->danfse->formatarDocumentoNoLayout($valor);
            }
        }

        return '';
    }

    /**
     * "Código IBGE / CEP" concatenados, como manda o item 2.4.5.
     *
     * @param array $fonte Endereço ou a própria parte
     *
     * @return string
     */
    private function ibgeComCep(array $fonte)
    {
        $ibge = trim((string) ($fonte['codigo_municipio'] ?? ''));
        $cep  = preg_replace('/\D/', '', (string) ($fonte['cep'] ?? '')) ?? '';

        if (8 === strlen($cep)) {
            $cep = substr($cep, 0, 5) . '-' . substr($cep, 5);
        }

        $partes = array_filter([$ibge, $cep], static fn ($v) => '' !== $v);

        return implode(' / ', $partes);
    }

    /**
     * Endereço numa linha: "logradouro, nro, complemento, bairro".
     *
     * @return string
     */
    private function enderecoEmLinha(array $end)
    {
        $partes = array_filter([
            $end['logradouro'] ?? '',
            $end['numero'] ?? '',
            $end['complemento'] ?? '',
            $end['bairro'] ?? '',
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(', ', $partes);
    }

    /**
     * Uma parte (tomador/destinatário/intermediário) sem identificação alguma.
     *
     * @return bool
     */
    private function parteVazia(array $parte)
    {
        if ([] === $parte) {
            return true;
        }

        foreach (['cnpj', 'cpf', 'nif', 'razao_social'] as $chave) {
            if ('' !== trim((string) ($parte[$chave] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Destinatário e tomador são a mesma pessoa (nota 3)?
     *
     * @return bool
     */
    private function destinatarioEhOTomador()
    {
        $dest = $this->documento($this->dados['destinatario']);
        $toma = $this->documento($this->dados['tomador']);

        return '' !== $dest && $dest === $toma;
    }

    /**
     * A operação está sujeita ao ISSQN (nota 4)?
     *
     * @return bool
     */
    private function sujeitaAoIssqn()
    {
        $valores = $this->dados['servico']['valores'] ?? [];

        // tribISSQN: 1 = operação tributável; 2 = exportação; 3 = não incidência;
        // 4 = imunidade. Só a primeira mantém o bloco com os dados de cálculo.
        return 1 === (int) ($valores['tipo_tributacao'] ?? 1);
    }

    /**
     * Competência dentro do ano-calendário de 2026 (nota 6).
     *
     * @return bool
     */
    private function competenciaAte2026()
    {
        $competencia = (string) ($this->dados['infNfse']['competencia'] ?? '');

        if ('' === $competencia) {
            return true;
        }

        $ano = (int) substr(preg_replace('/\D/', '', $competencia) ?? '', 0, 4);

        return 0 === $ano || $ano <= 2026;
    }

    /**
     * Ambiente gerador (ambGer): 1 = Prefeitura, 2 = Sistema Nacional NFS-e.
     *
     * @return string
     */
    private function descricaoAmbienteGerador()
    {
        $codigo = (string) ($this->dados['infNfse']['ambiente_gerador'] ?? '');

        $mapa = [
            '1' => 'Prefeitura',
            '2' => 'Sistema Nacional NFS-e',
        ];

        return $mapa[$codigo] ?? '-';
    }

    /**
     * Tipo de ambiente (tpAmb): 1 = Produção, 2 = Homologação.
     *
     * @return string
     */
    private function descricaoTipoAmbiente()
    {
        return 2 === (int) ($this->dados['infNfse']['tipo_ambiente'] ?? 2)
            ? 'Homologação'
            : 'Produção';
    }

    /**
     * Emitente da NFS-e (tpEmit): 1 = Prestador, 2 = Tomador, 3 = Intermediário.
     *
     * @return string
     */
    private function descricaoEmitente()
    {
        $mapa = [
            '1' => 'Prestador',
            '2' => 'Tomador',
            '3' => 'Intermediário',
        ];

        return $mapa[(string) ($this->dados['infNfse']['tipo_emitente'] ?? '1')] ?? 'Prestador';
    }

    /**
     * Situação da NFS-e a partir do cStat.
     *
     * @return string
     */
    private function descricaoSituacao()
    {
        $cStat = (int) ($this->dados['infNfse']['status'] ?? 0);

        if (100 === $cStat) {
            return 'NFS-e regular';
        }

        if (101 === $cStat) {
            return 'NFS-e cancelada';
        }

        return 0 === $cStat ? '' : 'NFS-e com cStat ' . $cStat;
    }

    /**
     * Finalidade da NFS-e (finNFSe).
     *
     * @return string
     */
    private function descricaoFinalidade()
    {
        $mapa = [
            '0' => 'NFS-e regular',
            '1' => 'NFS-e de Decisão Judicial ou Administrativa',
            '2' => 'NFS-e de Substituição',
            '3' => 'NFS-e de Complemento',
        ];

        return $mapa[(string) ($this->dados['infNfse']['finalidade'] ?? '0')] ?? 'NFS-e regular';
    }

    /**
     * Situação do Simples Nacional na data de competência (opSimpNac).
     *
     * @return string
     */
    private function descricaoSimplesNacional()
    {
        $mapa = [
            '1' => 'Não Optante',
            '2' => 'Optante - MEI',
            '3' => 'Optante - ME/EPP',
        ];

        return $mapa[(string) ($this->dados['prestador']['optante_simples'] ?? '')] ?? '-';
    }

    /**
     * Regime de apuração tributária pelo SN (regApTribSN).
     *
     * @return string
     */
    private function descricaoRegimeApuracao()
    {
        $mapa = [
            '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            '2' => 'Regime de apuração dos tributos federais pelo Simples Nacional e ISSQN por fora do Simples Nacional',
            '3' => 'Regime de apuração dos tributos federais e municipal por fora do Simples Nacional',
        ];

        return $mapa[(string) ($this->dados['prestador']['regime_tributacao'] ?? '')] ?? '-';
    }

    /**
     * Regime especial de tributação do ISSQN (regEspTrib).
     *
     * @return string
     */
    private function descricaoRegimeEspecial()
    {
        $mapa = [
            '0' => 'Nenhum',
            '1' => 'Ato Cooperado',
            '2' => 'Estimativa',
            '3' => 'Microempresa Municipal',
            '4' => 'Notário ou Registrador',
            '5' => 'Profissional Autônomo',
            '6' => 'Sociedade de Profissionais',
            '9' => 'Outros',
        ];

        return $mapa[(string) ($this->dados['servico']['regime_especial'] ?? '')] ?? '-';
    }

    /**
     * Tipo de tributação do ISSQN (tribISSQN).
     *
     * @return string
     */
    private function descricaoTipoTributacao()
    {
        $mapa = [
            '1' => 'Operação tributável',
            '2' => 'Exportação de serviço',
            '3' => 'Não Incidência',
            '4' => 'Imunidade',
        ];

        $codigo = (string) ($this->dados['servico']['valores']['tipo_tributacao'] ?? '1');

        return $mapa[$codigo] ?? '-';
    }

    /**
     * Retenção do ISSQN (tpRetISSQN).
     *
     * @return string
     */
    private function descricaoRetencaoIssqn()
    {
        $mapa = [
            '1' => 'Retido pelo Tomador',
            '2' => 'Retido pelo Intermediário',
            '3' => 'Não Retido',
        ];

        $codigo = (string) ($this->dados['servico']['valores']['iss_retido'] ?? '3');

        return $mapa[$codigo] ?? 'Não Retido';
    }

    /**
     * Descrição das contribuições sociais retidas (tpRetPisCofins).
     *
     * @return string
     */
    private function descricaoRetencaoPisCofins()
    {
        $codigo = (int) ($this->dados['servico']['valores']['ret_pis_cofins'] ?? 2);

        return 1 === $codigo ? 'PIS/COFINS Retido' : 'PIS/COFINS/CSLL Não Retido';
    }

    /**
     * "Código de Tributação Nacional / Municipal" concatenados.
     *
     * @return string
     */
    private function codigosDeTributacao()
    {
        $s = $this->dados['servico'];

        $partes = array_filter([
            $s['codigo_tributacao_nacional'] ?? '',
            $s['codigo_tributacao_municipio'] ?? '',
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(' / ', $partes);
    }

    /**
     * Descrição do código de tributação: a municipal quando houver, senão a
     * nacional (regra explícita do item 2.4.5).
     *
     * @return string
     */
    private function descricaoDoCodigoDeTributacao()
    {
        $s = $this->dados['servico'];

        $municipal = trim((string) ($s['descricao_tributacao_municipal'] ?? ''));

        if ('' !== $municipal) {
            return $municipal;
        }

        return (string) ($s['descricao_tributacao_nacional'] ?? $s['codigo_tributacao_nacional_descricao'] ?? '');
    }

    /**
     * "Local da Prestação / Sigla UF / País".
     *
     * @return string
     */
    private function localDaPrestacao()
    {
        $s = $this->dados['servico'];

        $partes = array_filter([
            $s['local_prestacao'] ?? '',
            $this->nomeDoPais($s['pais_prestacao'] ?? ''),
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(' / ', $partes);
    }

    /**
     * "Município / Sigla UF / País de Incidência do ISSQN".
     *
     * @return string
     */
    private function localDaIncidencia()
    {
        $partes = array_filter([
            $this->dados['infNfse']['local_incidencia'] ?? '',
            $this->nomeDoPais($this->dados['servico']['pais_resultado'] ?? ''),
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(' / ', $partes);
    }

    /**
     * Sigla do país (a NT pede o código ISO de 2 dígitos; "BR" no caso nacional).
     *
     * @param string $codigo
     *
     * @return string
     */
    private function nomeDoPais($codigo)
    {
        $codigo = trim((string) $codigo);

        if ('' === $codigo || '1058' === $codigo || 'BR' === $codigo) {
            return 'BR';
        }

        return $codigo;
    }

    /**
     * "CST / cClassTrib" concatenados.
     *
     * @return string
     */
    private function cstComClassificacao()
    {
        $ibs = $this->dados['ibsCbs'];

        $partes = array_filter([
            $ibs['cst'] ?? '',
            $ibs['classificacao_tributaria'] ?? '',
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(' / ', $partes);
    }

    /**
     * "Indicador de Operação / Código IBGE / Município / UF" concatenados.
     *
     * @return string
     */
    private function indicadorDeOperacao()
    {
        $ibs = $this->dados['ibsCbs'];

        $partes = array_filter([
            $ibs['indicador_operacao'] ?? '',
            $ibs['codigo_localidade_incidencia'] ?? '',
            $ibs['localidade_incidencia'] ?? '',
        ], static fn ($v) => '' !== trim((string) $v));

        return implode(' / ', $partes);
    }

    /**
     * "Red. Alíquota IBS / Red. Alíquota CBS" no formato "% / % / %".
     *
     * @return string
     */
    private function reducoesDeAliquota()
    {
        $ibs = $this->dados['ibsCbs'];

        return implode(' / ', [
            $this->percentual($ibs['reducao_aliquota_ibs_uf'] ?? 0),
            $this->percentual($ibs['reducao_aliquota_ibs_mun'] ?? 0),
            $this->percentual($ibs['reducao_aliquota_cbs'] ?? 0),
        ]);
    }

    /**
     * "Alíquota - IBS UF / IBS Mun" no formato "% / %".
     *
     * @return string
     */
    private function aliquotasIbs()
    {
        $ibs = $this->dados['ibsCbs'];

        return implode(' / ', [
            $this->percentual($ibs['aliquota_ibs_uf'] ?? 0),
            $this->percentual($ibs['aliquota_ibs_mun'] ?? 0),
        ]);
    }

    /**
     * Texto do bloco de informações complementares.
     *
     * A ordem dos itens e o separador (pipe) são os do item 2.4.5; a linha de
     * Totais Aproximados dos Tributos é obrigatória (nota 10) e vai por último,
     * como no modelo do Anexo I.
     *
     * @return string
     */
    private function textoComplementar()
    {
        $s       = $this->dados['servico'];
        $valores = $s['valores'] ?? [];
        $infNfse = $this->dados['infNfse'];

        $itens = [];

        foreach ([
            'Inf. Cont.'   => $s['info_complementar'] ?? '',
            'NFS-e Subst.' => $infNfse['chave_substituida'] ?? '',
            'Cod. Obra'    => $s['codigo_obra'] ?? '',
            'Insc. Imob.'  => $s['inscricao_imobiliaria'] ?? '',
            'Cod. Evt.'    => $s['codigo_evento'] ?? '',
        ] as $rotulo => $valor) {
            $valor = trim((string) $valor);

            if ('' !== $valor) {
                $itens[] = $rotulo . ': ' . $valor;
            }
        }

        $itens[] = sprintf(
            'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: %s ; Estaduais: %s ; Municipais: %s',
            $this->dinheiro($valores['total_tributos_federais'] ?? 0),
            $this->dinheiro($valores['total_tributos_estaduais'] ?? 0),
            $this->dinheiro($valores['total_tributos_municipais'] ?? 0)
        );

        return implode(' | ', $itens);
    }

    /**
     * Texto da marca d'água, quando a nota está cancelada ou substituída.
     *
     * @return string
     */
    private function situacaoParaMarcaDagua()
    {
        $cStat = (int) ($this->dados['infNfse']['status'] ?? 0);

        if (101 === $cStat) {
            return 'CANCELADA';
        }

        if ('' !== trim((string) ($this->dados['infNfse']['chave_substituta'] ?? ''))) {
            return 'SUBSTITUÍDA';
        }

        return '';
    }

    /**
     * Data no formato do documento.
     *
     * @param string $valor
     * @param string $formato
     *
     * @return string
     */
    private function data($valor, $formato = 'd/m/Y H:i:s')
    {
        return $this->danfse->formatarDataNoLayout($valor, $formato);
    }
}
