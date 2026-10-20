/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

/**
 * Where to open a file in the enabled office app: its own editor page when it has one, which
 * starts the editor without loading the Files app first (about 8 seconds less on a large
 * instance), otherwise the file in Files.
 *
 * @param {number} fileId the file
 * @param {string[]} enabledApps ids of the enabled apps
 * @return {string} the URL
 */
export function officeUrl(fileId, enabledApps) {
	if (enabledApps.includes('eurooffice')) {
		return generateUrl('/apps/eurooffice/{fileId}', { fileId })
	}
	if (enabledApps.includes('onlyoffice')) {
		return generateUrl('/apps/onlyoffice/{fileId}', { fileId })
	}
	if (enabledApps.includes('richdocuments')) {
		return generateUrl('/apps/richdocuments/index?fileId={fileId}', { fileId })
	}
	return generateUrl('/f/{fileId}', { fileId })
}
