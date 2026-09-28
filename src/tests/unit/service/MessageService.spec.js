/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import * as MessageService from '../../../service/MessageService.js'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/router')

describe('service/MessageService test suite', () => {
	afterEach(() => {
		vi.clearAllMocks()
	})

	it('should include a given cache buster as a URL parameter', async () => {
		generateUrl.mockReturnValueOnce('/generated-url')
		axios.get.mockResolvedValueOnce({ data: [] })

		await MessageService.fetchEnvelopes(
			13, // account id
			21, // mailbox id
			undefined, // query
			undefined, // cursor
			undefined, // limit
			undefined, // sort ordre
			undefined, // layout
			'abcdef123', // cache buster
		)

		expect(axios.get).toHaveBeenCalledWith('/generated-url', {
			params: {
				mailboxId: 21,
				v: 'abcdef123',
			},
		})
	})

	it('should not include a cache buster by default', async () => {
		generateUrl.mockReturnValueOnce('/generated-url')
		axios.get.mockResolvedValueOnce({ data: [] })

		await MessageService.fetchEnvelopes(
			13, // account id
			21, // mailbox id
		)

		expect(axios.get).toHaveBeenCalledWith('/generated-url', {
			params: {
				mailboxId: 21,
			},
		})
	})

	it('should pass an abort signal to the request', async () => {
		generateUrl.mockReturnValueOnce('/generated-url')
		axios.get.mockResolvedValueOnce({ data: [] })
		const abortController = new AbortController()

		await MessageService.fetchEnvelopes(
			13, // account id
			21, // mailbox id
			undefined, // query
			undefined, // cursor
			undefined, // limit
			undefined, // sort order
			undefined, // layout
			undefined, // cache buster
			abortController.signal,
		)

		expect(axios.get).toHaveBeenCalledWith('/generated-url', {
			params: {
				mailboxId: 21,
			},
			signal: abortController.signal,
		})
	})
})
