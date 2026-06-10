<?php

namespace Hadder\NfseNacional\Danfse;

class DanfseTemplate
{
    /** @param array<string, mixed> $data */
    public static function html(array $data): string
    {
        $fixEncoding = static function ($value): string {
            $text = (string) $value;
            if ($text === '') {
                return '';
            }
            if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1, Windows-1252');
            }
            return $text;
        };
        $h = static fn ($value): string => htmlspecialchars($fixEncoding($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dash = static fn ($value): string => trim((string) $value) !== '' ? (string) $value : '-';
        $gap = self::gap();

        $prefeitura = $data['prefeitura'] ?? [];
        $prestador = $data['prestador'] ?? [];
        $tomador = $data['tomador'] ?? [];
        $intermediario = $data['intermediario'] ?? [];
        $servico = $data['servico'] ?? [];
        $tribMun = $data['tributacao_municipal'] ?? [];
        $tribFed = $data['tributacao_federal'] ?? [];
        $valorTotal = $data['valor_total'] ?? [];
        $totais = $data['totais_aproximados'] ?? [];

        $homologacao = !empty($data['homologacao'])
            ? '<div class="homologacao">DOCUMENTO EMITIDO EM AMBIENTE DE HOMOLOGAÇÃO - SEM VALOR FISCAL</div><div class="gap-small"></div>'
            : '';

        $copyright = trim((string)($data['copyright'] ?? ''));
        $copyrightHtml = $copyright !== ''
            ? '<div class="footer-system">' . $h($copyright) . '</div>'
            : '';

        $codigoNacional = trim((string)($servico['codigo_tributacao_nacional'] ?? ''));
        $descNacional = trim((string)($servico['codigo_tributacao_nacional_descricao'] ?? ''));
        $codigoNacionalLinha = trim($codigoNacional . ($descNacional !== '' ? ' - ' . $descNacional : ''));

        $tomadorHtml = !empty($data['tomador_identificado'])
            ? self::partyBox($tomador, $h, 'TOMADOR DO SERVIÇO')
            : '' . $gap . '<div class="section-title">TOMADOR DO SERVIÇO NÃO IDENTIFICADO NA NFS-e</div>';

        $intermediarioHtml = !empty($data['intermediario_identificado'])
            ? self::partyBox($intermediario, $h, 'INTERMEDIÁRIO DO SERVIÇO')
            : '' . $gap . '<div class="section-title">INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e</div>';
		
		$logoNfse = __DIR__ . '/assets/logo_nfse_horizontal.png';
		
		
        return '<style>
            body { font-family: dejavu sans, helvetica, sans-serif; font-size: 5.9px; color: #111; }
            table { border-collapse: collapse; width: 100%; }
            td { vertical-align: top; padding: 0.95px; line-height: 1.0; }
            .border td, .border th { border: 1px solid #555; }
            .section-title { background-color: #e9e9e9; border: 1px solid #555; font-weight: bold; font-size: 6.4px; padding: 0.9px; line-height: 1.0; }
            .homologacao { border: 1px solid #555; background-color: #eee; font-weight: bold; text-align: center; font-size: 8.5px; padding: 3px; }
            .title { font-size: 12px; font-weight: bold; }
            .subtitle { font-size: 5.7px; }
            .label { font-weight: bold; color: #222; padding:2px; }
            .center { text-align: center; }
            .right { text-align: right; }
            .small { font-size: 5.2px; }
            .tiny { font-size: 5.0px; }
            .muted { color: #444; }
            .gap-small { height: 1.3mm; line-height: 1.3mm; font-size: 1px; }
            .gap-section { height: 1.3mm; line-height: 1.3mm; font-size: 1px; }
            .gap-footer { height: 1.3mm; line-height: 1.3mm; font-size: 1px; }
            .footer-system { text-align: center; font-size: 5.8px; font-weight: bold; margin: 0; padding: 0; line-height: 1.0; }
            .footer-note { text-align: center; font-size: 5.6px; margin: 0; padding: 0; line-height: 1.0; }
            .service-desc { min-height: 20px; }
        </style>' . $homologacao . '

        <table class="border" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 27%; height: 80px;">
					<table cellpadding="4" cellspacing="0" border="0" style="height:80px; width:100%;">
						<tr>
							<td>
								<span class="title">DANFSe</span><br>
								<span class="subtitle">Documento Auxiliar da NFS-e</span>
								<br><br>
								<img src="' . $logoNfse . '" width="130" /><br>
							</td>
						</tr>
					</table>
                </td>
                <td style="width:45%; height:80px;">
					<table cellpadding="4" cellspacing="0" border="0" style="height:80px; width:100%;">
						<tr>
							<td valign="middle">

								<span class="label">Chave de Acesso da NFS-e</span><br>
								<span class="small">' . $h($data['chave_acesso'] ?? '') . '</span><br><br>

								<div style="font-size:8px; line-height:11px;">
									A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave<br>
									de acesso no portal nacional da NFS-e.
								</div>

							</td>
						</tr>
					</table>
				</td>
                <td style="width: 28%; height: 80px;" class="center">
                    <!--
					<span class="label">QR Code</span><br>
					<span class="tiny">Consulta Pública</span><br><br>
					-->
                </td>
            </tr>
        </table>

        ' . $gap . '
        <table class="border" cellpadding="2">
            <tr>
                <td style="width: 24%;"><span class="label">Número da NFS-e</span><br>' . $h($data['numero'] ?? '') . '</td>
                <td style="width: 25%;"><span class="label">Competência da NFS-e</span><br>' . $h($data['competencia'] ?? '') . '</td>
                <td style="width: 26%;"><span class="label">Data e Hora da emissão da NFS-e</span><br>' . $h($data['data_emissao_nfse'] ?? $data['data_emissao'] ?? '') . '</td>
                <td style="width: 13%;"><span class="label">Número da DPS</span><br>' . $h($data['numero_dps'] ?? '') . '</td>
                <td style="width: 12%;"><span class="label">Série da DPS</span><br>' . $h($data['serie_dps'] ?? '') . '</td>
            </tr>
            <tr>
                <td colspan="5"><span class="label">Data e Hora da emissão da DPS</span><br>' . $h($data['data_emissao_dps'] ?? '') . '</td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">EMITENTE DA NFS-e</div>
        <table class="border" cellpadding="2">
            <tr><td colspan="4" class="label">Prestador do Serviço</td></tr>
            <tr>
                <td style="width: 25%;"><span class="label">CNPJ / CPF / NIF</span><br>' . $h($prestador['documento'] ?? '') . '</td>
                <td style="width: 25%;"><span class="label">Inscrição Municipal</span><br>' . $h($dash($prestador['inscricao_municipal'] ?? '')) . '</td>
                <td style="width: 25%;"><span class="label">Telefone</span><br>' . $h($dash($prestador['telefone'] ?? '')) . '</td>
                <td style="width: 25%;"><span class="label">E-mail</span><br>' . $h($dash($prestador['email'] ?? '')) . '</td>
            </tr>
            <tr>
                <td colspan="4"><span class="label">Nome / Nome Empresarial</span><br>' . $h($prestador['razao_social'] ?? '') . '</td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><br>' . $h($dash($prestador['endereco'] ?? '')) . '</td>
                <td><span class="label">Município</span><br>' . $h($dash(($prefeitura['municipio'] ?? '') . (($prefeitura['uf'] ?? '') ? ' - ' . $prefeitura['uf'] : ''))) . '</td>
                <td><span class="label">CEP</span><br>' . $h(self::extractCep((string)($prestador['endereco'] ?? ''))) . '</td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Simples Nacional na Data de Competência</span><br>' . $h($data['simples_nacional'] ?? '-') . '</td>
                <td colspan="2"><span class="label">Regime de Apuração Tributária pelo SN</span><br>' . $h($data['regime_apuracao_sn'] ?? '-') . '</td>
            </tr>
        </table>

        ' . $tomadorHtml . '
        ' . $intermediarioHtml . '

        ' . $gap . '
        <div class="section-title">SERVIÇO PRESTADO</div>
        <table class="border" cellpadding="2">
            <tr>
                <td style="width: 50%;"><span class="label">Código de Tributação Nacional</span><br>' . $h($dash($codigoNacionalLinha)) . '</td>
                <td style="width: 20%;"><span class="label">Código de Tributação Municipal</span><br>' . $h($servico['codigo_tributacao_municipal'] ?? '-') . '</td>
                <td style="width: 15%;"><span class="label">Local da Prestação</span><br>' . $h($servico['local_prestacao'] ?? '-') . '</td>
                <td style="width: 15%;"><span class="label">País da Prestação</span><br>' . $h($servico['pais_prestacao'] ?? '-') . '</td>
            </tr>
            <tr>
                <td colspan="4"><span class="label">Descrição do Serviço</span><br><span class="service-desc">' . nl2br($h($servico['descricao'] ?? '')) . '</span></td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">TRIBUTAÇÃO MUNICIPAL</div>
        <table class="border" cellpadding="2">
            <tr>
                <td style="width: 25%;"><span class="label">Tributação do ISSQN</span><br>' . $h($tribMun['tributacao_issqn'] ?? '-') . '</td>
                <td style="width: 25%;"><span class="label">País Resultado da Prestação do Serviço</span><br>' . $h($tribMun['pais_resultado'] ?? '-') . '</td>
                <td style="width: 25%;"><span class="label">Município de Incidência do ISSQN</span><br>' . $h($tribMun['municipio_incidencia'] ?? '-') . '</td>
                <td style="width: 25%;"><span class="label">Regime Especial de Tributação</span><br>' . $h($tribMun['regime_especial'] ?? '-') . '</td>
            </tr>
            <tr>
                <td><span class="label">Tipo de Imunidade</span><br>' . $h($tribMun['tipo_imunidade'] ?? '-') . '</td>
                <td><span class="label">Suspensão da Exigibilidade do ISSQN</span><br>' . $h($tribMun['suspensao_exigibilidade'] ?? '-') . '</td>
                <td><span class="label">Número Processo Suspensão</span><br>' . $h($tribMun['numero_processo_suspensao'] ?? '-') . '</td>
                <td><span class="label">Benefício Municipal</span><br>' . $h($tribMun['beneficio_municipal'] ?? '-') . '</td>
            </tr>
            <tr>
                <td><span class="label">Valor do Serviço</span><br>' . $h($tribMun['valor_servico'] ?? '-') . '</td>
                <td><span class="label">Desconto Incondicionado</span><br>' . $h($tribMun['desconto_incondicionado'] ?? '-') . '</td>
                <td><span class="label">Total Deduções/Reduções</span><br>' . $h($tribMun['total_deducoes'] ?? '-') . '</td>
                <td><span class="label">Cálculo do BM</span><br>' . $h($tribMun['calculo_bm'] ?? '-') . '</td>
            </tr>
            <tr>
                <td><span class="label">BC ISSQN</span><br>' . $h($tribMun['bc_issqn'] ?? '-') . '</td>
                <td><span class="label">Alíquota Aplicada</span><br>' . $h($tribMun['aliquota_aplicada'] ?? '-') . '</td>
                <td><span class="label">Retenção do ISSQN</span><br>' . $h($tribMun['retencao_issqn'] ?? '-') . '</td>
                <td><span class="label">ISSQN Apurado</span><br>' . $h($tribMun['issqn_apurado'] ?? '-') . '</td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">TRIBUTAÇÃO FEDERAL</div>
        <table class="border" cellpadding="2">
            <tr>
                <td><span class="label">IRRF</span><br>' . $h($tribFed['irrf'] ?? '-') . '</td>
                <td><span class="label">Contribuição Previdenciária - Retida</span><br>' . $h($tribFed['contribuicao_previdenciaria_retida'] ?? '-') . '</td>
                <td><span class="label">Contribuições Sociais - Retidas</span><br>' . $h($tribFed['contribuicoes_sociais_retidas'] ?? '-') . '</td>
                <td><span class="label">Descrição Contrib. Sociais - Retidas</span><br>' . $h($tribFed['descricao_contribuicoes_sociais_retidas'] ?? '-') . '</td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">PIS - Débito Apuração Própria</span><br>' . $h($tribFed['pis_debito_apuracao_propria'] ?? '-') . '</td>
                <td colspan="2"><span class="label">COFINS - Débito Apuração Própria</span><br>' . $h($tribFed['cofins_debito_apuracao_propria'] ?? '-') . '</td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">VALOR TOTAL DA NFS-E</div>
        <table class="border" cellpadding="2">
            <tr>
                <td><span class="label">Valor do Serviço</span><br>' . $h($valorTotal['valor_servico'] ?? '-') . '</td>
                <td><span class="label">Desconto Condicionado</span><br>' . $h($valorTotal['desconto_condicionado'] ?? '-') . '</td>
                <td><span class="label">Desconto Incondicionado</span><br>' . $h($valorTotal['desconto_incondicionado'] ?? '-') . '</td>
                <td><span class="label">ISSQN Retido</span><br>' . $h($valorTotal['issqn_retido'] ?? '-') . '</td>
            </tr>
            <tr>
                <td><span class="label">Total das Retenções Federais</span><br>' . $h($valorTotal['total_retencoes_federais'] ?? '-') . '</td>
                <td><span class="label">PIS/COFINS - Débito Apur. Própria</span><br>' . $h($valorTotal['pis_cofins_debito_apuracao_propria'] ?? '-') . '</td>
                <td colspan="2"><span class="label">Valor Líquido da NFS-e</span><br>' . $h($valorTotal['valor_liquido'] ?? '-') . '</td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">TOTAIS APROXIMADOS DOS TRIBUTOS</div>
        <table class="border" cellpadding="2">
            <tr>
                <td><span class="label">Federais</span><br>' . $h($totais['federais'] ?? '-') . '</td>
                <td><span class="label">Estaduais</span><br>' . $h($totais['estaduais'] ?? '-') . '</td>
                <td><span class="label">Municipais</span><br>' . $h($totais['municipais'] ?? '-') . '</td>
            </tr>
        </table>

        ' . $gap . '
        <div class="section-title">INFORMAÇÕES COMPLEMENTARES</div>
        <table class="border" cellpadding="2"><tr><td>' . nl2br($h($data['informacoes_complementares'] ?? '')) . '</td></tr></table>

        ' . $gap . '
        ' . $copyrightHtml . '
        <div class="footer-note">Este documento é uma representação auxiliar da NFS-e Nacional gerada a partir do XML autorizado.</div>';
    }

    /** @param array<string, mixed> $party */
    private static function partyBox(array $party, callable $h, string $title): string
    {
        return '' . $gap . '<div class="section-title">' . $h($title) . '</div>
        <table class="border" cellpadding="2">
            <tr>
                <td style="width: 25%;"><span class="label">CNPJ / CPF / NIF</span><br>' . $h($party['documento'] ?? '') . '</td>
                <td style="width: 25%;"><span class="label">Inscrição Municipal</span><br>' . $h((string)($party['inscricao_municipal'] ?: '-')) . '</td>
                <td style="width: 25%;"><span class="label">Telefone</span><br>' . $h((string)($party['telefone'] ?: '-')) . '</td>
                <td style="width: 25%;"><span class="label">E-mail</span><br>' . $h((string)($party['email'] ?: '-')) . '</td>
            </tr>
            <tr><td colspan="4"><span class="label">Nome / Nome Empresarial</span><br>' . $h($party['razao_social'] ?? '') . '</td></tr>
            <tr><td colspan="4"><span class="label">Endereço</span><br>' . $h((string)($party['endereco'] ?: '-')) . '</td></tr>
        </table>';
    }

    private static function gap(): string
    {
        // 5px aproximados em TCPDF HTML (cerca de 1,3mm), sem margem extra de div.
        return '<table cellpadding="0" cellspacing="0" style="width:100%; border:none;"><tr><td style="height:1.8mm; line-height:1.8mm; font-size:1px; border:none; padding:0;"></td></tr></table>';
    }

    private static function extractCep(string $address): string
    {
        if (preg_match('/\b\d{5}-\d{3}\b/', $address, $m)) {
            return $m[0];
        }
        return '-';
    }
}
