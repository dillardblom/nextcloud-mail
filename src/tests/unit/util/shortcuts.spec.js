/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { DEFAULT_SHORTCUTS, effectiveShortcuts, matchesShortcut, shortcutKeyFromEvent, shortcutKeyLabel, shortkeyOf } from '../../../util/shortcuts.js'

describe('shortcuts', () => {
	it('uses the defaults without a preference', () => {
		expect(effectiveShortcuts(undefined)).toEqual(DEFAULT_SHORTCUTS)
		expect(effectiveShortcuts('not json')).toEqual(DEFAULT_SHORTCUTS)
	})

	it('puts the user keys on top and ignores unknown actions', () => {
		const keys = effectiveShortcuts(JSON.stringify({ junk: 's', flag: 'f', nonsense: 'x', del: '' }))
		expect(keys.junk).toBe('s')
		expect(keys.flag).toBe('f')
		expect(keys.del).toBe('del')
		expect(keys).not.toHaveProperty('nonsense')
	})

	it('reads a key press', () => {
		expect(shortcutKeyFromEvent({ key: 'J' }, false)).toBe('j')
		expect(shortcutKeyFromEvent({ key: 'Delete' }, false)).toBe('del')
		expect(shortcutKeyFromEvent({ key: 'Enter' }, false)).toBeNull()
		expect(shortcutKeyFromEvent({ key: ' ' }, false)).toBeNull()
	})

	it('reads Ctrl, or Cmd on macOS, with a letter or digit', () => {
		expect(shortcutKeyFromEvent({ key: 'r', code: 'KeyR', ctrlKey: true }, false)).toBe('mod+r')
		expect(shortcutKeyFromEvent({ key: 'r', code: 'KeyR', metaKey: true }, true)).toBe('mod+r')
		expect(shortcutKeyFromEvent({ key: 'к', code: 'KeyR', ctrlKey: true }, false)).toBe('mod+r')
		expect(shortcutKeyFromEvent({ key: 'r', code: 'KeyR', metaKey: true }, false)).toBeNull()
		expect(shortcutKeyFromEvent({ key: 'r', code: 'KeyR', ctrlKey: true }, true)).toBeNull()
	})

	it('refuses Shift, Alt, keys the browser keeps and Ctrl with other keys', () => {
		expect(shortcutKeyFromEvent({ key: 'R', code: 'KeyR', ctrlKey: true, shiftKey: true }, false)).toBeNull()
		expect(shortcutKeyFromEvent({ key: 'r', code: 'KeyR', altKey: true }, false)).toBeNull()
		expect(shortcutKeyFromEvent({ key: 'w', code: 'KeyW', ctrlKey: true }, false)).toBeNull()
		expect(shortcutKeyFromEvent({ key: 'Delete', ctrlKey: true }, false)).toBeNull()
	})

	it('matches a key press against a shortcut', () => {
		expect(matchesShortcut({ key: 'r', code: 'KeyR', ctrlKey: true }, 'mod+r', false)).toBe(true)
		expect(matchesShortcut({ key: 'r', code: 'KeyR' }, 'mod+r', false)).toBe(false)
		expect(matchesShortcut({ key: 'r', code: 'KeyR' }, 'r', false)).toBe(true)
	})

	it('gives vue-shortkey the platform modifier', () => {
		expect(shortkeyOf('mod+r', false)).toEqual(['ctrl', 'r'])
		expect(shortkeyOf('mod+r', true)).toEqual(['meta', 'r'])
		expect(shortkeyOf('del', false)).toEqual(['del'])
	})

	it('labels keys like the shortcut list does', () => {
		expect(shortcutKeyLabel('del', false)).toBe('Delete')
		expect(shortcutKeyLabel('j', false)).toBe('J')
		expect(shortcutKeyLabel('mod+r', false)).toBe('Ctrl+R')
		expect(shortcutKeyLabel('mod+r', true)).toBe('Cmd+R')
	})
})
