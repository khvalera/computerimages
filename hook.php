<?php
/**
 * Hook file for Computer Images plugin.
 * Adapted for GLPI 11.0.x.
 *
 * @package   Computer Images
 * @copyright 2024 khvalera
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 */

// Don't allow direct access
if (!defined('GLPI_ROOT')) {
    die(__('Direct access is not allowed', 'computerimages') . "\n");
}

/**
 * Register plugin hooks.
 *
 * @return array
 */
function plugin_computerimages_get_hooks() {
    return [
        'install'   => 'plugin_computerimages_install',   // Register install hook
        'uninstall' => 'plugin_computerimages_uninstall', // Register uninstall hook
    ];
}

//function plugin_computerimages_translate() {
//    Plugin::loadLang('computerimages');
//}