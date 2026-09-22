# Logos dos Municípios Emitentes (DANFSe)

Este diretório armazena os logotipos das prefeituras para impressão automática no
cabeçalho do DANFSe (NT-008 SE/CGNFS-e §2.4.3).

## Convenção de nomeação

Cada arquivo deve ser nomeado com o **código IBGE de 7 dígitos** do município
emitente, sem zeros adicionais à esquerda além do próprio código:

```
storage/logos/{cMun_IBGE}.{png|jpg|jpeg}
```

Exemplos:

| Município | Código IBGE | Arquivo |
|---|---|---|
| Monte Carmelo / MG | `3143104` | `3143104.png` |
| São Paulo / SP | `3550308` | `3550308.png` |
| Londrina / PR | `4113700` | `4113700.png` |

## Como o componente resolve o logo

Estratégia híbrida implementada em `Hadder\NfseNacional\Danfse\LogoResolver`:

1. **Prioridade 1** — caminho passado explicitamente para
   `Danfse::logoMunicipioParameters($path)` (path no filesystem ou
   data-URI base64);
2. **Prioridade 2** — convenção `storage/logos/{cMun_IBGE}.{png|jpg|jpeg}`
   (este diretório), usando o `cLocEmi` do XML da NFS-e;
3. **Prioridade 3** — `null` (sem logo do município; o cabeçalho mantém
   apenas o logotipo fixo da NFS-e nacional).

## Recomendações para os arquivos

- **Formato**: PNG com fundo transparente (preferencial) ou JPG;
- **Resolução**: mínimo 300 dpi para boa renderização em A4;
- **Proporção**: aproximadamente 4:1 (horizontal) ou 1:1 (quadrado);
- **Altura útil no DANFSe**: ~8 mm (cabeçalho ocupa 11,6 mm de altura total);
- **Cores**: preferir versão monocromática ou em escala de cinza, para
  manter contraste com o sombreamento cinza 5% do cabeçalho (§2.2.3).

## Logo fixo da NFS-e nacional

O logotipo `NFSe` (padrão nacional) que aparece no canto esquerdo do
cabeçalho **não** segue esta convenção — ele é fixo e único, distribuído
junto com este componente como `logo-nfs-e-horizontal.png`. Baixe-o do
portal oficial:

<https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/logos-da-nfs-e/Logo%20-%20NFS-e%20-%20Horizontal.png/view>

e salve neste mesmo diretório.
