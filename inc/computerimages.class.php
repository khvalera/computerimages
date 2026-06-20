<?php
/**
 * Plugin setup file for Computer Images.
 * Adapted for GLPI 11.0.x and older CSRF method.
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
     */
    public function __construct(DBConnection $DB = null, $id = 0, $fields = '') {
        parent::__construct($DB, $id, $fields);
    }

    /**
     * Get the base storage directory used by GLPI for variable files.
     *
     * @return string
     */
    private static function getStorageBaseDir() {
        if (defined('GLPI_VAR_DIR')) {
            return rtrim(GLPI_VAR_DIR, '/');
        }

        return rtrim(GLPI_ROOT, '/') . '/files';
    }

    /**
     * Get the directory where original images are stored.
     *
     * @return string
     */
    private static function getPicturesDir() {
        return self::getStorageBaseDir() . '/_plugins/computerimages/pictures';
    }

    /**
     * Get the directory where thumbnail images are stored.
     *
     * @return string
     */
    private static function getThumbsDir() {
        return self::getStorageBaseDir() . '/_plugins/computerimages/thumbs';
    }

    /**
     * Rotate JPEG image according to EXIF orientation.
     *
     * @param resource|GdImage $image
     * @param string           $source
     *
     * @return resource|GdImage
     */
    private static function fixJpegOrientation($image, $source) {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        if (empty($exif['Orientation'])) {
            return $image;
        }

        switch ((int)$exif['Orientation']) {
            case 3:
                $rotated = imagerotate($image, 180, 0);
                break;
            case 6:
                $rotated = imagerotate($image, -90, 0);
                break;
            case 8:
                $rotated = imagerotate($image, 90, 0);
                break;
            default:
                $rotated = false;
                break;
        }

        if ($rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    /**
     * Create a small image copy for fast display in the computer tab.
     * This method must never block upload of the original file.
     *
     * @param string $source
     * @param string $destination
     * @param int    $max_width
     * @param int    $max_height
     *
     * @return bool
     */
    private static function createThumbnail($source, $destination, $max_width = 300, $max_height = 300) {
        if (!function_exists('getimagesize') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $info = @getimagesize($source);
        if ($info === false || empty($info['mime'])) {
            return false;
        }

        $mime = $info['mime'];
        $src = false;

        switch ($mime) {
            case 'image/jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $src = @imagecreatefromjpeg($source);
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $src = @imagecreatefrompng($source);
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $src = @imagecreatefromgif($source);
                }
                break;
            default:
                return false;
        }

        if ($src === false) {
            return false;
        }

        if ($mime === 'image/jpeg') {
            $src = self::fixJpegOrientation($src, $source);
        }

        $width = imagesx($src);
        $height = imagesy($src);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($src);
            return false;
        }

        $ratio = min($max_width / $width, $max_height / $height, 1);
        $new_width = max(1, (int)round($width * $ratio));
        $new_height = max(1, (int)round($height * $ratio));

        $thumb = imagecreatetruecolor($new_width, $new_height);
        if ($thumb === false) {
            imagedestroy($src);
            return false;
        }

        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $transparent);
        }

        $resampled = imagecopyresampled(
            $thumb,
            $src,
            0,
            0,
            0,
            0,
            $new_width,
            $new_height,
            $width,
            $height
        );

        if (!$resampled) {
            imagedestroy($src);
            imagedestroy($thumb);
            return false;
        }

        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($thumb, $destination, 75);
                break;
            case 'image/png':
                $result = imagepng($thumb, $destination, 6);
                break;
            case 'image/gif':
                $result = imagegif($thumb, $destination);
                break;
        }

        imagedestroy($src);
        imagedestroy($thumb);

        return $result;
    }

    /**
     * Get images for a specific computer.
     *
     * FIXED: Rewritten using QueryBuilder because GLPI 11 forbids direct SQL queries.
     */
    public static function getImagesForComputer($computers_id) {
        global $DB;

        if ($computers_id <= 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => [
                'glpi_plugin_computerimages_images.id',
                'glpi_plugin_computerimages_images.filename',
                'glpi_plugin_computerimages_images.upload_date',
                'glpi_plugin_computerimages_images.filepath',
                'glpi_plugin_computerimages_images.mimetype',
                'glpi_plugin_computerimages_images.filesize',
                'glpi_users.firstname AS uploader_firstname',
                'glpi_users.realname AS uploader_realname'
            ],
            'FROM'   => 'glpi_plugin_computerimages_images',
            'LEFT JOIN' => [
                'glpi_users' => [
                    'FKEY' => [
                        'glpi_users'                       => 'id',
                        'glpi_plugin_computerimages_images' => 'users_id_upload'
                    ]
                ]
            ],
            'WHERE'  => [
                'glpi_plugin_computerimages_images.computers_id' => $computers_id
            ],
            'ORDERBY' => ['upload_date DESC']
        ]);

        $images = [];

        foreach ($iterator as $row) {
            $row['uploader_name'] = trim($row['uploader_firstname'] . ' ' . $row['uploader_realname']);
            $images[] = $row;
        }

        return $images;
    }

    /**
     * Upload an image for a specific computer.
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

        $pictures_dir = self::getPicturesDir() . '/';
        $thumbs_dir = self::getThumbsDir() . '/';

        if (!is_dir($pictures_dir)) {
            if (!mkdir($pictures_dir, 0775, true)) {
                return ['success' => false, 'message' => __('Image upload directory cannot be created.', 'computerimages')];
            }
        }
        if (!is_writable($pictures_dir)) {
            return ['success' => false, 'message' => __('Image upload directory is not writable.', 'computerimages')];
        }

        if (!is_dir($thumbs_dir)) {
            @mkdir($thumbs_dir, 0775, true);
        }

        $original_filename = basename($file_data['name']);
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $clean_filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
        $unique_filename = uniqid('img_' . $computers_id . '_') . '.' . $extension;
        $target_filepath = $pictures_dir . $unique_filename;
        $thumb_filepath = $thumbs_dir . $unique_filename;

        $relative_filepath = '_plugins/computerimages/pictures/' . $unique_filename;

        if (move_uploaded_file($file_data['tmp_name'], $target_filepath)) {
            if (is_dir($thumbs_dir) && is_writable($thumbs_dir)) {
                self::createThumbnail($target_filepath, $thumb_filepath, 300, 300);
            }

            $image = new self();
            $image->fields = [
                'computers_id'    => $computers_id,
                'filename'        => Html::cleanInputText($original_filename),
                'filepath'        => Html::cleanInputText($relative_filepath),
                'mimetype'        => Html::cleanInputText($file_data['type']),
                'filesize'        => $file_data['size'],
                'upload_date'     => date('Y-m-d H:i:s'),
                'users_id_upload' => Session::getLoginUserID()
            ];

            if ($image->add($image->fields)) {
                return ['success' => true, 'message' => __('Image uploaded successfully.', 'computerimages')];
            } else {
                if (file_exists($target_filepath)) {
                    unlink($target_filepath);
                }
                if (file_exists($thumb_filepath)) {
                    unlink($thumb_filepath);
                }
                return ['success' => false, 'message' => __('Failed to save image information.', 'computerimages')];
            }
        } else {
            return ['success' => false, 'message' => __('Failed to move uploaded file. Check directory permissions.', 'computerimages')];
        }
    }

    /**
     * Delete an image by ID.
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
        $full_image_path = self::getStorageBaseDir() . '/' . $filepath_in_db;
        $full_thumb_path = str_replace('/pictures/', '/thumbs/', $full_image_path);

        if ($image->delete(['id' => $image_id])) {
            if (file_exists($full_image_path)) {
                if (unlink($full_image_path)) {
                    if (file_exists($full_thumb_path)) {
                        @unlink($full_thumb_path);
                    }
                    return ['success' => true, 'message' => __('Image deleted successfully.', 'computerimages')];
                } else {
                    return ['success' => false, 'message' => __('Failed to delete image file.', 'computerimages')];
                }
            } else {
                if (file_exists($full_thumb_path)) {
                    @unlink($full_thumb_path);
                }
                return ['success' => true, 'message' => __('Image record deleted, file not found.', 'computerimages')];
            }
        } else {
            return ['success' => false, 'message' => __('Failed to delete image information.', 'computerimages')];
        }
    }

    /**
     * Define tabs for plugin.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (!Session::haveRight('computer', READ)) {
            return false;
        }

        if ($item instanceof Computer) {
            return '<span class="d-flex align-items-center">
            <i class="ti ti-photo me-2"></i>'
            . __('Images', 'computerimages') .
            '</span>';

           // return '<i class="ti ti-photo me-2"></i>' . __('Images', 'computerimages');
        }
        return false;
    }

    /**
     * Display tab content.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if (!($item instanceof Computer) || $item->getID() <= 0) {
            Html::displayErrorPage(__('Invalid computer ID.', 'computerimages'));
            return false;
        }

        if (!Session::haveRight('computer', READ)) {
            Html::displayErrorPage(__('You don\'t have permission to view this.', 'computerimages'));
            return false;
        }

        $_GET['computers_id'] = $item->getID();
        $_GET['id']           = $item->getID();

        include GLPI_ROOT . '/plugins/computerimages/front/image.form.php';
        include GLPI_ROOT . '/plugins/computerimages/front/image.view.php';

        return true;
    }
}
?>
