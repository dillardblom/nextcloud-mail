<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="list-item-content__quick-actions">
		<NcButton
			v-if="canFavorite"
			variant="tertiary"
			:title="isFavorite ? t('mail', 'Unfavorite') : t('mail', 'Favorite')"
			@click.prevent="$emit('toggle-flagged')">
			<Star v-if="isFavorite" :size="20" fill-color="#f9cf3d" />
			<StarOutline v-else :size="20" />
		</NcButton>
		<NcButton
			v-if="isRead"
			variant="tertiary"
			:title="t('mail', 'Mark as unread')"
			@click.prevent="$emit('toggle-seen')">
			<EmailRead :size="20" />
		</NcButton>
		<NcButton
			v-else
			variant="tertiary"
			:title="t('mail', 'Mark as read')"
			@click.prevent="$emit('toggle-seen')">
			<EmailUnread :size="20" />
		</NcButton>
		<NcButton
			v-if="isImportant"
			variant="tertiary"
			:title="t('mail', 'Mark as unimportant')"
			@click.prevent="$emit('toggle-important')">
			<ImportantIcon :size="20" />
		</NcButton>
		<NcButton
			v-else
			variant="tertiary"
			:title="t('mail', 'Mark as important')"
			@click.prevent="$emit('toggle-important')">
			<ImportantOutlineIcon :size="20" />
		</NcButton>
		<NcButton
			variant="tertiary"
			:title="t('mail', 'Delete thread')"
			@click.prevent="$emit('delete')">
			<IconDelete :size="20" />
		</NcButton>
	</div>
</template>

<script>

import { NcButton } from '@nextcloud/vue'
import EmailRead from 'vue-material-design-icons/EmailOpenOutline.vue'
import EmailUnread from 'vue-material-design-icons/EmailOutline.vue'
import ImportantIcon from 'vue-material-design-icons/LabelVariant.vue'
import ImportantOutlineIcon from 'vue-material-design-icons/LabelVariantOutline.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'EnvelopeSingleClickActions',
	components: {
		EmailRead,
		EmailUnread,
		ImportantIcon,
		ImportantOutlineIcon,
		IconDelete,
		NcButton,
		Star,
		StarOutline,
	},

	props: {
		/** Favorite (the IMAP \Flagged flag, which other mail clients show too) */
		isFavorite: {
			type: Boolean,
			default: false,
		},

		/** Whether the user may change the flags of the message */
		canFavorite: {
			type: Boolean,
			default: false,
		},

		isRead: {
			type: Boolean,
			default: false,
		},

		isImportant: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
.list-item-content__quick-actions {
	display: none;
}

.list-item:hover {
	.list-item-content__quick-actions {
		display: flex;
	}
}
</style>
