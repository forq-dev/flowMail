# Integracao GLPI - Validacao Nativa e Hooks

> **Audiencia:** Desenvolvedor
> **Relacionado:** [indice tecnico](../../README.md)

## O que e

Este documento descreve os mecanismos do GLPI que o FlowMail usa para se integrar a chamados, validacoes, notificacoes, configuracao do plugin e endpoints publicos.

## Versao do GLPI

| Item | Valor |
|------|-------|
| Versao alvo informada pelo usuario | GLPI 11.0.7 |
| Core local inspecionado nesta execucao | Nao |
| Motivo | O usuario pediu para desconsiderar a ausencia de core GLPI local. |
| Compatibilidade declarada no plugin | GLPI `>= 10.0.0` e `< 12.0.0` |
| PHP minimo declarado no plugin | `>= 7.4` |

Esta documentacao registra o comportamento do plugin neste checkout. Onde houver diferenca entre GLPI 10 e GLPI 11, o codigo usa `version_compare(GLPI_VERSION, '11.0.0', 'ge')`; para a versao alvo GLPI 11.0.7, o caminho esperado e o de `itemtype_target`/`items_id_target`.

## Como o plugin usa este mecanismo

| Mecanismo | Onde no plugin | Uso |
|-----------|----------------|-----|
| `plugin_flowMail_boot()` | `setup.php:11` | Registra endpoints publicos como stateless quando o GLPI oferece `SessionManager::registerPluginStatelessPath()`. |
| `SessionManager::registerPluginStatelessPath()` | `setup.php:14` | Libera `front/decision.php`, `front/approve.php` e `front/reject.php` como fluxo publico por token. |
| `$PLUGIN_HOOKS['csrf_compliant']` | `setup.php:26` | Declara o plugin como compatível com CSRF do GLPI. |
| `$PLUGIN_HOOKS['config_page']` | `setup.php:27` | Aponta a pagina de configuracao para `front/config.php`. |
| `$PLUGIN_HOOKS['item_add']` | `setup.php:28` | Aciona o FlowMail quando uma `TicketValidation` e criada. |
| `Plugin::registerClass()` | `setup.php:32` | Adiciona a classe `PluginApprovalbyemailRequest` como aba em `Ticket`. |
| `TicketValidation::displayTabContentForItem()` | `inc/request.class.php:48` | Reusa a interface nativa de validacoes dentro da aba do plugin. |
| `TicketValidation` | `inc/request.class.php:136` | Cria e atualiza validacoes nativas, sem tabela paralela de aprovacao. |
| `QueuedNotification` | `inc/request.class.php:501` | Enfileira o e-mail na fila oficial de notificacoes do GLPI. |
| `Session::checkRight()` | `front/config.php:5`, `front/request.php:5`, `front/request.form.php:5` | Protege paginas autenticadas auxiliares por direitos nativos. |

## Evidencia no GLPI core

O core local do GLPI nao foi inspecionado nesta execucao. A tabela abaixo registra essa limitacao de forma explicita, conforme o escopo informado pelo usuario.

| API / Hook / Classe | Arquivo no core | Linha | Para que e usado no plugin |
|---------------------|-----------------|-------|----------------------------|
| `TicketValidation` | Nao inspecionado | Nao inspecionado | Criar, listar e atualizar validacoes de chamados. |
| `QueuedNotification` | Nao inspecionado | Nao inspecionado | Enfileirar e-mail de aprovacao/recusa. |
| `Plugin::registerClass()` | Nao inspecionado | Nao inspecionado | Registrar aba do plugin em `Ticket`. |
| `SessionManager::registerPluginStatelessPath()` | Nao inspecionado | Nao inspecionado | Permitir endpoints publicos sem sessao GLPI normal. |
| `$PLUGIN_HOOKS['item_add']` | Nao inspecionado | Nao inspecionado | Reagir a criacao de `TicketValidation`. |

## Compatibilidade GLPI 10 e GLPI 11

O codigo trata o aprovador da validacao de duas formas:

| Versao | Campos usados | Onde aparece |
|--------|---------------|--------------|
| GLPI 10.x | `users_id_validate` | `inc/request.class.php:149`, `inc/request.class.php:327`, `inc/request.class.php:345` |
| GLPI 11.x | `itemtype_target = User::class` e `items_id_target` | `inc/request.class.php:145`, `inc/request.class.php:146`, `inc/request.class.php:147`, `inc/request.class.php:323`, `inc/request.class.php:324`, `inc/request.class.php:325` |

`getValidationTargetUserId()` normaliza a leitura do aprovador para os dois modelos.

## Paginas do plugin

| Arquivo | Tipo | Controle de acesso | Responsabilidade |
|---------|------|--------------------|------------------|
| `front/config.php` | Autenticada | `config` com `READ` | Exibe versao, status, compatibilidade e orientacao de uso. |
| `front/request.php` | Autenticada | `ticket` com `READ` | Redireciona para a busca de `TicketValidation`. |
| `front/request.form.php` | Autenticada | `ticket` com `READ` | Endpoint auxiliar que chama `createNativeValidation($_POST)`. |
| `front/approve.php` | Publica por token | Token | Wrapper de aprovacao. |
| `front/reject.php` | Publica por token | Token | Wrapper de recusa. |
| `front/decision.php` | Publica por token | Token | Valida entrada, mostra formulario e grava decisao. |

## Riscos e pontos de atencao

- A compatibilidade com GLPI 11.0.7 foi assumida a partir da informacao do usuario e do caminho condicional existente no plugin; nao houve verificacao de assinaturas no core local.
- `SessionManager::registerPluginStatelessPath()` so e chamado se a classe e o metodo existirem; em ambientes sem esse mecanismo, o comportamento publico depende do carregamento normal de `inc/includes.php`.
- O plugin usa `TicketValidation::displayTabContentForItem()` para evitar duplicar a UI nativa; alterar isso pode criar divergencia com as regras do core.
- `QueuedNotification` e preenchido diretamente com `notificationtemplates_id = 0`; mudancas futuras no core sobre fila de notificacao podem exigir revisao.
- O fluxo publico cria sessao temporaria do aprovador para gravar a decisao; qualquer mudanca em regras de sessao/direitos do GLPI pode afetar esse trecho.

---

<br>

<p align="center">
  <sub>━━━━━━━━━━━━━━━━━━━━━━━</sub><br>
  <sub>Curtiu o plugin? Deixe uma ⭐ no repositório para ajudar outros administradores a encontrá-lo.</sub><br>
  <sub>Desenvolvido e mantido com apoio de <a href="https://www.forq.com.br/">Forq</a></sub>
</p>
