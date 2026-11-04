<!--
  - SPDX-FileCopyrightText: 2026 EDALVIX
  - SPDX-License-Identifier: GPL-2.0-or-later
-->
# Servidor de atualizações do Edalvix Cloud

Responde às verificações de atualização do cliente desktop em
`https://updates.edalvix.cloud/client/` (o `APPLICATION_UPDATE_URL` do
`NEXTCLOUD.cmake`). O servidor é só PHP, sem banco de dados: as versões
publicadas ficam em `config/releases.json`.

## Como o cliente consulta

O cliente faz um `GET /client/?version=35.0.50.1&platform=win32&channel=stable&currentArch=x86_64&msi=true…`
e recebe:

| Situação | Resposta |
| --- | --- |
| Há versão mais nova (Windows/Linux) | `<owncloudclient>` com `version`, `versionstring`, `web` e `downloadurl` |
| Já está atualizado | `<owncloudclient/>` vazio |
| macOS com Sparkle (`sparkle=true`) | feed RSS do Sparkle, com `sparkle:edSignature` |

- **Windows:** baixa o MSI e instala sozinho (pergunta ao usuário antes de reiniciar).
- **macOS:** o Sparkle baixa o pacote, confere a assinatura EdDSA e instala.
- **Linux:** o usuário só recebe o aviso, com o link para baixar.

Canais: `stable` (padrão) e `beta`. Um canal desconhecido (ex.: `enterprise`)
recebe o `stable`. Para builds específicas de uma arquitetura, use uma chave
como `win32-arm64`; sem ela, vale a entrada genérica (`win32`).
`minimumVersion` impede que versões muito antigas recebam a atualização.

## Implantação

```bash
docker build -t edalvix-update-server admin/edalvix/update-server
docker run -d --name edalvix-updates -p 8080:80 \
  -v /srv/edalvix/releases.json:/srv/config/releases.json:ro \
  -v /srv/edalvix/downloads:/srv/downloads:ro \
  edalvix-update-server
```

Coloque um proxy HTTPS (Caddy, Nginx, Traefik) em `updates.edalvix.cloud`
apontando para a porta 8080:

- `/client/`: verificação de atualização
- `/download/`: instaladores (pasta `/srv/edalvix/downloads`)

Os instaladores também podem ficar em outro lugar (S3, GitHub Releases), desde
que a URL seja HTTPS.

## Publicar uma versão

1. Compile e **assine** os instaladores:
   - Windows: certificado de assinatura de código
   - macOS: notarização da Apple e `sign_update` do Sparkle
2. Copie os arquivos para a pasta de downloads.
3. Registre cada plataforma no `releases.json` com o script:

```bash
cd admin/edalvix/update-server
php tools/publish-release.php --manifest=/srv/edalvix/releases.json \
  --platform=win32 --version=35.0.50.1 \
  --url=https://updates.edalvix.cloud/download/EdalvixCloud-35.0.50-x64.msi \
  --file=/srv/edalvix/downloads/EdalvixCloud-35.0.50-x64.msi
```

```bash
php tools/publish-release.php --manifest=/srv/edalvix/releases.json \
  --platform=macos --version=35.0.50.1 \
  --url=https://updates.edalvix.cloud/download/EdalvixCloud-35.0.50.pkg \
  --sparkle-url=https://updates.edalvix.cloud/download/EdalvixCloud-35.0.50.pkg.tbz \
  --sparkle-file=/srv/edalvix/downloads/EdalvixCloud-35.0.50.pkg.tbz \
  --ed-signature="$(sign_update -p EdalvixCloud-35.0.50.pkg.tbz)"
```

O script valida os dados, exige HTTPS, calcula o SHA-256 e grava o arquivo de
forma atômica. Os clientes recebem a nova versão na próxima verificação, que é
automática (a cada poucas horas) ou manual (nas configurações do cliente).
Exemplo completo do manifesto: `config/releases.example.json`.

Para testar primeiro com um grupo, publique no canal `beta` (`--channel=beta`).
Os usuários escolhem o canal nas configurações do cliente.

## Chave do Sparkle (macOS): fazer uma vez

```bash
generate_keys            # ferramenta do Sparkle; guarda a chave privada no Keychain
generate_keys -p         # mostra a chave pública
```

Compile o cliente com `-DSPARKLE_PUBLIC_ED_KEY=<chave pública>`. A chave
privada nunca vai para o repositório. Se ela for perdida, os Macs já instalados
não aceitam mais atualizações automáticas.

## Desligar a atualização em computadores gerenciados

```bash
msiexec /i EdalvixCloud.msi SKIPAUTOUPDATE=1
```

## Testes

```bash
php admin/edalvix/update-server/tests/run.php
```
