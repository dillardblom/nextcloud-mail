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
	saveAttachmentForViewing: vi.fn().mockResolvedValue({ path: '/Mail attachments/server.log', fileId: 7 }),
	saveAttachmentToFiles: vi.fn(),
}))

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

function mountLog() {
	return shallowMount(MessageAttachment, {
		propsData: {
			id: '2',
			messageId: 41,
			fileName: 'server.log',
			url: '/apps/mail/api/messages/41/attachment/2',
			size: 100,
			mime: 'text/plain',
			mimeUrl: '',
		},
		mocks: { $route: { params: { threadId: 41 } } },
		localVue,
	})
}

describe('MessageAttachment in the Viewer', () => {
	beforeEach(() => {
		window.OC = { ...window.OC, appswebroots: { text: '/apps/text' } }
		vi.spyOn(window, 'open').mockReturnValue(null)
	})

	afterEach(() => {
		delete window.OCA?.Viewer
		vi.restoreAllMocks()
	})

	it('opens a saved text file over Mail when Text is in the Viewer', async () => {
		const open = vi.fn()
		window.OCA = { ...window.OCA, Viewer: { availableHandlers: [{ id: 'text' }], mimetypes: [], open } }
		const view = mountLog()

		await view.vm.openInOffice()

		expect(saveAttachmentForViewing).toHaveBeenCalledWith(41, '2', expect.any(String))
		expect(open).toHaveBeenCalledWith({ path: '/Mail attachments/server.log' })
		expect(window.open).not.toHaveBeenCalled()
	})

	it('opens the file in Files without the Text handler', async () => {
		const open = vi.fn()
		window.OCA = { ...window.OCA, Viewer: { availableHandlers: [{ id: 'images' }], mimetypes: [], open } }
		const tab = { location: '', close: vi.fn() }
		window.open.mockReturnValue(tab)
		const view = mountLog()

		await view.vm.openInOffice()

		expect(open).not.toHaveBeenCalled()
		expect(tab.location).toMatch(/\/f\/7$/)
	})
})
