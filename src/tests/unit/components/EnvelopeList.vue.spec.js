/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import EnvelopeList from '../../../components/EnvelopeList.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import useMainStore from '../../../store/mainStore.js'

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

function envelope(databaseId, accountId, mailboxId) {
	return {
		databaseId,
		accountId,
		mailboxId,
		flags: { seen: true, flagged: false, $junk: false, important: false },
		tags: [],
	}
}

describe('EnvelopeList', () => {
	let store
	let view

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useMainStore()
		store.accountsUnmapped[1] = { archiveMailboxId: 12 }
		store.accountsUnmapped[2] = { archiveMailboxId: null }
		store.mailboxes[11] = { databaseId: 11 }
		store.mailboxes[12] = { databaseId: 12 }
		store.mailboxes[13] = { databaseId: 13, myAcls: 'lr' }
		store.mailboxes[21] = { databaseId: 21 }
		store.moveThread = vi.fn().mockResolvedValue(undefined)

		view = shallowMount(EnvelopeList, {
			propsData: {
				account: { isUnified: true },
				mailbox: { databaseId: 11 },
				envelopes: [
					envelope(101, 1, 11),
					envelope(102, 1, 12),
					envelope(103, 2, 21),
					envelope(104, 1, 13),
					envelope(105, 1, 11),
				],
			},
			mocks: { $route: { params: {} } },
			localVue,
		})
	})

	it('archives only the selected threads that can go to their account archive', async () => {
		view.vm.selectAll()

		expect(view.vm.archivableSelection.map((env) => env.databaseId)).toEqual([101, 105])

		await view.vm.archiveAllSelected()

		expect(store.moveThread).toHaveBeenCalledTimes(2)
		expect(store.moveThread).toHaveBeenCalledWith({ envelope: expect.objectContaining({ databaseId: 101 }), destMailboxId: 12 })
		expect(store.moveThread).toHaveBeenCalledWith({ envelope: expect.objectContaining({ databaseId: 105 }), destMailboxId: 12 })
		expect(view.vm.selection).toEqual([])
	})

	it('offers no archive when nothing selected can be archived', () => {
		view.vm.setEnvelopeSelected(view.vm.sortedEnvelops[2], true)

		expect(view.vm.archivableSelection).toEqual([])
	})
})
