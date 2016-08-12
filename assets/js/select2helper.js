(function (s2helper, $, undefined) {
    "use strict";
    s2helper.formatResult = function (result, container, query, escapeMarkup, depth) {
        if (typeof depth == 'undefined') {
            depth = 0;
        }
        var markup = [];
        window.Select2.util.markMatch(result._label, query.term, markup, escapeMarkup);
        return markup.join("");
    };

    s2helper.formatSelection = function (item) {
        return item._label;
    };

    // generates query params
    s2helper.data = function (term, page) {
        return { search: term, page: page };
    };

    // builds query results from ajax response
    s2helper.results = function (data, page) {
        return { results: data.items, more: page < data._meta.pageCount };
    };

    s2helper.getParams = function (element) {
        var primaryKey = element.data('relation-pk');
        if (typeof primaryKey === 'undefined' || primaryKey === null) {
            primaryKey = 'id';
        }

        var params = {search: {}};
        params.search[primaryKey] = element.val();
        return params;
    };

    s2helper.initSingle = function (element, callback) {
        $.getJSON(element.data('select2').opts.ajax.url, s2helper.getParams(element), function (data) {
            if (typeof data.items[0] != 'undefined') {
                callback(data.items[0]);
            }
        });
    };

    s2helper.initMulti = function (element, callback) {
        $.getJSON(element.data('select2').opts.ajax.url, s2helper.getParams(element), function (data) {callback(data.items);});
    };
}( window.s2helper = window.s2helper || {}, jQuery ));