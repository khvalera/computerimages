(function () {
    'use strict';

    function placeComputerImagesPreview() {
        var preview = document.getElementById('computerimages-main-preview');
        if (!preview) {
            return false;
        }

        var form = preview.closest('form');
        if (!form) {
            return false;
        }

        var buttonBar = form.querySelector('.form-button-separator');
        if (!buttonBar) {
            return false;
        }

        if (buttonBar.nextElementSibling !== preview) {
            buttonBar.insertAdjacentElement('afterend', preview);
        }

        return true;
    }

    function initComputerImagesPreview() {
        placeComputerImagesPreview();

        // GLPI may render the item form asynchronously after the main page has
        // already loaded, so keep watching for the preview and button bar.
        var observer = new MutationObserver(function () {
            placeComputerImagesPreview();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComputerImagesPreview, {once: true});
    } else {
        initComputerImagesPreview();
    }
})();
