<?php

include('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::header(
   'FlowMail',
   $_SERVER['PHP_SELF'],
   'config',
   'plugins'
);

echo '<div class="center">';
echo '<table class="tab_cadre_fixe">';
echo '<tr><th colspan="2">FlowMail</th></tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Version') . '</td>';
echo '<td>' . PLUGIN_APPROVALBYEMAIL_VERSION . '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Status') . '</td>';
echo '<td>Plugin ativo. Usa as aprovações nativas de chamados do GLPI e envia botões de aprovação/recusa por e-mail.</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>Compatibilidade</td>';
echo '<td>GLPI >= 10.0.0 e < 12.0.0. No GLPI 11 usa itemtype_target/items_id_target para o aprovador.</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>Uso</td>';
echo '<td>Abra um chamado e use a aba Aprovação por e-mail. O e-mail é colocado na fila oficial de notificações do GLPI.</td>';
echo '</tr>';

echo '</table>';
echo '</div>';

Html::footer();
