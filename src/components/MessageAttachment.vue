<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="attachment" :class="{ 'message-attachment--can-preview': canPreview || canOpenInOffice }">
		<div class="mail-attachment-img--wrapper" @click="onClick">
			<img
				v-if="isImage"
				class="mail-attached-image"
				:src="url">
			<img v-else class="attachment-icon" :src="mimeUrl">
		</div>
		<div class="mail-attached--content" @click="onClick">
			<span
				class="attachment-name"
				:title="label">{{ name }}
			</span>
			<span class="attachment-size">{{ humanReadable(size) }}</span>
		</div>
		<NcActions :boundaries-element="boundariesElement">
			<template v-if="!showCalendarPopover">
				<NcActionButton
					v-if="isCalendarEvent"
					class="attachment-import calendar"
					:disabled="loadingCalendars"
					@click.stop="loadCalendars">
					<template #icon>
						<IconAdd v-if="!loadingCalendars" :size="20" />
						<NcLoadingIcon v-else-if="loadingCalendars" :size="20" />
					</template>
					{{ t('mail', 'Import into calendar') }}
				</NcActionButton>
				<NcActionButton
					class="attachment-download"
					:close-after-click="true"
					@click="download">
					<template #icon>
						<IconDownload :size="20" />
					</template>
					{{ t('mail', 'Download attachment') }}
				</NcActionButton>
				<NcActionButton
					v-if="canOpenInOffice"
					class="attachment-open-in-office"
					:disabled="openingInOffice"
					:close-after-click="true"
					@click="openInOffice">
					<template #icon>
						<IconOpenInOffice v-if="!openingInOffice" :size="20" />
						<NcLoadingIcon v-else :size="20" />
					</template>
					{{ t('mail', 'Open in Office') }}
				</NcActionButton>
				<NcActionButton
					class="attachment-save-to-cloud"
					:disabled="savingToCloud"
					:close-after-click="true"
					@click="saveToCloud">
					<template #icon>
						<IconSave v-if="!savingToCloud" :size="20" />
						<NcLoadingIcon v-else-if="savingToCloud" :size="20" />
					</template>
					{{ t('mail', 'Save to Files') }}
				</NcActionButton>
			</template>
			<template v-else>
				<NcActionButton @click="closeCalendarPopover">
					<template #icon>
						<IconArrow :size="20" />
					</template>
					{{ t('mail', 'Go back') }}
				</NcActionButton>
				<NcActionButton
					v-for="entry in calendarMenuEntries"
					:key="entry.text"
					@click="entry.action">
					{{ entry.text }}
				</NcActionButton>
			</template>
		</NcActions>
	</div>
</template>

<script>

import { showError, showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActions, NcLoadingIcon } from '@nextcloud/vue'
import IconArrow from 'vue-material-design-icons/ArrowLeft.vue'
import IconOpenInOffice from 'vue-material-design-icons/FileDocumentEditOutline.vue'
import IconSave from 'vue-material-design-icons/FolderOutline.vue'
import IconAdd from 'vue-material-design-icons/Plus.vue'
import IconDownload from 'vue-material-design-icons/TrayArrowDown.vue'
import Logger from '../logger.js'
import { downloadAttachment, saveAttachmentForViewing, saveAttachmentToFiles } from '../service/AttachmentService.js'
import { getUserCalendars, importCalendarEvent } from '../service/DAVService.js'
import { pickFolder } from '../util/filePicker.js'

export default {
	name: 'MessageAttachment',
	components: {
		NcActions,
		NcActionButton,
		IconAdd,
		IconArrow,
		IconOpenInOffice,
		NcLoadingIcon,
		IconSave,
		IconDownload,
	},

	props: {
		id: {
			type: String,
			required: true,
		},

		fileName: {
			type: String,
			default: t('mail', 'Unnamed'),
			required: false,
		},

		url: {
			type: String,
			required: true,
		},

		size: {
			type: Number,
			required: true,
		},

		mime: {
			type: String,
			required: true,
		},

		mimeUrl: {
			type: String,
			required: true,
		},

		isImage: {
			type: Boolean,
			default: false,
		},

		isCalendarEvent: {
			type: Boolean,
			default: false,
		},

		canPreview: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			savingToCloud: false,
			openingInOffice: false,
			loadingCalendars: false,
			calendars: [],
			showCalendarPopover: false,
		}
	},

	computed: {
		name() {
			if (this.mime === 'message/rfc822') {
				return t('mail', 'Embedded message')
			}
			return this.fileName
		},

		label() {
			if (this.mime === 'message/rfc822') {
				return t('mail', 'Embedded message') + ' (' + formatFileSize(this.size) + ')'
			}
			return this.fileName + ' (' + formatFileSize(this.size) + ')'
		},

		calendarMenuEntries() {
			return this.calendars.map((cal) => {
				return {
					text: cal.displayname,
					action: this.importCalendar(cal.url),
				}
			})
		},

		/** An office document, and an office app (Nextcloud Office, ONLYOFFICE, Euro-Office) is enabled */
		canOpenInOffice() {
			const officeApps = ['richdocuments', 'onlyoffice', 'eurooffice']
			const enabled = Object.keys(window.OC?.appswebroots ?? {})
			if (!officeApps.some((app) => enabled.includes(app))) {
				return false
			}
			return /^application\/(msword|rtf|vnd\.ms-(excel|powerpoint)|vnd\.openxmlformats-officedocument\.|vnd\.oasis\.opendocument\.(text|spreadsheet|presentation))/.test(this.mime ?? '')
				|| /\.(docx?|xlsx?|pptx?|od[tsp]|rtf)$/i.test(this.fileName ?? '')
		},

		boundariesElement() {
			return document.querySelector('#content-vue')
		},
	},

	mounted() {
		document.addEventListener('click', this.handleClickOutside)
	},

	beforeDestroy() {
		document.removeEventListener('click', this.handleClickOutside)
	},

	methods: {
		handleClickOutside(event) {
			if (!this.showCalendarPopover) {
				return
			}

			if (this.$el && !this.$el.contains(event.target)) {
				this.closeCalendarPopover()
			}
		},

		humanReadable(size) {
			return formatFileSize(size)
		},

		async saveToCloud() {
			let path
			try {
				path = await pickFolder(t('mail', 'Choose a folder to store the attachment in'))
			} catch (error) {
				Logger.debug('file picker closed without picking a folder', { error })
				return
			}

			this.savingToCloud = true
			const id = this.$route.params.threadId

			try {
				await saveAttachmentToFiles(id, this.id, path)
				Logger.info('saved')
				showSuccess(t('mail', 'Attachment saved to Files'))
			} catch (e) {
				Logger.error('not saved', { error: e })
				showError(t('mail', 'Attachment could not be saved'))
			} finally {
				this.savingToCloud = false
			}
		},

		/**
		 * A click previews images, video, audio and PDF, and opens office documents in the office app
		 *
		 * @param {MouseEvent} event the click
		 */
		onClick(event) {
			if (!this.canPreview && this.canOpenInOffice) {
				this.openInOffice()
				return
			}
			this.$emit('open', event)
		},

		/**
		 * Save the attachment in the "Mail attachments" folder of the user's files and open it
		 * there, which starts the office app. Opening it again reuses the saved copy.
		 */
		async openInOffice() {
			this.openingInOffice = true
			// Open the tab now: browsers block a new tab opened after an await
			const tab = window.open('', '_blank')
			try {
				const file = await saveAttachmentForViewing(this.$route.params.threadId, this.id, '/' + t('mail', 'Mail attachments'))
				const url = generateUrl('/f/{fileId}', { fileId: file.fileId })
				if (tab) {
					tab.location = url
				} else {
					window.location = url
				}
			} catch (error) {
				tab?.close()
				Logger.error('could not open the attachment in office', { error })
				showError(t('mail', 'Could not open the attachment'))
			} finally {
				this.openingInOffice = false
			}
		},

		download() {
			window.location = this.url
		},

		loadCalendars() {
			this.loadingCalendars = true
			getUserCalendars().then((calendars) => {
				this.calendars = calendars
				this.showCalendarPopover = true
				this.loadingCalendars = false
			})
		},

		closeCalendarPopover() {
			this.showCalendarPopover = false
		},

		importCalendar(url) {
			return () => {
				downloadAttachment(this.url)
					.then(importCalendarEvent(url))
					.then(() => {
						showSuccess(t('mail', 'calendar imported'))
					})
					.catch((error) => {
						Logger.error('Could not import event', { error })
						showError(t('mail', 'Could not create event'))
					})
					.then(() => (this.showCalendarPopover = false))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../../css/variables.scss';

@media screen and (max-width: #{variables.$breakpoint-mobile}) {
	.attachment{
		width: 100% !important;
	}
}

@media screen and (min-width: 1025px) and (max-width: 1500px) {
	.attachment{
		width: calc(50% - 4px)!important;
	}
}

.attachment {
	height: auto;
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: space-between;
	width: calc(33.3334% - 4px);
    margin: 2px;
	padding: 5px;
    position: relative;
    align-items: center;
	flex-grow: 1;

	&:hover {
		border-radius: 6px;
	}
}

.attachment:hover,
.attachment span:hover {
	background-color: var(--color-background-hover);

	&.message-attachment--can-preview * {
		cursor: pointer;
	}
}

.mail-attachment-img--wrapper {
	height: 44px;
	width: 44px;
	overflow: hidden;
	display:flex;
	justify-content: center;
	position: relative;
	border-radius: 6px;

	img {
		transition: 0.3s;
		opacity: 1;
		width: 44px;
		height: 44px;
	}

	.mail-attached-image {
		width: 100px;
	}
}

.mail-attached--content {
	width: calc(100% - 100px);
	display: flex;
    flex-direction: column;
}

.mail-attached-image {
	display: block;
	max-width: 100%;
	border-radius: var(--border-radius);
}

.attachment-import-popover {
	inset-inline-end: 32px;
	top: 42px;
}

.mail-attached-image:hover {
	opacity: 0.8;
}

.attachment-name {
	display: inline-block;
	width: 100%;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	vertical-align: middle;
}

/* show attachment size less prominent */
.attachment-size {
	-ms-filter: 'progid:DXImageTransform.Microsoft.Alpha(Opacity=50)';
	opacity: 0.5;
	font-size: 12px;
	line-height: 14px;
}

.attachment-icon {
	vertical-align: middle;
	text-align: start;
	margin-bottom: 20px;
}

.action-item {
	transition: 0.4s;
}

.mail-message-attachments {
	overflow-x: auto;
	overflow-y: auto;
}
</style>
