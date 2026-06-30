<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginApprovalbyemailTool
{
   public static function hashToken($token)
   {
      return hash('sha256', $token);
   }

   public static function newToken()
   {
      return bin2hex(random_bytes(32));
   }

   public static function glpiBase()
   {
      global $CFG_GLPI;

      if (!empty($CFG_GLPI['url_base'])) {
         return rtrim($CFG_GLPI['url_base'], '/');
      }

      $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
      $scheme = $https ? 'https' : 'http';
      $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $root   = $CFG_GLPI['root_doc'] ?? '';

      return $scheme . '://' . $host . rtrim($root, '/');
   }

   public static function pluginWebBase()
   {
      return self::glpiBase() . '/plugins/flowMail';
   }

   public static function ticketUrl($tickets_id)
   {
      return self::glpiBase() . '/front/ticket.form.php?id=' . rawurlencode((string) $tickets_id);
   }

   public static function decisionUrl($token, $decision, $tickets_id = 0, $itemtype = 'Ticket')
   {
      $front = $decision === 'reject' ? 'reject.php' : 'approve.php';
      return self::pluginWebBase()
         . '/front/' . $front
         . '?items_id=' . rawurlencode((string) $tickets_id)
         . '&itemtype=' . rawurlencode((string) $itemtype)
         . '&token=' . rawurlencode($token);
   }

   public static function h($value)
   {
      return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
