/* Shared helpers for all pages. */
window.App = (function ($) {
    'use strict';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            Accept: 'application/json',
        },
    });

    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
    }

    function formatDate(iso) {
        return new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
    }

    function formatDuration(ms) {
        const totalMinutes = Math.max(1, Math.ceil(ms / 60000));
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return (hours ? hours + ' h ' : '') + minutes + ' min';
    }

    function errorMessage(xhr) {
        const json = xhr.responseJSON;
        if (json && json.errors) {
            return Object.values(json.errors).flat().join(' ');
        }
        if (xhr.status === 413) {
            return 'The file is too large.';
        }
        if (json && json.message) {
            return json.message;
        }
        return xhr.status ? 'Server error (' + xhr.status + '). Please try again.' : 'Cannot reach the server.';
    }

    function toast(message, type) {
        const $toast = $(
            '<div class="toast align-items-center border-0" role="status" aria-live="polite" aria-atomic="true">' +
                '<div class="d-flex"><div class="toast-body"></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                '</div></div>'
        );
        $toast.addClass('text-bg-' + (type || 'success')).find('.toast-body').text(message);
        $('#toasts').append($toast);
        $toast.on('hidden.bs.toast', function () {
            $toast.remove();
        });
        bootstrap.Toast.getOrCreateInstance($toast[0]).show();
    }

    return { formatBytes, formatDate, formatDuration, errorMessage, toast };
})(jQuery);
