<?php

namespace Hadder\NfseNacional\Danfse;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;

class DanfseXmlData
{
    private DOMDocument $dom;
    private DOMXPath $xpath;

    public function __construct(string $xml)
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new InvalidArgumentException('XML da NFS-e não informado.');
        }

        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;

        libxml_use_internal_errors(true);
        $loaded = $this->dom->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!$loaded) {
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'XML inválido.';
            throw new InvalidArgumentException('Não foi possível carregar o XML da NFS-e: ' . $message);
        }

        $this->xpath = new DOMXPath($this->dom);
    }

    /** @return array<string, mixed> */
    public static function fromXml(string $xml): array
    {
        return (new self($xml))->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $infNFSe = $this->firstNode([
            '/*[local-name()="NFSe"]/*[local-name()="infNFSe"]',
            '//*[local-name()="infNFSe"]',
        ]);

        $infDPS = $this->firstNode([
            '//*[local-name()="DPS"]/*[local-name()="infDPS"]',
            '//*[local-name()="infDPS"]',
        ]);

        $idInfNfse = $infNFSe instanceof DOMElement ? (string) $infNFSe->getAttribute('Id') : '';
        $chaveAcesso = $this->firstText(['./*[local-name()="chNFSe"]', './*[local-name()="ChaveAcesso"]', './*[local-name()="chaveAcesso"]'], $infNFSe);
        if ($chaveAcesso === '' && $idInfNfse !== '') {
            $chaveAcesso = preg_replace('/^NFS/i', '', $idInfNfse) ?? '';
        }

        $urlConsulta = $this->firstText([
            '//*[local-name()="urlChave"]',
            '//*[local-name()="urlNfse"]',
            '//*[local-name()="LinkImpressao"]',
            '//*[local-name()="UrlVisualizacao"]',
            '//*[local-name()="urlConsulta"]',
            '//*[local-name()="qrCode"]',
        ]);
        if ($urlConsulta === '' && $chaveAcesso !== '') {
            $urlConsulta = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . $chaveAcesso;
        }

        // Ambiente: para a tarja do DANFSe, usar tpAmb da DPS.
        // tpAmb=1 => Produção; tpAmb=2 => Homologação.
        $tpAmb = $this->firstText([
            './*[local-name()="tpAmb"]',
            '//*[local-name()="infDPS"]/*[local-name()="tpAmb"]',
            '//*[local-name()="tpAmb"]',
        ], $infDPS);

        $localEmissao = $this->firstText(['./*[local-name()="xLocEmi"]', '//*[local-name()="xLocEmi"]'], $infNFSe);
        $ufPrestador = $this->firstText(['./*[local-name()="emit"]/*[local-name()="enderNac"]/*[local-name()="UF"]'], $infNFSe);

        $prestadorNode = $this->firstNode(['./*[local-name()="emit"]'], $infNFSe);
        $prestDpsNode = $this->firstNode(['./*[local-name()="prest"]'], $infDPS);

        // Não usar fallback para emitente. Se não existir toma/tomador, o tomador é NÃO IDENTIFICADO.
        $tomadorNode = $this->firstNode([
            './*[local-name()="toma"]',
            './*[local-name()="tomador"]',
            './*[local-name()="TomadorServico"]',
            './*[local-name()="Tomador"]',
        ], $infDPS);

        $intermediarioNode = $this->firstNode([
            './*[local-name()="interm"]',
            './*[local-name()="intermediario"]',
            './*[local-name()="Intermediario"]',
            './*[local-name()="IntermediarioServico"]',
        ], $infDPS);

        $servicoNode = $this->firstNode(['./*[local-name()="serv"]'], $infDPS);
        $valoresDpsNode = $this->firstNode(['./*[local-name()="valores"]'], $infDPS);
        $valoresNfseNode = $this->firstNode(['./*[local-name()="valores"]'], $infNFSe);

        $codigoTribNac = $this->firstText(['.//*[local-name()="cTribNac"]'], $servicoNode);
        $codigoTribMun = $this->firstText(['.//*[local-name()="cTribMun"]'], $servicoNode);
        $descTribNac = $this->firstText(['./*[local-name()="xTribNac"]', '//*[local-name()="xTribNac"]'], $infNFSe);
        $descricaoServico = $this->firstText(['.//*[local-name()="xDescServ"]', './/*[local-name()="Discriminacao"]', './/*[local-name()="Descricao"]'], $servicoNode);
        $nbs = $this->firstText(['.//*[local-name()="cNBS"]', './*[local-name()="xNBS"]', '//*[local-name()="cNBS"]', '//*[local-name()="xNBS"]'], $servicoNode ?: $infNFSe);

        $xLocPrestacao = $this->firstText(['./*[local-name()="xLocPrestacao"]', '//*[local-name()="xLocPrestacao"]'], $infNFSe);
        $xLocIncid = $this->firstText(['./*[local-name()="xLocIncid"]', '//*[local-name()="xLocIncid"]'], $infNFSe);
        $municipioPrestacao = $this->formatCityUf($xLocPrestacao, $ufPrestador);
        $municipioIncidencia = $this->formatCityUf($xLocIncid, $ufPrestador);

        $regTribNode = $this->firstNode(['.//*[local-name()="regTrib"]'], $prestDpsNode);
        $opSimpNac = $this->firstText(['./*[local-name()="opSimpNac"]'], $regTribNode);
        $regApTribSN = $this->firstText(['./*[local-name()="regApTribSN"]'], $regTribNode);
        $regEspTrib = $this->firstText(['./*[local-name()="regEspTrib"]'], $regTribNode);

        $tribMunNode = $this->firstNode(['.//*[local-name()="tribMun"]'], $valoresDpsNode);
        $tribISSQN = $this->firstText(['./*[local-name()="tribISSQN"]'], $tribMunNode);
        $tpRetISSQN = $this->firstText(['./*[local-name()="tpRetISSQN"]'], $tribMunNode);

        $valorServico = $this->firstText(['.//*[local-name()="vServPrest"]/*[local-name()="vServ"]', './/*[local-name()="vServ"]'], $valoresDpsNode);
        $valorLiquido = $this->firstText(['./*[local-name()="vLiq"]'], $valoresNfseNode);
        if ($valorLiquido === '') {
            $valorLiquido = $this->firstText(['.//*[local-name()="vLiq"]', './/*[local-name()="ValorLiquidoNfse"]'], $valoresDpsNode);
        }

        $prefeitura = $this->prefeitura($localEmissao, $ufPrestador);
        $prestador = $this->party($prestadorNode, $prestDpsNode);
        $tomador = $this->party($tomadorNode);
        $intermediario = $this->party($intermediarioNode);

        return [
            'versao_danfse' => '1.0',
            'numero' => $this->firstText(['./*[local-name()="nNFSe"]', '//*[local-name()="Numero"]'], $infNFSe),
            'codigo_verificacao' => $this->firstText(['./*[local-name()="cVerif"]', './*[local-name()="codVerif"]', '//*[local-name()="CodigoVerificacao"]'], $infNFSe),
            'chave_acesso' => $chaveAcesso,
            'id_nfse' => $idInfNfse,
            'url_consulta' => $urlConsulta,
            'ambiente' => $tpAmb,
            'homologacao' => $tpAmb === '2',
            'local_emissao' => $localEmissao,
            'prefeitura' => $prefeitura,
            'data_emissao_nfse' => $this->formatDateTime($this->firstText(['./*[local-name()="dhProc"]', './*[local-name()="dhEmi"]', '//*[local-name()="dhProc"]'], $infNFSe)),
            'data_emissao' => $this->formatDateTime($this->firstText(['./*[local-name()="dhProc"]', './*[local-name()="dhEmi"]', '//*[local-name()="dhProc"]'], $infNFSe)),
            'competencia' => $this->formatDate($this->firstText(['./*[local-name()="dCompet"]', '//*[local-name()="infDPS"]/*[local-name()="dCompet"]'], $infDPS)),
            'numero_dps' => $this->firstText(['./*[local-name()="nDPS"]'], $infDPS),
            'serie_dps' => $this->firstText(['./*[local-name()="serie"]'], $infDPS),
            'data_emissao_dps' => $this->formatDateTime($this->firstText(['./*[local-name()="dhEmi"]'], $infDPS)),
            'prestador' => $prestador,
            'tomador_identificado' => $tomador['identificado'],
            'tomador' => $tomador,
            'intermediario_identificado' => $intermediario['identificado'],
            'intermediario' => $intermediario,
            'simples_nacional' => $this->mapOpSimpNac($opSimpNac),
            'regime_apuracao_sn' => $this->mapRegApTribSN($regApTribSN),
            'servico' => [
                'codigo_tributacao_nacional' => $this->formatCodigoTributacao($codigoTribNac),
                'codigo_tributacao_nacional_descricao' => $descTribNac,
                'codigo_tributacao_municipal' => $codigoTribMun !== '' ? $codigoTribMun : '-',
                'local_prestacao' => $municipioPrestacao !== '' ? $municipioPrestacao : '-',
                'pais_prestacao' => '-',
                'descricao' => $descricaoServico,
                'nbs' => $nbs,
            ],
            'tributacao_municipal' => [
                'tributacao_issqn' => $this->mapTribISSQN($tribISSQN),
                'pais_resultado' => '-',
                'municipio_incidencia' => $municipioIncidencia !== '' ? $municipioIncidencia : '-',
                'regime_especial' => $this->mapRegEspTrib($regEspTrib),
                'tipo_imunidade' => '-',
                'suspensao_exigibilidade' => 'Não',
                'numero_processo_suspensao' => '-',
                'beneficio_municipal' => '-',
                'valor_servico' => $this->formatMoney($valorServico),
                'desconto_incondicionado' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vDescIncond"]'], $valoresDpsNode)),
                'total_deducoes' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vDed"]', './/*[local-name()="vRedBC"]'], $valoresDpsNode)),
                'calculo_bm' => '-',
                'bc_issqn' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vBC"]'], $valoresDpsNode)),
                'aliquota_aplicada' => $this->formatPercentOrDash($this->firstText(['.//*[local-name()="pAliqAplic"]', './/*[local-name()="pAliq"]'], $valoresDpsNode)),
                'retencao_issqn' => $this->mapRetencaoIssqn($tpRetISSQN),
                'issqn_apurado' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vISSQN"]', './/*[local-name()="vISS"]'], $valoresDpsNode)),
            ],
            'tributacao_federal' => [
                'irrf' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vIRRF"]', './/*[local-name()="vIR"]'], $valoresDpsNode)),
                'contribuicao_previdenciaria_retida' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vRetCP"]', './/*[local-name()="vINSS"]'], $valoresDpsNode)),
                'contribuicoes_sociais_retidas' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vRetCSLL"]'], $valoresDpsNode)),
                'descricao_contribuicoes_sociais_retidas' => '-',
                'pis_debito_apuracao_propria' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vPis"]', './/*[local-name()="vPIS"]'], $valoresDpsNode)),
                'cofins_debito_apuracao_propria' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vCofins"]', './/*[local-name()="vCOFINS"]'], $valoresDpsNode)),
            ],
            'valor_total' => [
                'valor_servico' => $this->formatMoney($valorServico),
                'desconto_condicionado' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vDescCond"]'], $valoresDpsNode)),
                'desconto_incondicionado' => $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vDescIncond"]'], $valoresDpsNode)),
                'issqn_retido' => $tpRetISSQN === '2' ? $this->formatMoneyOrDash($this->firstText(['.//*[local-name()="vISSQN"]', './/*[local-name()="vISS"]'], $valoresDpsNode)) : '-',
                'total_retencoes_federais' => '-',
                'pis_cofins_debito_apuracao_propria' => '-',
                'valor_liquido' => $this->formatMoney($valorLiquido !== '' ? $valorLiquido : $valorServico),
            ],
            'totais_aproximados' => [
                'federais' => '-',
                'estaduais' => '-',
                'municipais' => $this->formatPercentOrDash($this->firstText(['.//*[local-name()="pTotTribSN"]'], $valoresDpsNode)),
            ],
            'informacoes_complementares' => $nbs !== '' ? 'NBS: ' . $nbs : '',
        ];
    }

    /** @return array<string, string|bool> */
    private function prefeitura(string $municipio, string $uf): array
    {
        $telefone = '';
        $email = '';

        if (strtolower($municipio) === 'londrina') {
            $telefone = '(43)3372-4253';
            $email = 'issonline@londrina.pr.gov.br';
        }

        return [
            'nome' => trim('PREFEITURA MUNICIPAL DE ' . strtoupper($municipio ?: '')),
            'municipio' => $municipio,
            'uf' => $uf,
            'telefone' => $telefone,
            'email' => $email,
        ];
    }

    /** @return array<string, string|bool> */
    private function party(?DOMElement $primaryNode, ?DOMElement $secondaryNode = null): array
    {
        if (!$primaryNode && !$secondaryNode) {
            return $this->emptyParty();
        }

        $documento = $this->firstNonEmpty([
            $this->firstText(['./*[local-name()="CNPJ"]', './*[local-name()="CPF"]', './/*[local-name()="CNPJ"]', './/*[local-name()="CPF"]'], $primaryNode),
            $this->firstText(['./*[local-name()="CNPJ"]', './*[local-name()="CPF"]', './/*[local-name()="CNPJ"]', './/*[local-name()="CPF"]'], $secondaryNode),
        ]);

        $razaoSocial = $this->firstNonEmpty([
            $this->firstText(['./*[local-name()="xNome"]', './*[local-name()="RazaoSocial"]', './*[local-name()="Nome"]', './/*[local-name()="xNome"]'], $primaryNode),
            $this->firstText(['./*[local-name()="xNome"]', './*[local-name()="RazaoSocial"]', './*[local-name()="Nome"]', './/*[local-name()="xNome"]'], $secondaryNode),
        ]);

        return [
            'identificado' => $documento !== '' || $razaoSocial !== '',
            'documento' => $this->formatCpfCnpj($documento),
            'razao_social' => $razaoSocial,
            'inscricao_municipal' => $this->firstNonEmpty([
                $this->firstText(['./*[local-name()="IM"]', './*[local-name()="InscricaoMunicipal"]', './/*[local-name()="IM"]'], $primaryNode),
                $this->firstText(['./*[local-name()="IM"]', './*[local-name()="InscricaoMunicipal"]', './/*[local-name()="IM"]'], $secondaryNode),
            ]),
            'email' => $this->firstNonEmpty([
                $this->firstText(['./*[local-name()="email"]', './*[local-name()="Email"]', './/*[local-name()="email"]'], $primaryNode),
                $this->firstText(['./*[local-name()="email"]', './*[local-name()="Email"]', './/*[local-name()="email"]'], $secondaryNode),
            ]),
            'telefone' => $this->formatPhone($this->firstNonEmpty([
                $this->firstText(['./*[local-name()="fone"]', './*[local-name()="Telefone"]', './/*[local-name()="fone"]'], $primaryNode),
                $this->firstText(['./*[local-name()="fone"]', './*[local-name()="Telefone"]', './/*[local-name()="fone"]'], $secondaryNode),
            ])),
            'endereco' => $this->firstNonEmpty([$this->address($primaryNode), $this->address($secondaryNode)]),
        ];
    }

    /** @return array<string, string|bool> */
    private function emptyParty(): array
    {
        return [
            'identificado' => false,
            'documento' => '',
            'razao_social' => '',
            'inscricao_municipal' => '',
            'email' => '',
            'telefone' => '',
            'endereco' => '',
        ];
    }

    private function address(?DOMElement $node): string
    {
        if (!$node) {
            return '';
        }

        $logradouro = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="xLgr"]', './/*[local-name()="xLgr"]', './/*[local-name()="Logradouro"]'], $node);
        $numero = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="nro"]', './/*[local-name()="nro"]', './/*[local-name()="NumeroEndereco"]'], $node);
        $complemento = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="xCpl"]', './/*[local-name()="xCpl"]', './/*[local-name()="Complemento"]'], $node);
        $bairro = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="xBairro"]', './/*[local-name()="xBairro"]', './/*[local-name()="Bairro"]'], $node);
        $municipio = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="xMun"]', './/*[local-name()="xMun"]', './/*[local-name()="Municipio"]'], $node);
        $uf = $this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="UF"]', './/*[local-name()="UF"]'], $node);
        $cep = $this->formatCep($this->firstText(['.//*[local-name()="enderNac"]/*[local-name()="CEP"]', './/*[local-name()="CEP"]'], $node));

        $linha = trim(implode(', ', array_filter([$logradouro, $numero, $complemento])));
        $cidadeUf = trim(implode(' - ', array_filter([$municipio, $uf])));

        return trim(implode(', ', array_filter([$linha, $bairro, $cidadeUf, $cep])));
    }

    /** @param string[] $queries */
    private function firstText(array $queries, ?DOMNode $context = null): string
    {
        $node = $this->firstNode($queries, $context);
        return $node ? trim((string) $node->textContent) : '';
    }

    /** @param string[] $queries */
    private function firstNode(array $queries, ?DOMNode $context = null): ?DOMElement
    {
        foreach ($queries as $query) {
            if ($context === null && str_starts_with(ltrim($query), '.')) {
                continue;
            }

            $nodes = $this->xpath->query($query, $context);
            if ($nodes && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
                return $nodes->item(0);
            }
        }
        return null;
    }

    /** @param string[] $values */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function formatCpfCnpj(string $value): string
    {
        $digits = $this->onlyDigits($value);
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $value;
        }
        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $value;
        }
        return $value;
    }

    private function formatCep(string $value): string
    {
        $digits = $this->onlyDigits($value);
        if (strlen($digits) === 8) {
            return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $value;
        }
        return $value;
    }

    private function formatPhone(string $value): string
    {
        $digits = $this->onlyDigits($value);
        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?? $value;
        }
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?? $value;
        }
        return $value;
    }

    private function formatCityUf(string $city, string $uf): string
    {
        $city = trim($city);
        $uf = trim($uf);
        if ($city === '') {
            return '';
        }
        return $uf !== '' ? $city . ' - ' . $uf : $city;
    }

    private function formatCodigoTributacao(string $value): string
    {
        $digits = $this->onlyDigits($value);
        if (strlen($digits) === 6) {
            return substr($digits, 0, 2) . '.' . substr($digits, 2, 2) . '.' . substr($digits, 4, 2);
        }
        return $value;
    }

    private function formatMoney(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }
        $number = (float) str_replace(',', '.', $value);
        return 'R$ ' . number_format($number, 2, ',', '.');
    }

    private function formatMoneyOrDash(string $value): string
    {
        return trim($value) === '' ? '-' : $this->formatMoney($value);
    }

    private function formatPercentOrDash(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }
        $number = (float) str_replace(',', '.', $value);
        if ($number > 0 && $number < 1) {
            $number *= 100;
        }
        return number_format($number, 2, ',', '.') . '%';
    }

    private function formatDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i:s', $timestamp) : $value;
    }

    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y', $timestamp) : $value;
    }

    private function mapOpSimpNac(string $value): string
    {
        return match ($value) {
            '1' => 'Não Optante',
            '2' => 'Optante - Microempreendedor Individual (MEI)',
            '3' => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
            default => $value !== '' ? $value : '-',
        };
    }

    private function mapRegApTribSN(string $value): string
    {
        return match ($value) {
            '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            '2' => 'Regime de apuração dos tributos federais pelo Simples Nacional e ISSQN por fora do Simples Nacional',
            default => $value !== '' ? $value : '-',
        };
    }

    private function mapRegEspTrib(string $value): string
    {
        return match ($value) {
            '0', '' => 'Nenhum',
            default => $value,
        };
    }

    private function mapTribISSQN(string $value): string
    {
        return match ($value) {
            '1' => 'Operação Tributável',
            '2' => 'Imunidade',
            '3' => 'Exportação de Serviço',
            '4' => 'Não incidência',
            default => $value !== '' ? $value : '-',
        };
    }

    private function mapRetencaoIssqn(string $value): string
    {
        return match ($value) {
            '1', '' => 'Não Retido',
            '2' => 'Retido',
            default => $value,
        };
    }
}
