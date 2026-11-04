<!--
  - SPDX-FileCopyrightText: 2026 EDALVIX
  - SPDX-License-Identifier: GPL-2.0-or-later
-->
# Edalvix Cloud — cliente desktop

Este fork gera o cliente **Edalvix Cloud** (Windows, macOS e Linux) com a
identidade visual da EDALVIX e conexão pré-configurada a
`https://<empresa>.edalvix.cloud`.

## O que foi alterado

| Item | Valor |
| --- | --- |
| Nome exibido | Edalvix Cloud |
| Executável / config | `edalvixcloud` |
| Fornecedor | Edalvix (`edalvix.com.br`) |
| ID da aplicação | `br.com.edalvix.cloud` |
| Cor padrão | `#0068ce`; cabeçalho do assistente branco com texto `#071d33` |
| Ícones | cilindro azul do Edalvix Cloud: `theme/colored/EdalvixCloud-*`, ícone Linux `br.com.edalvix.cloud.edalvixcloud-icon.svg` |
| Logo do assistente | `wizard_logo.*`: ícone + "Edalvix Cloud"; a marca EDALVIX aparece só como assinatura secundária ("por EDALVIX") |
| GUIDs do Windows | novos (não conflita com o Nextcloud instalado na mesma máquina) |
| Atualizador | ligado, consultando `https://updates.edalvix.cloud/client/`, um servidor próprio em `admin/edalvix/update-server/` |
| Idioma | Português (Brasil) por padrão, independentemente do idioma do sistema (`APPLICATION_DEFAULT_LANGUAGE=pt_BR`) |
| Lista de provedores Nextcloud | desligada (`WITH_PROVIDERS=OFF`) |

A configuração fica em `NEXTCLOUD.cmake`.

## Domínio pré-configurado

São três formas, escolha a que se adapta a cada cliente:

### 1. Build exclusivo por empresa (travado no domínio)

```bash
cmake -S . -B build -DEDALVIX_TENANT=algumaempresa
```

O assistente já abre com `https://algumaempresa.edalvix.cloud` e o campo não
pode ser alterado. Outro domínio base: `-DEDALVIX_CLOUD_DOMAIN=outro.dominio`.

### 2. Um único instalador para todos (MSI no Windows)

```bash
msiexec /i EdalvixCloud.msi OVERRIDESERVERURL=https://algumaempresa.edalvix.cloud
```

Bom para GPO/Intune. Grava `overrideServerUrl` em
`HKLM\Software\Edalvix\Edalvix Cloud`. No macOS, use a chave `overrideServerUrl`
em `/Library/Preferences/br.com.edalvix.cloud.plist` (MDM). No Linux, use
`/etc/Edalvix Cloud/Edalvix Cloud.conf`. Também funciona por linha de comando:
`edalvixcloud --overrideserverurl https://algumaempresa.edalvix.cloud`.

### 3. Cliente genérico: o usuário digita só o nome da empresa

No build sem `EDALVIX_TENANT`, digitar `algumaempresa` no campo do servidor é
convertido automaticamente em `https://algumaempresa.edalvix.cloud`. URLs
completas continuam aceitas.

## Antes de distribuir

- **macOS:** defina `-DDEVELOPMENT_TEAM=<seu Team ID Apple>` e assine/notarize.
- **Windows:** assine os binários e o MSI com o certificado da EDALVIX.
- **Atualizações:** suba o servidor de `admin/edalvix/update-server/` em
  `updates.edalvix.cloud` e publique cada versão nele (veja o README de lá).
  No macOS, compile com `-DSPARKLE_PUBLIC_ED_KEY=<chave pública>`.

## Idioma

A interface abre em português do Brasil em qualquer sistema. Para outro
idioma, use `edalvixcloud --set-language en` (ou outro código, como `es`).
Para seguir o idioma do sistema por padrão, compile com
`-DAPPLICATION_DEFAULT_LANGUAGE=""`.

As traduções vêm de `translations/client_pt_BR.ts`. Foram completadas as frases
recentes do macOS (File Provider e Sparkle) que ainda não tinham tradução. Ao
trazer atualizações do Nextcloud, esse arquivo pode ter conflito: mantenha as
traduções novas dos dois lados.
- O esquema de URL `nc://` foi mantido porque o servidor Nextcloud o usa para
  "abrir localmente" e para o login.
- Licença GPL-2.0+: o código-fonte do cliente distribuído deve ficar disponível.
