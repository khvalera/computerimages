<?php

// Ensure the main GLPI environment is loaded
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

// Check access rights
Session::checkLoginUser();

$computerimage = new PluginComputerimagesComputerimages();

$computer_id = 0;
if (isset($_REQUEST['computers_id'])) {
    $computer_id = (int) $_REQUEST['computers_id'];
}
if (isset($_REQUEST['id'])) {
    $computer_id = (int) $_REQUEST['id'];
}

$image_id = 0;
if (isset($_REQUEST['image_id'])) {
    $image_id = (int) $_REQUEST['image_id'];
}

global $GLPI_WEB_ROOT;

// Handle the delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete') {

    // Check delete permissions
    if (!Session::haveRight('plugin_computerimages_profile', DELETE)) {
        Html::displayErrorAndDie(__('No permission to delete files'));
    }
    // Check the CSRF token
    if (!Session::validateCSRF($_GET)) {
        Html::displayErrorAndDie(__('Invalid CSRF token.'));
    }

    $upload_result = $computerimage->deleteImage($image_id);
    if (!$upload_result['success']) {
        Session::addMessageAfterRedirect($upload_result['message'], false, ERROR);
    } else {
        Session::addMessageAfterRedirect($upload_result['message'], false, INFO);
    }
    Html::redirect($GLPI_WEB_ROOT . '/front/computer.form.php?id=' . $computer_id );
}

// File upload processing
if (isset($_FILES['image_file'])) {
    // Check creation (download) rights
    if (!Session::haveRight('plugin_computerimages_profile', CREATE)) {
       Html::displayErrorAndDie(__('You do not have access to upload the images.'));
    }
    // Check the CSRF token
    //if (!Session::validateCSRF($_POST)) {
    //    Html::displayErrorAndDie(__('Invalid CSRF token.'));
    //}

    $upload_result = $computerimage->uploadImage($computer_id, $_FILES['image_file']);
    if (!$upload_result['success']) {
        Session::addMessageAfterRedirect($upload_result['message'], false, ERROR);
    } else {
        Session::addMessageAfterRedirect($upload_result['message'], false, INFO);
    }
    Html::redirect($GLPI_WEB_ROOT . '/front/computer.form.php?id=' . $computer_id );
}

if (Session::haveRight('plugin_computerimages_profile', CREATE)) {
   // Display the form
   echo '<h2>' . __('Uploading files', 'computerimages') . '</h2>';

   echo '<div class="card w-50 mt-4">';
   echo '<div class="card-body">';
   echo '<form method="post" action="' . Plugin::getWebDir('computerimages') . '/front/image.form.php?id=' . $computer_id . '" enctype="multipart/form-data">';

   // Computer ID
   echo '<input type="hidden" name="computers_id" value="' . $computer_id . '">';

   // CSRF token
   $csrf_token = '';
   if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
       $csrf_token = Session::getNewCSRFToken();
   }
   echo '<input type="hidden" name="_glpi_csrf_token" value="' . $csrf_token . '">';

   // File selection
   echo '<div class="form-group">';
   echo '<label for="image_file" style="margin-bottom: 10px; display: block;">' . __('Select Image (only JPG, PNG, GIF are allowed)', 'computerimages') . '</label>';
   echo '<input type="file" class="form-control-file" name="image_file" id="image_file">';
   echo '</div>';

   // Button
   echo '<div class="form-group mt-3">';
   echo '<button type="submit" class="btn btn-warning">' . __('Upload', 'computerimages') . '</button>';
   echo '</div>';

   echo '</form>';
   echo '</div>'; // .card-body
   echo '</div>'; // .card
}

?>