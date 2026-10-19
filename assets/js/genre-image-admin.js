(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.donghua-genre-image-field').forEach(function (field) {
            var input = field.querySelector('#donghua-genre-image-id');
            var preview = field.querySelector('[data-genre-image-preview]');
            var choose = field.querySelector('[data-genre-image-choose]');
            var remove = field.querySelector('[data-genre-image-remove]');
            var frame = null;
            if (!input || !preview || !choose || !remove || !window.wp || !window.wp.media) return;

            choose.addEventListener('click', function (event) {
                event.preventDefault();
                if (!frame) {
                    frame = window.wp.media({
                        title: 'اختيار صورة التصنيف',
                        button: { text: 'استخدام الصورة' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        var image = document.createElement('img');
                        image.src = (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
                        image.alt = '';
                        input.value = String(attachment.id);
                        preview.replaceChildren(image);
                        remove.hidden = false;
                    });
                }
                frame.open();
            });

            remove.addEventListener('click', function (event) {
                event.preventDefault();
                input.value = '0';
                preview.replaceChildren();
                var note = document.createElement('span');
                note.textContent = 'لم تُحدد صورة بعد.';
                preview.appendChild(note);
                remove.hidden = true;
            });
        });
    });
})();
