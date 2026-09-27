/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { createTestingPinia } from '@pinia/testing'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { PiniaVuePlugin, setActivePinia } from 'pinia'
import AccountLabelSettings from '../../../components/AccountLabelSettings.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import useMainStore from '../../../store/mainStore.js'

vi.mock('@nextcloud/dialogs', async (importOriginal) => ({
	...await importOriginal(),
	showError: vi.fn(),
}))

const localVue = createLocalVue()
localVue.use(PiniaVuePlugin)

localVue.mixin(Nextcloud)

describe('AccountLabelSettings', () => {
	let store
	let account

	const mountSettings = () => shallowMount(AccountLabelSettings, {
		propsData: {
			account,
		},
		localVue,
	})

	beforeEach(() => {
		setActivePinia(createTestingPinia())
		store = useMainStore()
		account = { id: 13, unifiedLabel: '', unifiedLabelColor: '' }
		store.setAccountUnifiedLabel = vi.fn(async ({ label, color }) => {
			account.unifiedLabel = label
			account.unifiedLabelColor = color
		})
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	it('saves a trimmed label with its color', async () => {
		const view = mountSettings()
		view.vm.label = '  Work  '
		view.vm.color = '#0082c9'

		await view.vm.save()

		expect(store.setAccountUnifiedLabel).toHaveBeenCalledWith({ accountId: 13, label: 'Work', color: '#0082c9' })
		expect(view.vm.label).toBe('Work')
	})

	it('does not save when nothing changed', async () => {
		account.unifiedLabel = 'Work'
		const view = mountSettings()

		await view.vm.save()

		expect(store.setAccountUnifiedLabel).not.toHaveBeenCalled()
	})

	it('saves the picked color', async () => {
		const view = mountSettings()

		await view.vm.onColorSubmit('#ff0000')

		expect(store.setAccountUnifiedLabel).toHaveBeenCalledWith({ accountId: 13, label: '', color: '#ff0000' })
	})

	it('removes the label and the color', async () => {
		account.unifiedLabel = 'Work'
		account.unifiedLabelColor = '#0082c9'
		const view = mountSettings()

		await view.vm.clear()

		expect(store.setAccountUnifiedLabel).toHaveBeenCalledWith({ accountId: 13, label: '', color: '' })
	})

	it('shows an error and restores the saved values when saving fails', async () => {
		account.unifiedLabel = 'Home'
		store.setAccountUnifiedLabel = vi.fn().mockRejectedValue(new Error('network down'))
		const view = mountSettings()
		view.vm.label = 'Work'

		await view.vm.save()

		expect(showError).toHaveBeenCalled()
		expect(view.vm.label).toBe('Home')
		expect(view.vm.saving).toBe(false)
	})
})
