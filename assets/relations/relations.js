(function(netis, $, undefined) {
    'use strict';

    var defaults = {
        modalId: '#relationModal',
        saveButtonId: '#relationSave',
        i18n: {
            loadingText: 'Loading, please wait.'
        }
    };

    var _settings;

    netis.init = function(settings) {
        _settings = $.extend({}, defaults, settings);

        $(document).on('pjax:timeout', _settings.modalId, function(event) {
            event.preventDefault();
        });
        $(document).on('pjax:error', _settings.modalId, function(event) {
            event.preventDefault();
        });
        //$(document).on('pjax:send', settings.modalId, function() { })
        //$(document).on('pjax:complete', settings.modalId, function() { })

        $(_settings.modalId).on('show.bs.modal', netis.showModal);

        var saveButton = $(_settings.saveButtonId);
        saveButton.on('click', netis.saveRelation);

        $('.relations-panel .tab-pane').on('click', 'a.remove', function(event) {
            var key = $(this).parent().closest('tr').data('key');
            netis.removeRelation($(event.delegateTarget).children('div'), key);
            event.preventDefault();
            return false;
        });
    };

    netis.showModal = function(event) {
        var button = $(event.relatedTarget),
            modal = $(this),
            container = _settings.modalId + ' .modal-body',
            options = {
                'push': false,
                'replace': false
            };

        modal.find('.modal-title').text(button.data('title'));
        modal.find('.modal-body').text(_settings.i18n.loadingText);

        $(document).off('click.pjax', container + ' a');
        $(document).off('submit', container + ' form[data-pjax]');

        $(document).pjax(container + ' a', container, options);
        $(document).on('submit', container + ' form[data-pjax]', function(event) {
            $.pjax.submit(event, container, options);
        });
        $(document).on('pjax:success', _settings.modalId, function(event) {
            var saveButton = $(_settings.saveButtonId),
                selectionFields = $('#' + button.data('relation') + 'Pjax').data('selectionFields'),
                added = JSON.parse($(selectionFields.add).val()),
                removed = JSON.parse($(selectionFields.remove).val()),
                grid = $(_settings.modalId + ' .grid-view');

            saveButton.data('relation', button.data('relation'));

            grid.find("input[name='selection[]']").each(function() {
                var key = $(this).parent().closest('tr').data('key');
                if ($.inArray(key, added) !== -1) {
                    $(this).prop('disabled', true).prop('checked', true);
                } else if ($.inArray(key, removed) !== -1) {
                    $(this).prop('disabled', false).prop('checked', false);
                }
            });
            //$(document).off('pjax:success', settings.modalId);
            grid.yiiGridView({
                'filterUrl': button.data('pjax-url'),
                'filterSelector': '#relationGrid-quickSearch'
            });
            grid.yiiGridView('setSelectionColumn', {
                'name': 'selection[]',
                'multiple': true,
                'checkAll': 'selection_all'
            });
        });
        $.pjax.reload(container, {
            'url': button.data('pjax-url'),
            'push': false,
            'replace': false
        });
    };

    netis.saveRelation = function(event) {
        var saveButton = $(_settings.saveButtonId),
            container = $('#' + saveButton.data('relation') + 'Pjax'),
            grid = $(_settings.modalId + ' .grid-view'),
            add = JSON.parse($(container.data('selectionFields').add).val()),
            remove = JSON.parse($(container.data('selectionFields').remove).val());

        grid.find("input[name='selection[]']:checked").not(':disabled').each(function() {
            var key = $(this).parent().closest('tr').data('key'),
                idx = $.inArray(key, remove);
            if (idx !== -1) {
                remove.splice(idx, 1);
            } else {
                add.push(key);
            }
        });


        $(container.data('selectionFields').add).val(JSON.stringify(add));
        $(container.data('selectionFields').remove).val(JSON.stringify(remove));
        $.pjax.reload(container);

        $(_settings.modalId).modal('hide');
    };

    netis.removeRelation = function(container, key) {
        var add = JSON.parse($(container.data('selectionFields').add).val()),
            remove = JSON.parse($(container.data('selectionFields').remove).val()),
            idx = $.inArray(key, add);
        if (idx !== -1) {
            add.splice(idx, 1);
        } else {
            remove.push(key);
        }

        $(container.data('selectionFields').add).val(JSON.stringify(add));
        $(container.data('selectionFields').remove).val(JSON.stringify(remove));
        $.pjax.reload(container);
    };
}(window.netis = window.netis || {}, jQuery));
