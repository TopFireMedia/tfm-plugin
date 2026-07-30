/**
 * TFM Tracking Consent — admin settings screen.
 */
(function ($) {
	'use strict';

	$(function () {
		$('.tfm-tc-color').wpColorPicker();

		var $tabs = $('.tfm-tc-tabs .nav-tab');
		var $panels = $('.tfm-tc-tab');

		function activate(hash) {
			var $target = $(hash);
			if (!$target.length) { return; }
			$tabs.removeClass('nav-tab-active').filter('[href="' + hash + '"]').addClass('nav-tab-active');
			$panels.removeClass('is-active');
			$target.addClass('is-active');
		}

		$tabs.on('click', function (e) {
			e.preventDefault();
			var hash = $(this).attr('href');
			activate(hash);
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', hash);
			}
		});

		if (window.location.hash && window.location.hash.indexOf('#tfm-tab-') === 0) {
			activate(window.location.hash);
		}
	});
})(jQuery);
