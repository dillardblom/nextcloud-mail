/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isOfficeAttachment } from '../../../util/officeAttachment.js'

describe('isOfficeAttachment', () => {
	it.each([
		['text/csv', 'file.csv'],
		['application/csv', 'export'],
		['text/x-csv', 'export'],
		['application/vnd.ms-excel', 'sheet.xls'],
		['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'a.docx'],
		['application/vnd.ms-word.document.macroEnabled.12', 'a.docm'],
		['application/vnd.oasis.opendocument.text', 'a.odt'],
		['text/rtf', 'a.rtf'],
		['application/octet-stream', 'file.CSV'],
		['application/octet-stream', 'a.docx'],
		['text/plain', 'file.csv'],
	])('opens %s named %s in any office app', (mime, fileName) => {
		expect(isOfficeAttachment(mime, fileName, ['richdocuments'])).toBe(true)
		expect(isOfficeAttachment(mime, fileName, ['eurooffice'])).toBe(true)
	})

	it.each([
		['text/plain', 'notes.txt'],
		['text/markdown', 'README.md'],
		['application/octet-stream', 'notes.TXT'],
		['text/tab-separated-values', 'data.tsv'],
	])('opens %s named %s in Euro-Office and ONLYOFFICE only', (mime, fileName) => {
		expect(isOfficeAttachment(mime, fileName, ['eurooffice'])).toBe(true)
		expect(isOfficeAttachment(mime, fileName, ['onlyoffice'])).toBe(true)
		expect(isOfficeAttachment(mime, fileName, ['richdocuments'])).toBe(false)
	})

	it.each([
		['text/plain', 'server.log'],
		['text/plain', 'data.json'],
		['text/plain', 'noextension'],
		['text/html', 'page.html'],
		['application/pdf', 'a.pdf'],
		['image/png', 'a.png'],
		['application/zip', 'a.zip'],
		[null, null],
		[undefined, undefined],
	])('does not open %s named %s', (mime, fileName) => {
		expect(isOfficeAttachment(mime, fileName, ['eurooffice'])).toBe(false)
	})

	it('needs an enabled office app', () => {
		expect(isOfficeAttachment('text/csv', 'file.csv', ['mail', 'viewer'])).toBe(false)
		expect(isOfficeAttachment('text/plain', 'notes.txt', [])).toBe(false)
	})
})
