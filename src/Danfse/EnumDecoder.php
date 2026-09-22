<?php

namespace Hadder\NfseNacional\Danfse;

/**
 * Decodifica códigos numéricos do leiaute da NFS-e para descrições legíveis,
 * conforme convenções da NT-008 SE/CGNFS-e §2.4.5.
 *
 * Centralizar essas tabelas aqui evita strings espalhadas pelos traits e
 * facilita auditoria contra a NT.
 */
final class EnumDecoder
{
    /** tpEmit — Emitente da NFS-e (§2.4.5). */
    public const TP_EMIT = [
        '1' => 'Prestador',
        '2' => 'Tomador',
        '3' => 'Intermediário',
    ];

    /** cStat — Situação da NFS-e (§2.4.5; mapeamento conforme leiaute da NFS-e). */
    public const C_STAT = [
        '100' => 'NFS-e Autorizada',
        '101' => 'NFS-e Cancelada',
        '102' => 'NFS-e Substituída',
        '103' => 'NFS-e de Decisão Judicial ou Administrativa',
    ];

    /** finNFSe — Finalidade da NFS-e (§2.4.5). */
    public const FIN_NFSE = [
        '0' => 'NFS-e regular'
    ];

    /** tribISSQN — Tipo de Tributação do ISSQN (§2.1.8). */
    public const TRIB_ISSQN = [
        '1' => 'Operação Tributável',
        '2' => 'Exportação de Serviço',
        '3' => 'Não Incidência',
        '4' => 'Imunidade',
    ];

    /** regEspTrib — Regime Especial de Tributação do ISSQN (§2.1.8). */
    public const REG_ESP_TRIB = [
        '0' => 'Nenhum',
        '1' => 'Ad valorem',
        '2' => 'Estimativa',
        '3' => 'Sociedade de Profissionais',
        '4' => 'Cooperativa',
        '5' => 'MEI - Simples Nacional',
        '6' => 'ME EPP - Simples Nacional',
        '9' => 'Outros',
    ];

    /** opSimpNac — Optante do Simples Nacional na data de competência (§2.1.3). */
    public const OP_SIMP_NAC = [
        '1' => 'Não optante',
        '2' => 'Optante (não MEI)',
        '3' => 'MEI',
    ];

    /** regApTribSN — Regime de Apuração Tributária pelo SN (§2.1.3). */
    public const REG_AP_TRIB_SN = [
        '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
        '2' => 'Regime de apuração dos tributos federais pelo Simples Nacional e do ISSQN fora do SN',
        '3' => 'Regime de apuração do ISSQN pelo Simples Nacional e dos tributos federais fora do SN',
    ];

    /** tpImunidade — Tipo de Imunidade do ISSQN (§2.1.8). */
    public const TP_IMUNIDADE = [
        '1' => 'Livros, jornais, periódicos e o papel destinado à sua impressão',
        '2' => 'Templos de qualquer culto',
        '3' => 'Patrimônio, renda ou serviços, uns dos outros, de partidos políticos, entidades sindicais',
        '4' => 'Fonogramas e videofonogramas musicais',
        '5' => 'Outros',
    ];

    /** tpSusp — Suspensão da Exigibilidade do ISSQN (§2.1.8). */
    public const TP_SUSP = [
        '1' => 'Exigibilidade Suspensa por Decisão Judicial',
        '2' => 'Exigibilidade Suspensa por Processo Administrativo',
    ];

    /** tpBM — Benefício Municipal (§2.1.8). */
    public const TP_BM = [
        '1' => 'Isenção',
        '2' => 'Redução da Base de Cálculo',
        '3' => 'Redução da Alíquota',
        '4' => 'Outros',
    ];

    /** tpRetISSQN — Retenção do ISSQN (§2.1.8). */
    public const TP_RET_ISSQN = [
        '1' => 'Não Retido',
        '2' => 'Retido pelo Tomador',
        '3' => 'Retido pelo Intermediário',
    ];

    /** tpRetPisCofins — Tipo de retenção PIS/COFINS e CSLL (§2.1.9, NT-008). */
    public const TP_RET_PIS_COFINS = [
        '0' => 'PIS/COFINS/CSLL Não Retidos',
        '1' => 'PIS/COFINS Retido',
        '2' => 'PIS/COFINS Não Retido',
        '3' => 'PIS/COFINS/CSLL Retidos',
        '4' => 'PIS/COFINS Retidos, CSLL Não Retido',
        '5' => 'PIS Retido, COFINS/CSLL Não Retido',
        '6' => 'COFINS Retido, PIS/CSLL Não Retido',
        '7' => 'PIS Não Retido, COFINS/CSLL Retidos',
        '8' => 'PIS/COFINS Não Retido, CSLL Retidos',
        '9' => 'COFINS Não Retido, PIS/CSLL Retidos',
    ];

    /** cIndOp — Indicador de Operação IBS/CBS (§2.1.10). */
    public const C_IND_OP = [
        '1' => 'Operação Tributável',
        '2' => 'Operação Imune',
        '3' => 'Operação Isenta',
        '4' => 'Operação com Redução de Base de Cálculo',
        '5' => 'Operação com Suspensão',
        '9' => 'Outras',
    ];

    /** tpAmb — Ambiente do Sistema Nacional NFS-e. */
    public const TP_AMB = [
        '1' => 'Produção',
        '2' => 'Homologação',
    ];

    /**
     * Resolve uma chave em uma tabela enum, com fallback ao próprio valor.
     */
    public static function decode(array $table, ?string $value, string $default = '-'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $table[$value] ?? $value;
    }

    /**
     * Trunca string longa para o limite, adicionando reticências (NT-008 §2.4.5).
     */
    public static function truncate(string $value, int $maxLen): string
    {
        if (mb_strlen($value) <= $maxLen) {
            return $value;
        }
        return mb_substr($value, 0, $maxLen - 3) . '...';
    }

    /**
     * Trunca a string mantendo até `$threshold` caracteres + '...' quando excede.
     * Semântica do NT-008 §2.4.5: "Utilizar reticências (...), caso a descrição supere N caracteres".
     *
     * Diferença para {@see truncate()}: aqui `$threshold` é o número de caracteres
     * VISÍVEIS antes do '...' (saída total: $threshold + 3 quando truncado).
     * Em truncate(), o parâmetro é o tamanho TOTAL da saída (incluindo '...').
     */
    public static function truncateAfter(string $value, int $threshold): string
    {
        if (mb_strlen($value) <= $threshold) {
            return $value;
        }
        return mb_substr($value, 0, $threshold) . '...';
    }
}
