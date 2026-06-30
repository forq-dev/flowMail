<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

define('PLUGIN_APPROVALBYEMAIL_VERSION', '1.3.4');
define('PLUGIN_APPROVALBYEMAIL_MIN_GLPI_VERSION', '10.0.0');
define('PLUGIN_APPROVALBYEMAIL_MAX_GLPI_VERSION', '12.0.0');

function plugin_flowMail_boot()
{
   if (class_exists('\Glpi\Http\SessionManager')
      && method_exists('\Glpi\Http\SessionManager', 'registerPluginStatelessPath')) {
      \Glpi\Http\SessionManager::registerPluginStatelessPath(
         'flowMail',
         '#^/front/(decision|approve|reject)\.php#'
      );
   }
}

function plugin_init_flowMail()
{
   global $PLUGIN_HOOKS;

   include_once __DIR__ . '/inc/request.class.php';

   $PLUGIN_HOOKS['csrf_compliant']['flowMail'] = true;
   $PLUGIN_HOOKS['config_page']['flowMail'] = 'front/config.php';
   $PLUGIN_HOOKS['item_add']['flowMail'] = [
      'TicketValidation' => ['PluginApprovalbyemailRequest', 'onTicketValidationAdd']
   ];

   Plugin::registerClass('PluginApprovalbyemailRequest', [
      'addtabon' => ['Ticket']
   ]);
}

function plugin_version_flowMail()
{
   return [
      'name'           => 'FlowMail',
      'version'        => PLUGIN_APPROVALBYEMAIL_VERSION,
      'author'         => 'Forq',
      'license'        => 'GPLv3+',
      'homepage'       => '',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_APPROVALBYEMAIL_MIN_GLPI_VERSION,
            'max' => PLUGIN_APPROVALBYEMAIL_MAX_GLPI_VERSION
         ],
         'php' => [
            'min' => '7.4'
         ]
      ]
   ];
}

function plugin_flowMail_check_prerequisites()
{
   if (version_compare(GLPI_VERSION, PLUGIN_APPROVALBYEMAIL_MIN_GLPI_VERSION, 'lt')
      || version_compare(GLPI_VERSION, PLUGIN_APPROVALBYEMAIL_MAX_GLPI_VERSION, 'ge')) {
      if (method_exists('Plugin', 'messageIncompatible')) {
         Plugin::messageIncompatible(
            'core',
            PLUGIN_APPROVALBYEMAIL_MIN_GLPI_VERSION,
            PLUGIN_APPROVALBYEMAIL_MAX_GLPI_VERSION
         );
      } else {
         echo 'This plugin requires GLPI >= '
            . PLUGIN_APPROVALBYEMAIL_MIN_GLPI_VERSION
            . ' and < '
            . PLUGIN_APPROVALBYEMAIL_MAX_GLPI_VERSION;
      }
      return false;
   }

   return true;
}

function plugin_flowMail_check_config($verbose = false)
{
   return true;
}
