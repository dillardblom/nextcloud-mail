/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isAppleDevice } from './printMessage.ts'
import { DEFAULT_SHORTCUTS, effectiveShortcuts, matchesShortcut } from './shortcuts.js'

/**
 * The reply key in effect: the user's own, or the default
 *
 * @param {object} mainStore the main store, for the "shortcuts" preference
 * @return {string}
 */
export function replyKey(mainStore) {
	return effectiveShortcuts(mainStore.getPreference('shortcuts')).reply
}

/**
 * Whether a keydown event is the "reply" shortcut: the user's own key, or by default the one
 * known from Outlook and Thunderbird, Cmd+R on macOS and Ctrl+R everywhere else. Shift and Alt
 * must not be held, so Ctrl+Shift+R (the browser's hard reload) keeps working.
 *
 * @param {KeyboardEvent} event the keydown event
 * @param {string} key the reply key, e.g. "mod+r" or "r"
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {boolean}
 */
export function isReplyShortcut(event, key = DEFAULT_SHORTCUTS.reply, appleDevice = isAppleDevice()) {
	return matchesShortcut(event, key, appleDevice)
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
