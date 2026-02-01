# NFSe Padrão Nacional

Pacote para geração de NFSe Padrão Nacional (https://www.nfse.gov.br/) usando componentes NFePHP (https://github.com/nfephp-org).

Este pacote foi desenvolvido para atender algumas das minhas necessidades, implementei o que utilizei e a toque de caixa. Se quiser colaborar envie seu PR.

**Em desenvolvimento. Use por sua conta e risco.**

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

## !!! AVISO !!!

### Configuração da Prefeitura

Na configuração do sistema, a variável `prefeitura` pode receber atualmente dois tipos de valores:

- Um identificador textual, por exemplo: `americana-sp`
- O código IBGE do município

⚠️ **Importante:** no momento, ambos os formatos são aceitos por compatibilidade.  
Porém, **futuramente o padrão adotado será exclusivamente o código IBGE**.  
Recomenda-se desde já utilizar o código IBGE para evitar ajustes em versões futuras.

## Install

**Este pacote é desenvolvido para uso do [Composer](https://getcomposer.org/), então não terá nenhuma explicação de instalação alternativa.**

```bash
composer require hadder/nfse-nacional
```

### Serviços implementados

- consultarNfseChave
- consultarDpsChave
- consultarNfseEventos
- consultarDanfse
- enviaDps
- cancelaNfse

## Requerimentos

- PHP 8.2+
- ext-dom
- ext-curl
- ext-zlib
- ext-openssl
- ext-mbstring

## FAQ - E999 - Erro não catalogado

Podem existir diversos motivos para esse erro ocorrer, já que ele se refere a uma falha não catalogada pela própria Receita, incluindo erros de servidor (500) e outros problemas aleatórios.

Vale mencionar que, no ambiente de **homologação**, esses erros costumam aparecer sem motivo algum, enquanto no ambiente de **produção** a nota normalmente é emitida sem problemas.

Como a Receita só atualiza suas APIs quando está inspirada, listamos abaixo as causas mais comuns com base nos relatos que já recebemos:

- CPF/CNPJ do **prestador** não existente/cadastrado/habilitado na NFSe Nacional/Prefeitura;
