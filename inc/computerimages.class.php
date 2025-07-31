<?php
/**
 * Plugin setup file for Computer Images.
 * Adapted for GLPI 10.0.x and older CSRF method.
 *
 * @package   Computer Images
 * @copyright 2024 khvalera
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 */

// Don't allow direct access
if (!defined('GLPI_ROOT')) {
    die(__('Direct access is not allowed', 'computerimages') . "\n");
}

class PluginComputerimagesComputerimages extends CommonDBTM {

    public $dohistory = true; // Enable history tracking for this object

    /**
     * Get the table name.
     *
     * @return string
     */
    static function getTable($classname = null) {
        return 'glpi_plugin_computerimages_images';
    }

    /**
     * Get the type name.
     *
     * @return string
     */
    static function getType() {
        return 'PluginComputerimagesComputerimages';
    }

    /**
     * Constructor.
     *
     * @param DBConnection $DB       Database connection
     * @param integer      $id       Object ID
     * @param string       $fields   Fields to load
     */
    public function __construct(DBConnection $DB = null, $id = 0, $fields = '') {
        parent::__construct($DB, $id, $fields);
    }

    /**
     * Get images for a specific computer.
     *
     * @param int $computers_id ID of the computer
     * @return array Array of image data
     */
    public static function getImagesForComputer($computers_id) {
        global $DB;

        if ($computers_id <= 0) {
            return [];
        }

        $query = "SELECT
                    `glpi_plugin_computerimages_images`.`id`,
                    `glpi_plugin_computerimages_images`.`filename`,
                    `glpi_plugin_computerimages_images`.`upload_date`,
                    `glpi_plugin_computerimages_images`.`filepath`,
                    `glpi_plugin_computerimages_images`.`mimetype`,
                    `glpi_plugin_computerimages_images`.`filesize`,
                    `glpi_users`.`firstname` AS uploader_firstname,
                    `glpi_users`.`realname` AS uploader_realname
                  FROM `glpi_plugin_computerimages_images`
                  LEFT JOIN `glpi_users` ON `glpi_users`.`id` = `glpi_plugin_computerimages_images`.`users_id_upload`
                  WHERE `glpi_plugin_computerimages_images`.`computers_id` = " . (int)$computers_id . "
                  ORDER BY `upload_date` DESC;";

        $result = $DB->query($query);

        if (!$result) {
            return [];
        }

        $images = [];

        while ($row = $DB->fetchAssoc($result)) {
            $row['uploader_name'] = trim($row['uploader_firstname'] . ' ' . $row['uploader_realname']);
            $images[] = $row;
        }

        return $images;
    }

    /**
     * Upload an image for a specific computer.
     *
     * @param int   $computers_id ID of the computer
     * @param array $file_data    $_FILES array data for the uploaded file
     * @return array Array with 'success' (bool) and 'message' (string)
     */
    public static function uploadImage($computers_id, $file_data) {
        global $DB;

        if (!is_numeric($computers_id) || $computers_id <= 0) {
            return ['success' => false, 'message' => __('Invalid computer ID.', 'computerimages')];
        }

        if (!Session::haveRight('computer', UPDATE)) {
            return ['success' => false, 'message' => __('You don\'t have permission to perform this action.', 'computerimages')];
        }

        if (empty($file_data['name']) || $file_data['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => __('No file uploaded or upload error.', 'computerimages')];
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_data['type'], $allowed_types)) {
            return ['success' => false, 'message' => __('Invalid file type. Only JPG, PNG, GIF are allowed.', 'computerimages')];
        }

        // Define GLPI_PLUGIN_DOC_DIR
        if (!defined('GLPI_PLUGIN_DOC_DIR')) {
            define('GLPI_PLUGIN_DOC_DIR', GLPI_ROOT . '/files/_plugins');
        }

        $pictures_dir = GLPI_PLUGIN_DOC_DIR . '/computerimages/pictures/';

        // Ensure directory exists and is writable
        if (!is_dir($pictures_dir)) {
            if (!mkdir($pictures_dir, 0775, true)) { // Create recursively with permissions
                return ['success' => false, 'message' => __('Image upload directory cannot be created.', 'computerimages')];
            }
        }
        if (!is_writable($pictures_dir)) {
            return ['success' => false, 'message' => __('Image upload directory is not writable.', 'computerimages')];
        }

        // Sanitize filename to prevent directory traversal and other issues
        $original_filename = basename($file_data['name']);
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $clean_filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
        $unique_filename = uniqid('img_' . $computers_id . '_') . '.' . $extension;
        $target_filepath = $pictures_dir . $unique_filename;
        // relative_filepath for DB should be relative to GLPI_ROOT/files/
        $relative_filepath = '_plugins/computerimages/pictures/' . $unique_filename;

        if (move_uploaded_file($file_data['tmp_name'], $target_filepath)) {
            $image = new self();
            $image->fields = [
                'computers_id'    => $computers_id,
                'filename'        => Html::clean($original_filename), // Keep original name for display
                'filepath'        => Html::clean($relative_filepath), // Store unique path for actual file
                'mimetype'        => Html::clean($file_data['type']),
                'filesize'        => $file_data['size'],
                'upload_date'     => date('Y-m-d H:i:s'),
                'users_id_upload' => Session::getLoginUserID()
            ];

            if ($image->add($image->fields)) {
                return ['success' => true, 'message' => __('Image uploaded successfully.', 'computerimages')];
            } else {
                if (file_exists($target_filepath)) {
                    unlink($target_filepath); // Clean up uploaded file if DB save fails
                }
                return ['success' => false, 'message' => __('Failed to save image information.', 'computerimages')];
            }
        } else {
            return ['success' => false, 'message' => __('Failed to move uploaded file. Check directory permissions.', 'computerimages')];
        }
    }

    /**
     * Delete an image by ID.
     *
     * @param int $image_id ID of the image to delete
     * @return array Array with 'success' (bool) and 'message' (string)
     */
    public static function deleteImage($image_id) {
        global $DB;
        if (!is_numeric($image_id) || $image_id <= 0) {
            return ['success' => false, 'message' => __('Invalid image ID.', 'computerimages')];
        }

        if (!Session::haveRight('computer', DELETE)) {
            return ['success' => false, 'message' => __('You don\'t have permission to perform this action.', 'computerimages')];
        }

        $image = new self();
        if (!$image->getFromDB($image_id)) {
            return ['success' => false, 'message' => __('Image not found.', 'computerimages')];
        }

        $filepath_in_db = $image->fields['filepath'];
        // Reconstruct full path
        $full_image_path = GLPI_VAR_DIR . '/' . $filepath_in_db;
        // Delete from database first
        if ($image->delete(['id' => $image_id])) {
            // Then delete the physical file
            if (file_exists($full_image_path)) {
                if (unlink($full_image_path)) {
                    return ['success' => true, 'message' => __('Image deleted successfully.', 'computerimages')];
                } else {
                    return ['success' => false, 'message' => __('Failed to delete image file.', 'computerimages')];
                }
            } else {
                return ['success' => true, 'message' => __('Image record deleted, file not found.', 'computerimages')];
            }
        } else {
            return ['success' => false, 'message' => __('Failed to delete image information.', 'computerimages')];
        }
    }

    /**
     * Define the tabs for the plugin.
     * Required for GLPI 9.3+ `addtabon` functionality.
     *
     * @param string $type The type of item (e.g., 'Computer')
     * @param string $tab  The tab name
     * @return string|array|boolean The tab name, or an array of tabs, or false if no tab.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (!Session::haveRight('computer', READ)) {
            return false; // User doesn't have rights to view computer, so no tab.
        }

        // Only add tab for Computer objects
        if ($item instanceof Computer) {
           return __('Images', 'computerimages');
        }
        return false;
    }

    /**
     * Display the content of the plugin tab.
     *
     * @param CommonGLPI $item         The item object (e.g., Computer)
     * @param int        $tabnum       The tab number
     * @param int        $withtemplate Whether to display with template (usually 0 for custom tabs)
     * @return boolean True on success
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        // Ensure it's a Computer item and has a valid ID
        if (!($item instanceof Computer) || $item->getID() <= 0) {
            Html::displayErrorPage(__('Invalid computer ID.', 'computerimages'));
            return false;
        }

        // Check if the user has permission to view computers
        if (!Session::haveRight('computer', READ)) {
            Html::displayErrorPage(__('You don\'t have permission to view this.', 'computerimages'));
            return false;
        }

        // Set the computers_id in $_GET so image.form.php and image.view.php can pick it up
        $_GET['computers_id'] = $item->getID();
        $_GET['id']           = $item->getID(); // Also pass 'id' for consistency

        include GLPI_ROOT . '/plugins/computerimages/front/image.form.php';
        include GLPI_ROOT . '/plugins/computerimages/front/image.view.php';

        return true;
    }
}
?>