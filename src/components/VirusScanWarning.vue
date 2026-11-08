<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcNoteCard v-if="text" :type="isInfected ? 'error' : 'warning'" class="virus-scan-warning">
		<p>{{ text }}</p>
	</NcNoteCard>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

export default {
	name: 'VirusScanWarning',
	components: {
		NcNoteCard,
	},

	props: {
		scan: {
			type: Object,
			required: true,
		},
	},

	computed: {
		isInfected() {
			return this.scan.status === 'infected'
		},

		text() {
			const signature = this.scan.signature ?? ''
			if (this.isInfected && this.scan.blocked) {
				return t('mail', 'This message contains a virus ({signature}). Its attachments are blocked.', { signature })
			}
			if (this.isInfected) {
				return t('mail', 'This message contains a virus ({signature}). Do not open its attachments.', { signature })
			}
			if (this.scan.status !== 'unscannable') {
				return ''
			}
			if (this.scan.blocked) {
				return t('mail', 'The attachments of this message could not be scanned for viruses, so they are blocked.')
			}
			return t('mail', 'The attachments of this message could not be scanned for viruses. Only open them if you trust the sender.')
		},
	},
}
</script>
