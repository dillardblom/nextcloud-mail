<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="shortcut-settings">
		<p class="shortcut-settings__hint">
			{{ t('mail', 'Click a key and press the new key to change it.') }}
		</p>
		<ul>
			<li v-for="(label, action) in labels" :key="action" class="shortcut-settings__row">
				<span class="shortcut-settings__label">{{ label }}</span>
				<NcButton
					variant="secondary"
					:aria-label="t('mail', 'Change the key for {action}', { action: label })"
					@click="startRecording(action)"
					@keydown.native="onKeydown($event, action)">
					<kbd v-if="recording !== action">{{ keyLabel(shortcuts[action]) }}</kbd>
					<span v-else>{{ t('mail', 'Press a key …') }}</span>
				</NcButton>
			</li>
		</ul>
		<p v-if="message" class="shortcut-settings__message" role="alert">
			{{ message }}
		</p>
		<NcButton variant="tertiary" :disabled="!customized" @click="reset">
			{{ t('mail', 'Restore default keys') }}
		</NcButton>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { mapStores } from 'pinia'
import logger from '../logger.js'
import useMainStore from '../store/mainStore.js'
import { DEFAULT_SHORTCUTS, effectiveShortcuts, shortcutKeyFromEvent, shortcutKeyLabel, shortcutLabels } from '../util/shortcuts.js'

export default {
	name: 'ShortcutSettings',
	components: {
		NcButton,
	},

	data() {
		return {
			recording: null,
			message: '',
		}
	},

	computed: {
		...mapStores(useMainStore),

		labels() {
			return shortcutLabels()
		},

		shortcuts() {
			return effectiveShortcuts(this.mainStore.getPreference('shortcuts'))
		},

		customized() {
			return Object.keys(DEFAULT_SHORTCUTS).some((action) => this.shortcuts[action] !== DEFAULT_SHORTCUTS[action])
		},
	},

	methods: {
		keyLabel(key) {
			return shortcutKeyLabel(key)
		},

		startRecording(action) {
			this.recording = this.recording === action ? null : action
			this.message = ''
		},

		async onKeydown(event, action) {
			if (this.recording !== action) {
				return
			}
			event.preventDefault()
			event.stopPropagation()
			if (event.key === 'Escape') {
				this.recording = null
				return
			}
			const key = shortcutKeyFromEvent(event)
			if (key === null) {
				this.message = t('mail', 'Use a letter, digit, arrow key or Delete, without Ctrl, Alt or Cmd.')
				return
			}
			const taken = Object.entries(this.shortcuts).find(([other, otherKey]) => other !== action && otherKey === key)
			if (taken) {
				this.message = t('mail', '{key} is already used for {action}.', { key: shortcutKeyLabel(key), action: this.labels[taken[0]] })
				return
			}
			this.recording = null
			this.message = ''
			await this.save({ ...this.shortcuts, [action]: key })
		},

		async reset() {
			this.message = ''
			await this.save({ ...DEFAULT_SHORTCUTS })
		},

		/**
		 * Store only the keys that differ from the defaults, so later default changes still apply
		 *
		 * @param {Record<string, string>} shortcuts action => key
		 */
		async save(shortcuts) {
			const own = Object.fromEntries(Object.entries(shortcuts).filter(([action, key]) => DEFAULT_SHORTCUTS[action] !== key))
			try {
				await this.mainStore.savePreference({ key: 'shortcuts', value: JSON.stringify(own) })
			} catch (error) {
				logger.error('could not save the shortcuts', { error })
				this.message = t('mail', 'Could not save the shortcuts')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.shortcut-settings {
	&__hint {
		color: var(--color-text-maxcontrast);
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: calc(var(--default-grid-baseline) * 2);
		padding: calc(var(--default-grid-baseline) / 2) 0;
	}

	&__message {
		color: var(--color-error-text);
		margin: calc(var(--default-grid-baseline) * 2) 0;
	}
}
</style>
