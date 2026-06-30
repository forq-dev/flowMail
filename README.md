# FlowMail for GLPI

Plugin para GLPI 10.x e 11.x que envia solicitações de aprovação de chamado por e-mail usando a validação nativa do GLPI.

O **FlowMail** permite que o aprovador responda uma aprovação diretamente pelo e-mail, sem substituir o fluxo oficial de validações do GLPI. O plugin cria os links seguros de aprovação e recusa, coloca o e-mail na fila nativa de notificações e mantém o resultado salvo em `TicketValidation`.

> 📚 [Documentação técnica completa](docs/README.md) para administradores técnicos e desenvolvedores.

---

## 🚀 Funcionalidades Principais

- **Aprovação por e-mail em chamados**: adiciona a aba `Aprovação por e-mail` no chamado e reutiliza as aprovações nativas do GLPI.
- **E-mail com ações diretas**: envia botões para `Aprovar solicitação de aprovação` e `Recusar solicitação de aprovação`.
- **Aprovação direta por token**: o botão de aprovação salva a resposta e exibe a página pública `Chamado aprovado`.
- **Recusa com justificativa obrigatória**: o botão de recusa abre formulário público com comentário obrigatório de até 500 caracteres.
- **Integração nativa com notificações**: o envio usa `QueuedNotification`, respeitando a fila oficial de e-mails do GLPI.
- **Compatibilidade GLPI 10/11**: usa `users_id_validate` no GLPI 10 e `itemtype_target` / `items_id_target` no GLPI 11.
- **Links de uso único**: os tokens são armazenados como hash, expiram e passam a mostrar mensagem específica quando já utilizados.

---

## 📋 Requisitos Mínimos

| Requisito | Versão / condição |
|-----------|-------------------|
| GLPI | `>= 10.0.0` e `< 12.0.0` |
| PHP | `>= 7.4` para compatibilidade com GLPI 10.x |
| GLPI 11 | O core do GLPI 11 exige PHP 8.2 |
| E-mail do GLPI | Notificações configuradas e ação automática `queuednotification` ativa |
| Aprovador | Usuário com e-mail principal válido no GLPI |
| Pasta do plugin | `glpi/plugins/flowMail` |

> ⚠️ O nome da pasta precisa ser exatamente `flowMail`. O GLPI não carrega corretamente o plugin se houver pasta intermediária ou sufixos como `flowMail-glpi-plugin`.

> ℹ️ A partir da versão 1.3.4, a pasta técnica do plugin passou de `approvalbyemail` para `flowMail`. As tabelas internas mantêm o prefixo antigo para preservar dados existentes.

---

## ⚙️ Instalação Rápida

### 1. Copiar o plugin

Copie a pasta `flowMail` para o diretório `plugins` da instalação GLPI:

```txt
glpi/plugins/flowMail
```

### 2. Conferir a estrutura

A estrutura final deve ficar assim:

```txt
glpi/
└── plugins/
    └── flowMail/
        ├── setup.php
        ├── hook.php
        ├── inc/
        ├── front/
        └── locales/
```

Não use:

```txt
glpi/plugins/flowMail/flowMail
glpi/plugins/flowMail-glpi-plugin
```

### 3. Ativar no GLPI

1. Acesse `Configurar > Plugins`.
2. Clique em `Instalar` no plugin `FlowMail`.
3. Clique em `Ativar`.
4. Abra a página de configuração do plugin para confirmar versão, status e compatibilidade.

---

## ⚠️ Configuração Obrigatória Pós-Instalação

Depois de ativar o plugin, confira estes pontos antes de usar em produção:

1. Configure as notificações de e-mail do GLPI.
2. Ative ou valide a ação automática `queuednotification`.
3. Garanta que `url_base` do GLPI esteja correto, pois os links do e-mail usam a URL pública da instalação.
4. Confirme que os usuários aprovadores possuem e-mail principal válido.
5. Após criar uma aprovação de teste, confira a fila em:

```txt
Administração > Fila de notificações
```

Se o e-mail não aparecer na fila, a solicitação de aprovação pode ter sido criada, mas a notificação não foi enfileirada.

---

## ✉️ Aprovação por E-mail

> 📖 [Documentação completa da feature](docs/features/approval-by-email.md)

Esta é a feature principal do FlowMail. Ela conecta a validação nativa de chamados do GLPI a botões de aprovação e recusa enviados por e-mail.

### Como funciona

1. Um técnico ou gestor abre um chamado.
2. Na aba `Aprovação por e-mail`, cria uma solicitação de aprovação usando o mecanismo nativo de validações do GLPI.
3. O plugin identifica a nova `TicketValidation`, cria tokens para aprovar e recusar, e enfileira um e-mail para o aprovador.
4. O e-mail contém o comentário da solicitação, a URI do chamado, o status atual e dois botões:
   - `Aprovar solicitação de aprovação`;
   - `Recusar solicitação de aprovação`.
5. A aprovação salva `TicketValidation::ACCEPTED`.
6. A recusa salva `TicketValidation::REFUSED` com a justificativa informada.

O template HTML do e-mail mantém apenas os botões de aprovar e recusar, sem links de fallback abaixo.

### Comportamento dos links

| Ação | Comportamento |
|------|---------------|
| Aprovar | Aprova diretamente ao abrir o link e mostra `Chamado aprovado`. |
| Recusar | Abre um formulário público com justificativa obrigatória. |
| Token inválido | Mostra `Link de aprovação inválido.` |
| Token já utilizado | Mostra `Link expirado, pois já foi utilizado.` |
| Token expirado | Mostra `Este link de aprovação expirou.` |
| Solicitação já respondida | Mostra `Solicitação de aprovação já respondida.` |

Os tokens expiram em 168 horas, ou seja, 7 dias.

### Segurança operacional

O link do e-mail é um token de ação. Quem tiver acesso ao link consegue responder aquela solicitação específica sem login no GLPI.

Por isso:

- envie aprovações apenas para e-mails válidos e controlados;
- trate o e-mail de aprovação como informação sensível;
- evite encaminhar mensagens de aprovação para caixas compartilhadas;
- use HTTPS na URL pública do GLPI;
- monitore respostas inesperadas pela própria validação do chamado.

→ [Ver documentação técnica completa da aprovação por e-mail](docs/features/approval-by-email.md)

---

## 🔑 Permissões e Acesso

O FlowMail não cria uma matriz própria de direitos no perfil. Ele usa os direitos nativos do GLPI para chamados, validações e configuração.

### Direitos nativos usados

| Área | Direito / validação | O que libera |
|------|---------------------|--------------|
| Chamado | `ticket` com `READ` | Acessar o chamado e a aba do plugin. |
| Validações | Permissão nativa de `TicketValidation` | Visualizar e criar solicitações de aprovação. |
| Configuração | `config` com `READ` | Abrir `front/config.php` na lista de plugins. |
| Resposta por e-mail | Token do link | Aprovar ou recusar apenas a validação associada ao token. |

### Matriz recomendada por perfil

| Ação | Aprovador | Técnico | Gestor | Administrador |
|------|-----------|---------|--------|---------------|
| Responder por link de e-mail | Sim | Se for o aprovador | Se for o aprovador | Se for o aprovador |
| Visualizar aba no chamado | Conforme acesso ao chamado | Sim | Sim | Sim |
| Criar solicitação de aprovação | Não recomendado | Sim | Sim | Sim |
| Ver fila de notificações | Não | Opcional | Opcional | Sim |
| Ver configuração do plugin | Não | Não | Opcional | Sim |

> ⚠️ O link por token não concede acesso geral ao chamado. Ele apenas registra a decisão da solicitação de aprovação vinculada ao token.

---

## 🧭 Diagnóstico e Operação

### E-mail não enviado

Verifique:

1. Se o aprovador tem e-mail principal válido.
2. Se as notificações de e-mail do GLPI estão configuradas.
3. Se a ação automática `queuednotification` está ativa.
4. Se a mensagem aparece em `Administração > Fila de notificações`.
5. Se há erros em `files/_log/php-errors.log`.

### Instalação fica carregando

Confira primeiro:

1. A pasta deve estar como `glpi/plugins/flowMail`.
2. Não use `glpi/plugins/flowMail-glpi-plugin`.
3. Não deixe uma pasta intermediária dentro do plugin.
4. Confira o log do GLPI em `files/_log/php-errors.log`.
5. Confira o log do servidor web, como Apache ou Nginx/PHP-FPM.

### GLPI fica em branco após ativar

1. Renomeie temporariamente:

```txt
glpi/plugins/flowMail
```

para:

```txt
glpi/plugins/flowMail_off
```

2. Recarregue o GLPI.
3. Confira `files/_log/php-errors.log`.
4. Substitua os arquivos pela versão corrigida.
5. Volte o nome da pasta para `flowMail`.

### Link inválido ou expirado

As mensagens públicas indicam o motivo mais provável:

| Mensagem | Significado |
|----------|-------------|
| `Link de aprovação inválido.` | Token ausente, alterado ou incompatível com a ação. |
| `Link expirado, pois já foi utilizado.` | A aprovação ou recusa já foi registrada. |
| `Este link de aprovação expirou.` | O prazo do token terminou. |
| `Solicitação de aprovação já respondida.` | A validação já não está mais em espera no GLPI. |

---

## 🛠️ Documentação Técnica Detalhada

Para desenvolvedores e administradores técnicos, estes são os principais pontos internos do plugin.

O índice completo fica em [docs/README.md](docs/README.md). Os detalhes internos estão separados por assunto:

| Área | Documento |
|------|-----------|
| Feature | [Aprovação por e-mail](docs/features/approval-by-email.md) |
| Banco de dados | [Tokens de aprovação](docs/architecture/database/tokens.md) |
| Fluxo | [Fluxo de aprovação por e-mail](docs/architecture/flows/approval-by-email.md) |
| Endpoints públicos | [Endpoints públicos e decisão por token](docs/architecture/flows/public-token-decision.md) |
| Integração GLPI | [Validação nativa e hooks GLPI](docs/architecture/glpi-integration/native-validation.md) |

### Arquivos principais

| Arquivo | Responsabilidade |
|---------|------------------|
| `setup.php` | Metadados, requisitos, hooks, aba do chamado e paths stateless. |
| `hook.php` | Instalação e remoção da tabela de tokens. |
| `inc/request.class.php` | Criação de aprovações, geração de tokens, envio da notificação e decisão por token. |
| `inc/tool.class.php` | Helpers de URL, token, hash e escape HTML. |
| `front/approve.php` | Entrada pública para aprovação por token. |
| `front/reject.php` | Entrada pública para recusa por token. |
| `front/decision.php` | Controller público que valida token, decisão e formulário de recusa. |
| `front/config.php` | Página de configuração e status do plugin. |

### Integração com GLPI

| Mecanismo | Uso no FlowMail |
|-----------|-----------------|
| `Plugin::registerClass()` | Registra a aba `Aprovação por e-mail` em `Ticket`. |
| `TicketValidation` | Base nativa para criar, listar, aprovar e recusar validações. |
| `TicketValidation::displayTabContentForItem()` | Renderiza o conteúdo nativo da aba de validações. |
| `QueuedNotification` | Enfileira o e-mail de aprovação na fila oficial do GLPI. |
| `SessionManager::registerPluginStatelessPath()` | Registra `decision.php`, `approve.php` e `reject.php` como endpoints públicos no GLPI 11. |

### Banco de dados

O plugin usa a tabela própria `glpi_plugin_approvalbyemail_tokens` apenas para controlar os links de aprovação e recusa.

| Campo | Uso |
|-------|-----|
| `ticketvalidations_id` | Validação nativa do GLPI associada ao token. |
| `tickets_id` | Chamado relacionado. |
| `entities_id` | Entidade do chamado. |
| `users_id_validate` | Usuário aprovador. |
| `approve_token_hash` | Hash SHA-256 do token de aprovação. |
| `reject_token_hash` | Hash SHA-256 do token de recusa. |
| `date_expiration` | Data de expiração do link. |
| `used_at` | Data em que o token foi consumido. |

Tokens brutos não são armazenados no banco. Apenas hashes SHA-256 são persistidos.

### Compatibilidade GLPI 10 e GLPI 11

No GLPI 10, o aprovador da validação é lido pelo campo legado:

```txt
users_id_validate
```

No GLPI 11, o plugin usa:

```txt
itemtype_target = User
items_id_target = [ID do aprovador]
```

A versão atual declara compatibilidade com GLPI `>= 10.0.0` e `< 12.0.0`.

---
