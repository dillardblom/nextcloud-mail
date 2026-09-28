/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Mailbox from '../../../components/Mailbox.vue'

function createContext(fetchEnvelopes) {
	return {
		mailbox: { databaseId: 21 },
		searchQuery: undefined,
		initialPageSize: 20,
		syncedMailboxes: new Set(),
		loadingEnvelopes: false,
		loadingCacheInitialization: false,
		error: false,
		endReached: false,
		skipListTransition: false,
		$nextTick: (cb) => cb(),
		mainStore: { fetchEnvelopes },
	}
}

function rejectOnAbort(signal) {
	return new Promise((resolve, reject) => {
		signal.addEventListener('abort', () => reject(new Error('canceled')))
	})
}

describe('Mailbox', () => {
	describe('loadEnvelopes', () => {
		it('aborts the previous request and ignores its failure', async () => {
			const fetchEnvelopes = vi.fn()
				.mockImplementationOnce(({ signal }) => rejectOnAbort(signal))
				.mockResolvedValueOnce([])
			const context = createContext(fetchEnvelopes)
			const loadEnvelopes = Mailbox.methods.loadEnvelopes.bind(context)

			const first = loadEnvelopes()
			context.searchQuery = 'subject:invoice'
			const second = loadEnvelopes()
			await Promise.all([first, second])

			expect(fetchEnvelopes.mock.calls[0][0].signal.aborted).toBe(true)
			expect(fetchEnvelopes.mock.calls[1][0].signal.aborted).toBe(false)
			expect(context.error).toBe(false)
			expect(context.loadingEnvelopes).toBe(false)
			expect([...context.syncedMailboxes]).toEqual(['21subject:invoice'])
		})

		it('does not let a superseded response change the list state', async () => {
			let resolveFirst
			const fetchEnvelopes = vi.fn()
				.mockImplementationOnce(() => new Promise((resolve) => {
					resolveFirst = resolve
				}))
				.mockImplementationOnce(({ signal }) => rejectOnAbort(signal))
			const context = createContext(fetchEnvelopes)
			const loadEnvelopes = Mailbox.methods.loadEnvelopes.bind(context)

			const first = loadEnvelopes()
			context.mailbox = { databaseId: 22 }
			const second = loadEnvelopes()
			resolveFirst([])
			await first

			expect(context.syncedMailboxes.size).toBe(0)
			expect(context.loadingEnvelopes).toBe(true)

			context.envelopesAbortController.abort()
			await second
		})

		it('does not reload after a superseded cache initialization', async () => {
			let finishSync
			const context = createContext(vi.fn())
			context.sync = vi.fn(() => new Promise((resolve) => {
				finishSync = resolve
			}))
			context.loadEnvelopes = vi.fn()
			const abortController = new AbortController()

			Mailbox.methods.initializeCache.call(context, abortController.signal)
			abortController.abort()
			finishSync()
			await new Promise((resolve) => setTimeout(resolve))

			expect(context.sync).toHaveBeenCalledWith(true)
			expect(context.loadEnvelopes).not.toHaveBeenCalled()
		})
	})
})
