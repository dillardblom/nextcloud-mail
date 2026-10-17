/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { DEFAULT_SHORTCUTS, effectiveShortcuts, shortcutKeyFromEvent, shortcutKeyLabel } from '../../../util/shortcuts.js'

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
		expect(shortcutKeyFromEvent({ key: 'J' })).toBe('j')
		expect(shortcutKeyFromEvent({ key: 'Delete' })).toBe('del')
		expect(shortcutKeyFromEvent({ key: 'j', ctrlKey: true })).toBeNull()
		expect(shortcutKeyFromEvent({ key: 'Enter' })).toBeNull()
		expect(shortcutKeyFromEvent({ key: ' ' })).toBeNull()
	})

	it('labels keys like the shortcut list does', () => {
		expect(shortcutKeyLabel('del')).toBe('Delete')
		expect(shortcutKeyLabel('j')).toBe('J')
	})
})
