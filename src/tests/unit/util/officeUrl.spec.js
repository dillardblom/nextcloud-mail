/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { fileUrl, officeUrl } from '../../../util/officeUrl.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params) => '/index.php' + url.replace('{fileId}', params.fileId),
}))

describe('officeUrl', () => {
	it('opens the editor of Euro-Office directly', () => {
		expect(officeUrl(42, ['mail', 'eurooffice'])).toMatch(/\/apps\/eurooffice\/42$/)
	})

	it('opens the editor of ONLYOFFICE directly', () => {
		expect(officeUrl(42, ['onlyoffice'])).toMatch(/\/apps\/onlyoffice\/42$/)
	})

	it('opens the editor of Nextcloud Office directly', () => {
		expect(officeUrl(42, ['richdocuments'])).toMatch(/\/apps\/richdocuments\/index\?fileId=42$/)
	})

	it('opens the file in Files without a known office app', () => {
		expect(officeUrl(42, ['mail'])).toMatch(/\/f\/42$/)
	})

	it('opens a file in Files whatever office app is enabled', () => {
		expect(fileUrl(42)).toMatch(/\/f\/42$/)
	})
})
