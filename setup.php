<?php

use Glpi\Plugin\Hooks;
/**
 * Plugin setup file for Computer Images.
 * Adapted for GLPI 10-11.
 *
 * @package   Computer Images
 * @license   GPLv3
 */

// Don't allow direct access
if (!defined('GLPI_ROOT')) {
    die(__('Direct access is not allowed', 'computerimages') . "\n");
}

/**
 * Get plugin information.
 *
 * @return array
 */
function plugin_version_computerimages() {
    return [
        'name'           => __('Computer Images', 'computerimages'),
        'version'        => '1.1.0',
        'author'         => 'Your Name',
        'license'        => 'GPLv3',
        'homepage'       => 'https://example.com',

        'minGlpiVersion' => '10.00',
        'maxGlpiVersion' => '11.99',

        'requirements'   => [
            'glpi' => [
                'min' => '10.00',
                'max' => '11.99',
                'plugin' => [],
            ],
        ],

        'displayname'      => __('Computer Images', 'computerimages'),
        'description'      => __('Allows uploading and displaying images for Computer assets.', 'computerimages'),
        'long_description' => __('This plugin extends GLPI functionality by adding a dedicated tab to Computer assets, enabling users to upload, view, and manage images associated with each computer.', 'computerimages'),
    ];
}

/**
 * Return plugin storage directories.
 *
 * @return array
 */
function plugin_computerimages_get_storage_dirs() {
    $base_dir = defined('GLPI_VAR_DIR') ? rtrim(GLPI_VAR_DIR, '/') : rtrim(GLPI_ROOT, '/') . '/files';

    return [
        'pictures' => $base_dir . '/_plugins/computerimages/pictures',
        'thumbs'   => $base_dir . '/_plugins/computerimages/thumbs',
    ];
}

/**
 * Ensure plugin storage directories exist and are writable.
 *
 * @return bool
 */
function plugin_computerimages_prepare_storage_dirs() {
    foreach (plugin_computerimages_get_storage_dirs() as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true)) {
                Session::addMessageAfterRedirect(
                    __('Failed to create image directory: ', 'computerimages') . $dir,
                    false,
                    ERROR
                );
                return false;
            }
        }

        if (!is_writable($dir)) {
            Session::addMessageAfterRedirect(
                sprintf(__('The "%s" image directory is not writable', 'computerimages'), $dir),
                false,
                ERROR
            );
            return false;
        }
    }

    return true;
}

/**
 * Check if plugin can be activated.
 *
 * @return bool
 */
function plugin_computerimages_check_prerequisites() {
    return true;
}

/**
 * Check if plugin can be installed.
 *
 * @return bool
 */
function plugin_computerimages_check_config() {
    return plugin_computerimages_prepare_storage_dirs();
}

/**
 * Plugin initialization function.
 *
 * @return void
 */
function plugin_init_computerimages() {
    global $PLUGIN_HOOKS;

    Plugin::registerClass(
        PluginComputerimagesComputerimages::class,
        [ 'addtabon' => ['Computer'] ]
    );

    Plugin::registerClass(
        'PluginComputerimagesProfile',
        [ 'addtabon' => ['Profile'] ]
    );

    $PLUGIN_HOOKS['csrf_compliant']['computerimages'] = true;

    // GLPI 11 renders this hook directly before the form action buttons.
    // The generated preview remains visible there as a safe fallback; the
    // JavaScript helper moves it into the native .asset-pictures column.
    $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['computerimages'] = [
        PluginComputerimagesComputerimages::class,
        'displayMainFormPreview',
    ];
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['computerimages'][] = 'js/computerimages-preview.js';
}

/**
 * Plugin installation function.
 *
 * @return bool
 */
function plugin_computerimages_install() {
    global $DB;

    $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_computerimages_images` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `computers_id` INT UNSIGNED NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `filepath` VARCHAR(255) NOT NULL,
        `mimetype` VARCHAR(100) NOT NULL,
        `filesize` INT(11) NOT NULL,
        `upload_date` TIMESTAMP NOT NULL,
        `users_id_upload` INT UNSIGNED NOT NULL,
        `comment` TEXT NULL,
        PRIMARY KEY (`id`),
        INDEX `computers_id` (`computers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if (!$DB->doQuery($query)) {
        Session::addMessageAfterRedirect(
            __('Failed to create table glpi_plugin_computerimages_images. DB Error: ', 'computerimages') . $DB->error(),
            false,
            ERROR
        );
        return false;
    }

    // Upgrade existing installations: add a plain-text comment field.
    if (!$DB->fieldExists('glpi_plugin_computerimages_images', 'comment')) {
        $alter_query = "ALTER TABLE `glpi_plugin_computerimages_images` "
            . "ADD `comment` TEXT NULL AFTER `users_id_upload`";

        if (!$DB->doQuery($alter_query)) {
            Session::addMessageAfterRedirect(
                __('Failed to add the image comment field. DB Error: ', 'computerimages') . $DB->error(),
                false,
                ERROR
            );
            return false;
        }
    }

    // Create pictures and thumbnails dirs
    if (!plugin_computerimages_prepare_storage_dirs()) {
        return false;
    }

    include_once Plugin::getPhpDir('computerimages').'/inc/profile.class.php';
    PluginComputerimagesProfile::initProfile();

    return true;
}

/**
 * Plugin uninstallation function.
 *
 * @return bool
 */
function plugin_computerimages_uninstall() {
    global $DB;

    $base_dir = defined('GLPI_VAR_DIR') ? rtrim(GLPI_VAR_DIR, '/') : rtrim(GLPI_ROOT, '/') . '/files';
    $archive_dir = $base_dir . '/_archives';
    $pictures_dir = $base_dir . '/_plugins/computerimages/pictures';
    $thumbs_dir = $base_dir . '/_plugins/computerimages/thumbs';
    $timestamp = date('Ymd_His');
    $archive_file = $archive_dir . "/computerimages_backup_{$timestamp}.zip";

    if (!file_exists($archive_dir)) {
        mkdir($archive_dir, 0755, true);
    }

    $export_sql = $archive_dir . "/computerimages_table_{$timestamp}.sql";
    $iterator = $DB->request('glpi_plugin_computerimages_images');

    $fh = fopen($export_sql, 'w');
    foreach ($iterator as $row) {
        $values = array_map([$DB, 'escape'], array_values($row));
        $fields = implode('`,`', array_keys($row));
        $vals = implode("','", $values);
        fwrite($fh, "INSERT INTO `glpi_plugin_computerimages_images` (`$fields`) VALUES ('$vals');\n");
    }
    fclose($fh);

    $zip = new ZipArchive();
    if ($zip->open($archive_file, ZipArchive::CREATE) === true) {
        $zip->addFile($export_sql, basename($export_sql));

        if (is_dir($pictures_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pictures_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                $filePath = $file->getRealPath();
                $relativePath = 'pictures/' . substr($filePath, strlen($pictures_dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        Session::addMessageAfterRedirect(
            sprintf(__('Archive creation "%s" completed', 'computerimages'), $archive_file),
            true,
            INFO
        );
    }

    unlink($export_sql);

    // Drop table
    $query = "DROP TABLE IF EXISTS `glpi_plugin_computerimages_images`;";
    if ($DB->doQuery($query)) {
        Session::addMessageAfterRedirect(
            __('Table glpi_plugin_computerimages_images dropped successfully.', 'computerimages'),
            true,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Failed to drop table glpi_plugin_computerimages_images. DB Error: ', 'computerimages') . $DB->error(),
            false,
            ERROR
        );
    }

    // Delete pictures and thumbnails
    $deleteDir = function($dir) use (&$deleteDir) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $deleteDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    };

    foreach ([$pictures_dir, $thumbs_dir] as $dir) {
        if (is_dir($dir)) {
            if ($deleteDir($dir)) {
                Session::addMessageAfterRedirect(
                    __('Directory deleted successfully: ', 'computerimages') . $dir,
                    true,
                    INFO
                );
            } else {
                Session::addMessageAfterRedirect(
                    __('Failed to delete directory: ', 'computerimages') . $dir,
                    false,
                    ERROR
                );
            }
        }
    }

    include_once Plugin::getPhpDir('computerimages').'/inc/profile.class.php';
    PluginComputerimagesProfile::removeRights();

    return true;
}
