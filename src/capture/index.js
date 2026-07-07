/**
 * CartBay Capture — Classic Checkout
 *
 * Renders a consent checkbox below the billing email field and
 * sends a capture request when a valid email + consent is present.
 */

(function () {
	'use strict';

	if (
		typeof cartbayCapture === 'undefined' ||
		! cartbayCapture.endpoint
	) {
		return;
	}

	var consentText =
		cartbayCapture.settings &&
		cartbayCapture.settings.consent_text
			? cartbayCapture.settings.consent_text
			: 'Save my email to recover my cart if I leave.';

	var consentDefaultState =
		cartbayCapture.settings &&
		cartbayCapture.settings.consent_default_state
			? cartbayCapture.settings.consent_default_state
			: 'checked';

	var lastCapturedEmail = '';
	var captureInFlight = false;
	var prefilledCaptureSent = false;

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
	 * Get the REST nonce localized alongside the capture endpoint.
	 */
	function getNonce() {
		return cartbayCapture.nonce ? cartbayCapture.nonce : '';
	}

	/**
	 * Get the current billing email value.
	 */
	function getEmail() {
		var emailField = document.getElementById('billing_email');

		return emailField && emailField.value ? emailField.value.trim() : '';
	}

	/**
	 * Create and insert the consent checkbox.
	 */
	function createConsentCheckbox() {
		var emailWrapper = document.getElementById('billing_email_field');
		if (!emailWrapper || document.getElementById('cartbay-consent-wrapper')) {
			return;
		}

		var wrapper = document.createElement('div');
		wrapper.id = 'cartbay-consent-wrapper';
		wrapper.className = 'form-row form-row-wide';
		wrapper.style.marginBottom = '12px';

		var label = document.createElement('label');
		label.className = 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox';

		var checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.id = 'cartbay-consent';
		checkbox.className = 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox';
		checkbox.name = 'cartbay_consent';
		checkbox.value = '1';
		checkbox.checked = consentDefaultState !== 'unchecked';

		var span = document.createElement('span');
		span.textContent = consentText;

		label.appendChild(checkbox);
		label.appendChild(document.createTextNode(' '));
		label.appendChild(span);
		wrapper.appendChild(label);

		emailWrapper.parentNode.insertBefore(wrapper, emailWrapper.nextSibling);
	}

	/**
	 * Send capture request.
	 */
	function sendCapture(email) {
		var checkbox = document.getElementById('cartbay-consent');

		if (!checkbox) {
			return;
		}

		if (!email || !checkbox.checked || !isValidEmail(email)) {
			return;
		}

		if (email === lastCapturedEmail) {
			return;
		}

		if (captureInFlight) {
			return;
		}

		captureInFlight = true;

		var data = {
			email: email,
			consent: true,
			source: 'classic',
			cart: cartbayCapture.cart || {},
			session_id: getStoredSessionId(),
		};

		fetch(cartbayCapture.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': getNonce(),
			},
			credentials: 'same-origin',
			body: JSON.stringify(data),
		}).then(function (response) {
			return response.json();
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
	 */
	function deleteCapture() {
		var sessionId = getStoredSessionId();
		// Prefer the email the session was captured with so consent withdrawal
		// still proves ownership even if the email field was cleared first.
		var email = lastCapturedEmail || getEmail();

		clearStoredSessionId();
		lastCapturedEmail = '';

		// Deletion requires the captured email as proof of ownership.
		if (!email) {
			return;
		}

		var data = {
			email: email,
			consent: false,
			source: 'classic',
			cart: cartbayCapture.cart || {},
			session_id: sessionId,
		};

		fetch(cartbayCapture.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': getNonce(),
			},
			credentials: 'same-origin',
			body: JSON.stringify(data),
		}).catch(function () {
			// Silent fail — don't block checkout.
		});
	}

	/**
	 * Capture after email blur when consent is currently checked.
	 */
	function captureAfterEmailBlur() {
		var checkbox = document.getElementById('cartbay-consent');

		if (!checkbox || !checkbox.checked) {
			return;
		}

		sendCapture(getEmail());
	}

	/**
	 * Capture or delete when consent changes.
	 */
	function onConsentChange() {
		var checkbox = document.getElementById('cartbay-consent');

		if (!checkbox) {
			return;
		}

		if (checkbox.checked) {
			sendCapture(getEmail());
			return;
		}

		deleteCapture();
	}

	/**
	 * Capture a prefilled checkout email without requiring field interaction.
	 */
	function capturePrefilledEmail() {
		if (prefilledCaptureSent || isRestoredSession()) {
			return;
		}

		var checkbox = document.getElementById('cartbay-consent');
		var email = getEmail();

		if (!checkbox || !checkbox.checked || !isValidEmail(email)) {
			return;
		}

		prefilledCaptureSent = true;
		sendCapture(email);
	}

	/**
	 * Initialise capture events.
	 */
	function init() {
		createConsentCheckbox();

		var emailField = document.getElementById('billing_email');
		if (emailField) {
			emailField.addEventListener('blur', captureAfterEmailBlur);
		}

		var checkbox = document.getElementById('cartbay-consent');
		if (checkbox) {
			checkbox.addEventListener('change', onConsentChange);
		}

		[0, 300, 1000].forEach(function (delay) {
			window.setTimeout(capturePrefilledEmail, delay);
		});
	}

	// Initialise when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
