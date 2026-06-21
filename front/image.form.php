<?php

// Ensure the main GLPI environment is loaded
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

$computerimage = new PluginComputerimagesComputerimages();

$computer_id = 0;
if (isset($_REQUEST['computers_id'])) {
    $computer_id = (int)$_REQUEST['computers_id'];
}
if (isset($_REQUEST['id'])) {
    $computer_id = (int)$_REQUEST['id'];
}

$image_id = isset($_REQUEST['image_id']) ? (int)$_REQUEST['image_id'] : 0;

$computer_url = $CFG_GLPI['root_doc']
    . '/front/computer.form.php?id=' . $computer_id
    . '&forcetab=PluginComputerimagesComputerimages$1';

// Delete an image.
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    if (!Session::haveRight('plugin_computerimages_profile', DELETE)) {
        Html::displayErrorAndDie('Немає прав для видалення фото.');
    }

    if (!Session::validateCSRF($_GET)) {
        Html::displayErrorAndDie('Некоректний CSRF-токен.');
    }

    $result = $computerimage->deleteImage($image_id);
    Session::addMessageAfterRedirect(
        $result['message'],
        false,
        $result['success'] ? INFO : ERROR
    );

    Html::redirect($computer_url);
}

// POST actions are handled by dedicated endpoints in GLPI 11.
// This avoids the generic *.form.php controller and duplicate CSRF validation.

if (Session::haveRight('plugin_computerimages_profile', CREATE)) {
    $csrf_token = Session::getNewCSRFToken();
    $comments_supported = PluginComputerimagesComputerimages::supportsImageComments();

    echo '<h2>Завантаження фото</h2>';
    echo '<div class="card mt-4" style="max-width:760px;">';
    echo '<div class="card-body">';
    echo '<form method="post" action="'
        . Plugin::getWebDir('computerimages')
        . '/front/image.upload.php'
        . '" enctype="multipart/form-data">';

    echo '<input type="hidden" name="computers_id" value="' . $computer_id . '">';
    echo '<input type="hidden" name="_glpi_csrf_token" value="'
        . htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">';

    echo '<div class="mb-3">';
    echo '<label for="image_file" class="form-label">Фото (JPG, PNG або GIF)</label>';
    echo '<input type="file" class="form-control" name="image_file" id="image_file" accept="image/jpeg,image/png,image/gif" required>';
    echo '</div>';

    if ($comments_supported) {
        echo '<div class="mb-3">';
        echo '<label for="image_comment" class="form-label">Коментар до фото</label>';
        echo '<textarea class="form-control" name="image_comment" id="image_comment" rows="3" maxlength="2000"'
            . ' placeholder="Наприклад: задня панель, пошкодження корпусу, розташування кабелів"></textarea>';
        echo '<div class="form-text">Необов’язково, до 2000 символів.</div>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">Щоб додавати коментарі, оновіть плагін у розділі «Налаштування → Плагіни».</div>';
    }

    echo '<button type="submit" class="btn btn-warning">'
        . '<i class="ti ti-upload me-1"></i>Завантажити'
        . '</button>';

    echo '</form>';
    echo '</div>';
    echo '</div>';
}

?>
