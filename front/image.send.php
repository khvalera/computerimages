<?php

// Ensure the main GLPI environment is loaded
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

// Detailed logging of the image.send.php call
//if (class_exists('Toolbox')) {
//    Toolbox::logInFile('computerimages', "image.send.php called. Request ID: " . ($_GET['id'] ?? 'N/A'), LOG_INFO);
//}

// Check if the image ID is provided in the URL
$image_id = 0;
if (isset($_GET['id'])) {
    $image_id = (int)$_GET['id'];
}

if ($image_id > 0) {
    // Create a Computer Images object and load image data from the database
    $image = new PluginComputerimagesComputerimages();
    if ($image->getFromDB($image_id)) {
        // Original file name
        $filename_db = $image->fields['filename'];
        // The unique file name under which it is saved on disk
        $filepath_db = $image->fields['filepath'];

        // Form the full path to the image file on the server
        $image_full_path = GLPI_VAR_DIR . '/' . $filepath_db;

        // Check if the file exists
        if (file_exists($image_full_path)) {
            // Determine the MIME type of the file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $image_full_path);
            finfo_close($finfo);

            if (empty($mime_type)) {
                // Fallback MIME type
                $mime_type = 'application/octet-stream';
            }

            // Set HTTP headers for file return
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime_type);
            // inline for display in the browser
            header('Content-Disposition: inline; filename="' . basename($filename_db) . '"'); 
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($image_full_path));

            // Clear the output buffers and send the file
            ob_clean();
            flush();
            readfile($image_full_path);
            // It is important to complete the script execution after sending the file
            exit;
        } else {
            // If file not found
            header("HTTP/1.0 404 Not Found");
            echo "Image not found on server.";
        }
    } else {
        // If the image is not found in the database
        header("HTTP/10 404 Not Found");
        echo "Image not found in database.";
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    echo "Bad request.";
}

?>