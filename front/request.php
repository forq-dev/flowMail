<?php

include('../../../inc/includes.php');

Session::checkRight('ticket', READ);

Html::redirect(TicketValidation::getSearchURL());
