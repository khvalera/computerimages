<?php

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

global $CFG_GLPI;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Html::redirect($CFG_GLPI['root_doc'] . '/front/computer.php');
}

$computer_id = isset($_POST['computers_id']) ? (int)$_POST['computers_id'] : 0;
$computer_url = $CFG_GLPI['root_doc']
    . '/front/computer.form.php?id=' . $computer_id
    . '&forcetab=PluginComputerimagesComputerimages$1';

if (!Session::haveRight('plugin_computerimages_profile', CREATE)) {
    Session::addMessageAfterRedirect('Немає прав для завантаження фото.', false, ERROR);
    Html::redirect($computer_url);
}

if ($computer_id <= 0 || !isset($_FILES['image_file'])) {
    Session::addMessageAfterRedirect('Не вибрано фото для завантаження.', false, ERROR);
    Html::redirect($computer_url);
}

// GLPI 11 validates the POST CSRF token globally before this endpoint.
$result = PluginComputerimagesComputerimages::uploadImage(
    $computer_id,
    $_FILES['image_file'],
    $_POST['image_comment'] ?? ''
);

Session::addMessageAfterRedirect(
    $result['message'],
    false,
    $result['success'] ? INFO : ERROR
);

Html::redirect($computer_url);
