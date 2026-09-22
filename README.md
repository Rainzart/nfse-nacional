# NFSe Padrão Nacional

> **Fork mantido pela [EZAPP/Sygma](https://github.com/ezapp-sistemas/nfse-nacional)** (Paulo Henrique de Castro), com **impressão nativa do DANFSe** (NT-008 SE/CGNFS-e). Continuação do projeto `hadder/nfse-nacional`.

Pacote para geração de NFSe Padrão Nacional (https://www.nfse.gov.br/) usando componentes NFePHP (https://github.com/nfephp-org).

Este pacote foi desenvolvido para atender algumas das minhas necessidades, implementei o que utilizei e a toque de caixa. Se quiser colaborar envie seu PR.

**Em desenvolvimento. Use por sua conta e risco.**

## ⚠️⚠️⚠️ AVISOS ⚠️⚠️⚠️

###  Configuração da Prefeitura

Na configuração do sistema, a variável `prefeitura` pode receber atualmente dois tipos de valores:

- Um identificador textual, por exemplo: `americana-sp`
- O código IBGE do município

⚠️ **Importante:** no momento, ambos os formatos são aceitos por compatibilidade.  
Porém, **futuramente o padrão adotado será exclusivamente o código IBGE**.  
Recomenda-se desde já utilizar o código IBGE para evitar ajustes em versões futuras.

### Método consultarNfseChave() e encoding

O arquivo XML após o gz_decode está vindo em ISO-8859-1. O método vai passar pelo mb_convert_encoding mantendo ISO, caso você tenha problemas utilize o segundo parâmetro como false como exemplo abaixo:

```
//Retorna ISO, padrão.
$tools->consultarNfseChave('CHAVE_NFSE');

//Retorna XML cru, sem passar por mb_convert_enconding
$tools->consultarNfseChave('CHAVE_NFSE', false);
```

## Install

**Este pacote é desenvolvido para uso do [Composer](https://getcomposer.org/), então não terá nenhuma explicação de instalação alternativa.**

Este fork **não está publicado no Packagist**, portanto a instalação é feita diretamente a partir do repositório no GitHub. Adicione o repositório VCS ao `composer.json` do seu projeto:

```jsonc
// composer.json do seu projeto
"repositories": [
  { "type": "vcs", "url": "https://github.com/ezapp-sistemas/nfse-nacional" }
]
```

E então instale o pacote:

```bash
composer require ezapp-sistemas/nfse-nacional:dev-master
```

> O pacote usa `minimum-stability: dev` com `prefer-stable: true`, por isso o sufixo `dev-master` (aponte para a branch ou tag desejada). As demais dependências (incluindo as libs de PDF do DANFSe) são resolvidas automaticamente pelo Composer.

### Serviços implementados

- consultarNfseChave
- consultarDpsChave
- consultarNfseEventos
- consultarDanfse
- enviaDps
- cancelaNfse

## Impressão do DANFSe

Este fork inclui a **geração local do DANFSe** (Documento Auxiliar da NFS-e), conforme a Nota Técnica nº 008 SE/CGNFS-e. A implementação é **standalone** — não depende de `nfephp-org/sped-da` — e usa internamente TCPDF/FPDF + `chillerlan/php-qrcode`.

> Diferente do método `consultarDanfse`, que recupera o PDF oficial retornado pela Receita, esta seção cobre a **geração do PDF a partir do XML da NFS-e** dentro da sua própria aplicação.

```php
use Hadder\NfseNacional\Danfse\Danfse;

$danfse = new Danfse($xmlNFSe);                   // XML completo da NFS-e
$pdf = $danfse
    ->printParameters('P', 'A4', 1.5, 1.5)        // orientação, papel, margens (sup, esq)
    ->logoMunicipioParameters($pathLogo)          // logo da prefeitura (opcional)
    ->exibirCanhoto(true)                         // exibe o canhoto de recebimento
    ->creditsIntegratorFooter('Sua Empresa')      // mensagem do integrador no rodapé
    ->render();                                   // retorna o binário do PDF

file_put_contents('danfse.pdf', $pdf);
```

- **Status da nota**: notas **canceladas** ou **substituídas** recebem automaticamente a **marca d'água** correspondente.
- **Resolução do logo do município** — estratégia híbrida (`Hadder\NfseNacional\Danfse\LogoResolver`):
  1. caminho explícito passado em `logoMunicipioParameters($path)` (filesystem ou data-URI base64);
  2. convenção `storage/logos/{cMun_IBGE}.{png|jpg|jpeg}`, usando o município emitente do XML;
  3. sem logo do município — o cabeçalho mantém apenas o logotipo fixo da NFS-e nacional.

  Veja `storage/logos/README.md` para a convenção de nomeação e recomendações dos arquivos.
- **Exemplo executável**: `exemples/ImprimeDanfse.php` gera PDFs de demonstração (autorizada, cancelada e completa) em `exemples/output/`:

  ```bash
  php exemples/ImprimeDanfse.php [caminho-para-xml.xml] [saida.pdf]
  ```

## Requerimentos

- PHP 8.1+
- ext-dom
- ext-curl
- ext-zlib
- ext-openssl
- ext-mbstring

Dependências de PDF do DANFSe (instaladas automaticamente pelo Composer):

- `tecnickcom/tcpdf`
- `setasign/fpdf`
- `chillerlan/php-qrcode`

## FAQ - E999 - Erro não catalogado

Podem existir diversos motivos para esse erro ocorrer, já que ele se refere a uma falha não catalogada pela própria Receita, incluindo erros de servidor (500) e outros problemas aleatórios.

Vale mencionar que, no ambiente de **homologação**, esses erros costumam aparecer sem motivo algum, enquanto no ambiente de **produção** a nota normalmente é emitida sem problemas.

Como a Receita só atualiza suas APIs quando está inspirada, listamos abaixo as causas mais comuns com base nos relatos que já recebemos:

- CPF/CNPJ do **prestador** não existente/cadastrado/habilitado na NFSe Nacional/Prefeitura;

# Sobre este fork (EZAPP/Sygma)

Este repositório é a **continuação** do projeto `hadder/nfse-nacional`, preservando integralmente o trabalho e os créditos de quem veio antes. A linhagem do projeto é:

- **[Roberto L. Machado](https://github.com/robmachado)** — base e referência de arquitetura ([sped-nfse](https://github.com/robmachado/sped-nfse) / [NFePHP](https://github.com/nfephp-org));
- **Fernando Friedrich** — autor do pacote `hadder/nfse-nacional`;
- **[Rainzart/nfse-nacional](https://github.com/Rainzart/nfse-nacional)** — continuidade e contribuições da comunidade;
- **EZAPP/Sygma — Paulo Henrique de Castro** ([phc@sygmasistemas.com.br](mailto:phc@sygmasistemas.com.br)) — mantenedor atual, responsável pela **impressão nativa do DANFSe** (NT-008 SE/CGNFS-e) e pelas correções subsequentes.

Os créditos originais permanecem registrados na seção abaixo, exatamente como escritos pelo autor.

# CRÉDITOS (por Fernando Friedrich)

Este pacote **não caiu do céu**, **não apareceu por geração espontânea** e muito menos foi escrito do zero em um surto de genialidade de minha parte.

Ele foi **copiado, clonado, analisado, desmontado, reaproveitado, adaptado e por fim ajustado por mim**, tendo como base pacotes de emissão de **NFSe** que eram disponibilizados como **Open Source** pelo Sr. **[Roberto L. Machado](https://github.com/robmachado)** e que, atualmente, não se encontram mais disponíveis publicamente.

Sim, **variáveis, métodos, classes, estruturas e ideias de arquitetura** foram utilizadas como referência (copiadas) — algumas foram alteradas, outras melhoradas, outras apenas sobreviveram ao tempo — sempre tendo como principal base o projeto **[NFePHP](https://github.com/robmachado/sped-nfse)**.

Na época da criação deste repositório, o cenário era simples:
eu precisava **emitir notas fiscais para meus clientes**.  
Não existia nenhuma alternativa Open Source ativa e funcional em PHP, e depender de **APIs pagas** definitivamente não era uma opção para mim (principalmente considerando a realidade financeira do momento).

Diante disso, fica aqui meu agradecimento **mais do que merecido** ao **Roberto**, por criar, manter e disponibilizar gratuitamente projetos como o **NFePHP**, além de sempre contribuir com a comunidade.

Sem esse trabalho prévio, este repositório **muito provavelmente não existiria** — ou, no mínimo, teria me dado muito mais dor de cabeça.

Por fim, meu agradecimento também a todas as pessoas que contribuem com este repositório seja enviando PRs, sugerindo melhorias, corrigindo bugs ou apontando problemas.  
A lista de contribuidores pode ser vista em: https://github.com/Rainzart/nfse-nacional/graphs/contributors