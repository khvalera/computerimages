<?php
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
        'version'        => '1.0.0',
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

    $pictures_dir = GLPI_VAR_DIR . '/_plugins/computerimages/pictures';

    if (!is_dir($pictures_dir)) {
        if (!mkdir($pictures_dir, 0775, true)) {
            Session::addMessageAfterRedirect(
                __("Failed to create image directory: ", 'computerimages') . $pictures_dir,
                                             false,
                                             ERROR
            );
            return false;
        }
    }

    if (!is_writable($pictures_dir)) {
        Session::addMessageAfterRedirect(
            sprintf(__('The "%s" image directory is not writable', 'computerimages'), $pictures_dir),
                                         false,
                                         ERROR
        );
        return false;
    }

    return true;
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
        PRIMARY KEY (`id`),
        INDEX `computers_id` (`computers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if (!$DB->query($query)) {
        Session::addMessageAfterRedirect(
            __("Failed to create table glpi_plugin_computerimages_images. DB Error: ", 'computerimages') . $DB->error(),
                                         false,
                                         ERROR
        );
        return false;
    }

    // Create pictures dir
    $pictures_dir = GLPI_PLUGIN_DOC_DIR . '/computerimages/pictures';
    if (!is_dir($pictures_dir)) {
        if (!mkdir($pictures_dir, 0775, true)) {
            Session::addMessageAfterRedirect(
                __("Failed to create image directory: ", 'computerimages') . $pictures_dir,
                                             false,
                                             ERROR
            );
            return false;
        }
    }

    if (!is_writable($pictures_dir)) {
        Session::addMessageAfterRedirect(
            sprintf(__('The "%s" image directory is not writable', 'computerimages'), $pictures_dir),
                                         false,
                                         ERROR
        );
        return false;
    }

    include_once Plugin::getPhpDir('computerimages').'/inc/profile.class.php';
    PluginComputerimagesProfile::removeRights();

    return true;
}

/**
 * Plugin uninstallation function.
 *
 * @return bool
 */
function plugin_computerimages_uninstall() {
    global $DB;

    $archive_dir = GLPI_VAR_DIR . '/_archives';
    $pictures_dir = GLPI_VAR_DIR . '/_plugins/computerimages/pictures';
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
    if ($DB->query($query)) {
        Session::addMessageAfterRedirect(
            __("Table glpi_plugin_computerimages_images dropped successfully.", 'computerimages'),
                                         true,
                                         INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            __("Failed to drop table glpi_plugin_computerimages_images. DB Error: ", 'computerimages') . $DB->error(),
                                         false,
                                         ERROR
        );
    }

    // Delete pictures
    $pictures_dir = GLPI_PLUGIN_DOC_DIR . '/computerimages/pictures';
    if (is_dir($pictures_dir)) {
        function deleteDir($dir) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? deleteDir("$dir/$file") : unlink("$dir/$file");
            }
            return rmdir($dir);
        }
        if (deleteDir($pictures_dir)) {
            Session::addMessageAfterRedirect(
                __("Pictures directory deleted successfully: ", 'computerimages') . $pictures_dir,
                                             true,
                                             INFO
            );
        } else {
            Session::addMessageAfterRedirect(
                __("Failed to delete pictures directory: ", 'computerimages') . $pictures_dir,
                                             false,
                                             ERROR
            );
        }
    }

    include_once Plugin::getPhpDir('computerimages').'/inc/profile.class.php';
    PluginComputerimagesProfile::removeRights();

    return true;
}
