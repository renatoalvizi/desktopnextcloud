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
| Ícones | `theme/colored/EdalvixCloud-*`, `wizard_logo.*`, ícone Linux `br.com.edalvix.cloud.edalvixcloud-icon.svg` |
| GUIDs do Windows | novos (não conflita com o Nextcloud instalado na mesma máquina) |
| Atualizador | desligado por padrão (`BUILD_UPDATER=OFF`); URL `https://updates.edalvix.cloud/client/` |
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
- **Atualizações:** para atualização automática, publique um servidor de
  updates em `APPLICATION_UPDATE_URL` e compile com `-DBUILD_UPDATER=ON`.
- O esquema de URL `nc://` foi mantido porque o servidor Nextcloud o usa para
  "abrir localmente" e para o login.
- Licença GPL-2.0+: o código-fonte do cliente distribuído deve ficar disponível.
