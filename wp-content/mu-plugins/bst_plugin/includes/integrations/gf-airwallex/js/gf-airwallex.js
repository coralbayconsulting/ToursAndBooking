/**
 * Gravity Forms + Airwallex Drop-in (ES module).
 *
 * @package BST_Plugin
 */

const SDK_URL = 'https://cdn.jsdelivr.net/npm/@airwallex/components-sdk@2.4.0/+esm';

let sdkModule = null;
let initPromise = null;

async function loadSdk() {
	if ( ! sdkModule ) {
		sdkModule = await import( SDK_URL );
	}
	return sdkModule;
}

async function initPayments( cfg ) {
	if ( ! initPromise ) {
		const { init } = await loadSdk();
		initPromise = init( {
			env: cfg.env,
			enabledElements: [ 'payments' ],
			locale: 'en',
		} );
	}
	return initPromise;
}

function getFormElement( formId ) {
	return document.getElementById( 'gform_' + formId );
}

function serializeFormToObject( formEl ) {
	const data = {};
	if ( ! formEl || ! formEl.elements ) {
		return data;
	}
	const formData = new FormData( formEl );
	formData.forEach( ( value, key ) => {
		data[ key ] = value;
	} );
	return data;
}

async function createIntent( cfg, formEl ) {
	const body = new URLSearchParams();
	body.append( 'action', 'bst_gf_airwallex_create_intent' );
	body.append( 'nonce', cfg.nonce );
	body.append( 'form_id', String( cfg.formId ) );
	body.append( 'posted', JSON.stringify( serializeFormToObject( formEl ) ) );
	body.append( 'return_url', cfg.returnUrl || window.location.href );

	const res = await fetch( cfg.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body,
	} );
	const json = await res.json();
	if ( ! json.success ) {
		const d = json.data;
		const msg =
			typeof d === 'string'
				? d
				: d && typeof d === 'object' && d.message
					? d.message
					: 'Intent create failed';
		throw new Error( msg );
	}
	return json.data;
}

/**
 * Human-readable message from Airwallex Drop-in `error` / SDK failures.
 *
 * @param {*} detail Event detail or thrown value.
 * @return {string}
 */
function formatAirwallexError( detail ) {
	if ( detail == null ) {
		return '';
	}
	if ( typeof detail === 'string' ) {
		return detail;
	}
	if ( typeof detail === 'object' ) {
		if ( typeof detail.message === 'string' && detail.message ) {
			return detail.message;
		}
		if ( detail.error && typeof detail.error.message === 'string' ) {
			return detail.error.message;
		}
		if ( typeof detail.code === 'string' && detail.code ) {
			return detail.code;
		}
		try {
			return JSON.stringify( detail );
		} catch ( e ) {
			return 'Payment error';
		}
	}
	return String( detail );
}

/**
 * Base Drop-in options merged with `cfg.dropIn` from PHP (`bst_gf_airwallex_dropin_options` filter).
 *
 * @param {object} intent Intent payload from AJAX.
 * @param {object} cfg Parsed `data-bst-airwallex-config`.
 * @return {object}
 */
function buildDropInOptions( intent, cfg ) {
	const base = {
		intent_id: intent.intent_id,
		client_secret: intent.client_secret,
		currency: intent.currency || cfg.currency,
	};
	const extra =
		cfg.dropIn && typeof cfg.dropIn === 'object' && ! Array.isArray( cfg.dropIn )
			? cfg.dropIn
			: {};
	return { ...base, ...extra };
}

async function mountDropIn( wrap, cfg ) {
	const mountEl = wrap.querySelector( '.bst-airwallex-dropin' );
	const hidden = wrap.querySelector( '.bst-airwallex-intent-id' );
	const loading = wrap.querySelector( '.bst-airwallex-loading' );
	const formEl = getFormElement( cfg.formId );
	if ( ! mountEl || ! hidden || ! formEl ) {
		return;
	}

	const status = wrap.querySelector( '.bst-airwallex-status' );
	const setStatus = ( msg, isError ) => {
		if ( status ) {
			status.textContent = msg || '';
			status.style.color = isError ? '#b32d2e' : '';
		}
	};

	try {
		if ( loading ) {
			loading.style.display = '';
		}
		setStatus( '' );

		const intent = await createIntent( cfg, formEl );
		const { payments } = await initPayments( cfg );
		const { createElement } = await loadSdk();

		mountEl.innerHTML = '';
		const dropInOpts = buildDropInOptions( intent, cfg );
		const element = payments
			? payments.createElement( 'dropIn', dropInOpts )
			: createElement( 'dropIn', dropInOpts );

		element.mount( mountEl.id );
		if ( loading ) {
			loading.style.display = 'none';
		}

		element.on( 'ready', () => {
			setStatus( '' );
		} );

		element.on( 'success', () => {
			hidden.value = intent.intent_id;
			setStatus( '' );
		} );

		element.on( 'error', ( event ) => {
			const detail = event && event.detail ? event.detail : event;
			const msg = formatAirwallexError( detail ) || 'Payment error';
			setStatus( msg, true );
		} );
	} catch ( e ) {
		if ( loading ) {
			loading.style.display = 'none';
		}
		setStatus( e.message || 'Unable to start payment.', true );
	}
}

function parseCfg( wrap ) {
	const raw = wrap.getAttribute( 'data-bst-airwallex-config' );
	if ( ! raw ) {
		return null;
	}
	try {
		return JSON.parse( raw );
	} catch ( e ) {
		return null;
	}
}

function boot() {
	document.querySelectorAll( '.bst-airwallex-wrap' ).forEach( ( wrap ) => {
		const cfg = parseCfg( wrap );
		if ( ! cfg ) {
			return;
		}
		if ( ! wrap.querySelector( '.bst-airwallex-status' ) ) {
			const p = document.createElement( 'p' );
			p.className = 'bst-airwallex-status';
			p.setAttribute( 'role', 'status' );
			wrap.insertBefore( p, wrap.firstChild );
		}
		const formEl = getFormElement( cfg.formId );
		if ( ! formEl ) {
			return;
		}

		const run = () => {
			mountDropIn( wrap, cfg );
		};

		// Initial mount after Gravity Forms scripts calculated totals.
		setTimeout( run, 300 );

		if ( window.jQuery ) {
			window.jQuery( document ).on(
				'gform_post_calculation.gfAirwallex' + cfg.formId,
				function ( event, formId ) {
					if ( parseInt( formId, 10 ) === cfg.formId ) {
						setTimeout( run, 200 );
					}
				}
			);
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
