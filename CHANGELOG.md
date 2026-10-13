# Changelog

Todas as mudanças relevantes desta biblioteca são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

**Antes de atualizar em produção, leia a seção da versão de destino.** Mudanças que exigem ação manual aparecem sob **⚠️ Requer atenção**.

> **Nota sobre o histórico.** Este arquivo começa na `3.3.0`. As versões anteriores (24 tags, de `1.0.0` a `3.2.4`) não tinham changelog escrito e não serão reconstruídas a partir do log do Git.

## [3.5.0] — 2026-10-13

### ⚠️ Requer atenção

- **Cancelamento e substituição de NFS-e: o XML do evento mudou.** Saem o elemento `<nPedRegEvento>` e os 3 dígitos finais do `Id` (que volta a ter 59 caracteres). Se a sua integração valida ou monta o evento por fora do pacote, ajuste. O campo `$std->infPedReg->nPedRegEvento`, se ainda vier no payload, é ignorado.
- **XSDs em subpastas por versão** (`storage/schemes/1.00/`, `storage/schemes/1.01/`), como no pacote oficial. Quem lia os arquivos direto da pasta deve passar a usar `SchemaValidator::schemaPath($documento, $versao)`.

### Corrigido

- **Os XSDs eram uma edição antiga da v1.01, anterior à de produção.** Agora são os oficiais: a v1.00 vem do pacote de produção (`nfse-esquemas_xsd-v1-01-20260209`) e a v1.01 do de produção restrita (`esquemas-nfse-rtc-v1-01-20260727`), que é o de produção mais o CNPJ alfanumérico. Com o XSD antigo, a validação local recusava XML que a Sefin aceita e aceitava XML que ela recusa.
- **Cancelamento passava na validação local e era recusado pela Sefin Nacional.** A edição antiga exigia `<nPedRegEvento>` e `Id` de 62 caracteres. A 3.x (commit `188f3ac`) passou a gerá-los para "passar no XSD", desfazendo a correção do upstream de jan/2026. O XSD oficial não tem o elemento e limita o `Id` a 59.
- **`xDesc` do cancelamento por substituição (e105102)** passou a ser "Cancelamento de NFS-e por Substituição", com acento, como na v1.01 oficial. Na v1.00 continua sem acento.
- **DANFSe apagava as letras do CNPJ alfanumérico** ao formatar o documento: `12ABC34501DE35` saía como `123450135`.
- **`Dps::IBSCBS_OBRIGATORIO_EM` passa de `2026-08-01` para `2026-10-01`**, a data do Ato Conjunto RFB/CGIBS nº 4 e do Anexo VI v1.04.01 da NT 009 v1.01. Antes, DPS sem `<IBSCBS>` com competência de agosto ou setembro gerava um aviso indevido.
- **O README e o exemplo `GeraDanfseLocal.php` mandavam limpar a chave com `\D`**, o que apaga as letras de uma chave com CNPJ alfanumérico. Agora a limpeza é com `[^A-Z0-9]`.

### Documentação

- README ganhou três seções: **CNPJ alfanumérico**, **Conformidade com as Notas Técnicas** (NT 004 a 010, com o estado de cada uma no pacote) e o **calendário revisado**, com o que já vale e o que vem até 2027, cada item com a fonte (Ato Conjunto RFB/CGIBS nº 4, Resolução CGSN nº 191, NTs). A lista "previsto no leiaute v1.04" agora indica a NT de cada campo e inclui os da NT 009 que faltavam (`regApIBSCBSSN`, `cAtvSN`, `vAjusteBC`, notas de ajuste, `comExt`, condomínios).

### Adicionado

- **CNPJ alfanumérico** em todos os campos de CNPJ e nas chaves (`Id` da DPS, chave da NFS-e e do evento), conforme o XSD oficial. Está em produção na Sefin Nacional desde 10/08/2026. O builder já montava o `Id` corretamente; faltava o schema aceitar.

### Pendente (sem schema oficial ainda)

- **NT 009 (`gPgtoVinc`, `regApIBSCBSSN`, `cAtvSN`).** A NT 009 v1.01 está publicada, mas nenhum pacote XSD oficial traz esses campos até 13/10/2026: nem o de produção (09/02) nem o de produção restrita (27/07). Entram quando o schema sair.
- **O XSD oficial se contradiz sobre onde a letra cabe na chave.** O `TSChaveNFSe` aceita letras nas posições 7 a 20, e o `Id` do evento aceita na inscrição, posições 10 a 23. Um CNPJ com letra no 12º caractere (o último da ordem) passa no `Id` e é recusado na chave. Ficou como está no oficial.

## [3.4.4] — 2026-10-05

### Alterado

- **DANFSe v2.0: o cinza do cabeçalho passa a correr por baixo da logomarca.** A logo de fundo branco deixava um retângulo branco destacado no canto do cabeçalho cinza 5%. O pacote agora traz `imgs/nfse_logo_cinza5.png`, que é a marca oficial transparente (do site da NFS-e Nacional) achatada sobre o cinza exato do cabeçalho (RGB 242, o `CINZA_5` do `LayoutV2`). No PDF o efeito é igual ao de uma imagem transparente, e o arquivo continua sem canal alfa, que o FPDF não desenha. O v1, de fundo branco, continua usando `nfse_logo.png`.
- **Logo do integrador** (`render($logo)` ou `$logomarca`) mantém a faixa branca de antes: é um arquivo opaco de fundo desconhecido, e o cinza por baixo viraria um retângulo em volta dela. A decisão fica em `AbstractDanfse::marcaAceitaFundoCinza()`.

## [3.4.3] — 2026-09-28

### Corrigido

- **DANFSe v2.0: frases de bloco suprimido sobrepostas.** Sem tomador (nota 2), "TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO" e "DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO" saíam **uma em cima da outra**. O `suprimeBloco()` recebia a altura do bloco à mão e somava a faixa de título de novo — que já está dentro da distância entre os blocos —, então o bloco seguinte subia exatamente para cima da faixa. A altura agora vem da própria tabela `Y` (distância até o bloco seguinte). O mesmo erro deixava folgas tortas depois do destinatário, do intermediário e do ISSQN suprimidos. Apareceu agora porque o tomador passou a ser opcional.
- Teste novo mede a **posição** das frases no PDF (os testes de texto não pegavam bloco fora de lugar).

## [3.4.2] — 2026-09-15

### Adicionado

- **A logomarca oficial da NFS-e passa a ser distribuída com o pacote** (`imgs/nfse_logo.png`), baixada do endereço que a própria NT nº 008 indica (item 2.4.3). O código já a procurava nesse caminho desde a 3.3, mas o arquivo nunca existiu — então o cabeçalho caía no desenho em texto ("NFSe / Nota Fiscal de / Serviço eletrônico"), que não é a marca oficial. Vale para os dois layouts.

  ⚠️ **A imagem é gravada SEM canal alfa, de propósito.** O FPDF lança `Alpha channel not supported` em PNG com transparência, e o `catch` do renderizador caía no texto **sem registrar nada** — o sintoma era "a logo não aparece", sem erro nenhum para investigar. Se um dia a imagem for substituída, achate o alfa antes.

### Corrigido

- **Endereço longo atravessava a divisória da coluna vizinha.** Os campos de coluna dupla (Nome, Endereço, E-mail) ocupam de X 0,30 a 10,51, mas a divisória de X 5,41 era desenhada descendo o bloco inteiro e cortava o texto ao meio. Agora ela desce apenas a faixa do título, onde as quatro colunas de fato existem; as demais linhas recebem só as divisórias que não cruzam campos largos.

- **Faixa em branco entre um bloco suprimido e o seguinte.** A altura desenhada para a frase de supressão (`cell` de 0,32cm) e a altura descontada do deslocamento divergiam, e a sobra virava um espaço vazio com linhas soltas no meio do documento. As duas passam a usar a mesma constante.

- **Vão em branco entre a descrição do serviço e a Tributação Municipal** quando havia blocos suprimidos acima. O espaço liberado era devolvido ao bloco de serviço, mas os blocos seguintes voltavam às coordenadas cruas da NT — a folga ficava presa no meio da página em vez de descer para as informações complementares (item 2.5.3).

## [3.4.1] — 2026-09-14

### Corrigido

- **Cabeçalho do v2.0 exibia o código IBGE cru em nota ABRASF.** O campo `xLocEmi` só existe no leiaute NACIONAL; num XML ABRASF ele não vem, e o cabeçalho saía como `Município: 3501608` — que não diz nada a quem recebe a nota. Agora o código é resolvido pela tabela do IBGE já embarcada no pacote (`Município: Americana - SP`), com o código cru mantido apenas quando o município não está na tabela.

## [3.4.0] — 2026-09-14

DANFSe no modelo oficial da **NT SE/CGNFS-e nº 008 v1.02** (14/07/2026), com a versão do layout selecionável.

### ⚠️ Requer atenção

- **O DANFSe gerado muda de aparência.** O padrão passa a ser o layout **v2.0** — o modelo do Anexo I da NT nº 008. O documento tem blocos novos, outra ordem e outros rótulos; quem recebe o PDF vai notar.

  O motivo de o padrão mudar agora: a **API de geração do DANFSe do Ambiente Nacional foi suspensa em 03/08/2026** (item 1 da NT). O PDF gerado localmente deixou de ser plano B e passou a ser o documento que o contribuinte entrega.

  Para manter a aparência anterior, fixe o layout antigo na chamada:

  ```php
  (new Danfse($xml))->setLayout(Danfse::LAYOUT_V1)->render();
  ```

- **O layout v1.0 está congelado.** Ele é o desenho histórico desta biblioteca (herdado do `sped-da`) e **não corresponde ao modelo oficial de nenhuma versão** do DANFSe — nem do v1.0 publicado pelo CGNFS-e. Continua disponível, mas não receberá correções de conformidade. Para emitir, use o v2.0.

### Adicionado

- **Layout selecionável** por `setLayout()`, com as constantes `Danfse::LAYOUT_V1` e `Danfse::LAYOUT_V2`. Uma versão desconhecida cai no padrão em vez de lançar exceção: o método está no caminho de geração de um documento fiscal, e ficar sem PDF é pior que receber o layout vigente.
- **Bloco "Destinatário da Operação"** (item 2.1.5), que não existia. É o adquirente para fins de IBS/CBS (`infDPS/IBSCBS/dest`) e nem sempre é o tomador do serviço — por isso ganha bloco próprio no modelo oficial.
- **Tributação IBS/CBS completa** (item 2.1.10): CST/cClassTrib, indicador de operação, exclusões e reduções da base de cálculo, base após exclusões, reduções de alíquota e as **alíquotas efetivas** de IBS (UF e municipal) e CBS.
- **Totais do item 2.1.11**: "Total das Retenções (ISSQN / Federais)", "Total do IBS/CBS" e "VALOR LÍQUIDO DA NFS-e + IBS/CBS".
- **Supressões previstas na NT** (item 2.3 e notas 2 a 4): quando tomador, destinatário, intermediário ou a tributação municipal não se aplicam, o bloco não some — imprime a frase exigida. A ausência é informação fiscal tanto quanto a presença.
- **Marca d'água** de nota `CANCELADA` / `SUBSTITUÍDA` e a expressão `NFS-e SEM VALIDADE JURÍDICA` em homologação (itens 2.4.3 e 2.5).

### Corrigido

- **PIS e COFINS eram contados duas vezes quando havia retenção.** Pela regra da NT v1.02 (pág. 19), com `tpRetPisCofins = 1` os campos de débito de apuração própria saem **zerados** e os valores migram para "Contribuições Sociais - Retidas". Antes, o mesmo tributo aparecia nos dois lugares.
- **`ambGer` e `tpAmb` estavam no mesmo campo.** São coisas distintas no leiaute — `ambGer` diz *quem* gerou a NFS-e (prefeitura ou Sistema Nacional); `tpAmb`, se é produção ou homologação. O v2.0 exibe os dois separadamente, como o modelo oficial.
- **Descrição de serviço com acento podia sumir do PDF inteiro, em silêncio.** A normalização de espaços usava `preg_replace` com o modificador `/u`, que devolve `NULL` para texto em ISO-8859-1 — o encoding dos dados após o parse.

## [3.3.1] — 2026-09-01

### Alterado

- **O rodapé do DANFSe passa a creditar a Quantum.** Antes assinava apenas `Powered by NFePHP`, que é o pacote de onde vem o motor de PDF (`sped-da`) — não quem gera o documento. Agora consta `Powered by Quantum Tecnologia - NFS-e Nacional (sobre NFePHP)`. A atribuição ao NFePHP **permanece de propósito**: o FPDF usado vem mesmo do `sped-da` (LGPL/MIT) e retirar o crédito seria incorreto, além de contrariar a licença.

## [3.3.0] — 2026-09-01

DANFSe reescrito para bater com o documento oficial e passar a exibir o quadro **IBS/CBS** da Reforma Tributária.

### Corrigido

Esta seção não trata de estética: o DANFSe **imprimia informação fiscal errada**. Todos os casos abaixo foram conferidos contra o XML de notas reais autorizadas, versionadas em `tests/Fixtures/`.

- **ISSQN e alíquota saíam ZERADOS.** O parser lia os valores de `DPS/infDPS/valores` — o documento que o contribuinte **envia**, onde `tribMun` apenas declara a intenção (`tribISSQN`, `tpRetISSQN`) e não existe `vBC`, `pAliq` nem `vISSQN`. Quem **calcula** base, alíquota e imposto é o Fisco, em `infNFSe/valores`. Numa nota de Uberlândia o documento mostrava `BC R$ 441,60 | 0,00 % | R$ 0,00` onde o correto era `BC R$ 441,60 | 3,00 % | R$ 13,25`. O DPS continua sendo usado como *fallback* quando o XML não traz o cálculo.

- **Tributação federal também zerada, por nomes de chave inexistentes.** O código buscava `vPIS`, `vCOFINS`, `vINSS`, `vIR` e `vCSLL`. No schema v1.01 os nomes são `piscofins/vPis`, `piscofins/vCofins`, `vRetCP`, `vRetIRRF` e `vRetCSLL` — nenhum dos antigos existe em lugar nenhum do XML, então o bloco inteiro imprimia `R$ 0,00`.

- **Regime do Simples Nacional classificado errado.** `opSimpNac` era traduzido pela tabela de `regEspTrib`, que tem domínio completamente diferente. Uma ME/EPP (`opSimpNac=3`) aparecia como *"Sociedade de Profissionais"*. Agora são três de-para distintos — `opSimpNac`, `regApTribSN` e `regEspTrib` — e o documento mostra os **dois** campos do Simples, como o oficial.

- **A retenção nunca aparecia.** O ternário era `$x == 1 ? 'Nao Retido' : 'Nao Retido'` — ramos idênticos, tanto no ISSQN quanto no PIS/COFINS. Uma nota com ISS retido saía como não retido. Passa a ler `tpRetISSQN` / `tpRetPisCofins` (1 = Retido).

- **Município em branco.** O parser lia `xMun`, que **não existe** em `enderNac`/`endNac` — só `cMun`, o código IBGE. Ver *Adicionado* sobre a tabela de municípios.

- **`*** DOCUMENTO PROVISORIO ***` carimbado em vermelho de forma incondicional**, inclusive em nota autorizada (`cStat 100`). A linha existia apenas em `DanfseSimples`; em `Danfse` estava comentada — sintoma direto de as duas classes serem cópias que divergiram em silêncio. Em compensação, `DanfseSimples` **não** desenhava as tarjas reais de homologação/cancelamento, que só existiam na outra.

- **Códigos de tributação sem descrição.** `xTribNac` e `xTribMun` vêm no XML autorizado e eram ignorados: o PDF imprimia só `080201` e deixava o municipal vazio. Agora sai `038 - 8599603 - Treinamento em informática`, como no oficial.

- **Campos fixos no lugar de dados reais.** *País Resultado* era sempre `Brasil` e *Regime Especial* sempre `Nenhum`, apesar de `cPaisResult` e `regEspTrib` já serem parseados.

- **`Str::lower($x) ?? ''`** tinha o `??` fora do parêntese e emitia notice quando o e-mail faltava.

### Adicionado

- **Quadro IBS/CBS** (Reforma Tributária, EC 132/2023) com CST, classificação tributária, município de incidência, base, as três alíquotas e os valores apurados. O bloco é **sempre exibido** — zerado quando a nota não tem o grupo, porque a ausência do tributo é informação fiscal tanto quanto a presença. Obrigatório a partir de **01/08/2026** (`Dps::IBSCBS_OBRIGATORIO_EM`) e **01/01/2027** para o Simples Nacional.

- **Acentuação correta nos rótulos** (`TRIBUTAÇÃO MUNICIPAL`, `Município`, `Código de Tributação`). As fontes core do FPDF são ISO-8859-1 e não cobrem acentuação — por isso os rótulos tinham sido escritos sem acento. O pacote passa a distribuir **DejaVu Sans Condensed** (licença livre, em `storage/fonts/`), na variante Condensed por ser a mais próxima da usada no documento oficial.

- **Tabela IBGE com 5.571 municípios** (`storage/municipios.php`, da API oficial do IBGE, carregada sob demanda). Para o emitente o XML traz `xLocEmi`; para o **tomador não existe nome nenhum** no documento, apenas o código — e o oficial imprime `Campinas - SP`.

- **Seções que faltavam**: intermediário do serviço (com a faixa *"NÃO IDENTIFICADO NA NFS-e"* quando ausente, como no oficial) e totais aproximados dos tributos (Lei 12.741/2012).

- **`setOrgaoEmissor()`** para o bloco de Prefeitura/Secretaria/telefone/e-mail no cabeçalho. Esses dados **não existem no XML** — o portal os obtém de cadastro próprio por município. Sem eles o espaço fica livre, em vez de o documento exibir dado inventado.

- **Primeira cobertura de testes do DANFSe**: 73 testes, 233 asserções. Antes eram **zero** para ~2.600 linhas.

### Alterado

- **`Danfse` e `DanfseSimples` agora compartilham `Danfse\AbstractDanfse`.** Eram cópias de ~1.300 linhas que divergiram em silêncio (daí a tarja de provisório numa e não na outra); qualquer correção precisava ser feita duas vezes — e nem sempre era. As subclasses ficaram com o que de fato as diferencia: a marca do cabeçalho e o QR Code. **1.044 linhas a menos.**

- **`Danfse` funciona fora do Laravel.** Antes usava `Illuminate\Support\Str`, `public_path()` e a facade `LaravelQRCode` — **nenhum dos três declarado no `composer.json`** — e gravava um PNG por nota em `public/images/`, sem criar o diretório nem limpar depois. O QR passa a ser gerado em memória.

- Título `DANFSe V1.0` → `DANFSe v1.0`, e campo sem valor passa a exibir `-`, como no oficial.

### ⚠️ Requer atenção

- **Nova dependência: `chillerlan/php-qrcode ^6.0`.** Substitui a facade `LaravelQRCode`, que era usada sem estar declarada.

- **Quem ESTENDE as classes deve conferir.** A API pública não muda — `new Danfse($xml)` e `render($logo)` seguem iguais —, mas `Danfse` e `DanfseSimples` passaram a estender `Danfse\AbstractDanfse` em vez de `DaCommon` diretamente. As propriedades e métodos protegidos de uso comum (`$infNfse`, `$prestador`, `$tomador`, `$servico`, `statusNFSe()`, `adjustImage()`, `$logomarca`) foram preservados.

- **Contornos que viraram redundantes.** Quem mantinha um placeholder de logo para evitar o `$nImgW`/`$nImgH` indefinidos do `renderCabecalho()` pode removê-lo: a ausência de logo passou a ser tratada e o QR tem posição própria.

- **O peso do pacote subiu ~880 KB** (fontes + tabela IBGE). É o custo de acentuação correta e de resolver o município do tomador, que o XML não informa.

- **`.gitattributes` marca `*.z` como binário.** Sem isso a conversão de fim de linha corromperia as fontes no checkout — o PDF sairia sem texto, e o erro só apareceria para quem clonasse o repositório.

### Não implementado

- Leiaute **RTC IBS/CBS v1.04** (NT SE/CGNFS-e nº 009): `indZFMALC`, `indDoacao`/`gEstornoCred`, `gIBSCBSAjuste`, `gPagAntecipado`, `gPgtoVinc`, `bensMoveis`.
- **Imposto Seletivo (IS)** — não existe em nenhum ponto do pacote.
- Grupo `interm` na **geração** do DPS (segue como TODO em `Dps.php`); o DANFSe já sabe **ler** o grupo quando o XML o traz.
