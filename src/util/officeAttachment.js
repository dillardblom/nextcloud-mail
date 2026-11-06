/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const OFFICE_APPS = ['richdocuments', 'onlyoffice', 'eurooffice']
const ONLYOFFICE_APPS = ['onlyoffice', 'eurooffice']

const OFFICE_MIME = /^(application\/(msword|rtf|csv|vnd\.ms-(excel|powerpoint|word)|vnd\.openxmlformats-officedocument\.|vnd\.oasis\.opendocument\.(text|spreadsheet|presentation))|text\/(rtf|csv|x-csv|x-comma-separated-values))/i
const OFFICE_EXTENSION = /\.(docx?|docm|dotx?|dotm|odt|ott|rtf|xlsx?|xlsm|xltx?|xltm|ods|ots|csv|pptx?|pptm|ppsx?|ppsm|potx?|potm|odp|otp)$/i
// Nextcloud Office has no plain text editor, and text/plain alone says nothing about the file
// (a .log or .json is text/plain too, and their editors refuse those), so go by the extension.
const ONLYOFFICE_EXTENSION = /\.(txt|md|tsv)$/i

/**
 * Whether an attachment opens in the enabled office app. Mail clients often send office files as
 * application/octet-stream, so the extension counts as well.
 *
 * @param {string|null|undefined} mime the MIME type of the attachment
 * @param {string|null|undefined} fileName the name of the attachment
 * @param {string[]} enabledApps ids of the enabled apps
 * @return {boolean} whether it can be opened in the office app
 */
export function isOfficeAttachment(mime, fileName, enabledApps) {
	if (!OFFICE_APPS.some((app) => enabledApps.includes(app))) {
		return false
	}
	if (OFFICE_MIME.test(mime ?? '') || OFFICE_EXTENSION.test(fileName ?? '')) {
		return true
	}
	return ONLYOFFICE_APPS.some((app) => enabledApps.includes(app))
		&& ONLYOFFICE_EXTENSION.test(fileName ?? '')
}
