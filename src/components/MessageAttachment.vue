<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="attachment" :class="{ 'message-attachment--can-preview': !blocked && (canPreview || canOpenInApp || isAudio), 'message-attachment--blocked': blocked }">
		<div class="mail-attachment-img--wrapper" @click="onClick">
			<img
				v-if="isImage && !blocked"
				class="mail-attached-image"
				:src="url">
			<img v-else class="attachment-icon" :src="mimeUrl">
		</div>
		<div class="mail-attached--content" @click="onClick">
			<span
				class="attachment-name"
				:title="label">{{ name }}
			</span>
			<span v-if="blocked" class="attachment-size">{{ t('mail', 'Blocked by the virus scan') }}</span>
			<span v-else class="attachment-size">{{ humanReadable(size) }}</span>
		</div>
		<audio
			v-if="isAudio && !blocked"
			ref="audio"
			class="attachment-audio"
			controls
			preload="none"
			:src="url"
			:aria-label="t('mail', 'Play {name}', { name })" />
		<NcActions v-if="!blocked" :boundaries-element="boundariesElement">
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
					v-if="canOpenInApp"
					class="attachment-open-in-office"
					:disabled="openingInOffice"
					:close-after-click="true"
					@click="openInOffice">
					<template #icon>
						<IconOpenInOffice v-if="!openingInOffice" :size="20" />
						<NcLoadingIcon v-else :size="20" />
					</template>
					{{ canOpenInOffice ? t('mail', 'Open in Office') : t('mail', 'Open in Text') }}
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
import { hasTextViewer, isOfficeAttachment, isPdfForOffice, isTextAttachment } from '../util/officeAttachment.js'
import { fileUrl, officeUrl } from '../util/officeUrl.js'

const HTTP_UNPROCESSABLE_ENTITY = 422

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

		/**
		 * The message the attachment belongs to. In a thread this need not be the message in
		 * the route, and an attachment id is only unique within its own message.
		 */
		messageId: {
			type: Number,
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

		viewingCopy: {
			type: Object,
			default: null,
		},

		blocked: {
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

		canOpenInOffice() {
			return isOfficeAttachment(this.mime, this.fileName, Object.keys(window.OC?.appswebroots ?? {}))
		},

		canOpenInText() {
			return !this.canOpenInOffice && isTextAttachment(this.fileName, Object.keys(window.OC?.appswebroots ?? {}))
		},

		canOpenInApp() {
			return this.canOpenInOffice || this.canOpenInText
		},

		/** Audio the browser plays; senders often label it application/octet-stream, so the extension counts too */
		isAudio() {
			if (/^audio\//.test(this.mime ?? '')) {
				return true
			}
			return /^application\/(octet-stream|ogg)$/.test(this.mime ?? '')
				&& /\.(mp3|ogg|oga|opus|wav|m4a|aac|flac|weba)$/i.test(this.fileName ?? '')
		},

		isPdfForOffice() {
			return isPdfForOffice(this.mime, this.fileName, Object.keys(window.OC?.appswebroots ?? {}))
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
			const id = this.messageId

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
		 * A click previews images, video, audio and PDF, and opens office documents (and PDF, when
		 * the office app shows those) in the office app
		 *
		 * @param {MouseEvent} event the click
		 */
		onClick(event) {
			if (this.blocked) {
				return
			}
			if (this.isAudio) {
				const audio = this.$refs.audio
				if (audio.paused) {
					audio.play()
				} else {
					audio.pause()
				}
				return
			}
			if ((!this.canPreview && this.canOpenInApp) || this.isPdfForOffice) {
				this.openInOffice()
				return
			}
			this.$emit('open', event)
		},

		/**
		 * Open the copy of the attachment in the "Mail attachments" folder of the user's files,
		 * made when the message was opened or else now.
		 */
		async openInOffice() {
			if (this.canOpenInText && hasTextViewer(window.OCA?.Viewer)) {
				await this.openInViewer()
				return
			}
			this.openingInOffice = true
			// Open the tab now: browsers block a new tab opened after an await
			const tab = window.open('', '_blank')
			try {
				const file = await this.getViewingCopy()
				const url = this.canOpenInText
					? fileUrl(file.fileId)
					: officeUrl(file.fileId, Object.keys(window.OC?.appswebroots ?? {}))
				if (tab) {
					tab.location = url
				} else {
					window.location = url
				}
			} catch (error) {
				tab?.close()
				Logger.error('could not open the attachment in office', { error })
				this.showOpenError(error)
			} finally {
				this.openingInOffice = false
			}
		},

		async openInViewer() {
			this.openingInOffice = true
			try {
				const file = await this.getViewingCopy()
				window.OCA.Viewer.open({ path: file.path })
			} catch (error) {
				Logger.error('could not open the attachment in the viewer', { error })
				this.showOpenError(error)
			} finally {
				this.openingInOffice = false
			}
		},

		async getViewingCopy() {
			return this.viewingCopy
				?? await saveAttachmentForViewing(this.messageId, this.id, '/' + t('mail', 'Mail attachments'))
		},

		showOpenError(error) {
			if (error?.response?.status === HTTP_UNPROCESSABLE_ENTITY) {
				showError(t('mail', 'The attachment was refused by the server, for example because it contains a virus'))
				return
			}
			showError(t('mail', 'Could not open the attachment'))
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

.message-attachment--blocked .attachment-size {
	color: var(--color-text-error);
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

.attachment-audio {
	width: 100%;
	height: 36px;
	margin-top: 4px;
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
