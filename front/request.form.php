<?php

include('../../../inc/includes.php');

Session::checkRight('ticket', READ);

include_once __DIR__ . '/../inc/request.class.php';

if (!isset($_POST['add'])) {
   Html::back();
}

$result = PluginApprovalbyemailRequest::createNativeValidation($_POST);

Session::addMessageAfterRedirect(
   $result['message'],
   false,
   $result['ok'] ? INFO : ERROR
);

if (!empty($result['ticket_url'])) {
   Html::redirect($result['ticket_url']);
}

Html::back();
