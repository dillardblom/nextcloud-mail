/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isAppleDevice } from './printMessage.ts'

/**
 * Whether a keydown event is the "reply" shortcut known from Outlook and
 * Thunderbird: Cmd+R on macOS, Ctrl+R everywhere else. Shift and Alt must not be
 * held, so Ctrl+Shift+R (the browser's hard reload) keeps working. The physical
 * key is matched, like the print shortcut, so the keyboard layout does not matter.
 *
 * @param {KeyboardEvent} event the keydown event
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {boolean}
 */
export function isReplyShortcut(event, appleDevice = isAppleDevice()) {
	if (event.code !== 'KeyR' || event.shiftKey || event.altKey) {
		return false
	}
	return appleDevice
		? event.metaKey && !event.ctrlKey
		: event.ctrlKey && !event.metaKey
}

/**
 * Whether a keyboard event comes from a place where the user is typing, where
 * shortcuts must keep their native meaning.
 *
 * @param {EventTarget|null} target the event target
 * @return {boolean}
 */
export function isEditableTarget(target) {
	if (!(target instanceof HTMLElement)) {
		return false
	}
	return target.isContentEditable
		|| target.closest('[contenteditable]:not([contenteditable="false"])') !== null
		|| ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
}

/**
 * Whether a dialog or modal is open, in which case a reply must not open behind it.
 *
 * @param {Document} doc the document to look in
 * @return {boolean}
 */
export function isDialogOpen(doc = document) {
	return doc.querySelector('.modal-mask, dialog[open], [role="dialog"][aria-modal="true"]') !== null
}
