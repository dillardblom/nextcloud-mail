/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import NeedsTranslationInfo from '../../../components/NeedsTranslationInfo.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import useMainStore from '../../../store/mainStore.js'

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

describe('NeedsTranslationInfo', () => {
	let store

	const mountInfo = () => shallowMount(NeedsTranslationInfo, {
		propsData: {
			isHtml: false,
		},
		localVue,
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useMainStore()
		store.translationOutputLanguages = [{ value: 'en', name: 'English' }]
	})

	it('offers a translation when languages are available', () => {
		const view = mountInfo()

		expect(view.find('.needs-translation-info').exists()).toBe(true)
	})

	it('offers no translation when the user turned AI features off', () => {
		store.savePreferenceMutation({ key: 'ai-features', value: 'false' })

		const view = mountInfo()

		expect(view.find('.needs-translation-info').exists()).toBe(false)
	})

	it('offers no translation without output languages', () => {
		store.translationOutputLanguages = []

		const view = mountInfo()

		expect(view.find('.needs-translation-info').exists()).toBe(false)
	})
})
