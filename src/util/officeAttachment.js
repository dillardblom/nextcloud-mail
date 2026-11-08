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
const ONLYOFFICE_EXTENSION = /\.(txt|md|tsv|pages|numbers)$/i
// By type only: .key is also the extension of private keys, which must not go to the document server
const ONLYOFFICE_MIME = /^application\/(vnd\.apple\.keynote|x-iwork-keynote-sffkey)/i
const TEXT_EXTENSION = /\.(log|json|xml|ya?ml|conf|html?|php)$/i

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
		&& (ONLYOFFICE_EXTENSION.test(fileName ?? '') || ONLYOFFICE_MIME.test(mime ?? ''))
}

/**
 * Whether an attachment opens in the Text app: plain text files that are not office documents.
 * Text shows HTML and PHP as source, it does not render or run them.
 *
 * @param {string|null|undefined} fileName the name of the attachment
 * @param {string[]} enabledApps ids of the enabled apps
 * @return {boolean} whether it can be opened in the Text app
 */
export function isTextAttachment(fileName, enabledApps) {
	return enabledApps.includes('text') && TEXT_EXTENSION.test(fileName ?? '')
}

/**
 * Whether Text is registered in the Viewer of this page, so a saved text file can open over
 * Mail instead of loading the Files app in a new tab.
 *
 * @param {object} [viewer] OCA.Viewer
 * @return {boolean} whether the Viewer has the Text handler
 */
export function hasTextViewer(viewer) {
	return (viewer?.availableHandlers ?? []).some((handler) => handler.id === 'text')
}

/**
 * A PDF while Euro-Office or ONLYOFFICE is enabled: they take over the PDF view of the Viewer,
 * which then fails on an attachment that is not a file yet ("FileId is empty"). Saved first, it
 * opens in their editor like an office document.
 *
 * @param {string} mime MIME type of the attachment
 * @param {string} fileName name of the attachment
 * @param {string[]} enabledApps ids of the enabled apps
 * @return {boolean} whether the PDF opens in the office app
 */
export function isPdfForOffice(mime, fileName, enabledApps) {
	return ONLYOFFICE_APPS.some((app) => enabledApps.includes(app))
		&& (mime === 'application/pdf' || /\.pdf$/i.test(fileName ?? ''))
}

/**
 * @param {string} mime MIME type of the attachment
 * @param {string} fileName name of the attachment
 * @param {string[]} enabledApps ids of the enabled apps
 * @return {boolean} whether the attachment is copied to the user's files to open it in an app
 */
export function opensInApp(mime, fileName, enabledApps) {
	return isOfficeAttachment(mime, fileName, enabledApps)
		|| isTextAttachment(fileName, enabledApps)
		|| isPdfForOffice(mime, fileName, enabledApps)
}
