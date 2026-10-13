# NFSe Padrão Nacional — Quantum Tecnology

Pacote PHP para emissão, consulta e geração de DANFSe da **NFS-e Padrão Nacional** ([nfse.gov.br](https://www.nfse.gov.br/)), construído sobre os componentes [NFePHP](https://github.com/nfephp-org).

Este é um **fork mantido pela [Quantum Tecnology](https://github.com/Quantum-Tecnology)** do excelente trabalho da equipe **Hadder** (ver [Créditos](#créditos)). Partindo daquela base, evoluímos o pacote com foco em **robustez em produção, suporte a prefeituras com emissor próprio e geração de DANFSe local** — recursos detalhados abaixo.

> **Em uso em produção**, porém em evolução contínua. Contribuições são bem-vindas via PR.

## ✨ Melhorias desta versão (Quantum)

Além de tudo que já existia no pacote original, esta versão adiciona:

### 🏛️ Destaque de IBS/CBS (Reforma Tributária — EC 132/2023)
O grupo `<IBSCBS>` do DPS é gerado a partir de `$std->infDPS->IBSCBS`.

> 🚨 **A partir da competência 01/10/2026 este grupo é obrigatório** para os serviços em geral (01/12/2026 para plataformas, locações, condomínios e bens imateriais; 01/01/2027 para o Simples Nacional) — sem ele a NFS-e é rejeitada na recepção. No pacote ele continua sendo emitido apenas quando presente no payload (sem o campo, o XML sai como antes), então **a responsabilidade de preenchê-lo é de quem integra**. Veja o [calendário](#-calendário-da-reforma--o-que-entra-e-o-que-sai-e-quando).

No DPS você **apenas declara a situação tributária** — `CST` e `cClassTrib`. As alíquotas e os valores de IBS/CBS são calculados pelo Ambiente de Dados Nacional e voltam no `<infNFSe>` da NFS-e autorizada; **não envie valores**.

```php
$std->infDPS->IBSCBS = (object) [
    'finNFSe'  => '0',        // 0 = regular (único valor aceito no schema v1.01)
    'indFinal' => '0',        // 0 = não é consumidor final
    'cIndOp'   => '030101',   // 6 dígitos (tabela oficial)
    'indDest'  => '0',        // 0 = tomador é o destinatário
    'valores'  => (object) [
        'trib' => (object) [
            'gIBSCBS' => (object) [
                'CST'        => '000',     // 3 dígitos
                'cClassTrib' => '000001',  // 6 dígitos
            ],
        ],
    ],
];
```

Grupos opcionais suportados: `tpOper`, `gRefNFSe` (até 99 chaves), `tpEnteGov`, `dest` (com endereço nacional ou exterior), `imovel` (`cCIB` ou endereço), `gReeRepRes` (até 1000 documentos), `cCredPres`, `gTribRegular` e `gDif`.

> ⚠️ **Os códigos são strings, nunca inteiros.** `CST`, `cClassTrib`, `cIndOp` e `cCredPres` têm zero à esquerda significativo — um cast para `int` transforma `'000001'` em `1` e a nota é rejeitada.

#### 📅 Calendário — o que já vale e o que vem

Situação em **13/10/2026**. Os itens com ⏳ ainda não estão em vigor.

| Data | O que muda | Impacto em quem emite | Fonte |
|---|---|---|---|
| ✅ **05/01/2026** | Leiaute RTC (grupo `<IBSCBS>`) entra em produção, **opcional** | Já dá para destacar IBS/CBS | NT 004 v2.0 |
| ✅ **03/08/2026** | **API de geração do DANFSe do ADN suspensa**; DANFSe v2.0 na Consulta Pública | O PDF passa a ser gerado localmente, ver [DANFSe local](#-danfse-local-sem-depender-do-adn) | NT 008 v1.02 |
| ✅ **10/08/2026** | **CNPJ alfanumérico em produção** na Sefin Nacional | Pacote aceita desde a 3.5.0, ver [CNPJ alfanumérico](#-cnpj-alfanumérico) | [Notícia do portal NFS-e](https://www.gov.br/nfse/pt-br/noticias/plataforma-nfs-e-disponibiliza-novas-evolucoes-em-producao-restrita-e-divulga-cronograma-de-implantacao) |
| ✅ **01/10/2026** | Grupo `<IBSCBS>` **obrigatório** para os serviços em geral | 🚨 **Emissão sem o grupo é rejeitada.** O pacote avisa com `E_USER_WARNING` (`Dps::IBSCBS_OBRIGATORIO_EM`) | Ato Conjunto RFB/CGIBS nº 4 |
| ✅ **01/10/2026** | `tpRetPisCofins` **deixa de aceitar `1` e `2`** | Use `0` ou os códigos `3` a `9` (já no XSD) | NT 007 · Anexo VI v1.04.01 |
| ⏳ **01/11/2026** | ME/EPP do Simples Nacional **obrigadas** a emitir NFS-e de padrão nacional | Quem ainda emite no padrão municipal migra | Resolução CGSN nº 191 |
| ⏳ **01/12/2026** | `<IBSCBS>` obrigatório para **plataformas digitais, locações (bens móveis e imóveis), condomínios, bens imateriais** e itens 1.03, 1.05, 1.09 e 16.01 da LC 116 | Esses segmentos ganham dois meses a mais | Ato Conjunto RFB/CGIBS nº 4 |
| ⏳ **01/01/2027** | `<IBSCBS>` obrigatório **também para o Simples Nacional** (até lá, dispensado) | Emissores do Simples precisam preencher o grupo | Ato Conjunto RFB/CGIBS nº 4 · Anexo VI |
| ⏳ **01/01/2027** | IBS/CBS cobrados **"por fora"**: `vTotNF = vLiq + vCBS + vIBSTot` (em 2026 é só `vLiq`) | Muda o valor total da nota — atenção a conciliação e integrações | Anexo VI v1.04.01 |
| ⏳ **01/01/2027** | PIS/COFINS saem da base do IBS/CBS: `vBC` deixa de subtrair `vPIS`/`vCOFINS` | Base de cálculo aumenta | Anexo VI v1.04.01 |
| ⏳ **a definir** | Leiaute da **NT 009** (notas de ajuste, Simples Nacional, `gPgtoVinc`…) | A NT diz que o cronograma "será publicado no portal". Até 13/10 não saiu | NT 009 v1.01 |
| ⏳ **até 2032** | Transição da EC 132/2023 | Convivência dos dois regimes | EC 132/2023 |

> ⚠️ **As datas da Reforma já mudaram mais de uma vez.** A obrigatoriedade do `<IBSCBS>` era 01/08/2026 e foi para 01/10/2026 com a NT 009 v1.01 e o Ato Conjunto nº 4. Confirme sempre no [portal da NFS-e](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica).

#### 🔭 Previsto no leiaute v1.04, ainda não implementado

Estes campos estão no Anexo VI v1.04.01, que consolida as NT 005, 007 e 009, mas **não** nos XSDs oficiais publicados (nem no de produção, nem no de produção restrita de 27/07/2026). Entram no pacote quando o schema sair: implementar só pelo PDF da NT é chute, e o leiaute ainda muda (a NT 009 tem regras "tachadas, em evolução").

| Campo / grupo | Para quê | NT |
|---|---|---|
| `indZFMALC` | Zona Franca de Manaus / ALC — alíquota zero de CBS | 007 |
| `indDoacao` + `gEstornoCred` | operação de doação e estorno dos créditos de entrada | 005 |
| `gPagAntecipado` | referência a NFS-e de pagamento antecipado | 005 |
| `gIBSCBSAjuste`, `tpNFSeDebito`, `tpNFSeCredito` | notas de ajuste de crédito e débito (`vIBS`, `vCBS`) | 009 (2.2) |
| `vAjusteBC` | une `vDedRed` e `gReeRepRes` num grupo só | 009 (2.3) |
| `regApIBSCBSSN`, `cAtvSN`, `opSimpNac = 4` | regime de apuração de IBS/CBS no Simples, atividade do Simples, "optante pendente" | 009 (2.4) |
| `bensMoveis` | locação de bens móveis (era `gLocBensMoveis`) | 009 (2.7) |
| `gPgtoVinc` | vinculação com até 99 transações de pagamento (PIX, cartão) | 009 (2.8) |
| `vAjusteBCIBSCBSComExt` e o grupo `comExt` | operações com o exterior | 009 (2.12–2.13) |
| grupo de condomínios | operações de condomínio | 009 (2.15) |

Pontos que **mudam estrutura** e por isso aguardam os novos schemas: `finNFSe` sai de `IBSCBS/finNFSe` e vai para `infDPS/finNFSe` (`0` regular, `1` crédito, `2` débito); `indDest`/`dest` sobem para `infDPS` (2.11); `CST`/`cClassTrib` são reposicionados (2.16); `indFinal` é reinserido (2.5); e o grupo `imovel` é redesenhado em `cMun` + `gLocacao` + `gUnidImob` (2.6). O `verCalcIBSCBS` (2.9) só aparece na NFS-e devolvida, não no DPS.

### 📋 Conformidade com as Notas Técnicas

| NT | Versão / data | Assunto | No pacote |
|---|---|---|---|
| **004** | v2.0 · 10/12/2025 | Leiaute RTC (grupos IBS/CBS) | ✅ Grupo `<IBSCBS>` desde a 3.x |
| **005** | v1.1 · 19/11/2025 | Evoluções do leiaute RTC | ⏳ Consolidada no v1.04; aguarda XSD |
| **007** | v1.0 · 07/02/2026 | Novos códigos de `tpRetPisCofins`, PIS/COFINS fora da base | 🟡 Códigos aceitos (XSD v1.01); `indZFMALC` aguarda XSD |
| **008** | v1.02 · 14/07/2026 | DANFSe v2.0 e suspensão da API do ADN | ✅ Layout v2.0 padrão desde a 3.4 |
| **009** | v1.01 · 11/09/2026 | CNPJ alfanumérico, notas de ajuste, Simples, `gPgtoVinc`… | 🟡 CNPJ alfanumérico na 3.5.0; o resto aguarda XSD e cronograma |
| **010** | v1.00 · 11/09/2026 | NFS-e Via e evento de manifestação do adquirente | ➖ Fora do escopo (NFS-e Via) |

As NT 001 a 003 foram consolidadas pelas seguintes, e a NT 006 trata só da NFS-e Via.

### 🔤 CNPJ alfanumérico
Desde **10/08/2026** a Sefin Nacional aceita o CNPJ alfanumérico (IN RFB nº 2.229/2024; NT 009, item 2.1): 12 posições `[A-Z0-9]` + 2 dígitos verificadores numéricos, como em `12.ABC.345/01DE-35`. O CNPJ numérico continua válido.

A partir da 3.5.0 o pacote aceita esse formato em todo lugar onde há CNPJ:

- **Campos** `CNPJ` do prestador, tomador, intermediário, destinatário, fornecedor e autor do evento (`TSCNPJ` = `[0-9A-Z]{14}` no XSD);
- **Identificadores**: `Id` da DPS, chave da NFS-e e `Id` do pedido de evento carregam a inscrição do emitente e, portanto, as letras;
- **DANFSe**: o documento sai formatado com as letras (`12.ABC.345/01DE-35`).

> ⚠️ **Não limpe CNPJ nem chave com `\D`** (`preg_replace('/\D/', '', ...)`): isso apaga as letras e gera um documento que não existe, ou que casa com outra empresa. Use `[^A-Z0-9]` sobre o valor em maiúsculas:
>
> ```php
> $cnpj = preg_replace('/[^A-Z0-9]/', '', strtoupper($cnpj));
> ```
>
> Para CPF, CEP e telefone o `\D` continua certo.

> O pacote **não confere o dígito verificador**: o XSD só valida o formato. Valide o DV no seu cadastro. O cálculo é módulo 11 sobre o valor ASCII de cada caractere menos 48, então a mesma conta vale para o CNPJ numérico.

> 🧩 **O XSD oficial se contradiz sobre onde a letra cabe na chave.** O `TSChaveNFSe` aceita letras nas posições 7 a 20 da chave, e o `Id` do evento aceita na inscrição, posições 10 a 23. Um CNPJ com letra no 12º caractere (o último da ordem) passa num e é recusado no outro. O pacote segue o XSD como publicado.

### 🧭 Roteamento inteligente por município (emissão × cancelamento × consulta)
Municípios com **emissor próprio** (ex.: **Americana-SP**) aceitam o leiaute nacional, mas num endpoint específico da prefeitura — enquanto as **consultas** continuam no Ambiente Nacional (ADN/SEFIN).

O pacote separa essas responsabilidades automaticamente:
- **Emissão / cancelamento** → usam a URL configurada em `storage/prefeituras.json` (override da prefeitura).
- **Consultas por chave** → usam sempre o Ambiente Nacional central.

Isso elimina os erros `404` que ocorriam quando a consulta era enviada, por engano, ao endpoint de emissão da prefeitura.

> ⚠️ **Emissão e cancelamento costumam ter endpoints DIFERENTES.** Em Americana-SP, por exemplo, a emissão vai para `.../api/adn/dps/recepcao` e o cancelamento (evento) para `.../api/adn/dps/evento`. Por isso o `storage/prefeituras.json` separa `urls` (base) de `operations` (caminho por operação): a URL final é `base + "/" + operação`. Se `cancelar_nfse` ficar vazio, o evento de cancelamento é postado na base de emissão e a prefeitura rejeita com **"DPS inválido ou não informado"**. Veja [Configuração da prefeitura](#configuração-da-prefeitura).

### 🧾 DANFSe local (sem depender do ADN)
Geração do PDF da DANFSe **diretamente a partir do XML**, sem precisar baixar o PDF oficial do Ambiente Nacional:
- **`Danfse`** — DANFSe completa a partir do XML autorizado.
- **`DanfseSimples`** — renderização tolerante (lê só a estrutura, não exige assinatura), ideal para **rascunhos, prévias e notas recebidas via DFe**.

> 🚨 **A API de geração do DANFSe do Ambiente Nacional foi suspensa em 03/08/2026** (item 1 da [NT nº 008](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica)). O `consultarDanfse()` deixou de ser o caminho principal: **o PDF gerado por esta biblioteca é o documento que o contribuinte entrega.**

#### Versão do layout: v2.0 (padrão) e v1.0

A NT nº 008 v1.02 (14/07/2026) publicou o modelo **DANFSe v2.0**, que é o layout gerado por padrão:

```php
// Padrão — modelo oficial do Anexo I da NT nº 008
$pdf = (new Danfse($xml))->render();

// Layout antigo, para quem já se acostumou com ele
$pdf = (new Danfse($xml))->setLayout(Danfse::LAYOUT_V1)->render();
```

| | `LAYOUT_V2` (padrão) | `LAYOUT_V1` |
|---|---|---|
| Origem | Anexo I da NT nº 008 v1.02 | Layout histórico, herdado do `sped-da` |
| Conformidade | Modelo oficial vigente | **Não corresponde ao modelo oficial de nenhuma versão** |
| Manutenção | Ativa | Congelado — sem correções de conformidade |

O que o **v2.0** traz e o v1.0 não tem:

- Bloco **Destinatário da Operação** (item 2.1.5) — o adquirente para fins de IBS/CBS (`infDPS/IBSCBS/dest`), que nem sempre é o tomador do serviço;
- **Tributação IBS/CBS completa** (item 2.1.10): CST/cClassTrib, indicador de operação, exclusões e reduções da base de cálculo, e as **alíquotas efetivas** de IBS (UF e municipal) e CBS;
- Totais do item 2.1.11: `Total das Retenções`, `Total do IBS/CBS` e **`VALOR LÍQUIDO DA NFS-e + IBS/CBS`**;
- **Supressões previstas na NT** (item 2.3): quando tomador, destinatário, intermediário ou a tributação municipal não se aplicam, o bloco não some — imprime a frase exigida. A ausência é informação fiscal tanto quanto a presença;
- Marca d'água de nota `CANCELADA` / `SUBSTITUÍDA` e a expressão `NFS-e SEM VALIDADE JURÍDICA` em homologação (itens 2.4.3 e 2.5);
- Página única em A4 retrato, tipografia e sombreamento conforme os itens 2.2 e 2.4.

> ⚠️ **Ao atualizar da 3.3 para a 3.4 o documento muda de aparência**, porque o padrão passou a ser o v2.0. Quem recebe o PDF vai notar. Para manter o visual anterior, fixe `setLayout(Danfse::LAYOUT_V1)` na chamada.

Útil quando o ADN está indisponível, para pré-visualização antes da transmissão, ou para notas importadas que não têm PDF oficial salvo.

### ✅ Validação contra os XSDs oficiais
Os schemas em `storage/schemes/` eram arquivos órfãos: o pacote assinava e transmitia XML fora do schema, e o erro só aparecia como rejeição genérica da SEFAZ (`L2103`). Agora **toda transmissão é validada antes de assinar**, seguindo o mesmo padrão do [sped-nfe](https://github.com/nfephp-org/sped-nfe).

`enviaDps()` e `cancelaNfse()` lançam `SchemaValidationException` — com o elemento exato apontado — em vez de mandar um documento inválido para a SEFAZ:

```php
use QuantumTecnology\NfseNacional\SchemaValidationException;

try {
    $retorno = $tools->enviaDps($dps->render());
} catch (SchemaValidationException $e) {
    // ["Element 'cServ': Missing child element(s). Expected is ( cNBS ). (linha 1)"]
    $erros = $e->getErrors();
}
```

Para validar por conta própria, antes de transmitir:

```php
$xml = $dps->render();

if ($erros = $dps->validate($xml)) {   // [] quando válido
    // trata sem gastar uma ida à SEFAZ
}

$dps->getErrors();  // campos obrigatórios que ficaram vazios na montagem
```

O XSD é escolhido pela versão declarada no próprio XML (`<DPS versao="...">`), então a convivência de v1.01 e futuras v1.04 funciona sem configuração. Se o schema de uma versão não estiver no pacote, a emissão **não é bloqueada** — mesma decisão do sped-nfe. Para desligar: `$tools->setValidateSchema(false)`.

### 🛡️ Validação que falha cedo e com mensagem clara
- **`cTribNac` obrigatório (6 dígitos)** é validado na montagem do DPS. Antes, um valor ausente gerava uma tag `<cTribNac/>` vazia e a SEFAZ rejeitava com **`L2103`** (XML fora do schema) — erro difícil de diagnosticar. Agora você recebe uma `InvalidArgumentException` clara, **antes** de assinar e transmitir.
- **`getOperation()`** e a resolução de URL lançam exceção em chaves/origens desconhecidas, em vez de falhar silenciosamente.

### 🏷️ Namespace próprio
O namespace passou a ser **`QuantumTecnology\NfseNacional`**, alinhado aos demais pacotes da Quantum.

> ⚠️ **Breaking change** ao migrar de versões anteriores: troque os `use Hadder\NfseNacional\...` por `use QuantumTecnology\NfseNacional\...`.

### 🔁 Eventos: cancelamento e cancelamento por substituição
O pedido de registro de evento segue o XSD oficial v1.01: **sem** `<nPedRegEvento>` (o grupo do evento vem logo depois do `chNFSe`) e `Id` de 59 caracteres — `"PRE"` + chave (50) + tipo do evento (6). Ver a 3.5.0 no CHANGELOG: até ela, o pacote validava contra uma edição antiga do XSD e gerava o elemento.

Além do `e101101` (cancelamento), agora também é gerado o corpo do **`e105102`** (cancelamento por substituição), que antes tinha o código mapeado mas produzia um `infPedReg` sem o grupo do evento.

```php
$std->infPedReg->e101101 = (object) [
    'cMotivo' => '1',                              // TSCodJustCanc: 1, 2 ou 9
    'xMotivo' => 'Erro na emissao da nota fiscal',
];

// Substituição usa outra enumeração — com zero à esquerda:
$std->infPedReg->e105102 = (object) [
    'cMotivo'      => '01',                        // TSCodJustSubst: 01..05, 99
    'chSubstituta' => '...',                       // chave da NFS-e substituta
];
```

O `xDesc` **não precisa ser informado** — é enumeração de valor fixo no schema e o pacote o deriva do próprio evento e da versão do documento (a grafia do 105102 é acentuada na v1.01 e sem acento na v1.00).

### 🛡️ Compatibilidade de versão do PHP
`generateId()` e o cliente HTTP usavam `mb_str_pad()` e `mb_trim()`, **funções exclusivas do PHP 8.4**, enquanto o pacote declara `^8.1`. Em PHP 8.1–8.3 a instalação passava sem aviso e o pacote quebrava na primeira emissão. Trocadas por `str_pad()`/`trim()`.

> A regra `mb_str_functions` do `.php-cs-fixer.php` foi **desligada de propósito**: ela reconvertia essas chamadas automaticamente, reintroduzindo o bug a cada formatação.

### 🧹 Limpeza e correções
- **`cNBS` é obrigatório** no schema e era emitido sob `isset()`, como se fosse opcional — a nota saía sem ele e a SEFAZ rejeitava com `L2103`.
- Remoção de código morto (incluindo um elemento `<DPS>` órfão criado dentro do gerador de eventos) e de um `dump()` que vazava no output em produção.
- Correção de recursão sem `return` na geração de nomes de arquivos temporários de certificado.

## Instalação

Este pacote é distribuído via [Composer](https://getcomposer.org/):

```bash
composer require quantumtecnology/nfse-nacional
```

### Requisitos

- PHP 8.1+
- ext-dom, ext-curl, ext-zlib, ext-openssl, ext-mbstring

### Testes

```bash
composer install
composer test          # ou: vendor/bin/phpunit
```

A suíte valida o XML gerado contra os **XSDs oficiais v1.01** (`storage/schemes/`) e inclui um teste de paridade com uma NFS-e real autorizada pelo Ambiente Nacional (fixture anonimizada em `tests/Fixtures/`).

## Serviços implementados

| Método | Descrição | Exemplo |
|---|---|---|
| `enviaDps` | Emite a NFS-e (envia o DPS) | [MakeDps.php](examples/MakeDps.php) |
| `enviaDps` **com IBS/CBS** | Emissão com o destaque da Reforma Tributária | [MakeDpsIbsCbs.php](examples/MakeDpsIbsCbs.php) |
| `cancelaNfse` (`e101101`) | Cancela uma NFS-e autorizada | [CancelaNfse.php](examples/CancelaNfse.php) |
| `cancelaNfse` (`e105102`) | Cancelamento por **substituição** | [SubstituiNfse.php](examples/SubstituiNfse.php) |
| `consultarNfseChave` | Consulta a NFS-e pela chave (XML) | [ConsultaNfseChave.php](examples/ConsultaNfseChave.php) |
| `consultarDpsChave` | Consulta o DPS pela chave | [ConsultaDpsChave.php](examples/ConsultaDpsChave.php) |
| `consultarNfseEventos` | Consulta eventos de uma NFS-e | [ConsultaNfseEventos.php](examples/ConsultaNfseEventos.php) |
| `consultarDanfse` | Baixa a DANFSe (PDF oficial) do ADN | [ConsultaDanfse.php](examples/ConsultaDanfse.php) |
| `Danfse` / `DanfseSimples` | DANFSe oficial **com fallback local** quando o ADN falha | [GeraDanfseLocal.php](examples/GeraDanfseLocal.php) |
| `Dps::validate()` / `getErrors()` | Valida o XML antes de transmitir | [MakeDpsIbsCbs.php](examples/MakeDpsIbsCbs.php) |

Todos os exemplos estão em [`examples/`](examples/) e rodam em PHP puro — basta ajustar o certificado e os dados do emitente.

## ⚠️ Avisos importantes

### DANFSe: a API do ADN foi SUSPENSA em 03/08/2026

A NT nº 008 (item 1) sobrestou a API de geração do DANFSe (`https://adn.nfse.gov.br/danfse/...`). **Gere o PDF localmente** — veja [DANFSe local](#-danfse-local-sem-depender-do-adn):

```php
$pdf = (new Danfse($xml))->render(); // modelo oficial v2.0
```

O `consultarDanfse()` continua no pacote para quem ainda o usa contra endpoint de prefeitura, e o comportamento descrito abaixo vale para esses casos.

#### Quando o endpoint responde, ele é intermitente

`consultarDanfse()` pode voltar **vazio para uma chave válida** — indisponibilidade temporária, não "nota inexistente". Em produção, trate assim:

1. **Repita algumas vezes** antes de desistir (com espera progressiva);
2. **Confira a assinatura do PDF** (`str_starts_with($retorno, '%PDF')`) — o endpoint também devolve JSON de erro;
3. **Não repita em caso de exceção** (certificado, config, rede): é falha dura;
4. **Caia para a geração local** a partir do XML.

> 🔑 A chave tem **50 posições**. A SEFAZ devolve `chaveAcesso` com o prefixo `NFS` (decoração do `Id` do XML) e, com ele, a consulta volta **vazia sem erro** — um dos enganos mais comuns. Tire o prefixo e a pontuação **sem apagar letras**, porque a chave de emitente com [CNPJ alfanumérico](#-cnpj-alfanumérico) tem letras:
>
> ```php
> $chave = preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/^NFS/i', '', $chave)));
> ```

A implementação completa está em [GeraDanfseLocal.php](examples/GeraDanfseLocal.php).

### Configuração da prefeitura

A variável `prefeitura` aceita atualmente dois formatos:

- Um identificador textual, por exemplo: `americana-sp`
- O **código IBGE** do município (ex.: `3501608`)

⚠️ Ambos são aceitos por compatibilidade, mas **o padrão futuro será exclusivamente o código IBGE**. Recomenda-se já adotá-lo. As URLs e operações por município ficam em [`storage/prefeituras.json`](storage/prefeituras.json).

#### Estrutura do `prefeituras.json`

Cada município tem `urls` (a **base** por ambiente) e `operations` (o **caminho** de cada operação). A URL final é montada como `base + "/" + operação` (a operação vazia `""` usa a base direta):

```jsonc
{
    "3501608": {                         // código IBGE (chave recomendada)
        "urls": {
            "sefin_producao":    "https://nfse.americana.sp.gov.br/api/adn/dps",
            "sefin_homologacao": "https://americanahomologacao.nfe.com.br/api/adn/dps"
        },
        "operations": {
            "emitir_nfse":   "recepcao",  // => .../api/adn/dps/recepcao
            "cancelar_nfse": "evento"     // => .../api/adn/dps/evento
        }
    }
}
```

> ⚠️ **Não deixe `cancelar_nfse` vazio quando o cancelamento usa um caminho diferente da emissão.** Se a base apontar para `.../recepcao` e `cancelar_nfse` for `""`, o evento de cancelamento será postado em `.../recepcao` e a prefeitura responderá **"DPS inválido ou não informado"**. Prefira manter a base no nível comum (`.../api/adn/dps`) e especificar cada operação. Consulte o manual da prefeitura para os endpoints corretos (emissão, evento/cancelamento, consulta por chave, download de XML/DANFSe).

### `consultarNfseChave()` e encoding

O XML, após o `gzdecode`, vem em **ISO-8859-1**. Por padrão o método mantém ISO via `mb_convert_encoding`. Caso tenha problemas, passe `false` no segundo parâmetro para receber o XML cru:

```php
// Retorna ISO-8859-1 (padrão)
$tools->consultarNfseChave('CHAVE_NFSE');

// Retorna o XML cru, sem mb_convert_encoding
$tools->consultarNfseChave('CHAVE_NFSE', false);
```

## FAQ — E999 (erro não catalogado)

Esse erro se refere a uma falha **não catalogada pela própria Receita**, incluindo erros de servidor (500) e problemas aleatórios. No ambiente de **homologação** costuma aparecer sem motivo aparente, enquanto em **produção** a nota normalmente é emitida sem problemas.

Causa mais comum relatada:

- CPF/CNPJ do **prestador** não existente / não cadastrado / não habilitado na NFS-e Nacional ou na prefeitura.

## Créditos

Este fork é mantido pela **[Quantum Tecnology](https://github.com/Quantum-Tecnology)**, mas **não nasceu do zero**. Ele se apoia inteiramente no trabalho de quem veio antes.

Nosso agradecimento à **equipe Hadder** e em especial ao **Fernando Friedrich** ([Rainzart/nfse-nacional](https://github.com/Rainzart/nfse-nacional)) por criar, manter e disponibilizar este pacote como Open Source. As melhorias desta versão só foram possíveis porque havia uma base sólida e bem estruturada para construir em cima.

A seguir, o agradecimento original do autor — que mantemos na íntegra:

> ### (por Fernando Friedrich)
>
> Este pacote **não caiu do céu**, **não apareceu por geração espontânea** e muito menos foi escrito do zero em um surto de genialidade de minha parte.
>
> Ele foi **copiado, clonado, analisado, desmontado, reaproveitado, adaptado e por fim ajustado por mim**, tendo como base pacotes de emissão de **NFSe** que eram disponibilizados como **Open Source** pelo Sr. **[Roberto L. Machado](https://github.com/robmachado)** e que, atualmente, não se encontram mais disponíveis publicamente.
>
> Sim, **variáveis, métodos, classes, estruturas e ideias de arquitetura** foram utilizadas como referência (copiadas) — algumas foram alteradas, outras melhoradas, outras apenas sobreviveram ao tempo — sempre tendo como principal base o projeto **[NFePHP](https://github.com/robmachado/sped-nfse)**.
>
> Na época da criação deste repositório, o cenário era simples: eu precisava **emitir notas fiscais para meus clientes**. Não existia nenhuma alternativa Open Source ativa e funcional em PHP, e depender de **APIs pagas** definitivamente não era uma opção para mim.
>
> Diante disso, fica aqui meu agradecimento **mais do que merecido** ao **Roberto**, por criar, manter e disponibilizar gratuitamente projetos como o **NFePHP**.
>
> Sem esse trabalho prévio, este repositório **muito provavelmente não existiria**.

E, por fim, obrigado a **todas as pessoas que contribuem** com este projeto — enviando PRs, sugerindo melhorias, corrigindo bugs ou apontando problemas. A lista de contribuidores do projeto original pode ser vista em: https://github.com/Rainzart/nfse-nacional/graphs/contributors

## Licença

MIT.
