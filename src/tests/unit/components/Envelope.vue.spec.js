/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import Envelope from '../../../components/Envelope.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import useMainStore from '../../../store/mainStore.js'

const localVue = createLocalVue()
const $route = {
	params: {
		id: 1,
	},
}

const pinia = createPinia()

localVue.mixin(Nextcloud)

describe('Envelope', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())

		store = useMainStore()

		store.accountsUnmapped[123] = { sentMailboxId: '1' }
	})
	it('allows toggling seen flag without ACLs', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
				account: { sentMailboxId: '1' },
				mailbox: {
					myAcls: undefined,
					databaseId: '3',
					specialRole: '',
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasSeenAcl).toBe(true)
	})

	it('disallows toggling seen flag without s ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 'x',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			pinia,
			localVue,
		})

		expect(view.vm.hasSeenAcl).toBe(false)
	})

	it('allows toggling seen flag with s ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 's',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasSeenAcl).toBe(true)
	})
	it('allows toggling archive action without ACLs', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: undefined,
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			computed: {
				archiveMailbox() {
					return { myAcls: undefined }
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasArchiveAcl).toBe(true)
	})

	it('source mailbox has te and archive mailbox has i ACLs for archiving', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 'te',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			computed: {
				archiveMailbox() {
					return { myAcls: 'i' }
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasArchiveAcl).toBe(true)
	})

	it('source mailbox has te and archive mailbox has no ACLs for archiving', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 'te',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			computed: {
				archiveMailbox() {
					return { myAcls: undefined }
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasArchiveAcl).toBe(true)
	})

	it('source mailbox has no acls and archive mailbox has i ACL for archiving', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: undefined,
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			computed: {
				archiveMailbox() {
					return { myAcls: 'i' }
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasArchiveAcl).toBe(true)
	})

	it('disallows toggling archive action without i ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 'x',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasArchiveAcl).toBe(false)
	})

	it('allows toggling delete action without ACLs', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: undefined,
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },

				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasDeleteAcl).toBe(true)
	})
	it('disallows toggling delete action without x ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 's',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },

				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasDeleteAcl).toBe(false)
	})
	it('allows toggling delete action with te ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					sentMailboxId: '1',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasDeleteAcl).toBe(true)
	})
	it('allows toggling favorite, important and spam action with w ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 'w',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasWriteAcl).toBe(true)
	})
	it('allows toggling favorite, important and spam action without w ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: 's',
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasWriteAcl).toBe(false)
	})
	it('allows toggling favorite, important and spam action without ACL right', () => {
		const view = shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				mailbox: {
					specialRole: '',
					databaseId: '3',
					myAcls: undefined,
				},
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
			},
			store,
			localVue,
		})

		expect(view.vm.hasWriteAcl).toBe(true)
	})

	describe('account label', () => {
		const mountEnvelope = (mailbox) => shallowMount(Envelope, {
			mocks: {
				$route,
			},
			propsData: {
				data: {
					accountId: 123,
					from: [{ email: 'info@test.com' }],
					flags: { seen: false, flagged: false, $junk: false, answered: false, hasAttachments: false, draft: false },
				},
				mailbox: {
					databaseId: '3',
					specialRole: '',
					...mailbox,
				},
			},
			store,
			localVue,
		})

		it('shows the label of the account in a combined mailbox', () => {
			store.accountsUnmapped[123] = { sentMailboxId: '1', emailAddress: 'jane@example.com', unifiedLabel: 'Work', unifiedLabelColor: '#0082c9' }

			const view = mountEnvelope({ isUnified: true })

			expect(view.vm.accountLabel).toMatchObject({ text: 'Work', color: '#0082c9' })
			expect(view.find('.envelope__account-label-text').text()).toBe('Work')
		})

		it('shows the label of the account in the priority inbox', () => {
			store.accountsUnmapped[123] = { sentMailboxId: '1', emailAddress: 'jane@example.com', unifiedLabel: 'Work', unifiedLabelColor: '#0082c9' }

			const view = mountEnvelope({ isPriorityInbox: true })

			expect(view.vm.accountLabel).toMatchObject({ text: 'Work' })
		})

		it('updates the label when the account label changes', async () => {
			delete store.accountsUnmapped[123]
			store.addAccountMutation({ id: 123, accountId: 123, sentMailboxId: '1', emailAddress: 'jane@example.com', mailboxes: [], aliases: [] })
			const view = mountEnvelope({ isUnified: true })
			expect(view.find('.envelope__account-label').exists()).toBe(false)

			store.accountsUnmapped[123].unifiedLabel = 'Work'
			await view.vm.$nextTick()

			expect(view.find('.envelope__account-label-text').text()).toBe('Work')
		})

		it('does not show the label outside combined mailboxes', () => {
			store.accountsUnmapped[123] = { sentMailboxId: '1', emailAddress: 'jane@example.com', unifiedLabel: 'Work', unifiedLabelColor: '#0082c9' }

			const view = mountEnvelope({ isUnified: false })

			expect(view.vm.accountLabel).toBeNull()
			expect(view.find('.envelope__account-label').exists()).toBe(false)
		})

		it('shows nothing when the account has no label and no color', () => {
			store.accountsUnmapped[123] = { sentMailboxId: '1', emailAddress: 'jane@example.com', unifiedLabel: '', unifiedLabelColor: '' }

			const view = mountEnvelope({ isUnified: true })

			expect(view.vm.accountLabel).toBeNull()
		})

		it('shows a colored dot when the account only has a color', () => {
			store.accountsUnmapped[123] = { sentMailboxId: '1', emailAddress: 'jane@example.com', unifiedLabel: '', unifiedLabelColor: '#0082c9' }

			const view = mountEnvelope({ isUnified: true })

			expect(view.find('.envelope__account-label--dot').exists()).toBe(true)
			expect(view.find('.envelope__account-label .hidden-visually').text()).toBe(view.vm.accountLabel.description)
		})
	})
})
