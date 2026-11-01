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

	describe('audio', () => {
		function mountAttachment(fileName, mime) {
			return shallowMount(MessageAttachment, {
				propsData: {
					id: '3',
					messageId: 41,
					fileName,
					url: '/apps/mail/api/messages/41/attachment/3',
					size: 100,
					mime,
					mimeUrl: '',
				},
				localVue,
			})
		}

		it.each([
			['voice.ogg', 'audio/ogg', true],
			['call.wav', 'audio/x-wav', true],
			['song.mp3', 'application/octet-stream', true],
			['memo.opus', 'application/ogg', true],
			['data.bin', 'application/octet-stream', false],
			['price list.pdf', 'application/pdf', false],
		])('%s (%s) plays in the message: %s', (fileName, mime, playable) => {
			const view = mountAttachment(fileName, mime)

			expect(view.vm.isAudio).toBe(playable)
			expect(view.find('audio').exists()).toBe(playable)
		})

		it('plays from the attachment url and pauses on a second click', () => {
			const play = vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(() => Promise.resolve())
			const pause = vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {})
			const view = mountAttachment('voice.ogg', 'audio/ogg')
			const audio = view.find('audio')

			expect(audio.attributes('src')).toBe('/apps/mail/api/messages/41/attachment/3')

			view.vm.onClick()
			expect(play).toHaveBeenCalled()
			expect(view.emitted('open')).toBeUndefined()

			vi.spyOn(audio.element, 'paused', 'get').mockReturnValue(false)
			view.vm.onClick()
			expect(pause).toHaveBeenCalled()
		})
	})
})
