/* Narrow adapter for the bundled Validform and date picker plugins on jQuery 4. */
(function ($) {
    'use strict';
    $.trim = function (value) { return value == null ? '' : String(value).trim(); };
    $.browser = { msie: false, safari: false };
}(jQuery));
