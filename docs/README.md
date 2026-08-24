# Documentacao Tecnica - FlowMail

Guias para administradores tecnicos e desenvolvedores que precisam operar, diagnosticar ou modificar o plugin FlowMail (`flowMail`).
Para instalacao, requisitos e uso geral, consulte o [README principal](../README.md).

> Nota de escopo: esta documentacao foi criada a partir do codigo real deste checkout. A versao alvo informada pelo usuario e GLPI 11.0.7, mas o core local do GLPI nao foi inspecionado nesta execucao.

---

## Features

*Para administradores tecnicos que precisam de profundidade em configuracao, permissao ou diagnostico.*

| Doc | Quando consultar |
|-----|-----------------|
| [Aprovacao por e-mail](features/approval-by-email.md) | Ao configurar o fluxo de aprovacao por e-mail, diagnosticar links invalidos/expirados ou revisar os limites operacionais dos tokens. |

---

## Arquitetura

*Para desenvolvedores que vao debugar ou modificar o codigo.*

### Database

| Doc | Quando consultar |
|-----|-----------------|
| [Tokens de aprovacao](architecture/database/tokens.md) | Ao entender a tabela `glpi_plugin_approvalbyemail_tokens`, seus indices, estados e relacoes logicas com `TicketValidation`. |

### Fluxos

| Doc | Quando consultar |
|-----|-----------------|
| [Fluxo de aprovacao por e-mail](architecture/flows/approval-by-email.md) | Ao depurar o caminho completo entre criacao da validacao nativa, geracao de tokens, fila de notificacao e resposta do aprovador. |
| [Endpoints publicos e decisao por token](architecture/flows/public-token-decision.md) | Ao revisar `approve.php`, `reject.php`, `decision.php`, mensagens publicas, GET/POST e consumo de token. |

### Frontend

Nao ha documentacao separada de frontend nesta versao. O plugin inspecionado nao possui JavaScript/CSS proprio; as paginas publicas de decisao sao geradas em PHP por `front/decision.php`.

### Integracao com GLPI

| Doc | Quando consultar |
|-----|-----------------|
| [Validacao nativa e hooks GLPI](architecture/glpi-integration/native-validation.md) | Ao atualizar a versao do GLPI, revisar hooks, stateless paths, compatibilidade GLPI 10/11 ou o uso de `TicketValidation` e `QueuedNotification`. |

---

<br>

<p align="center">
  <sub>━━━━━━━━━━━━━━━━━━━━━━━</sub><br>
  <sub>Curtiu o plugin? Deixe uma ⭐ no repositório para ajudar outros administradores a encontrá-lo.</sub><br>
  <sub>Desenvolvido e mantido com apoio de <a href="https://www.forq.com.br/">Forq</a></sub>
</p>
