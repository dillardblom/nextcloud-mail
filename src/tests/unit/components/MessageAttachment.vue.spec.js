/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import MessageAttachment from '../../../components/MessageAttachment.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import { saveAttachmentForViewing } from '../../../service/AttachmentService.js'

vi.mock('../../../service/AttachmentService.js', () => ({
	downloadAttachment: vi.fn(),
	saveAttachmentForViewing: vi.fn().mockResolvedValue({ path: '/Mail attachments/a.pdf', fileId: 7 }),
	saveAttachmentToFiles: vi.fn(),
}))

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

describe('MessageAttachment', () => {
	it('opens the attachment of its own message, not of the message in the route', async () => {
		window.OC = { ...window.OC, appswebroots: { eurooffice: '/apps/eurooffice' } }
		const tab = { location: '', close: vi.fn() }
		vi.spyOn(window, 'open').mockReturnValue(tab)
		const view = shallowMount(MessageAttachment, {
			propsData: {
				id: '2',
				messageId: 41,
				fileName: 'price list.pdf',
				url: '/apps/mail/api/messages/41/attachment/2',
				size: 100,
				mime: 'application/pdf',
				mimeUrl: '',
			},
			mocks: { $route: { params: { threadId: 40 } } },
			localVue,
		})

		await view.vm.openInOffice()

		expect(saveAttachmentForViewing).toHaveBeenCalledWith(41, '2', expect.any(String))
		expect(tab.location).toMatch(/\/apps\/eurooffice\/7$/)
	})
})
