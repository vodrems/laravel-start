/* Asynchronous uploader: every file is sent in its own AJAX request with a progress bar. */
$(function () {
    'use strict';

    const $zone = $('#dropzone');
    const $input = $('#file-input');
    const $list = $('#upload-list');
    const uploadUrl = $zone.data('upload-url');
    const maxSize = Number($zone.data('max-size'));
    const extensions = String($zone.data('extensions')).split(',');

    function extensionOf(name) {
        const dot = name.lastIndexOf('.');
        return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
    }

    // Mirrors the server rules for instant feedback; the server still validates everything.
    function validate(file) {
        if (extensions.indexOf(extensionOf(file.name)) === -1) {
            return 'Допустимы только файлы ' + extensions.join(', ').toUpperCase() + '.';
        }
        if (file.size > maxSize) {
            return 'Максимальный размер файла — ' + App.formatBytes(maxSize) + '.';
        }
        return null;
    }

    function createItem(file) {
        const $item = $(
            '<li class="list-group-item">' +
                '<div class="d-flex justify-content-between gap-3">' +
                '<span class="upload-name text-break"></span>' +
                '<small class="upload-size text-body-secondary text-nowrap"></small>' +
                '</div>' +
                '<div class="progress mt-2" role="progressbar" aria-label="Прогресс загрузки">' +
                '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>' +
                '</div>' +
                '<div class="upload-status small mt-1 text-body-secondary">Ожидание…</div>' +
                '</li>'
        );
        $item.find('.upload-name').text(file.name);
        $item.find('.upload-size').text(App.formatBytes(file.size));
        $list.prepend($item);
        return $item;
    }

    function setProgress($item, percent) {
        $item.find('.progress-bar').css('width', percent + '%');
        $item.find('.upload-status').text('Загрузка… ' + percent + '%');
    }

    function finish($item, ok, message) {
        $item.find('.progress-bar')
            .removeClass('progress-bar-striped progress-bar-animated')
            .addClass(ok ? 'bg-success' : 'bg-danger')
            .css('width', '100%');
        $item.find('.upload-status')
            .removeClass('text-body-secondary')
            .addClass(ok ? 'text-success' : 'text-danger')
            .text(message);
    }

    function upload(file) {
        const $item = createItem(file);
        const error = validate(file);
        if (error) {
            finish($item, false, error);
            return;
        }

        const data = new FormData();
        data.append('file', file);

        $.ajax({
            url: uploadUrl,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                const xhr = $.ajaxSettings.xhr();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        setProgress($item, Math.round((e.loaded / e.total) * 100));
                    }
                });
                return xhr;
            },
        })
            .done(function (response) {
                finish($item, true, 'Загружен. Будет удалён ' + App.formatDate(response.data.expires_at) + '.');
            })
            .fail(function (xhr) {
                finish($item, false, App.errorMessage(xhr));
            });
    }

    function handle(fileList) {
        Array.prototype.slice.call(fileList).forEach(upload);
    }

    $input.on('change', function () {
        handle(this.files);
        this.value = '';
    });

    $zone
        .on('dragenter dragover', function (e) {
            e.preventDefault();
            $zone.addClass('is-dragover');
        })
        .on('dragleave drop', function (e) {
            e.preventDefault();
            $zone.removeClass('is-dragover');
        })
        .on('drop', function (e) {
            handle(e.originalEvent.dataTransfer.files);
        });
});
