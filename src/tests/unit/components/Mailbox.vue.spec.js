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

	describe('startAutoRefresh', () => {
		beforeEach(() => {
			vi.useFakeTimers()
		})

		afterEach(() => {
			vi.useRealTimers()
		})

		it('refreshes the open list every set number of minutes', () => {
			const context = { ...createContext(vi.fn()), autoRefreshMinutes: 15, sync: vi.fn().mockResolvedValue() }
			Mailbox.methods.startAutoRefresh.call(context)

			vi.advanceTimersByTime(14 * 60 * 1000)
			expect(context.sync).not.toHaveBeenCalled()
			vi.advanceTimersByTime(60 * 1000)
			expect(context.sync).toHaveBeenCalledWith(false)

			clearInterval(context.autoRefreshInterval)
		})

		it('does not refresh when set to manually, and stops the previous timer', () => {
			const context = { ...createContext(vi.fn()), autoRefreshMinutes: 5, sync: vi.fn().mockResolvedValue() }
			Mailbox.methods.startAutoRefresh.call(context)
			context.autoRefreshMinutes = 0
			Mailbox.methods.startAutoRefresh.call(context)

			vi.advanceTimersByTime(60 * 60 * 1000)
			expect(context.sync).not.toHaveBeenCalled()
			expect(context.autoRefreshInterval).toBeUndefined()
		})
	})

	describe('autoRefreshMinutes', () => {
		const minutes = (value) => Mailbox.computed.autoRefreshMinutes.call({ mainStore: { getPreference: () => value } })

		it('reads the preference, with 15 minutes for a missing or invalid value', () => {
			expect(minutes('5')).toBe(5)
			expect(minutes('0')).toBe(0)
			expect(minutes(undefined)).toBe(15)
			expect(minutes('soon')).toBe(15)
		})
	})
})
