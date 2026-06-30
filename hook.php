<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

function plugin_flowMail_install()
{
   global $DB;

   include_once __DIR__ . '/inc/request.class.php';

   $table = PluginApprovalbyemailRequest::tokenTable();
   if (!$DB->tableExists($table)) {
      $migration         = new Migration(PLUGIN_APPROVALBYEMAIL_VERSION);
      PluginApprovalbyemailRequest::ensureSchema();
      $migration->executeMigration();
   }

   return true;
}

function plugin_flowMail_uninstall()
{
   global $DB;

   foreach ([
      'glpi_plugin_approvalbyemail_tokens',
      'glpi_plugin_approvalbyemail_requests'
   ] as $table) {
      if ($DB->tableExists($table)) {
         $DB->doQuery("DROP TABLE `$table`");
      }
   }

   return true;
}
