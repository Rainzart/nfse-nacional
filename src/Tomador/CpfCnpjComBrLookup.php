<?php

namespace Hadder\NfseNacional\Tomador;

/**
 * Implementacao de referencia da PessoaLookup usando a API da CPF.CNPJ
 * (https://www.cpfcnpj.com.br).
 *
 * A consulta e feita via GET em:
 *   https://api.cpfcnpj.com.br/{token}/{pacote}/{documento}
 *
 * O token e obtido no painel da CPF.CNPJ (menu API > Tokens) e fica
 * atrelado ao IP de origem. Para testes existe um token publico que
 * devolve dados ficticios:
 *   5ae973d7a997af13f0aaf2bf60e65803
 *
 * Pacotes padrao:
 *   - CPF  pacote 3: nome + endereco completo + codigo IBGE
 *   - CNPJ pacote 6: razao/fantasia + endereco + Simples Nacional + situacao + porte
 *
 * O transporte HTTP e injetavel (callable que recebe a URL e devolve o
 * corpo bruto da resposta), o que mantem a classe testavel sem rede. Sem
 * transporte informado, usa cURL, o mesmo recurso ja adotado pelo pacote.
 */
class CpfCnpjComBrLookup implements PessoaLookup
{
    private string $token;
    private string $baseUrl;
    private int $pacoteCpf;
    private int $pacoteCnpj;
    private int $timeout;

    /**
     * @var callable(string):string
     */
    private $transporte;

    /**
     * @param string $token Token da conta na CPF.CNPJ
     * @param array $opcoes Chaves aceitas: baseUrl, pacoteCpf, pacoteCnpj, timeout, transporte
     */
    public function __construct(string $token, array $opcoes = [])
    {
        if (trim($token) === '') {
            throw new TomadorLookupException('Token da CPF.CNPJ nao informado.');
        }

        $this->token = $token;
        $this->baseUrl = rtrim($opcoes['baseUrl'] ?? 'https://api.cpfcnpj.com.br', '/');
        $this->pacoteCpf = (int) ($opcoes['pacoteCpf'] ?? 3);
        $this->pacoteCnpj = (int) ($opcoes['pacoteCnpj'] ?? 6);
        $this->timeout = (int) ($opcoes['timeout'] ?? 15);

        $transporte = $opcoes['transporte'] ?? null;
        if ($transporte !== null && !is_callable($transporte)) {
            throw new TomadorLookupException('O transporte informado nao e um callable valido.');
        }
        $this->transporte = $transporte ?? [$this, 'requisitarViaCurl'];
    }

    public function consultarCpf(string $cpf): PessoaTomador
    {
        $documento = preg_replace('/[^0-9]/', '', $cpf) ?? '';
        if (strlen($documento) !== 11) {
            throw new TomadorLookupException("CPF invalido para consulta: {$cpf}");
        }

        $dados = $this->consultar($this->pacoteCpf, $documento);

        return new PessoaTomador(
            tipo: 'cpf',
            documento: $documento,
            nome: trim((string) ($dados['nome'] ?? '')),
            logradouro: trim((string) ($dados['endereco'] ?? '')),
            numero: trim((string) ($dados['numero'] ?? '')),
            complemento: trim((string) ($dados['complemento'] ?? '')),
            bairro: trim((string) ($dados['bairro'] ?? '')),
            cep: preg_replace('/[^0-9]/', '', (string) ($dados['cep'] ?? '')) ?? '',
            cidade: trim((string) ($dados['cidade'] ?? '')),
            uf: trim((string) ($dados['uf'] ?? '')),
            codigoMunicipioIbge: trim((string) ($dados['ibge'] ?? ''))
        );
    }

    public function consultarCnpj(string $cnpj): PessoaTomador
    {
        $documento = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $cnpj) ?? '');
        if (strlen($documento) !== 14) {
            throw new TomadorLookupException("CNPJ invalido para consulta: {$cnpj}");
        }

        $dados = $this->consultar($this->pacoteCnpj, $documento);
        $endereco = $dados['matrizEndereco'] ?? [];
        $ibge = $dados['ibge']['cidade']['ibge_id'] ?? '';

        $simples = null;
        if (isset($dados['simplesNacional']['optante'])) {
            $simples = strtolower((string) $dados['simplesNacional']['optante']) === 'sim';
        }

        return new PessoaTomador(
            tipo: 'cnpj',
            documento: $documento,
            nome: trim((string) ($dados['razao'] ?? '')),
            logradouro: trim((string) ($endereco['logradouro'] ?? '')),
            numero: trim((string) ($endereco['numero'] ?? '')),
            complemento: trim((string) ($endereco['complemento'] ?? '')),
            bairro: trim((string) ($endereco['bairro'] ?? '')),
            cep: preg_replace('/[^0-9]/', '', (string) ($endereco['cep'] ?? '')) ?? '',
            cidade: trim((string) ($endereco['cidade'] ?? '')),
            uf: trim((string) ($endereco['uf'] ?? '')),
            codigoMunicipioIbge: trim((string) $ibge),
            fantasia: isset($dados['fantasia']) ? trim((string) $dados['fantasia']) : null,
            simplesNacional: $simples,
            situacao: isset($dados['situacao']['nome']) ? trim((string) $dados['situacao']['nome']) : null,
            porte: isset($dados['porte']['descricao']) ? trim((string) $dados['porte']['descricao']) : null
        );
    }

    /**
     * Executa a consulta e devolve o array de dados ja validado.
     *
     * @return array<string,mixed>
     */
    private function consultar(int $pacote, string $documento): array
    {
        $url = "{$this->baseUrl}/{$this->token}/{$pacote}/{$documento}";
        $corpo = ($this->transporte)($url);

        $dados = json_decode($corpo, true);
        if (!is_array($dados)) {
            throw new TomadorLookupException('Resposta invalida da CPF.CNPJ (JSON nao reconhecido).');
        }

        if ((int) ($dados['status'] ?? 0) !== 1) {
            $mensagem = $dados['erro'] ?? 'Consulta a CPF.CNPJ nao retornou dados.';
            $codigo = isset($dados['erroCodigo']) ? " (codigo {$dados['erroCodigo']})" : '';
            throw new TomadorLookupException("Consulta a CPF.CNPJ falhou: {$mensagem}{$codigo}");
        }

        return $dados;
    }

    /**
     * Transporte HTTP padrao via cURL.
     */
    private function requisitarViaCurl(string $url): string
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $resposta = curl_exec($curl);
        $erro = curl_error($curl);
        $httpcode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($resposta === false || $erro !== '') {
            throw new TomadorLookupException("Falha de comunicacao com a CPF.CNPJ: {$erro}");
        }
        if ($httpcode >= 400) {
            throw new TomadorLookupException("A CPF.CNPJ respondeu com HTTP {$httpcode}.");
        }

        return (string) $resposta;
    }
}
