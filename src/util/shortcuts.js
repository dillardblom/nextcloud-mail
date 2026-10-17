/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

/** The single-key shortcuts of the message list, by action; keys as vue-shortkey names them */
export const DEFAULT_SHORTCUTS = {
	del: 'del',
	arch: 'a',
	flag: 's',
	junk: 'j',
	unseen: 'u',
	refresh: 'r',
	next: 'arrowright',
	prev: 'arrowleft',
}

/** Labels of the actions, in the order they are shown in the settings */
export function shortcutLabels() {
	return {
		next: t('mail', 'Older message'),
		prev: t('mail', 'Newer message'),
		flag: t('mail', 'Toggle star'),
		unseen: t('mail', 'Toggle unread'),
		junk: t('mail', 'Mark as spam'),
		arch: t('mail', 'Archive'),
		del: t('mail', 'Delete'),
		refresh: t('mail', 'Refresh'),
	}
}

/**
 * The shortcuts in effect: the defaults with the user's own keys (the "shortcuts" preference,
 * a JSON object of action => key) on top. Unknown actions and invalid values are ignored.
 *
 * @param {string|undefined} preference the stored preference
 * @return {Record<string, string>} action => key
 */
export function effectiveShortcuts(preference) {
	let own = {}
	try {
		own = JSON.parse(preference || '{}') ?? {}
	} catch {
		own = {}
	}
	const result = { ...DEFAULT_SHORTCUTS }
	for (const [action, key] of Object.entries(own)) {
		if (action in DEFAULT_SHORTCUTS && typeof key === 'string' && key !== '') {
			result[action] = key
		}
	}
	return result
}

/**
 * The vue-shortkey name of the key in a keydown event, or null for keys that cannot be a
 * single-key shortcut (modifiers, Escape, Tab, Enter, Space)
 *
 * @param {KeyboardEvent} event the key press
 * @return {string|null}
 */
export function shortcutKeyFromEvent(event) {
	if (event.ctrlKey || event.metaKey || event.altKey) {
		return null
	}
	const named = { Delete: 'del', Backspace: 'backspace', ArrowLeft: 'arrowleft', ArrowRight: 'arrowright', ArrowUp: 'arrowup', ArrowDown: 'arrowdown', Home: 'home', End: 'end', PageUp: 'pageup', PageDown: 'pagedown' }
	if (event.key in named) {
		return named[event.key]
	}
	if (/^[a-z0-9]$/i.test(event.key)) {
		return event.key.toLowerCase()
	}
	return null
}

/**
 * How a key is shown, matching NcHotkey
 *
 * @param {string} key vue-shortkey key name
 * @return {string}
 */
export function shortcutKeyLabel(key) {
	const named = { del: 'Delete', backspace: 'Backspace', arrowleft: 'ArrowLeft', arrowright: 'ArrowRight', arrowup: 'ArrowUp', arrowdown: 'ArrowDown', home: 'Home', end: 'End', pageup: 'PageUp', pagedown: 'PageDown' }
	return named[key] ?? key.toUpperCase()
}
