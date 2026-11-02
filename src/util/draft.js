/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Whether the body of the composer holds nothing but the signature the
 * composer put in: no text, image or table of the user's own.
 *
 * @param {string|undefined} bodyHtml the composer's body, as the editor writes it
 * @return {boolean}
 */
export function isBodyEmptyApartFromSignature(bodyHtml) {
	const doc = new DOMParser().parseFromString(bodyHtml ?? '', 'text/html')
	for (const signature of doc.querySelectorAll('.signature')) {
		signature.remove()
	}

	return doc.body.textContent.trim() === ''
		&& doc.body.querySelector('img, table, hr, iframe, video, audio') === null
}

/**
 * Whether a message the user is composing has nothing worth keeping as a
 * draft: no subject, recipients, attachments or send time, and a body with
 * nothing but the signature.
 *
 * @param {object} data the message data of the composer
 * @param {string|undefined} bodyHtml the composer's body, as the editor writes it
 * @return {boolean}
 */
export function isEmptyApartFromSignature(data, bodyHtml) {
	return (data.subject ?? '').trim() === ''
		&& (data.to ?? []).length === 0
		&& (data.cc ?? []).length === 0
		&& (data.bcc ?? []).length === 0
		&& (data.attachments ?? []).length === 0
		// Without a send time the composer may give NaN instead of undefined
		&& !(data.sendAt > 0)
		&& isBodyEmptyApartFromSignature(bodyHtml)
}
