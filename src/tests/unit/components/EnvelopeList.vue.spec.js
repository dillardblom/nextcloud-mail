/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import EnvelopeList from '../../../components/EnvelopeList.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'

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
	let view

	beforeEach(() => {
		setActivePinia(createPinia())

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

	it('selects a range from the last clicked message after new messages arrived above it', async () => {
		view.vm.onEnvelopeSelectToggle(view.vm.sortedEnvelops[1], true)
		await view.setProps({ envelopes: [envelope(106, 1, 11), envelope(107, 1, 11), ...view.props('envelopes')] })

		view.vm.onEnvelopeSelectMultiple(view.vm.sortedEnvelops[5])

		expect(view.vm.selection.slice().sort()).toEqual([102, 103, 104])
	})

	it('selects a range from the open message when the last clicked message is gone', async () => {
		view.vm.$route.params.threadId = '103'
		view.vm.onEnvelopeSelectToggle(view.vm.sortedEnvelops[4], true)
		await view.setProps({ envelopes: view.props('envelopes').slice(0, 4) })

		view.vm.onEnvelopeSelectMultiple(view.vm.sortedEnvelops[0])

		expect(view.vm.selection.slice().sort()).toEqual([101, 102, 103])
	})
})
