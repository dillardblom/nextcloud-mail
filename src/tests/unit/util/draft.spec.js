/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isBodyEmptyApartFromSignature, isEmptyApartFromSignature } from '../../../util/draft.js'

const SIGNATURE = '<div class="signature"><table><tbody><tr><td><figure class="image"><img src="https://example.com/photo.png"></figure></td><td>Jane Doe</td></tr></tbody></table></div>'

const EMPTY = {
	subject: '',
	to: [],
	cc: [],
	bcc: [],
	attachments: [],
	sendAt: undefined,
}

describe('draft', () => {
	describe('isBodyEmptyApartFromSignature', () => {
		it.each([
			['an empty body', '', true],
			['empty paragraphs', '<p>&nbsp;</p><p></p>', true],
			['only the signature', `<p>&nbsp;</p>${SIGNATURE}`, true],
			['text above the signature', `<p>Hello</p>${SIGNATURE}`, false],
			['an image of its own', `<p><img src="a.png"></p>${SIGNATURE}`, false],
			['a table of its own', `<table><tbody><tr><td></td></tr></tbody></table>${SIGNATURE}`, false],
		])('%s: %s', (description, body, expected) => {
			expect(isBodyEmptyApartFromSignature(body)).toBe(expected)
		})
	})

	describe('isEmptyApartFromSignature', () => {
		it('is empty with nothing but the signature', () => {
			expect(isEmptyApartFromSignature(EMPTY, SIGNATURE)).toBe(true)
		})

		it.each([
			['a subject', { subject: 'Hi' }],
			['a recipient', { to: [{ email: 'a@example.com' }] }],
			['a cc', { cc: [{ email: 'a@example.com' }] }],
			['a bcc', { bcc: [{ email: 'a@example.com' }] }],
			['an attachment', { attachments: [{ id: 1 }] }],
			['a send time', { sendAt: 1800000000 }],
		])('is not empty with %s', (description, data) => {
			expect(isEmptyApartFromSignature({ ...EMPTY, ...data }, SIGNATURE)).toBe(false)
		})

		it('does not count a subject of only spaces', () => {
			expect(isEmptyApartFromSignature({ ...EMPTY, subject: '  ' }, SIGNATURE)).toBe(true)
		})
	})
})
