/**
 * CartBay Capture — Block Checkout
 *
 * Listens for the marketing consent field change in the block checkout
 * and sends a capture request when email + consent are present.
 */

import apiFetch from '@wordpress/api-fetch';

(function () {
	'use strict';

	var defaultStateApplied = false;
	var lastCapturedEmail = '';
	var captureInFlight = false;
	var prefilledCaptureSent = false;

	/**
	 * Get the cart data from the localized script.
	 */
	function getCartData() {
		if (typeof cartbayCapture !== 'undefined' && cartbayCapture.cart) {
			return cartbayCapture.cart;
		}
		return { hash: '', total: '0', currency: '', items: [] };
	}

	/**
	 * Convert the Store API cart response into a restore-safe payload.
	 *
	 * @param {Object} cart Store API cart response.
	 * @return {Object} CartBay cart payload.
	 */
	function normalizeStoreCart(cart) {
		if (!cart || !Array.isArray(cart.items)) {
			return getCartData();
		}
		var total = '0';
		if (cart.totals && cart.totals.total_price) {
			var minorUnit = parseInt(cart.totals.currency_minor_unit, 10);
			var divisor = Number.isFinite(minorUnit) ? Math.pow(10, minorUnit) : 100;
			total = parseFloat(cart.totals.total_price) / divisor;
		}

		return {
			hash: cart.items.map(function (item) {
				return item.key || '';
			}).join('|'),
			total: total,
			currency: cart.totals && cart.totals.currency_code ? cart.totals.currency_code : '',
			cart_item_count: cart.items_count || cart.items.length,
			items: cart.items.map(function (item) {
				var variation = {};
				if (Array.isArray(item.variation)) {
					item.variation.forEach(function (attribute) {
						if (attribute && attribute.attribute && attribute.value) {
							variation['attribute_' + attribute.attribute] = attribute.value;
						}
					});
				}

				return {
					product_id: item.id || 0,
					variation_id: 0,
					quantity: item.quantity || 1,
					variation: variation,
					cart_item_data: {},
					product_name: item.name || '',
				};
			}),
		};
	}

	/**
	 * Get the current Store API cart when available.
	 *
	 * @return {Promise<Object>} CartBay cart payload.
	 */
	function getCurrentCartData() {
		return apiFetch({ path: '/wc/store/v1/cart' })
			.then(normalizeStoreCart)
			.catch(function () {
				return getCartData();
			});
	}

	/**
	 * Get the capture endpoint.
	 */
	function getEndpoint() {
		if (typeof cartbayCapture !== 'undefined' && cartbayCapture.endpoint) {
			return cartbayCapture.endpoint;
		}
		return '/wp-json/cartbay/v1/capture';
	}

	/**
	 * Check whether the checkout belongs to an existing restored CartBay session.
	 */
	function isRestoredSession() {
		return !! (
			typeof cartbayCapture !== 'undefined' &&
			cartbayCapture.restored_session
		);
	}

	/**
	 * Validate an email enough to avoid sending obviously incomplete autofill values.
	 *
	 * @param {string} email Customer email.
	 * @return {boolean} Whether the value looks like an email address.
	 */
	function isValidEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

	/**
	 * Get the REST nonce localized alongside the capture endpoint.
	 */
	function getNonce() {
		if (typeof cartbayCapture !== 'undefined' && cartbayCapture.nonce) {
			return cartbayCapture.nonce;
		}
		return '';
	}

	/**
	 * Get stored session ID from sessionStorage.
	 */
	function getStoredSessionId() {
		try {
			return parseInt(sessionStorage.getItem('cartbay_session_id'), 10) || 0;
		} catch (e) {
			return 0;
		}
	}

	/**
	 * Store session ID in sessionStorage.
	 */
	function storeSessionId(id) {
		try {
			sessionStorage.setItem('cartbay_session_id', id);
		} catch (e) {
			// Ignore storage errors.
		}
	}

	/**
	 * Clear stored session ID from sessionStorage.
	 */
	function clearStoredSessionId() {
		try {
			sessionStorage.removeItem('cartbay_session_id');
		} catch (e) {
			// Ignore storage errors.
		}
	}

	/**
	 * Get configured consent checkbox default state.
	 */
	function getConsentDefaultState() {
		if (
			typeof cartbayCapture !== 'undefined' &&
			cartbayCapture.settings &&
			cartbayCapture.settings.consent_default_state
		) {
			return cartbayCapture.settings.consent_default_state;
		}
		return 'checked';
	}

	/**
	 * Send a capture request.
	 *
	 * @param {string}  email   Customer email.
	 * @param {boolean} consent Whether consent was given.
	 */
	function sendCapture(email, consent) {
		if (!email || !consent || !isValidEmail(email)) {
			return;
		}

		if (email === lastCapturedEmail) {
			return;
		}

		if (captureInFlight) {
			return;
		}

		captureInFlight = true;

		getCurrentCartData().then(function (cartData) {
			return apiFetch({
				url: getEndpoint(),
				method: 'POST',
				headers: { 'X-WP-Nonce': getNonce() },
				data: {
					email: email,
					consent: true,
					source: 'block',
					cart: cartData,
					session_id: getStoredSessionId(),
				},
			});
		}).then(function (json) {
			if (json && json.success && json.session_id) {
				storeSessionId(json.session_id);
				lastCapturedEmail = email;
			}
		}).catch(function () {
			// Silent fail — don't block checkout.
		}).finally(function () {
			captureInFlight = false;
		});
	}

	/**
	 * Delete the current captured session after consent is withdrawn.
	 *
	 * @param {string} email Customer email.
	 */
	function deleteCapture(email) {
		var sessionId = getStoredSessionId();
		// Prefer the email the session was captured with so consent withdrawal
		// still proves ownership even if the email field was cleared first.
		var captureEmail = email || lastCapturedEmail;

		clearStoredSessionId();
		lastCapturedEmail = '';

		// Deletion requires the captured email as proof of ownership.
		if (!captureEmail) {
			return;
		}

		apiFetch({
			url: getEndpoint(),
			method: 'POST',
			headers: { 'X-WP-Nonce': getNonce() },
			data: {
				email: captureEmail,
				consent: false,
				source: 'block',
				cart: getCartData(),
				session_id: sessionId,
			},
		}).catch(function () {
			// Silent fail — don't block checkout.
		});
	}

	/**
	 * Find the consent checkbox in the block checkout.
	 * WooCommerce Block Checkout renders the field with id="contact-cartbay-marketing-consent"
	 * and name="contact_cartbay/marketing-consent".
	 */
	function findConsentCheckbox() {
		return document.getElementById('contact-cartbay-marketing-consent');
	}

	/**
	 * Find the email input in the block checkout.
	 */
	function findEmailInput() {
		return document.querySelector(
			'.wc-block-checkout__contact-fields input[type="email"], .wc-block-components-text-input input[type="email"]'
		);
	}

	/**
	 * Apply the configured default to the rendered Block Checkout checkbox.
	 */
	function applyConsentDefaultState() {
		var consentBox = findConsentCheckbox();

		if (!consentBox || defaultStateApplied) {
			return;
		}

		consentBox.checked = getConsentDefaultState() !== 'unchecked';
		defaultStateApplied = true;
	}

	/**
	 * Check if a change event target is the CartBay consent checkbox.
	 */
	function isConsentCheckbox(target) {
		if (!target || target.type !== 'checkbox') {
			return false;
		}
		var cb = findConsentCheckbox();
		return cb && target === cb;
	}

	/**
	 * Handle consent changes — capture when checked, delete when unchecked.
	 */
	function onConsentChange() {
		var emailInput = findEmailInput();
		var consentBox = findConsentCheckbox();
		var email = emailInput && emailInput.value ? emailInput.value.trim() : '';

		if (!consentBox) {
			return;
		}

		if (consentBox.checked) {
			sendCapture(email, true);
			return;
		}

		deleteCapture(email);
	}

	/**
	 * Capture after the shopper leaves the email field.
	 */
	function captureAfterEmailBlur(target) {
		var consentBox = findConsentCheckbox();

		if (!target || target.type !== 'email' || !consentBox || !consentBox.checked) {
			return;
		}

		sendCapture(target.value ? target.value.trim() : '', true);
	}

	/**
	 * Capture a prefilled checkout email without requiring field interaction.
	 */
	function capturePrefilledEmail() {
		if (prefilledCaptureSent || isRestoredSession()) {
			return;
		}

		var consentBox = findConsentCheckbox();
		var emailInput = findEmailInput();
		var email = emailInput && emailInput.value ? emailInput.value.trim() : '';

		if (!consentBox || !consentBox.checked || !isValidEmail(email)) {
			return;
		}

		prefilledCaptureSent = true;
		sendCapture(email, true);
	}

	/**
	 * Observe the block checkout for consent field and email changes.
	 */
	function observeCheckout() {
		applyConsentDefaultState();
		capturePrefilledEmail();

		document.addEventListener('change', function (e) {
			var target = e.target;

			// Consent checkbox changed.
			if (isConsentCheckbox(target)) {
				onConsentChange();
			}
		});

		document.addEventListener('focusout', function (e) {
			var target = e.target;

			if (target && target.type === 'email') {
				captureAfterEmailBlur(target);
			}
		});

		var observer = new MutationObserver(function () {
			applyConsentDefaultState();
			capturePrefilledEmail();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		[0, 300, 1000].forEach(function (delay) {
			window.setTimeout(capturePrefilledEmail, delay);
		});
	}

	// Initialise.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', observeCheckout);
	} else {
		observeCheckout();
	}
})();
