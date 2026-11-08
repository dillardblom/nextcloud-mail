/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { hasTextViewer, isOfficeAttachment, isPdfForOffice, isTextAttachment, opensInApp } from '../../../util/officeAttachment.js'

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
		['application/octet-stream', 'report.pages'],
		['application/x-iwork-numbers-sffnumbers', 'sheet.numbers'],
		['application/vnd.apple.keynote', 'talk.key'],
		['application/x-iwork-keynote-sffkey', 'talk'],
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
		['application/octet-stream', 'server.key'],
		['application/x-pem-file', 'server.key'],
		['text/plain', 'server.key'],
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

describe('isTextAttachment', () => {
	it.each(['server.log', 'data.json', 'feed.XML', 'a.yml', 'a.yaml', 'a.conf', 'page.html', 'page.htm', 'script.php'])('opens %s in Text', (fileName) => {
		expect(isTextAttachment(fileName, ['text'])).toBe(true)
	})

	it.each(['run.sh', 'notes.txt', 'server.key', 'noextension', null, undefined])('does not open %s in Text', (fileName) => {
		expect(isTextAttachment(fileName, ['text'])).toBe(false)
	})

	it('needs the Text app', () => {
		expect(isTextAttachment('server.log', ['eurooffice'])).toBe(false)
	})
})

describe('hasTextViewer', () => {
	it('finds the Text handler', () => {
		expect(hasTextViewer({ availableHandlers: [{ id: 'images' }, { id: 'text' }] })).toBe(true)
	})

	it.each([
		[{ availableHandlers: [{ id: 'images' }] }],
		[{}],
		[undefined],
	])('is false without it: %o', (viewer) => {
		expect(hasTextViewer(viewer)).toBe(false)
	})
})

describe('isPdfForOffice', () => {
	it('opens a PDF in Euro-Office or ONLYOFFICE', () => {
		expect(isPdfForOffice('application/pdf', 'a.pdf', ['eurooffice'])).toBe(true)
		expect(isPdfForOffice('application/octet-stream', 'a.PDF', ['onlyoffice'])).toBe(true)
	})

	it('leaves a PDF to the Viewer otherwise', () => {
		expect(isPdfForOffice('application/pdf', 'a.pdf', ['richdocuments'])).toBe(false)
		expect(isPdfForOffice('image/png', 'a.png', ['eurooffice'])).toBe(false)
	})
})

describe('opensInApp', () => {
	it.each([
		['text/csv', 'file.csv', ['eurooffice']],
		['text/plain', 'server.log', ['text']],
		['application/pdf', 'a.pdf', ['eurooffice']],
	])('copies %s named %s', (mime, fileName, apps) => {
		expect(opensInApp(mime, fileName, apps)).toBe(true)
	})

	it.each([
		['application/zip', 'a.zip', ['eurooffice', 'text']],
		['image/png', 'a.png', ['eurooffice', 'text']],
		['text/plain', 'server.log', ['eurooffice']],
	])('does not copy %s named %s', (mime, fileName, apps) => {
		expect(opensInApp(mime, fileName, apps)).toBe(false)
	})
})
