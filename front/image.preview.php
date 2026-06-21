<?php
/**
 * Small read-only preview block for the main Computer form.
 * Shows latest thumbnails and links to original images.
 */

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', '/usr/share/webapps/glpi');
}

include_once GLPI_ROOT . '/inc/includes.php';

Session::checkLoginUser();

if (!Session::haveRight('computer', READ) || !Session::haveRight('plugin_computerimages_profile', READ)) {
    http_response_code(403);
    exit;
}

$computer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($computer_id <= 0) {
    exit;
}

$computerimage = new PluginComputerimagesComputerimages();
$images = $computerimage->getImagesForComputer($computer_id);

if (count($images) === 0) {
    exit;
}

$images = array_slice($images, 0, 4);
$total = count($computerimage->getImagesForComputer($computer_id));

$plugin_web_dir = Plugin::getWebDir('computerimages');
$computer_url = $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computer_id . '&forcetab=PluginComputerimagesComputerimages$1';

function plugin_computerimages_preview_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<div id="computerimages-inline-preview" class="card mt-3 computerimages-inline-preview-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-bold">
            <i class="ti ti-photo me-1"></i>
            <?php echo plugin_computerimages_preview_escape(__('Computer photos', 'computerimages')); ?>
        </span>
        <span class="badge bg-secondary"><?php echo (int)$total; ?></span>
    </div>

    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($images as $image): ?>
                <?php
                    $image_id = (int)$image['id'];
                    $filename = plugin_computerimages_preview_escape($image['filename'] ?? '');
                    $original_url = $plugin_web_dir . '/front/image.send.php?id=' . $image_id;
                    $thumb_url = $plugin_web_dir . '/front/image.send.php?id=' . $image_id . '&thumb=1';
                ?>
                <a href="<?php echo $original_url; ?>" target="_blank" rel="noopener" title="<?php echo $filename; ?>">
                    <img src="<?php echo $thumb_url; ?>"
                         alt="<?php echo $filename; ?>"
                         class="img-thumbnail"
                         loading="lazy"
                         decoding="async"
                         style="width: 92px; height: 92px; object-fit: cover;">
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-2 d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <?php echo plugin_computerimages_preview_escape(__('Latest images', 'computerimages')); ?>
            </span>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $computer_url; ?>">
                <?php echo plugin_computerimages_preview_escape(__('All images', 'computerimages')); ?>
            </a>
        </div>
    </div>
</div>
