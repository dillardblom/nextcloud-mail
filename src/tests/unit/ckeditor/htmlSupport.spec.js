/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	Alignment,
	Bold,
	ClassicEditor,
	Font,
	GeneralHtmlSupport,
	Image,
	ImageResize,
	Italic,
	Link,
	LinkImage,
	Paragraph,
} from 'ckeditor5'
import htmlSupport from '../../../ckeditor/htmlSupport.js'
import ImageDowncastPlugin from '../../../ckeditor/image/ImageDowncastPlugin.ts'
import CellParagraphPlugin from '../../../ckeditor/table/CellParagraphPlugin.js'

// The editor UI observes the size of its toolbar, which jsdom does not provide.
window.ResizeObserver = class {
	observe() {}
	unobserve() {}
	disconnect() {}
}

/**
 * Get the data of an editor configured like the composer, initialised with
 * the given content.
 *
 * @param {string} initialData content to load into the editor
 * @return {Promise<string>} the editor's data output
 */
async function roundTrip(initialData) {
	const element = document.createElement('div')
	document.body.appendChild(element)

	const editor = await ClassicEditor.create(element, {
		licenseKey: 'GPL',
		initialData,
		plugins: [Paragraph, GeneralHtmlSupport, Alignment, Bold, Italic, Link, Image, ImageResize, LinkImage, ImageDowncastPlugin, CellParagraphPlugin, Font],
		image: { resizeUnit: 'px' },
		htmlSupport,
		fontSize: { options: [9, 10, 11, 12, 13, 14, 16, 18, 24, 'default'], supportAllValues: true },
		fontFamily: { supportAllValues: true },
	})

	const data = editor.data.get()
	await editor.destroy()
	element.remove()

	return data
}

const F = 'font-family:Arial,Helvetica,sans-serif;'
const TEXT = `${F}font-size:14px;line-height:22px;color:#000000;`

// A table-based signature as signature generators write it
const SIGNATURE = `<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;${F}"><tr>`
	+ '<td valign="top" style="vertical-align:top;width:130px;"><table cellpadding="0" cellspacing="0" border="0" role="presentation">'
	+ '<tr><td align="center" style="text-align:center;"><img src="https://example.com/photo.png" width="130" height="130" alt="Jane Doe" style="display:block;width:130px;height:130px;border:0;"></td></tr>'
	+ '</table></td>'
	+ '<td width="46" style="width:46px;font-size:1px;line-height:1px;">&nbsp;</td>'
	+ '<td valign="middle" style="vertical-align:middle;">'
	+ `<div style="${F}font-size:18px;line-height:28px;font-weight:bold;color:#000000;">Jane Doe</div>`
	+ `<div style="${TEXT}">Office manager</div>`
	+ '<table cellpadding="0" cellspacing="0" border="0" role="presentation" width="100%" style="width:100%;">'
	+ '<tr><td height="1" style="height:1px;font-size:1px;line-height:1px;border-bottom:1px solid #f39200;">&nbsp;</td></tr>'
	+ '</table>'
	+ '<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'
	+ '<td width="26" valign="middle" style="width:26px;height:28px;vertical-align:middle;"><img src="https://example.com/phone.png" width="18" height="18" alt="" style="display:block;width:18px;height:18px;border:0;"></td>'
	+ `<td valign="middle" style="${TEXT}vertical-align:middle;"><a href="tel:+31201234567" style="color:#000000;text-decoration:none;">+31 20 123 4567</a></td>`
	+ '</tr></table>'
	+ '<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td><a href="https://www.linkedin.com/in/example" style="text-decoration:none;"><img src="https://example.com/linkedin.png" width="24" height="24" alt="LinkedIn" style="display:block;width:24px;height:24px;border:0;"></a></td></tr></table>'
	+ '</td></tr></table>'

describe('htmlSupport', () => {
	let data

	beforeAll(async () => {
		data = await roundTrip(SIGNATURE)
	})

	it('keeps the presentation attributes of tables and cells', () => {
		expect(data).toContain('cellpadding="0"')
		expect(data).toContain('cellspacing="0"')
		expect(data).toContain('border="0"')
		expect(data).toContain('role="presentation"')
		expect(data).toContain('valign="top"')
		expect(data).toContain('valign="middle"')
		expect(data).toContain('align="center"')
		expect(data).toContain('width="46"')
		expect(data).toContain('height="1"')
	})

	it('keeps the styles of tables, cells and text blocks', () => {
		expect(data).toContain('border-collapse:collapse;')
		expect(data).toContain('vertical-align:top;')
		expect(data).toContain('border-bottom:1px solid #f39200;')
		expect(data).toContain('font-size:18px;')
		expect(data).toContain('line-height:28px;')
		// Bold comes back as <strong>, which looks the same
		expect(data).toContain('<strong>Jane Doe</strong>')
		expect(data).toMatch(/font-family:Arial, ?Helvetica, ?sans-serif;/)
	})

	it('keeps the images, their size and their link', () => {
		expect(data).toContain('src="https://example.com/photo.png"')
		expect(data).toContain('src="https://example.com/phone.png"')
		expect(data).toMatch(/<a [^>]*href="https:\/\/www.linkedin.com\/in\/example"[^>]*><img [^>]*src="https:\/\/example.com\/linkedin.png"/)
		expect(data).toMatch(/<img [^>]*src="https:\/\/example.com\/photo.png"[^>]*width="130"/)
		expect(data).toContain('display:block;')
	})

	it('gives the text of a cell back without a paragraph around it', () => {
		expect(data).toMatch(/<td [^>]*valign="middle"><a [^>]*href="tel:\+31201234567"/)
	})

	it('does not leave a figure with the browser\'s default margins', () => {
		for (const [figure] of data.matchAll(/<figure[^>]*>/g)) {
			expect(figure).toContain('margin:0')
		}
	})

	it('keeps the signature the same when it is loaded again', async () => {
		expect(await roundTrip(data)).toBe(data)
	})
})
