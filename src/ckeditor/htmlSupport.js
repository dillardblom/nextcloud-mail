/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Presentation attributes that mail layouts (signatures in particular) put on
 * tables and cells. Mail clients rely on them, since many ignore or override
 * CSS.
 */
const TABLE_ATTRIBUTES = ['width', 'height', 'cellpadding', 'cellspacing', 'border', 'align', 'valign', 'bgcolor', 'role']

/**
 * Elements and attributes the composer keeps from inserted or pasted HTML
 * (General HTML Support). Anything not listed here is dropped by the editor,
 * also from a signature when it is saved or inserted into a message.
 */
export default {
	allow: [
		...['table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th'].map((name) => ({
			name,
			attributes: TABLE_ATTRIBUTES,
			styles: true,
		})),
		...['div', 'p', 'span'].map((name) => ({
			name,
			styles: true,
		})),
		'figure',
		{
			name: 'a',
			attributes: ['title', 'name', 'id'],
			classes: true,
			styles: true,
		},
		{
			name: 'img',
			attributes: ['src', 'alt', 'title', 'width', 'height', 'data-cid', 'loading'],
			styles: true,
		},
	],
}
