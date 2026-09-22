<?php

namespace Hadder\NfseNacional\Danfse;

/**
 * Resolve o logotipo do município emissor para impressão no DANFSe.
 *
 * Estratégia híbrida em 3 níveis:
 *   1. Parâmetro explícito (path no filesystem ou data-URI base64);
 *   2. Convenção storage/logos/{cMun_IBGE}.{png|jpg|jpeg};
 *   3. null — não imprime logo do município (cabeçalho mantém apenas o logo da NFS-e).
 */
final class LogoResolver
{
    /**
     * @param string|null $explicit  Path ou data-URI passado pelo consumidor.
     * @param string      $cMunIbge  Código IBGE de 7 dígitos do município emitente.
     * @param string      $storageDir Diretório base com fallbacks por município.
     * @return string|null Path resolvido ou null se nenhum logo estiver disponível.
     */
    public static function resolve(?string $explicit, string $cMunIbge, string $storageDir): ?string
    {
        if (!empty($explicit)) {
            if (str_starts_with($explicit, 'data://') || str_starts_with($explicit, 'data:')) {
                return $explicit;
            }
            if (is_readable($explicit)) {
                return $explicit;
            }
        }

        if ($cMunIbge !== '') {
            $base = rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR;
            foreach (['png', 'jpg', 'jpeg'] as $ext) {
                $candidate = $base . $cMunIbge . '.' . $ext;
                if (is_readable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
