/**
 * Yoast in-editor analysis often ignores post_content for CPTs; append BST-built plain text.
 * Data comes from wp_localize_script( 'bstYoastCptAnalysis', ... ) on post edit screens.
 */
(function () {
	var cfg = typeof window !== 'undefined' ? window.bstYoastCptAnalysis : null;
	if (!cfg || !cfg.extra || typeof cfg.extra !== 'string' || !cfg.extra.trim()) {
		return;
	}

	function appendContent(data) {
		var base = typeof data === 'string' ? data : '';
		var add = cfg.extra.trim();
		if (!base.trim()) {
			return add;
		}
		return base + '\n\n' + add;
	}

	function register() {
		if (typeof YoastSEO === 'undefined' || !YoastSEO.app) {
			return;
		}
		if (typeof YoastSEO.app.registerPlugin !== 'function' || typeof YoastSEO.app.registerModification !== 'function') {
			return;
		}
		try {
			YoastSEO.app.registerPlugin('bstCptAnalysis', { status: 'ready' });
			YoastSEO.app.registerModification('content', appendContent, 'bstCptAnalysis', 5);
		} catch (e) {
			// Yoast API surface varies by version; fail silently.
		}
	}

	if (typeof YoastSEO !== 'undefined' && YoastSEO.app) {
		register();
	} else if (typeof jQuery !== 'undefined') {
		jQuery(window).on('YoastSEO:ready', register);
	}
})();
