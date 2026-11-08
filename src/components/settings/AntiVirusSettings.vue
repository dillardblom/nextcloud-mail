<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form class="antivirus-settings" @submit.prevent.stop="save">
		<NcCheckboxRadioSwitch v-model="settings.enabled" type="switch">
			{{ t('mail', 'Scan received messages with attachments for viruses') }}
		</NcCheckboxRadioSwitch>

		<NcTextField
			v-model="settings.clamdAddress"
			class="antivirus-settings__address"
			:label="t('mail', 'ClamAV daemon')"
			:placeholder="effectiveClamdAddress ?? 'tcp://clamav:3310'"
			:helper-text="addressHelperText" />

		<p class="antivirus-settings__label">
			{{ t('mail', 'When a virus is found, move the message to') }}
		</p>
		<NcCheckboxRadioSwitch
			v-for="action in actions"
			:key="action.value"
			v-model="settings.infectedAction"
			type="radio"
			name="mail_antivirus_action"
			:value="action.value">
			{{ action.label }}
		</NcCheckboxRadioSwitch>

		<p class="antivirus-settings__label">
			{{ t('mail', 'Attachments') }}
		</p>
		<NcCheckboxRadioSwitch v-model="settings.allowInfectedDownload" type="switch">
			{{ t('mail', 'Let users still download, open and forward attachments in which a virus was found') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch v-model="settings.allowUnscanned" type="switch">
			{{ t('mail', 'Make attachments that could not be scanned available with a warning instead of blocking them') }}
		</NcCheckboxRadioSwitch>

		<div class="antivirus-settings__buttons">
			<NcButton variant="primary" type="submit" :disabled="saving">
				<template #icon>
					<NcLoadingIcon v-if="saving" :size="20" />
				</template>
				{{ t('mail', 'Save') }}
			</NcButton>
			<NcButton :disabled="testing" @click="test">
				<template #icon>
					<NcLoadingIcon v-if="testing" :size="20" />
				</template>
				{{ t('mail', 'Test connection') }}
			</NcButton>
		</div>
	</form>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import logger from '../../logger.js'
import { testAntiVirus, updateAntiVirusSettings } from '../../service/SettingsService.js'

export default {
	name: 'AntiVirusSettings',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
	},

	data() {
		const { effectiveClamdAddress, ...settings } = loadState('mail', 'antivirus_settings')
		return {
			settings,
			effectiveClamdAddress,
			saving: false,
			testing: false,
			actions: [
				{ value: 'junk', label: t('mail', 'Junk') },
				{ value: 'trash', label: t('mail', 'Trash') },
				{ value: 'none', label: t('mail', 'Leave it where it is') },
			],
		}
	},

	computed: {
		addressHelperText() {
			if (this.settings.clamdAddress !== '') {
				return t('mail', 'As tcp://host:port or unix:///path/to/clamd.sock')
			}
			if (this.effectiveClamdAddress) {
				return t('mail', 'Empty: the daemon of the Antivirus for files app is used ({address})', { address: this.effectiveClamdAddress })
			}
			return t('mail', 'As tcp://host:port or unix:///path/to/clamd.sock. Without a daemon, no attachment can be scanned.')
		},
	},

	methods: {
		async save() {
			this.saving = true
			try {
				const result = await updateAntiVirusSettings(this.settings)
				this.effectiveClamdAddress = result.effectiveClamdAddress
				showSuccess(t('mail', 'Virus scan settings saved'))
			} catch (error) {
				logger.error('Could not save the virus scan settings', { error })
				showError(t('mail', 'Could not save the virus scan settings'))
			} finally {
				this.saving = false
			}
		},

		async test() {
			this.testing = true
			try {
				const result = await testAntiVirus()
				if (result.reachable) {
					showSuccess(t('mail', 'ClamAV answered'))
				} else {
					logger.warn('ClamAV is not reachable', { message: result.message })
					showError(t('mail', 'ClamAV could not be reached. Save the settings first when you changed the daemon.'))
				}
			} catch (error) {
				logger.error('Could not test the ClamAV connection', { error })
				showError(t('mail', 'ClamAV could not be reached. Save the settings first when you changed the daemon.'))
			} finally {
				this.testing = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.antivirus-settings {
	&__address {
		max-width: calc(var(--default-clickable-area) * 10);
		margin-block: var(--default-grid-baseline);
	}

	&__label {
		margin-top: calc(var(--default-grid-baseline) * 3);
		font-weight: bold;
	}

	&__buttons {
		display: flex;
		gap: var(--default-grid-baseline);
		margin-top: calc(var(--default-grid-baseline) * 3);
	}
}
</style>
