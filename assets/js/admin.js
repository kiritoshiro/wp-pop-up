/**
 * Settings → Pop-up: media-library picker and the page list toggle.
 */
(function ($) {
	'use strict';

	$(function () {
		var $id = $('#wppu-image-id');
		var $url = $('#wppu-image-url');
		var $preview = $('#wppu-image-preview');
		var frame;

		function showPreview(src) {
			$preview.empty();
			if (src) {
				$('<img>', { src: src, alt: '' }).appendTo($preview);
			}
		}

		$('#wppu-image-select').on('click', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({
					title: wppuAdmin.title,
					button: { text: wppuAdmin.button },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var image = frame.state().get('selection').first().toJSON();
					$id.val(image.id);
					$url.val(image.url);
					showPreview(image.url);
					if (!$('#wppu-image-alt').val() && image.alt) {
						$('#wppu-image-alt').val(image.alt);
					}
				});
			}
			frame.open();
		});

		$('#wppu-image-clear').on('click', function (e) {
			e.preventDefault();
			$id.val('0');
			$url.val('');
			showPreview('');
		});

		// A typed URL replaces the media-library choice.
		$url.on('change', function () {
			$id.val('0');
			showPreview(/^https?:\/\//i.test($url.val()) ? $url.val() : '');
		});

		var $display = $('input[name$="[display]"]');
		function togglePages() {
			$('#wppu-pages').toggle($display.filter(':checked').val() === 'pages');
		}
		$display.on('change', togglePages);
		togglePages();
	});
})(jQuery);
