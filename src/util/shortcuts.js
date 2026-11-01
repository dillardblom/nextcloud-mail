/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { isAppleDevice } from './printMessage.ts'

/**
 * The configurable shortcuts, by action. A key is a vue-shortkey key name, optionally after
 * "mod+": Cmd on macOS, Ctrl everywhere else (so "mod+r" is the reply shortcut known from
 * Outlook and Thunderbird on both).
 */
export const DEFAULT_SHORTCUTS = {
	del: 'del',
	arch: 'a',
	flag: 's',
	junk: 'j',
	unseen: 'u',
	refresh: 'r',
	reply: 'mod+r',
	next: 'arrowright',
	prev: 'arrowleft',
}

/** Letters the browser keeps for itself with Ctrl/Cmd (close, new tab, new window, quit) */
const RESERVED_WITH_MOD = ['n', 'q', 't', 'w']

/** Labels of the actions, in the order they are shown in the settings */
export function shortcutLabels() {
	return {
		next: t('mail', 'Older message'),
		prev: t('mail', 'Newer message'),
		reply: t('mail', 'Reply'),
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
 * A key as vue-shortkey wants it: a list of key names, with "mod" as the platform's key
 *
 * @param {string} key e.g. "r" or "mod+r"
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {string[]}
 */
export function shortkeyOf(key, appleDevice = isAppleDevice()) {
	return key.split('+').map((part) => part === 'mod' ? (appleDevice ? 'meta' : 'ctrl') : part)
}

/**
 * The key name of a keydown event without its modifiers, or null for keys that cannot be a
 * shortcut (modifiers, Escape, Tab, Enter, Space). A letter or digit is the character typed, as
 * vue-shortkey reads it; on a layout without Latin letters, the physical key.
 *
 * @param {KeyboardEvent} event the key press
 * @return {string|null}
 */
function baseKey(event) {
	const named = { Delete: 'del', Backspace: 'backspace', ArrowLeft: 'arrowleft', ArrowRight: 'arrowright', ArrowUp: 'arrowup', ArrowDown: 'arrowdown', Home: 'home', End: 'end', PageUp: 'pageup', PageDown: 'pagedown' }
	if (event.key in named) {
		return named[event.key]
	}
	if (/^[a-z0-9]$/i.test(event.key)) {
		return event.key.toLowerCase()
	}
	const code = /^(?:Key([A-Z])|Digit([0-9]))$/.exec(event.code ?? '')
	return code ? (code[1] ?? code[2]).toLowerCase() : null
}

/**
 * The shortcut of a keydown event: a key on its own, or a letter or digit with Ctrl (Cmd on
 * macOS) as "mod+<key>". Null for what cannot be a shortcut: Shift or Alt held, both Ctrl and
 * Cmd, Ctrl on macOS, a key the browser keeps for itself, or a key that is not a shortcut key.
 *
 * @param {KeyboardEvent} event the key press
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {string|null}
 */
export function shortcutKeyFromEvent(event, appleDevice = isAppleDevice()) {
	if (event.altKey || event.shiftKey) {
		return null
	}
	const key = baseKey(event)
	if (key === null) {
		return null
	}
	const mod = appleDevice ? event.metaKey : event.ctrlKey
	const other = appleDevice ? event.ctrlKey : event.metaKey
	if (other) {
		return null
	}
	if (!mod) {
		return key
	}
	if (!/^[a-z0-9]$/.test(key) || RESERVED_WITH_MOD.includes(key)) {
		return null
	}
	return 'mod+' + key
}

/**
 * Whether a keydown event is the given shortcut
 *
 * @param {KeyboardEvent} event the key press
 * @param {string} key e.g. "r" or "mod+r"
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {boolean}
 */
export function matchesShortcut(event, key, appleDevice = isAppleDevice()) {
	return shortcutKeyFromEvent(event, appleDevice) === key
}

/**
 * How a key is shown, matching NcHotkey
 *
 * @param {string} key e.g. "del" or "mod+r"
 * @param {boolean} appleDevice whether the platform is macOS
 * @return {string}
 */
export function shortcutKeyLabel(key, appleDevice = isAppleDevice()) {
	const named = { del: 'Delete', backspace: 'Backspace', arrowleft: 'ArrowLeft', arrowright: 'ArrowRight', arrowup: 'ArrowUp', arrowdown: 'ArrowDown', home: 'Home', end: 'End', pageup: 'PageUp', pagedown: 'PageDown' }
	return key.split('+')
		.map((part) => part === 'mod' ? (appleDevice ? 'Cmd' : 'Ctrl') : (named[part] ?? part.toUpperCase()))
		.join('+')
}
