/**
 * Pop-up: shows the configured image in a modal dialog.
 *
 * Settings come from window.wppuConfig (see includes/Frontend.php). The
 * dates, device and "already seen" checks run here, in the browser, so a
 * cached page still follows them.
 */
(function () {
	'use strict';

	var config = window.wppuConfig;
	if (!config || !config.image) {
		return;
	}

	var KEY = 'wppu_seen';
	var DAY = 86400000;

	function store(kind) {
		try {
			var s = window[kind];
			s.setItem(KEY + '_test', '1');
			s.removeItem(KEY + '_test');
			return s;
		} catch (e) {
			return null; // private mode or blocked storage: show every time
		}
	}

	function alreadySeen() {
		var s;
		if (config.frequency === 'session') {
			s = store('sessionStorage');
			return !!s && s.getItem(KEY) === String(config.revision);
		}
		if (config.frequency === 'days') {
			s = store('localStorage');
			if (!s) {
				return false;
			}
			try {
				var seen = JSON.parse(s.getItem(KEY) || 'null');
				return !!seen && seen.r === config.revision && Date.now() - seen.t < config.days * DAY;
			} catch (e) {
				return false;
			}
		}
		return false;
	}

	function markSeen() {
		var s;
		if (config.frequency === 'session') {
			s = store('sessionStorage');
			if (s) {
				s.setItem(KEY, String(config.revision));
			}
		} else if (config.frequency === 'days') {
			s = store('localStorage');
			if (s) {
				s.setItem(KEY, JSON.stringify({ r: config.revision, t: Date.now() }));
			}
		}
	}

	function deviceMatches() {
		if (config.devices === 'all' || !window.matchMedia) {
			return true;
		}
		var mobile = window.matchMedia('(max-width: 767px)').matches;
		return config.devices === 'mobile' ? mobile : !mobile;
	}

	function inWindow() {
		var now = Date.now();
		return !(config.start && now < config.start) && !(config.end && now > config.end);
	}

	function el(tag, className, attrs) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		Object.keys(attrs || {}).forEach(function (name) {
			node.setAttribute(name, attrs[name]);
		});
		return node;
	}

	function open(img) {
		var previousFocus = document.activeElement;
		var overlay = el('div', 'wppu-overlay');
		var dialog = el('div', 'wppu-dialog', { role: 'dialog', 'aria-modal': 'true', 'aria-label': config.alt || '' });
		var close = el('button', 'wppu-close', { type: 'button', 'aria-label': config.closeLabel });
		close.appendChild(document.createTextNode('×'));

		overlay.style.setProperty('--wppu-backdrop', 'rgba(0, 0, 0, ' + config.opacity + ')');
		if (config.maxWidth > 0) {
			dialog.style.maxWidth = 'min(90vw, ' + config.maxWidth + 'px)';
		}

		img.className = 'wppu-image';
		img.alt = config.alt || '';

		dialog.appendChild(close);
		if (config.link) {
			var link = el('a', 'wppu-link', { href: config.link });
			if (config.newTab) {
				link.target = '_blank';
				link.rel = 'noopener';
				img.alt = (config.alt ? config.alt + ' ' : '') + config.newTabLabel;
			}
			link.appendChild(img);
			dialog.appendChild(link);
		} else {
			dialog.appendChild(img);
		}
		overlay.appendChild(dialog);

		function focusables() {
			return Array.prototype.slice.call(dialog.querySelectorAll('a[href], button'));
		}

		function onKey(e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				hide();
			} else if (e.key === 'Tab') {
				// Keep keyboard focus inside the dialog while it is open.
				var items = focusables();
				var first = items[0];
				var last = items[items.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		}

		function hide() {
			document.removeEventListener('keydown', onKey, true);
			document.documentElement.classList.remove('wppu-open');
			overlay.classList.add('wppu-closing');
			window.setTimeout(function () {
				overlay.remove();
			}, 250);
			if (previousFocus && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			}
		}

		close.addEventListener('click', hide);
		overlay.addEventListener('click', function (e) {
			if (config.closeOnOverlay && e.target === overlay) {
				hide();
			}
		});
		document.addEventListener('keydown', onKey, true);

		document.body.appendChild(overlay);
		document.documentElement.classList.add('wppu-open');
		markSeen();
		close.focus();
	}

	function start() {
		if (!inWindow() || !deviceMatches() || alreadySeen()) {
			return;
		}
		// Load the image first, so the dialog never opens empty; a broken
		// image means no pop-up at all.
		var img = new Image();
		img.decoding = 'async';
		img.onload = function () {
			window.setTimeout(function () {
				open(img);
			}, Math.max(0, config.delay) * 1000);
		};
		img.src = config.image;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
