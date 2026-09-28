/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isDialogOpen, isEditableTarget, isReplyShortcut } from '../../../util/replyShortcut.js'

function keyR(modifiers) {
	return {
		code: 'KeyR',
		key: 'r',
		ctrlKey: false,
		metaKey: false,
		shiftKey: false,
		altKey: false,
		...modifiers,
	}
}

describe('replyShortcut', () => {
	describe('isReplyShortcut', () => {
		it('matches Ctrl+R on non-Apple platforms', () => {
			expect(isReplyShortcut(keyR({ ctrlKey: true }), false)).toBe(true)
		})

		it('matches Cmd+R on Apple platforms', () => {
			expect(isReplyShortcut(keyR({ metaKey: true }), true)).toBe(true)
		})

		it('ignores Cmd+R on non-Apple platforms and Ctrl+R on Apple platforms', () => {
			expect(isReplyShortcut(keyR({ metaKey: true }), false)).toBe(false)
			expect(isReplyShortcut(keyR({ ctrlKey: true }), true)).toBe(false)
		})

		it('leaves Ctrl+Shift+R and Ctrl+Alt+R to the browser', () => {
			expect(isReplyShortcut(keyR({ ctrlKey: true, shiftKey: true }), false)).toBe(false)
			expect(isReplyShortcut(keyR({ ctrlKey: true, altKey: true }), false)).toBe(false)
		})

		it('ignores R without a modifier', () => {
			expect(isReplyShortcut(keyR({}), false)).toBe(false)
		})

		it('matches the R key whatever character it produces', () => {
			expect(isReplyShortcut(keyR({ ctrlKey: true, key: 'R' }), false)).toBe(true)
			expect(isReplyShortcut(keyR({ ctrlKey: true, key: 'к' }), false)).toBe(true)
		})

		it('ignores other keys', () => {
			expect(isReplyShortcut({ ...keyR({ ctrlKey: true }), code: 'KeyP', key: 'p' }, false)).toBe(false)
		})
	})

	describe('isDialogOpen', () => {
		it('detects an open modal or dialog', () => {
			const doc = document.implementation.createHTMLDocument('')
			expect(isDialogOpen(doc)).toBe(false)

			doc.body.innerHTML = '<div class="modal-mask"></div>'
			expect(isDialogOpen(doc)).toBe(true)

			doc.body.innerHTML = '<div role="dialog" aria-modal="true"></div>'
			expect(isDialogOpen(doc)).toBe(true)
		})
	})

	describe('isEditableTarget', () => {
		it('treats form fields and editable content as editable', () => {
			const editable = document.createElement('div')
			editable.setAttribute('contenteditable', 'true')
			document.body.appendChild(editable)

			expect(isEditableTarget(document.createElement('input'))).toBe(true)
			expect(isEditableTarget(document.createElement('textarea'))).toBe(true)
			expect(isEditableTarget(document.createElement('select'))).toBe(true)
			expect(isEditableTarget(editable)).toBe(true)
			const child = document.createElement('p')
			editable.appendChild(child)
			expect(isEditableTarget(child)).toBe(true)

			editable.remove()
		})

		it('does not treat other elements or a missing target as editable', () => {
			expect(isEditableTarget(document.createElement('div'))).toBe(false)
			expect(isEditableTarget(null)).toBe(false)
		})
	})
})
