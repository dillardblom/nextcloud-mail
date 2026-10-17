/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export async function saveAttachmentToFiles(id, attachmentId, directory) {
	const url = generateUrl(
		'/apps/mail/api/messages/{id}/attachment/{attachmentId}',
		{
			id,
			attachmentId,
		},
	)

	return await Axios.post(url, {
		targetPath: directory,
	})
}

/**
 * Save an attachment for opening it in Nextcloud: the folder is created if needed and an
 * identical file already there is reused.
 *
 * @param {number} id message id
 * @param {string} attachmentId attachment id
 * @param {string} directory folder in the user's files
 * @return {Promise<{path: string, fileId: number}>} the saved file
 */
export async function saveAttachmentForViewing(id, attachmentId, directory) {
	const url = generateUrl('/apps/mail/api/messages/{id}/attachment/{attachmentId}', { id, attachmentId })
	const { data } = await Axios.post(url, { targetPath: directory, forViewing: true })
	return data[0]
}

export async function saveAttachmentsToFiles(id, directory) {
	// attachmentId = 0 means 'all attachments' (see MessageController.php::saveAttachement)
	return await saveAttachmentToFiles(id, 0, directory)
}

export function downloadAttachment(url) {
	return Axios.get(url).then((res) => res.data)
}

export function uploadLocalAttachment(file, accountId, progress, controller) {
	const url = generateUrl('/apps/mail/api/attachments')
	const data = new FormData()
	const opts = {
		onUploadProgress: (prog) => progress(prog, prog.loaded, prog.total),
	}
	if (controller) {
		opts.signal = controller.signal
	}
	data.append('attachment', file)

	if (accountId) {
		data.append('accountId', accountId)
	}

	return Axios.post(url, data, opts)
		.then((resp) => resp.data)
		.then(({ id }) => {
			return {
				file,
				id,
			}
		})
}
