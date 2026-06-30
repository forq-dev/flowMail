<?php

include('../../../inc/includes.php');

include_once __DIR__ . '/../inc/request.class.php';

$token = $_REQUEST['token'] ?? '';
$decision = $_REQUEST['decision'] ?? '';

if (!in_array($decision, ['approve', 'reject'], true) || strlen((string) $token) < 40) {
   plugin_approvalbyemail_public_page(
      'Link inválido',
      'Link de aprovação inválido.'
   );
   exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $decision === 'approve') {
   $result = PluginApprovalbyemailRequest::decideByToken($token, 'approve', '');

   if ($result['ok']) {
      plugin_approvalbyemail_approval_success_page($result['ticket_url'] ?? '');
   } else {
      plugin_approvalbyemail_public_page(
         'Resposta não salva',
         $result['message']
      );
   }
   exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $comment = trim((string) ($_POST['decision_comment'] ?? ''));
   $result = PluginApprovalbyemailRequest::decideByToken($token, $decision, $comment);

   if (!empty($result['needs_comment'])) {
      plugin_approvalbyemail_decision_form($token, $decision, $result['message']);
      exit;
   }

   plugin_approvalbyemail_public_page(
      $result['ok'] ? 'Resposta salva' : 'Resposta não salva',
      $result['message']
   );
   exit;
}

plugin_approvalbyemail_decision_form($token, $decision);

function plugin_approvalbyemail_decision_form($token, $decision, $error = '')
{
   $is_refusal = $decision === 'reject';
   $label = $is_refusal
      ? 'Recusar solicitação de aprovação'
      : 'Aprovar solicitação de aprovação';
   $color = $is_refusal ? '#dc3545' : '#198754';

   $body = '';
   if ($error !== '') {
      $body .= '<p class="error">' . PluginApprovalbyemailTool::h($error) . '</p>';
   }

   $body .= '<p>' . PluginApprovalbyemailTool::h('Confirme sua decisão abaixo.') . '</p>';
   $body .= '<form method="post">';
   $body .= '<input type="hidden" name="token" value="' . PluginApprovalbyemailTool::h($token) . '">';
   $body .= '<input type="hidden" name="decision" value="' . PluginApprovalbyemailTool::h($decision) . '">';
   if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
      $body .= '<input type="hidden" name="_glpi_csrf_token" value="' . PluginApprovalbyemailTool::h(Session::getNewCSRFToken()) . '">';
   }

   if ($is_refusal) {
      $body .= '<label for="decision_comment">' . PluginApprovalbyemailTool::h('Justificativa da recusa') . '</label>';
      $body .= '<textarea id="decision_comment" name="decision_comment" rows="5" maxlength="' . PluginApprovalbyemailTool::h(PluginApprovalbyemailRequest::REFUSAL_COMMENT_MAX_LENGTH) . '" required></textarea>';
      $body .= '<p class="hint">' . PluginApprovalbyemailTool::h('Máximo de ' . PluginApprovalbyemailRequest::REFUSAL_COMMENT_MAX_LENGTH . ' caracteres.') . '</p>';
   }

   $body .= '<button type="submit" style="background:' . $color . ';">'
      . PluginApprovalbyemailTool::h($label) .
   '</button>';
   $body .= '</form>';

   plugin_approvalbyemail_public_page($label, $body, true);
}

function plugin_approvalbyemail_public_page($title, $content, $is_html = false)
{
   $safe_title = PluginApprovalbyemailTool::h($title);
   $safe_content = $is_html ? $content : '<p>' . PluginApprovalbyemailTool::h($content) . '</p>';

   echo '<!doctype html>
      <html lang="pt-BR">
      <head>
         <meta charset="utf-8">
         <meta name="viewport" content="width=device-width, initial-scale=1">
         <title>' . $safe_title . '</title>
         <style>
            body {
               margin: 0;
               font-family: Arial, Helvetica, sans-serif;
               background: #f5f7fb;
               color: #222;
            }
            main {
               max-width: 640px;
               margin: 8vh auto;
               background: #fff;
               border: 1px solid #d9dee8;
               border-radius: 6px;
               padding: 28px;
               box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            }
            h1 {
               margin-top: 0;
               font-size: 24px;
            }
            textarea {
               width: 100%;
               box-sizing: border-box;
               margin-top: 8px;
               margin-bottom: 8px;
            }
            .hint {
               color: #606975;
               font-size: 12px;
               margin: 0 0 16px;
            }
            button {
               border: 0;
               border-radius: 4px;
               color: #fff;
               padding: 10px 16px;
               cursor: pointer;
               font-weight: 700;
            }
            .error {
               color: #842029;
               background: #f8d7da;
               border: 1px solid #f5c2c7;
               border-radius: 4px;
               padding: 10px;
            }
         </style>
      </head>
      <body>
         <main>
            <h1>' . $safe_title . '</h1>
            ' . $safe_content . '
         </main>
      </body>
      </html>';
}

function plugin_approvalbyemail_approval_success_page($ticket_url = '')
{
   $ticket_link = '';
   if ($ticket_url !== '') {
      $ticket_link = '<p><a href="' . PluginApprovalbyemailTool::h($ticket_url) . '">Abrir chamado</a></p>';
   }

   echo '<!doctype html>
      <html lang="pt-BR">
      <head>
         <meta charset="utf-8">
         <meta name="viewport" content="width=device-width, initial-scale=1">
         <title>Chamado aprovado</title>
         <style>
            body {
               margin: 0;
               font-family: Arial, Helvetica, sans-serif;
               background: #f5f7fb;
               color: #111827;
            }
            main {
               max-width: 420px;
               margin: 10vh auto;
               background: #fff;
               border: 2px solid #111827;
               padding: 8px;
            }
            .box {
               border: 1px solid #d9dee8;
               padding: 28px 24px;
               text-align: center;
               box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
            }
            .check {
               width: 82px;
               height: 82px;
               margin: 0 auto 18px;
               position: relative;
            }
            .check::after {
               content: "";
               position: absolute;
               left: 20px;
               top: 10px;
               width: 28px;
               height: 52px;
               border: solid #20a65a;
               border-width: 0 9px 9px 0;
               transform: rotate(45deg);
               border-radius: 3px;
            }
            h1 {
               margin: 0;
               font-size: 18px;
               font-weight: 400;
            }
            a {
               color: #0d6efd;
            }
         </style>
      </head>
      <body>
         <main>
            <div class="box">
               <div class="check" aria-hidden="true"></div>
               <h1>Chamado aprovado</h1>
               ' . $ticket_link . '
            </div>
         </main>
      </body>
      </html>';
}
