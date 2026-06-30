<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

include_once __DIR__ . '/tool.class.php';

class PluginApprovalbyemailRequest extends CommonGLPI
{
   public const REFUSAL_COMMENT_MAX_LENGTH = 500;

   public static function getTypeName($nb = 0)
   {
      return _n('Aprovação por e-mail', 'Aprovações por e-mail', $nb, 'approvalbyemail');
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
   {
      if ($item instanceof Ticket && !$withtemplate) {
         return self::getTypeName(1);
      }

      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
   {
      if ($item instanceof Ticket) {
         self::showTicketTab($item);
      }

      return true;
   }

   public static function showTicketTab(Ticket $ticket)
   {
      if (!$ticket->can($ticket->getID(), READ)) {
         return;
      }

      echo '<div class="spaced">';
      echo '<p class="center">';
      echo PluginApprovalbyemailTool::h(__('Esta aba usa as aprovações nativas de chamados do GLPI. Os botões por e-mail são gerados quando uma solicitação de aprovação é criada.', 'approvalbyemail'));
      echo '</p>';

      if (TicketValidation::canView()) {
         TicketValidation::displayTabContentForItem($ticket);
      } else {
         echo '<div class="center">';
         echo PluginApprovalbyemailTool::h(__('Seu perfil não tem permissão para visualizar aprovações de chamados.', 'approvalbyemail'));
         echo '</div>';
      }
      echo '</div>';
   }

   public static function onTicketValidationAdd($validation)
   {
      if (!($validation instanceof TicketValidation)) {
         return;
      }

      $validation_id = (int) ($validation->fields['id'] ?? 0);
      $tickets_id = (int) ($validation->fields['tickets_id'] ?? 0);
      $users_id_validate = self::getValidationTargetUserId($validation->fields);

      if ($validation_id <= 0 || $tickets_id <= 0 || $users_id_validate <= 0) {
         return;
      }

      if (self::tokenExists($validation_id)) {
         return;
      }

      $ticket = new Ticket();
      if (!$ticket->getFromDB($tickets_id)) {
         return;
      }

      $approver = new User();
      if (!$approver->getFromDB($users_id_validate)) {
         return;
      }

      $email = $approver->getDefaultEmail();
      if (empty($email) || !NotificationMailing::check($email)) {
         Session::addMessageAfterRedirect(
            sprintf(__('The selected user (%s) has no valid email address.'), $approver->getName()),
            false,
            ERROR
         );
         return;
      }

      $tokens = self::createTokens($validation_id, $ticket, $users_id_validate, 168);
      self::queueApprovalEmail($validation_id, $ticket, $approver, $tokens['approve'], $tokens['reject']);
   }

   public static function createNativeValidation(array $input)
   {
      $tickets_id = (int) ($input['tickets_id'] ?? 0);
      $users_id_validate = (int) ($input['users_id_validate'] ?? 0);
      $ttl_hours = max(1, (int) ($input['ttl_hours'] ?? 168));

      $ticket = new Ticket();
      if (!$ticket->getFromDB($tickets_id) || !$ticket->can($tickets_id, READ)) {
         return [
            'ok'      => false,
            'message' => __('Ticket not found or access denied.', 'approvalbyemail')
         ];
      }

      $approver = new User();
      if ($users_id_validate <= 0 || !$approver->getFromDB($users_id_validate)) {
         return [
            'ok'      => false,
            'message' => __('Invalid approver.', 'approvalbyemail')
         ];
      }

      $email = $approver->getDefaultEmail();
      if (empty($email) || !NotificationMailing::check($email)) {
         return [
            'ok'      => false,
            'message' => sprintf(__('The selected user (%s) has no valid email address.'), $approver->getName())
         ];
      }

      if (self::nativeValidationExists($tickets_id, $users_id_validate)) {
         return [
            'ok'      => false,
            'message' => __('An approval request already exists for this user and ticket.', 'approvalbyemail')
         ];
      }

      $validation = new TicketValidation();
      $validation_input = [
         'tickets_id'          => $tickets_id,
         'comment_submission'  => trim((string) ($input['comment_submission'] ?? '')),
         'entities_id'         => (int) ($ticket->fields['entities_id'] ?? 0),
         'is_recursive'        => (int) ($ticket->fields['is_recursive'] ?? 0),
         '_disablenotif'       => true
      ];

      if (version_compare(GLPI_VERSION, '11.0.0', 'ge')) {
         $validation_input['itemtype_target'] = User::class;
         $validation_input['items_id_target'] = $users_id_validate;
      } else {
         $validation_input['users_id_validate'] = $users_id_validate;
      }

      if (!$validation->can(-1, CREATE, $validation_input)) {
         return [
            'ok'      => false,
            'message' => __('You are not allowed to create an approval request for this ticket.', 'approvalbyemail')
         ];
      }

      $validation_id = $validation->add($validation_input);
      if (!$validation_id) {
         return [
            'ok'      => false,
            'message' => __('Unable to create the approval request.', 'approvalbyemail')
         ];
      }

      return [
         'ok'             => true,
         'message'        => sprintf(__('Approval request sent to %s'), $approver->getName()),
         'ticket_url'     => PluginApprovalbyemailTool::ticketUrl($tickets_id),
         'validation_id'  => $validation_id
      ];
   }

   public static function decideByToken($token, $decision, $decision_comment = '')
   {
      $token_row = self::findToken($token, $decision);
      if (!$token_row) {
         return [
            'ok'         => false,
            'message'    => 'Link de aprovação inválido.',
            'ticket_url' => PluginApprovalbyemailTool::glpiBase()
         ];
      }

      if (!empty($token_row['used_at'])) {
         return [
            'ok'         => false,
            'message'    => 'Link expirado, pois já foi utilizado.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $token_row['tickets_id'])
         ];
      }

      if (!empty($token_row['date_expiration']) && strtotime($token_row['date_expiration']) < time()) {
         return [
            'ok'         => false,
            'message'    => 'Este link de aprovação expirou.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $token_row['tickets_id'])
         ];
      }

      $validation = new TicketValidation();
      if (!$validation->getFromDB((int) $token_row['ticketvalidations_id'])) {
         return [
            'ok'         => false,
            'message'    => 'Solicitação de aprovação não encontrada.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $token_row['tickets_id'])
         ];
      }

      if ((int) $validation->fields['status'] !== TicketValidation::WAITING) {
         return [
            'ok'         => false,
            'message'    => 'Solicitação de aprovação já respondida.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
         ];
      }

      $trimmed_comment = trim($decision_comment);

      if ($decision === 'reject' && $trimmed_comment === '') {
         return [
            'ok'              => false,
            'needs_comment'   => true,
            'message'         => 'Para recusar, informe uma justificativa.',
            'ticket_url'      => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
         ];
      }

      if ($decision === 'reject' && self::textLength($trimmed_comment) > self::REFUSAL_COMMENT_MAX_LENGTH) {
         return [
            'ok'              => false,
            'needs_comment'   => true,
            'message'         => 'A justificativa da recusa deve ter no máximo ' . self::REFUSAL_COMMENT_MAX_LENGTH . ' caracteres.',
            'ticket_url'      => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
         ];
      }

      $approver = new User();
      $users_id_validate = self::getValidationTargetUserId($validation->fields);
      if ($users_id_validate <= 0 || !$approver->getFromDB($users_id_validate)) {
         return [
            'ok'         => false,
            'message'    => 'Aprovador da solicitação não encontrado.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
         ];
      }

      $status = $decision === 'approve'
         ? TicketValidation::ACCEPTED
         : TicketValidation::REFUSED;

      $session_snapshot = self::beginTokenAnswerSession(
         $approver,
         (int) $token_row['entities_id'],
         (int) ($validation->fields['is_recursive'] ?? 0)
      );

      try {
         $updated = $validation->update([
            'id'                 => (int) $validation->fields['id'],
            'status'             => $status,
            'comment_validation' => $trimmed_comment,
            'validation_date'    => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')
         ]);
      } finally {
         self::restoreSessionSnapshot($session_snapshot);
      }

      if (!$updated) {
         $action = $decision === 'approve' ? 'aprovar' : 'recusar';

         return [
            'ok'         => false,
            'message'    => 'Deu erro e não foi possível ' . $action . ' o chamado.',
            'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
         ];
      }

      self::markTokenUsed((int) $token_row['id']);

      $message = $decision === 'approve'
         ? 'Sua resposta foi salva.'
         : 'Solicitação recusada.';

      return [
         'ok'         => true,
         'message'    => $message,
         'ticket_url' => PluginApprovalbyemailTool::ticketUrl((int) $validation->fields['tickets_id'])
      ];
   }

   public static function tokenTable()
   {
      return 'glpi_plugin_approvalbyemail_tokens';
   }

   private static function tokenExists($validation_id)
   {
      global $DB;

      self::ensureSchema();

      $iterator = $DB->request([
         'FROM'  => self::tokenTable(),
         'WHERE' => [
            'ticketvalidations_id' => (int) $validation_id
         ],
         'LIMIT' => 1
      ]);

      return count($iterator) > 0;
   }

   private static function nativeValidationExists($tickets_id, $users_id_validate)
   {
      global $DB;

      $where = [
         'tickets_id' => (int) $tickets_id
      ];

      if (version_compare(GLPI_VERSION, '11.0.0', 'ge')) {
         $where['itemtype_target'] = User::class;
         $where['items_id_target'] = (int) $users_id_validate;
      } else {
         $where['users_id_validate'] = (int) $users_id_validate;
      }

      $iterator = $DB->request([
         'FROM'  => TicketValidation::getTable(),
         'WHERE' => $where,
         'LIMIT' => 1
      ]);

      return count($iterator) > 0;
   }

   private static function getValidationTargetUserId(array $fields)
   {
      if (($fields['itemtype_target'] ?? '') === User::class) {
         return (int) ($fields['items_id_target'] ?? 0);
      }

      return (int) ($fields['users_id_validate'] ?? 0);
   }

   private static function textLength($value)
   {
      if (function_exists('mb_strlen')) {
         return mb_strlen((string) $value, 'UTF-8');
      }

      return strlen((string) $value);
   }

   private static function beginTokenAnswerSession(User $approver, $entities_id, $is_recursive)
   {
      $snapshot = [
         'exists' => isset($_SESSION) && is_array($_SESSION),
         'data'   => isset($_SESSION) && is_array($_SESSION) ? $_SESSION : []
      ];

      if (!isset($_SESSION) || !is_array($_SESSION)) {
         $_SESSION = [];
      }

      $_SESSION['glpiID'] = (int) $approver->getID();
      $_SESSION['glpi_use_mode'] = Session::NORMAL_MODE;
      $_SESSION['glpi_currenttime'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

      if (method_exists('Session', 'loadEntity')) {
         Session::loadEntity((int) $entities_id, (bool) $is_recursive);
      }

      if (method_exists($approver, 'loadPreferencesInSession')) {
         $approver->loadPreferencesInSession();
      }

      if (method_exists('Session', 'loadGroups')) {
         Session::loadGroups();
      }

      if (method_exists('Session', 'loadLanguage')) {
         Session::loadLanguage();
      }

      return $snapshot;
   }

   private static function restoreSessionSnapshot(array $snapshot)
   {
      if ($snapshot['exists']) {
         $_SESSION = $snapshot['data'];
         return;
      }

      $_SESSION = [];
   }

   public static function ensureSchema()
   {
      global $DB;

      $table = self::tokenTable();
      if ($DB->tableExists($table)) {
         return;
      }

      $default_charset   = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();

      $query = "CREATE TABLE `$table` (
         `id` int unsigned NOT NULL AUTO_INCREMENT,
         `ticketvalidations_id` int unsigned NOT NULL DEFAULT '0',
         `tickets_id` int unsigned NOT NULL DEFAULT '0',
         `entities_id` int unsigned NOT NULL DEFAULT '0',
         `users_id_validate` int unsigned NOT NULL DEFAULT '0',
         `approve_token_hash` char(64) DEFAULT NULL,
         `reject_token_hash` char(64) DEFAULT NULL,
         `date_creation` timestamp NULL DEFAULT NULL,
         `date_expiration` timestamp NULL DEFAULT NULL,
         `used_at` timestamp NULL DEFAULT NULL,
         PRIMARY KEY (`id`),
         KEY `ticketvalidations_id` (`ticketvalidations_id`),
         KEY `tickets_id` (`tickets_id`),
         KEY `users_id_validate` (`users_id_validate`),
         UNIQUE KEY `approve_token_hash` (`approve_token_hash`),
         UNIQUE KEY `reject_token_hash` (`reject_token_hash`)
      ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC";

      $DB->doQuery($query);
   }

   private static function createTokens($validation_id, Ticket $ticket, $users_id_validate, $ttl_hours)
   {
      global $DB;

      self::ensureSchema();

      $approve_token = PluginApprovalbyemailTool::newToken();
      $reject_token  = PluginApprovalbyemailTool::newToken();

      $DB->insert(self::tokenTable(), [
         'ticketvalidations_id' => (int) $validation_id,
         'tickets_id'           => (int) $ticket->getID(),
         'entities_id'          => (int) ($ticket->fields['entities_id'] ?? 0),
         'users_id_validate'    => (int) $users_id_validate,
         'approve_token_hash'   => PluginApprovalbyemailTool::hashToken($approve_token),
         'reject_token_hash'    => PluginApprovalbyemailTool::hashToken($reject_token),
         'date_creation'        => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
         'date_expiration'      => date('Y-m-d H:i:s', time() + ((int) $ttl_hours * 3600))
      ]);

      return [
         'approve' => $approve_token,
         'reject'  => $reject_token
      ];
   }

   private static function findToken($token, $decision)
   {
      global $DB;

      self::ensureSchema();

      $field = $decision === 'approve' ? 'approve_token_hash' : 'reject_token_hash';
      $iterator = $DB->request([
         'FROM'  => self::tokenTable(),
         'WHERE' => [
            $field => PluginApprovalbyemailTool::hashToken($token)
         ],
         'LIMIT' => 1
      ]);

      return $iterator->current();
   }

   private static function markTokenUsed($token_id)
   {
      global $DB;

      $DB->update(self::tokenTable(), [
         'used_at' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')
      ], [
         'id' => (int) $token_id
      ]);
   }

   private static function queueApprovalEmail($validation_id, Ticket $ticket, User $approver, $approve_token, $reject_token)
   {
      global $CFG_GLPI;

      $ticket_url  = PluginApprovalbyemailTool::ticketUrl((int) $ticket->getID());
      $approve_url = PluginApprovalbyemailTool::decisionUrl($approve_token, 'approve', (int) $ticket->getID(), Ticket::class);
      $reject_url  = PluginApprovalbyemailTool::decisionUrl($reject_token, 'reject', (int) $ticket->getID(), Ticket::class);

      $subject = '[GLPI] ' . sprintf(__('Approval request for ticket #%s', 'approvalbyemail'), $ticket->getID());
      $email = self::buildEmailContent($validation_id, $ticket, $ticket_url, $approve_url, $reject_url);

      $queue = new QueuedNotification();
      $queued_id = $queue->add([
         'itemtype'                 => Ticket::class,
         'items_id'                 => (int) $ticket->getID(),
         'entities_id'              => (int) ($ticket->fields['entities_id'] ?? 0),
         'notificationtemplates_id' => 0,
         'mode'                     => Notification_NotificationTemplate::MODE_MAIL,
         'event'                    => 'validation',
         'name'                     => $subject,
         'body_text'                => $email['text'],
         'body_html'                => $email['html'],
         'sender'                   => $CFG_GLPI['admin_email'] ?? '',
         'sendername'               => $CFG_GLPI['admin_email_name'] ?? 'GLPI',
         'recipient'                => $approver->getDefaultEmail(),
         'recipientname'            => $approver->getName(),
         'replyto'                  => $CFG_GLPI['admin_reply'] ?? '',
         'replytoname'              => $CFG_GLPI['admin_reply_name'] ?? '',
         'headers'                  => [],
         'documents'                => [],
         'messageid'                => ''
      ]);

      if ($queued_id) {
         Session::addMessageAfterRedirect(sprintf(__('Approval request sent to %s'), $approver->getName()));
         return true;
      }

      Session::addMessageAfterRedirect(
         __('Approval request was created, but the email could not be queued.', 'approvalbyemail'),
         false,
         ERROR
      );
      return false;
   }

   private static function buildEmailContent($validation_id, Ticket $ticket, $ticket_url, $approve_url, $reject_url)
   {
      $validation = new TicketValidation();
      $validation->getFromDB((int) $validation_id);

      $requester = getUserName((int) ($validation->fields['users_id'] ?? 0));
      $comments = trim((string) ($validation->fields['comment_submission'] ?? ''));
      $status = TicketValidation::getStatus(TicketValidation::WAITING);
      $title = $ticket->getName();
      $header = '=-=-=-= ' . __('To answer by email, write above this line') . ' =-=-=-=';

      $text = $header . "\n\n";
      $text .= sprintf(__('An approval request has been submitted by %s'), $requester) . "\n";
      $text .= __('Request comments') . ":\n";
      $text .= ($comments !== '' ? $comments : '-') . "\n\n";
      $text .= 'URI : ' . $ticket_url . "\n\n";
      $text .= __('Status of the approval request') . ' : ' . $status . "\n";

      $html = '
         <p style="font-family:monospace;">' . PluginApprovalbyemailTool::h($header) . '</p>
         <p>' . PluginApprovalbyemailTool::h(sprintf(__('An approval request has been submitted by %s'), $requester)) . '</p>
         <p><strong>' . PluginApprovalbyemailTool::h(__('Request comments')) . ':</strong><br>'
            . nl2br(PluginApprovalbyemailTool::h($comments !== '' ? $comments : '-')) . '</p>
         <p><strong>URI :</strong> <a href="' . PluginApprovalbyemailTool::h($ticket_url) . '">'
            . PluginApprovalbyemailTool::h($ticket_url) . '</a></p>
         <p><strong>' . PluginApprovalbyemailTool::h(__('Status of the approval request')) . ' :</strong> '
            . PluginApprovalbyemailTool::h($status) . '</p>
         <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:18px;">
            <tr>
               <td style="padding-right:10px;">
                  <a href="' . PluginApprovalbyemailTool::h($approve_url) . '" style="display:inline-block;background:#198754;color:#ffffff;text-decoration:none;padding:11px 16px;border-radius:4px;font-weight:bold;">
                     ' . PluginApprovalbyemailTool::h('Aprovar solicitação de aprovação') . '
                  </a>
               </td>
               <td>
                  <a href="' . PluginApprovalbyemailTool::h($reject_url) . '" style="display:inline-block;background:#dc3545;color:#ffffff;text-decoration:none;padding:11px 16px;border-radius:4px;font-weight:bold;">
                     ' . PluginApprovalbyemailTool::h('Recusar solicitação de aprovação') . '
                  </a>
               </td>
            </tr>
         </table>
         <p style="text-align:right;font-size:11px;color:#777;margin-top:18px;">
            Developed by Forq
         </p>';

      return [
         'text' => $text,
         'html' => $html
      ];
   }

   private static function showNativeValidationSummary(Ticket $ticket)
   {
      global $DB;

      echo '<table class="tab_cadre_fixe">';
      echo '<tr><th colspan="5">' . PluginApprovalbyemailTool::h(__('Native GLPI approval requests', 'approvalbyemail')) . '</th></tr>';
      echo '<tr>';
      echo '<th>' . PluginApprovalbyemailTool::h(__('Approver')) . '</th>';
      echo '<th>' . PluginApprovalbyemailTool::h(__('Approval requester')) . '</th>';
      echo '<th>' . PluginApprovalbyemailTool::h(__('Status')) . '</th>';
      echo '<th>' . PluginApprovalbyemailTool::h(__('Request date')) . '</th>';
      echo '<th>' . PluginApprovalbyemailTool::h(__('Approval comments')) . '</th>';
      echo '</tr>';

      $iterator = $DB->request([
         'FROM'  => TicketValidation::getTable(),
         'WHERE' => [
            'tickets_id' => (int) $ticket->getID()
         ],
         'ORDER' => ['submission_date DESC']
      ]);

      if (count($iterator) === 0) {
         echo '<tr class="tab_bg_1"><td colspan="5" class="center">' . PluginApprovalbyemailTool::h(__('No item found')) . '</td></tr>';
      }

      foreach ($iterator as $row) {
         echo '<tr class="tab_bg_1">';
         echo '<td>' . PluginApprovalbyemailTool::h(getUserName(self::getValidationTargetUserId($row))) . '</td>';
         echo '<td>' . PluginApprovalbyemailTool::h(getUserName($row['users_id'])) . '</td>';
         echo '<td>' . TicketValidation::getStatus((int) $row['status'], true) . '</td>';
         echo '<td>' . Html::convDateTime($row['submission_date']) . '</td>';
         echo '<td>' . nl2br(PluginApprovalbyemailTool::h($row['comment_validation'] ?? '')) . '</td>';
         echo '</tr>';
      }

      echo '</table>';
   }
}
