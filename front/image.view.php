<?php

// We provide the download of the main GLPI environment
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

// Check access rights
Session::checkLoginUser();
if (!Session::haveRight('plugin_computerimages_profile', READ)) {
    echo '<div class="text-center">';
    echo '<h3 class="mb-4">' . __('You do not have access to view images.', 'computerimages') . '</h3>';
    echo '</div>'; // text-center
    exit;
}

$computer_id = 0;
if (isset($_REQUEST['id'])) {
    $computer_id = (int) $_REQUEST['id'];
}

$computerimage = new PluginComputerimagesComputerimages();
$images = $computerimage->getImagesForComputer($computer_id);

echo '<div class="full_form">';
echo '<div class="container mt-4">';

// Title
echo '<div class="text-center">';
echo '<h2 class="mb-4">' . __('Computer Images', 'computerimages') . '</h2>';

echo '</div>'; // text-center

// Message or table
if (count($images) === 0) {
    echo '<p class="text-center">' . __('No images uploaded for this computer yet.', 'computerimages') . '</p>';
} else {
    echo '<table class="table table-bordered table-striped table-hover">';
    echo '<thead class="table-dark">';
    echo '<tr>';
    echo '<th>' . __('Image', 'computerimages') . '</th>';
    echo '<th>' . __('Filename', 'computerimages') . '</th>';
    echo '<th>' . __('Upload Date', 'computerimages') . '</th>';
    echo '<th>' . __('Uploaded By', 'computerimages') . '</th>';
    echo '<th>' . __('Actions', 'computerimages') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($images as $image) {
        $image_display_url = Plugin::getWebDir('computerimages') . '/front/image.send.php?id=' . $image['id'];

        $csrf_token = '';
        if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
            $csrf_token = Session::getNewCSRFToken();
        }

        $delete_url = Plugin::getWebDir('computerimages') . '/front/image.form.php?id=' . $computer_id . '&action=delete&image_id=' . $image['id'] . '&_glpi_csrf_token=' . $csrf_token . '&tab=PluginComputerimagesComputerimages';

        echo '<tr>';
        // Clicking on the image opens it in a new tab.
        echo '<td><a href="' . $image_display_url . '" target="_blank"><img src="' . $image_display_url . '" width="200" class="img-thumbnail"></a></td>';
        echo '<td>' . Html::clean($image['filename']) . '</td>';
        echo '<td>' . Html::clean($image['upload_date']) . '</td>';
        echo '<td>' . Html::clean($image['uploader_name']) . '</td>';
        echo '<td>';
        if (Session::haveRight('plugin_computerimages_profile', DELETE)) {
            echo '<a href="' . $delete_url . '" class="btn btn-sm btn-danger" onclick="return confirm(\'' . addslashes(__('Are you sure you want to delete this image?', 'computerimages')) . '\');">' . __('Delete', 'computerimages') . '</a>';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}

echo '</div>'; // container
echo '</div>'; // full_form
?>