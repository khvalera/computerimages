<?php

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

if (!Session::haveRight('plugin_computerimages_profile', READ)) {
    echo '<div class="text-center"><h3 class="mb-4">Немає прав для перегляду фото.</h3></div>';
    exit;
}

$computer_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$computerimage = new PluginComputerimagesComputerimages();
$images = $computerimage->getImagesForComputer($computer_id);
$plugin_web_dir = Plugin::getWebDir('computerimages');
$comments_supported = PluginComputerimagesComputerimages::supportsImageComments();
$can_edit_comments = $comments_supported
    && Session::haveRight('plugin_computerimages_profile', CREATE)
    && Session::haveRight('computer', UPDATE);

$escape = static function($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<div class="full_form">';
echo '<div class="container-fluid mt-4">';
echo '<div class="text-center"><h2 class="mb-4">Фото комп’ютера</h2></div>';

if (count($images) === 0) {
    echo '<p class="text-center">Для цього комп’ютера ще не завантажено жодного фото.</p>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-striped table-hover align-middle">';
    echo '<thead class="table-dark"><tr>';
    echo '<th>Фото</th>';
    echo '<th>Назва файлу</th>';
    echo '<th>Коментар</th>';
    echo '<th>Дата завантаження</th>';
    echo '<th>Завантажив</th>';
    echo '<th>Дії</th>';
    echo '</tr></thead><tbody>';

    foreach ($images as $image) {
        $image_id = (int)$image['id'];
        $filename = $escape($image['filename'] ?? '');
        $comment = (string)($image['comment'] ?? '');
        $image_original_url = $plugin_web_dir . '/front/image.send.php?id=' . $image_id;
        $image_thumb_url = $plugin_web_dir . '/front/image.send.php?id=' . $image_id . '&thumb=1';
        $delete_csrf_token = Session::getNewCSRFToken();
        $comment_csrf_token = Session::getNewCSRFToken();

        $delete_url = $plugin_web_dir
            . '/front/image.form.php?id=' . $computer_id
            . '&action=delete&image_id=' . $image_id
            . '&_glpi_csrf_token=' . rawurlencode($delete_csrf_token);

        echo '<tr>';

        echo '<td style="width:280px;">';
        echo '<a href="' . $escape($image_original_url) . '" target="_blank" rel="noopener">';
        echo '<img src="' . $escape($image_thumb_url) . '" alt="' . $filename . '"'
            . ' class="img-thumbnail" loading="lazy" decoding="async"'
            . ' style="width:260px;height:260px;object-fit:cover;">';
        echo '</a>';
        echo '</td>';

        echo '<td>' . $filename . '</td>';

        echo '<td style="min-width:360px;">';
        if ($can_edit_comments) {
            $display_id = 'computerimages-comment-display-' . $image_id;
            $form_id = 'computerimages-comment-form-' . $image_id;
            $comment_button_label = $comment !== '' ? 'Редагувати' : 'Додати коментар';

            echo '<div id="' . $display_id . '">';
            if ($comment !== '') {
                echo '<div class="mb-2" style="white-space:pre-wrap;">' . nl2br($escape($comment)) . '</div>';
            } else {
                echo '<div class="text-muted mb-2">Коментар відсутній.</div>';
            }
            echo '<button type="button" class="btn btn-sm btn-outline-secondary"'
                . ' onclick="computerimagesToggleComment(' . $image_id . ', true)">'
                . '<i class="ti ti-pencil me-1"></i>' . $comment_button_label
                . '</button>';
            echo '</div>';

            echo '<form id="' . $form_id . '" method="post"'
                . ' action="' . $escape($plugin_web_dir . '/front/image.comment.php') . '"'
                . ' style="display:none;">';
            echo '<input type="hidden" name="save_comment" value="1">';
            echo '<input type="hidden" name="image_id" value="' . $image_id . '">';
            echo '<input type="hidden" name="computers_id" value="' . $computer_id . '">';
            echo '<input type="hidden" name="_glpi_csrf_token" value="' . $escape($comment_csrf_token) . '">';
            echo '<textarea class="form-control" name="image_comment" rows="4" maxlength="2000"'
                . ' placeholder="Додайте коментар до фото">' . $escape($comment) . '</textarea>';
            echo '<div class="d-flex gap-2 mt-2">';
            echo '<button type="submit" class="btn btn-sm btn-primary">'
                . '<i class="ti ti-device-floppy me-1"></i>Зберегти'
                . '</button>';
            echo '<button type="button" class="btn btn-sm btn-outline-secondary"'
                . ' onclick="computerimagesToggleComment(' . $image_id . ', false)">'
                . '<i class="ti ti-x me-1"></i>Скасувати'
                . '</button>';
            echo '</div>';
            echo '</form>';
        } elseif ($comment !== '') {
            echo '<div style="white-space:pre-wrap;">' . nl2br($escape($comment)) . '</div>';
        } elseif (!$comments_supported) {
            echo '<span class="text-muted">Оновіть плагін, щоб використовувати коментарі.</span>';
        } else {
            echo '<span class="text-muted">Коментар відсутній.</span>';
        }
        echo '</td>';

        echo '<td>' . $escape($image['upload_date'] ?? '') . '</td>';
        echo '<td>' . $escape($image['uploader_name'] ?? '') . '</td>';

        echo '<td>';
        if (Session::haveRight('plugin_computerimages_profile', DELETE)) {
            echo '<a href="' . $escape($delete_url) . '" class="btn btn-sm btn-danger"'
                . ' onclick="return confirm(\'Видалити це фото?\');">'
                . '<i class="ti ti-trash me-1"></i>Видалити'
                . '</a>';
        }
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

echo '</div>';
echo '</div>';

echo <<<'HTML'
<script>
function computerimagesToggleComment(imageId, editing) {
    const display = document.getElementById('computerimages-comment-display-' + imageId);
    const form = document.getElementById('computerimages-comment-form-' + imageId);

    if (!display || !form) {
        return;
    }

    display.style.display = editing ? 'none' : '';
    form.style.display = editing ? '' : 'none';

    if (editing) {
        const textarea = form.querySelector('textarea[name="image_comment"]');
        if (textarea) {
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }
    }
}
</script>
HTML;

?>
