/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { Plugin, UpcastWriter } from 'ckeditor5'

/**
 * The editor wraps the text of a table cell in a paragraph. In the data output
 * a cell that holds a single plain paragraph gets its text back, because the
 * paragraph's default margins would change the height of every row of a table
 * layout such as a signature.
 */
export default class CellParagraphPlugin extends Plugin {
	static get pluginName() {
		return 'CellParagraph'
	}

	init() {
		this.editor.data.on('toView', (event) => {
			const fragment = event.return
			const writer = new UpcastWriter(fragment.document)

			const paragraphs = []
			for (const { item } of writer.createRangeIn(fragment)) {
				if ((item.is('element', 'td') || item.is('element', 'th'))
					&& item.childCount === 1) {
					const child = item.getChild(0)
					if (child.is('element', 'p') && [...child.getAttributeKeys()].length === 0) {
						paragraphs.push(child)
					}
				}
			}
			for (const paragraph of paragraphs) {
				writer.unwrapElement(paragraph)
			}
		}, { priority: 'low' })
	}
}
