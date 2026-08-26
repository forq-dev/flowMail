# Changelog

Todas as mudanças notáveis deste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.4] - 2026-08-24

> Histórico de mudanças anterior à versão 1.3.4 não foi rastreado formalmente neste
> repositório — não há tags nem releases publicados antes desta. Esta é a primeira
> versão documentada no CHANGELOG e cataloga o estado atual do plugin, não uma
> reconstrução de versões anteriores inexistentes.

### Added
- Aprovação de chamados por e-mail: nova aba "Aprovação por e-mail" no chamado,
  reaproveitando o mecanismo nativo de validações (`TicketValidation`) do GLPI.
- E-mail de aprovação com botões de ação direta ("Aprovar solicitação de aprovação" e
  "Recusar solicitação de aprovação"), enviado pela fila nativa de notificações do
  GLPI (`QueuedNotification`).
- Aprovação direta por link de token, com página pública de confirmação ("Chamado
  aprovado").
- Recusa por link de token, com formulário público de justificativa obrigatória (até
  500 caracteres).
- Tokens de aprovação e recusa de uso único: armazenados como hash SHA-256, com
  expiração automática em 168 horas (7 dias) e mensagens específicas para token
  inválido, já utilizado, expirado ou solicitação já respondida.
- Compatibilidade com GLPI 10.x e 11.x, incluindo o uso do campo correto de aprovador
  em cada versão (`users_id_validate` no GLPI 10, `itemtype_target`/`items_id_target`
  no GLPI 11).
- Licença GPLv3 adicionada ao repositório.

### Changed
- Pasta técnica do plugin renomeada de `approvalbyemail` para `flowMail` (as tabelas
  internas mantêm o prefixo `approvalbyemail` para preservar dados já existentes de
  instalações anteriores).

[unreleased]: https://github.com/forq-dev/flowMail/compare/v1.3.4...HEAD
[1.3.4]: https://github.com/forq-dev/flowMail/releases/tag/v1.3.4
