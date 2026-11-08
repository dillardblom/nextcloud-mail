/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import MessageAttachments from '../../../components/MessageAttachments.vue'
import Nextcloud from '../../../mixins/Nextcloud.js'
import { prefetchAttachmentsForViewing } from '../../../service/AttachmentService.js'

vi.mock('../../../service/AttachmentService.js', () => ({
	prefetchAttachmentsForViewing: vi.fn(),
	saveAttachmentsToFiles: vi.fn(),
}))

const localVue = createLocalVue()

localVue.mixin(Nextcloud)

const MB = 1024 * 1024

function attachment(id, fileName, mime, size = 100) {
	return { id, fileName, mime, size, downloadUrl: `/a/${id}`, mimeUrl: '', isImage: false, isCalendarEvent: false }
}

function mountAttachments(attachments, blocked = false) {
	return shallowMount(MessageAttachments, {
		propsData: {
			envelope: { databaseId: 41 },
			attachments,
			blocked,
		},
		mocks: { $route: { params: { threadId: 41 } } },
		localVue,
	})
}

describe('MessageAttachments prefetch', () => {
	beforeEach(() => {
		window.OC = { ...window.OC, appswebroots: { eurooffice: '/apps/eurooffice', text: '/apps/text' } }
		window.OCA = window.OCA ?? {}
		prefetchAttachmentsForViewing.mockReset()
	})

	it('copies only the attachments that open in an app, up to the size limit', async () => {
		prefetchAttachmentsForViewing.mockResolvedValue([{ attachmentId: '2', path: '/Mail attachments/file.csv', fileId: 7 }])
		const view = mountAttachments([
			attachment('1', 'photo.png', 'image/png'),
			attachment('2', 'file.csv', 'text/csv'),
			attachment('3', 'archive.zip', 'application/zip'),
			attachment('4', 'huge.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 30 * MB),
		])
		await new Promise(setImmediate)

		expect(prefetchAttachmentsForViewing).toHaveBeenCalledWith(41, ['2'], expect.any(String))
		expect(view.vm.viewingCopies).toEqual({ 2: { attachmentId: '2', path: '/Mail attachments/file.csv', fileId: 7 } })
	})

	it('asks nothing when no attachment opens in an app', async () => {
		mountAttachments([attachment('1', 'photo.png', 'image/png')])
		await new Promise(setImmediate)

		expect(prefetchAttachmentsForViewing).not.toHaveBeenCalled()
	})

	it('copies when opened if copying ahead fails', async () => {
		prefetchAttachmentsForViewing.mockRejectedValue(new Error('offline'))
		const view = mountAttachments([attachment('2', 'file.csv', 'text/csv')])
		await new Promise(setImmediate)

		expect(view.vm.viewingCopies).toEqual({})
	})

	it('copies nothing ahead and hides the bulk actions when the virus scan blocks the attachments', async () => {
		const view = mountAttachments([attachment('2', 'file.csv', 'text/csv'), attachment('3', 'other.csv', 'text/csv')], true)
		await new Promise(setImmediate)

		expect(prefetchAttachmentsForViewing).not.toHaveBeenCalled()
		expect(view.find('.attachments-button-wrapper').exists()).toBe(false)
		expect(view.findComponent({ name: 'MessageAttachment' }).props('blocked')).toBe(true)
	})
})
