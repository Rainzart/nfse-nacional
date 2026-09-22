<?php

namespace Hadder\NfseNacional\Danfse;

/**
 * Resolve a UF (sigla) e o País a partir do código IBGE do município.
 *
 * A NT-008 (DANFSE) exige exibir "MUNICÍPIO / SIGLA UF / PAÍS" em alguns
 * blocos, mas o leiaute da NFS-e não possui tag de UF — apenas o código IBGE
 * do município. Como os 2 primeiros dígitos do código IBGE identificam a UF
 * (mesma estratégia do componente ACBR), derivamos a sigla a partir deles.
 *
 * Regra de país: códigos de município brasileiros → 'BR'; o código especial
 * '9999999' (exterior) → 'EX'.
 *
 * O nome do município é resolvido por uma tabela IBGE embutida
 * (storage/municipios.json), já que o leiaute da NFS-e não traz o nome do
 * município nos endereços de tomador/destinatário/intermediário.
 */
final class LocalidadeIbge
{
    /** Código IBGE especial que indica município no exterior. */
    public const COD_EXTERIOR = '9999999';

    /** Caminho da tabela IBGE embutida (código → [nome, UF]). */
    private const ARQUIVO_MUNICIPIOS = __DIR__ . '/../../storage/municipios.json';

    /** Cache da tabela de municípios (lazy). */
    private static ?array $municipios = null;

    /** Prefixo (2 primeiros dígitos do código IBGE) → sigla da UF. */
    public const UF_POR_PREFIXO = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA',
        '16' => 'AP', '17' => 'TO',
        '21' => 'MA', '22' => 'PI', '23' => 'CE', '24' => 'RN', '25' => 'PB',
        '26' => 'PE', '27' => 'AL', '28' => 'SE', '29' => 'BA',
        '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP',
        '41' => 'PR', '42' => 'SC', '43' => 'RS',
        '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
    ];

    /**
     * Resolve Nome, UF e País a partir do código IBGE do município.
     *
     * O nome vem da tabela IBGE embutida; quando o código não está na tabela,
     * a UF ainda é derivada dos 2 primeiros dígitos (e o nome fica vazio).
     *
     * @return array{nome:string,uf:string,pais:string}
     */
    public static function resolver(?string $codIbge): array
    {
        $cod = preg_replace('/\D/', '', (string) $codIbge);

        if ($cod === self::COD_EXTERIOR) {
            return ['nome' => '', 'uf' => '', 'pais' => 'EX'];
        }

        $mun = self::municipios()[$cod] ?? null;
        if ($mun !== null) {
            return ['nome' => $mun[0], 'uf' => $mun[1], 'pais' => 'BR'];
        }

        // Fallback: código fora da tabela — UF pelo prefixo, sem nome.
        $uf = self::UF_POR_PREFIXO[substr($cod, 0, 2)] ?? '';

        return ['nome' => '', 'uf' => $uf, 'pais' => $uf !== '' ? 'BR' : ''];
    }

    /**
     * Carrega (lazy) a tabela IBGE embutida (código → [nome, UF]).
     * Retorna [] se o arquivo não existir — o componente degrada graciosamente
     * (UF ainda derivada do prefixo, nome vazio).
     */
    private static function municipios(): array
    {
        if (self::$municipios === null) {
            $json = is_readable(self::ARQUIVO_MUNICIPIOS)
                ? file_get_contents(self::ARQUIVO_MUNICIPIOS)
                : false;
            self::$municipios = $json !== false
                ? (json_decode($json, true) ?: [])
                : [];
        }
        return self::$municipios;
    }
}
