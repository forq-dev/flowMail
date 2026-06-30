# Aprovacao por E-mail - Documentacao Tecnica

> **Audiencia:** Administrador tecnico
> **Relacionado:** [README principal](../../README.md)

## O que faz

O FlowMail envia botoes de aprovacao e recusa por e-mail para solicitacoes nativas de validacao de chamados do GLPI, mantendo o resultado salvo em `TicketValidation`.

## Fluxo completo

1. Um tecnico ou gestor cria uma solicitacao de aprovacao em um chamado usando a aba `Aprovacao por e-mail`.
2. A aba do plugin exibe um aviso e delega o conteudo para `TicketValidation::displayTabContentForItem()`, ou seja, a criacao/listagem permanece no mecanismo nativo do GLPI.
3. Quando uma `TicketValidation` e adicionada, o hook `item_add` chama `PluginApprovalbyemailRequest::onTicketValidationAdd()`.
4. O plugin valida se ha chamado, aprovador e e-mail principal valido para o usuario aprovador.
5. O plugin cria uma linha em `glpi_plugin_approvalbyemail_tokens` com dois hashes SHA-256: um para aprovar e outro para recusar.
6. O e-mail e colocado em `QueuedNotification` com HTML e texto simples, contendo a URI do chamado, comentario da solicitacao, status atual e dois botoes.
7. O botao de aprovacao abre `front/approve.php`, que aprova diretamente por token via `front/decision.php`.
8. O botao de recusa abre `front/reject.php`, que mostra um formulario publico e exige justificativa antes de gravar a recusa.

Evidencias principais no codigo: `setup.php:28`, `setup.php:32`, `inc/request.class.php:48`, `inc/request.class.php:57`, `inc/request.class.php:95`, `inc/request.class.php:501`, `front/decision.php:18`, `front/decision.php:32`.

## Permissoes - detalhamento

O plugin nao registra uma matriz propria de direitos de perfil. O acesso autenticado usa direitos nativos do GLPI, e a resposta publica por e-mail usa o token como autorizacao limitada para aquela validacao.

| Area | Direito ou mecanismo | O que libera | Evidencia |
|------|----------------------|--------------|-----------|
| Chamado | `ticket` com `READ` | Acessar o chamado e as telas autenticadas auxiliares do plugin. | `front/request.php:5`, `front/request.form.php:5` |
| Aba no chamado | `Ticket::can(..., READ)` | Exibir o conteudo da aba somente para quem pode ler o chamado. | `inc/request.class.php:38` |
| Validacoes | `TicketValidation::canView()` e `TicketValidation::can(..., CREATE, ...)` | Visualizar e criar solicitacoes de aprovacao conforme regras nativas do GLPI. | `inc/request.class.php:47`, `inc/request.class.php:152` |
| Configuracao | `config` com `READ` | Abrir a pagina `front/config.php` pela lista de plugins. | `front/config.php:5` |
| Resposta por e-mail | Token do link | Aprovar ou recusar somente a `TicketValidation` associada ao token. | `inc/request.class.php:175`, `inc/request.class.php:203` |

**Justificativa por perfil:**

- **Aprovador:** pode responder pelo link se recebeu o e-mail e o token ainda e valido; o link nao concede acesso geral ao chamado.
- **Tecnico:** pode criar solicitacoes se o perfil permitir criar validacoes nativas no chamado.
- **Gestor:** pode criar ou responder solicitacoes conforme direitos nativos do GLPI e conforme for o aprovador da validacao.
- **Administrador:** deve ter acesso a configuracao, fila de notificacoes e diagnostico operacional.

## Tabelas de referencia

### Acoes por link

| Acao | Endpoint inicial | Metodo efetivo | Resultado esperado |
|------|------------------|----------------|-------------------|
| Aprovar | `front/approve.php` | `GET` | Salva `TicketValidation::ACCEPTED` e mostra `Chamado aprovado`. |
| Recusar | `front/reject.php` | `GET` depois `POST` | Abre formulario publico; no POST salva `TicketValidation::REFUSED` com comentario. |
| Confirmar aprovacao por formulario | `front/decision.php` | `POST` | O codigo aceita POST para `approve`, mas o e-mail gerado usa aprovacao direta por GET. |

### Mensagens publicas

| Situacao | Mensagem |
|----------|----------|
| Token ausente, curto ou decisao fora da lista | `Link de aprovacao invalido.` |
| Token nao encontrado para a acao | `Link de aprovacao invalido.` |
| Token ja consumido | `Link expirado, pois ja foi utilizado.` |
| Token fora do prazo | `Este link de aprovacao expirou.` |
| Validacao inexistente | `Solicitacao de aprovacao nao encontrada.` |
| Validacao ja respondida | `Solicitacao de aprovacao ja respondida.` |
| Recusa sem justificativa | `Para recusar, informe uma justificativa.` |
| Recusa com justificativa longa | `A justificativa da recusa deve ter no maximo 500 caracteres.` |
| Falha ao gravar | `Deu erro e nao foi possivel aprovar/recusar o chamado.` |

## Comportamentos nao obvios

### O token e a autorizacao da resposta publica

Quem possui o link consegue responder aquela validacao especifica sem login no GLPI. O token bruto nao e salvo no banco; o banco guarda apenas hashes SHA-256 em `approve_token_hash` e `reject_token_hash`.

### Aprovacao e direta por GET

O link de aprovacao executa a decisao ao ser aberto. Isso e comportamento confirmado em `front/decision.php:18` e deve ser considerado ao lidar com scanners de e-mail, prefetch ou redirecionamentos automaticos.

### Recusa exige comentario

A recusa usa formulario publico com `decision_comment` obrigatorio e limite de 500 caracteres. Quando a classe `Session` oferece `getNewCSRFToken()`, o formulario inclui `_glpi_csrf_token`.

### Um consumo invalida as duas acoes

A tabela possui uma coluna unica `used_at`. Depois que a aprovacao ou recusa e salva, o token row e marcado como usado, impedindo reutilizacao do mesmo registro de decisao.

### O update usa uma sessao temporaria do aprovador

Antes de chamar `TicketValidation->update()`, o plugin cria uma sessao temporaria com o ID do aprovador e a entidade do chamado; depois restaura o snapshot anterior. Esse detalhe existe para deixar a gravacao compatibilizada com o caminho nativo do GLPI.

## Limitacoes conhecidas

- Nao ha direito proprio do plugin para separar permissao de criar validacoes por e-mail dos direitos nativos de validacao do GLPI.
- Nao ha CronTask de limpeza de tokens expirados no codigo inspecionado.
- Nao ha inspecao do core local do GLPI nesta execucao; a versao alvo `GLPI 11.0.7` foi informada pelo usuario.
- O codigo documentado declara compatibilidade com GLPI `>= 10.0.0` e `< 12.0.0`, mas a documentacao aqui foca no comportamento do checkout atual.
