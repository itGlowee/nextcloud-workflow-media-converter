<template>
	<div class="wmc-rules">
		<div class="mb" style="margin-bottom: 1.5em;">
			<label>{{ t('workflow_media_converter', 'Convert to format') }}</label>
			<select v-model="outputExtension">
				<option v-for="format in formats"
					:key="format.extension"
					:value="format.extension">
					(.{{ format.extension }}) {{ format.label }}
				</option>
			</select>
		</div>
		<PostConversionRules v-model="config" />
		<div class="grid" style="margin-top: 1.5em; margin-bottom: 1.5em;">
			<div class="column">
				<label>{{ t('workflow_media_converter', 'Additional FFmpeg input flags') }}</label>
				<input v-model="additionalInputConversionFlags" type="text">
			</div>
			<div class="column">
				<label>{{ t('workflow_media_converter', 'Additional FFmpeg output flags') }}</label>
				<input v-model="additionalOutputConversionFlags" type="text">
			</div>
		</div>
		<input type="text"
			:value="commandString"
			style="background-color: #eee; color: #000"
			disabled>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateControllerUrl } from '../utils.js'
import formats from '../constants/formats.js'
import filepicker from '../mixins/filepicker.js'
import PostConversionRules from './PostConversionRules.vue'

const defaultState = {
	outputExtension: null,
	additionalInputConversionFlags: '',
	additionalOutputConversionFlags: '',
	postConversionSourceRule: 'keep',
	postConversionSourceRuleMoveFolder: null,
	postConversionOutputRule: 'keep',
	postConversionOutputRuleMoveFolder: null,
	postConversionOutputConflictRule: 'preserve',
	postConversionOutputConflictRuleMoveFolder: null,
	postConversionTimestampRule: 'conversionTime',
	tagOutputFiles: false
};

export default {
	name: 'ConvertMediaOperation',

	components: { PostConversionRules },

	mixins: [filepicker],

	props: {
		value: {
			default: null,
			type: String,
		},
	},

	data: () => ({
		formats,
		threads: 0,
		localTagOutputFiles: false,
	}),

	computed: {
		config: {
			get() {
				try {
					return JSON.parse(this.value || JSON.stringify(defaultState))
				} catch {
					return defaultState
				}
			},
			set(mutation) {
				let currentConfig = defaultState
				try {
					currentConfig = this.value ? JSON.parse(this.value) : defaultState
				} catch {
					currentConfig = defaultState
				}
				this.$emit(
					'input',
					JSON.stringify({ ...currentConfig, ...mutation }),
				)
			},
		},

		outputExtension: {
			get() {
				return this.config.outputExtension
			},
			set(outputExtension) {
				console.log("setting outputExtension to ", outputExtension);
				this.config = { outputExtension }
			},
		},

		additionalInputConversionFlags: {
			get() {
				return this.config.additionalInputConversionFlags
			},
			set(additionalInputConversionFlags) {
				this.config = { additionalInputConversionFlags }
			},
		},

		additionalOutputConversionFlags: {
			get() {
				return this.config.additionalOutputConversionFlags
			},
			set(additionalOutputConversionFlags) {
				this.config = { additionalOutputConversionFlags }
			},
		},

		commandString() {
			return [
				'ffmpeg',
				parseInt(this.threads) !== 0 ? `-threads ${this.threads}` : '',
				this.additionalInputConversionFlags ? `${this.additionalInputConversionFlags}` : '',
				'-i {input}',
				this.additionalOutputConversionFlags ? `${this.additionalOutputConversionFlags}` : '',
				'{output}',
			].filter(Boolean).join(' ')
		},
	},

	watch: {
		localTagOutputFiles(newVal) {
			console.log("localTagOutputFiles changed to", newVal);
			this.config = { tagOutputFiles: newVal }
		},
		'config.tagOutputFiles'(newVal) {
			if (this.localTagOutputFiles !== newVal) {
				this.localTagOutputFiles = newVal
			}
		},
	},

	async mounted() {
		// Initialize local data from config
		this.localTagOutputFiles = this.config.tagOutputFiles || false
		const { data } = await axios.get(generateControllerUrl('admin-settings'))

		this.threads = data.threadLimit
	},
}
</script>

<style lang="scss">
.wmc-rules {
	.multiselect {
		width: 100%;
		margin: auto;
		text-align: center;
	}

	input, select {
		width: 100%;
	}

	.mb {
		margin-bottom: 1.5em;
	}

}
</style>
