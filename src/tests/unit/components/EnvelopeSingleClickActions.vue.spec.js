/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import EnvelopeSingleClickActions from '../../../components/EnvelopeSingleClickActions.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

describe('EnvelopeSingleClickActions', () => {
	const mountActions = (propsData) => shallowMount(EnvelopeSingleClickActions, { propsData, localVue })
	const favoriteButton = (view) => view.findAll('ncbutton-stub').wrappers
		.find((button) => ['Favorite', 'Unfavorite'].includes(button.attributes('title')))

	it('offers Favorite when the message may be flagged', async () => {
		const view = mountActions({ canFavorite: true })

		expect(favoriteButton(view).attributes('title')).toBe('Favorite')
		favoriteButton(view).vm.$emit('click', { preventDefault: () => {} })
		expect(view.emitted('toggle-flagged')).toHaveLength(1)
	})

	it('offers Unfavorite for a favorite', () => {
		const view = mountActions({ canFavorite: true, isFavorite: true })

		expect(favoriteButton(view).attributes('title')).toBe('Unfavorite')
	})

	it('has no Favorite without the right to change flags', () => {
		const view = mountActions({ canFavorite: false })

		expect(favoriteButton(view)).toBeUndefined()
	})
})
