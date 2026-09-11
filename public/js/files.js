/* File management page: local times, countdown to expiration, AJAX deletion. */
$(function () {
    'use strict';

    const $table = $('#files-table');

    $('.js-local-time').each(function () {
        $(this).text(App.formatDate($(this).attr('datetime')));
    });

    function renderCountdowns() {
        $('.js-countdown').each(function () {
            const $el = $(this);
            const iso = $el.attr('datetime');
            const left = new Date(iso) - Date.now();
            $el.text(left > 0 ? 'через ' + App.formatDuration(left) : 'ожидает удаления')
                .attr('title', App.formatDate(iso));
        });
    }

    renderCountdowns();
    setInterval(renderCountdowns, 30000);

    function removeRow($row) {
        $row.fadeOut(300, function () {
            $row.remove();
            if ($table.find('tbody tr').length === 0) {
                window.location.reload();
            }
        });
    }

    // The form still works without JS (redirect with a flash message); with JS it goes through AJAX.
    $table.on('submit', '.js-delete-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const name = $form.data('name');
        if (!window.confirm('Удалить файл «' + name + '»?')) {
            return;
        }

        const $button = $form.find('button').prop('disabled', true);

        $.ajax({ url: $form.attr('action'), method: 'DELETE' })
            .done(function () {
                removeRow($form.closest('tr'));
                App.toast('Файл «' + name + '» удалён, уведомление отправлено.', 'success');
            })
            .fail(function (xhr) {
                if (xhr.status === 404) {
                    removeRow($form.closest('tr'));
                    App.toast('Файл «' + name + '» уже был удалён.', 'warning');
                    return;
                }
                $button.prop('disabled', false);
                App.toast(App.errorMessage(xhr), 'danger');
            });
    });
});
