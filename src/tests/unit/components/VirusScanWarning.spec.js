/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import VirusScanWarning from '../../../components/VirusScanWarning.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

function mountWarning(scan) {
	return shallowMount(VirusScanWarning, {
		propsData: { scan },
		localVue,
	})
}

describe('VirusScanWarning', () => {
	it('names the virus and says the attachments are blocked', () => {
		const view = mountWarning({ status: 'infected', signature: 'Eicar-Test-Signature', blocked: true })

		expect(view.vm.text).toBe('This message contains a virus (Eicar-Test-Signature). Its attachments are blocked.')
		expect(view.findComponent({ name: 'NcNoteCard' }).attributes('type')).toBe('error')
	})

	it('warns not to open the attachments when they are not blocked', () => {
		const view = mountWarning({ status: 'infected', signature: 'Eicar-Test-Signature', blocked: false })

		expect(view.vm.text).toBe('This message contains a virus (Eicar-Test-Signature). Do not open its attachments.')
	})

	it('warns about attachments that could not be scanned', () => {
		const view = mountWarning({ status: 'unscannable', signature: null, blocked: false })

		expect(view.vm.text).toBe('The attachments of this message could not be scanned for viruses. Only open them if you trust the sender.')
		expect(view.findComponent({ name: 'NcNoteCard' }).attributes('type')).toBe('warning')
	})

	it('says when unscanned attachments are blocked', () => {
		const view = mountWarning({ status: 'unscannable', signature: null, blocked: true })

		expect(view.vm.text).toBe('The attachments of this message could not be scanned for viruses, so they are blocked.')
	})

	it('shows nothing for a clean message', () => {
		const view = mountWarning({ status: 'clean', signature: null, blocked: false })

		expect(view.findComponent({ name: 'NcNoteCard' }).exists()).toBe(false)
	})
})
