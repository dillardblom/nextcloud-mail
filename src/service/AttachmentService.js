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
 * Copy an attachment to the user's files to open it there. The folder is created if needed, and
 * the copy made before is returned when it is unchanged.
 *
 * @param {number} id message id
 * @param {string} attachmentId attachment id
 * @param {string} directory folder in the user's files
 * @return {Promise<{attachmentId: string, path: string, fileId: number}>} the copy
 */
export async function saveAttachmentForViewing(id, attachmentId, directory) {
	const url = generateUrl('/apps/mail/api/messages/{id}/viewing-copies', { id })
	const { data } = await Axios.post(url, { attachmentId, targetPath: directory })
	if (!data[0]) {
		throw new Error('The attachment was not copied')
	}
	return data[0]
}

/**
 * Copy attachments ahead of opening them. The server skips the junk mailbox and attachments it
 * refuses, so the result can hold fewer copies than asked for.
 *
 * @param {number} id message id
 * @param {string[]} attachmentIds attachment ids
 * @param {string} directory folder in the user's files
 * @return {Promise<Array<{attachmentId: string, path: string, fileId: number}>>} the copies
 */
export async function prefetchAttachmentsForViewing(id, attachmentIds, directory) {
	const url = generateUrl('/apps/mail/api/messages/{id}/viewing-copies/prefetch', { id })
	const { data } = await Axios.post(url, { attachmentIds, targetPath: directory })
	return data
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
