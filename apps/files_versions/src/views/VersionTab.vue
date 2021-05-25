<!--
  - @copyright Copyright (c) 2021 Enoch <enoch@nextcloud.com>
  -
  - @author Enoch <enoch@nextcloud.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div :class="{ 'icon-loading': loading }">
		<!-- error message -->
		<EmptyContent v-if="error" icon="icon-error">
			{{ t('files_versions', 'Cannot load versions list') }}
			<template #desc>
				{{ error }}
			</template>
		</EmptyContent>

		<!-- Versions list  -->
		<ul>
			<VersionEntry v-for="version in versionsList"
				:key="version.basename"
				:file-info="fileInfo"
				:version="version" />
		</ul>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

import VersionEntry from '../components/VersionEntry'
import { fetchFileVersions } from '../services/FileVersion'

export default {
	name: 'VersionTab',

	components: {
		EmptyContent,
		VersionEntry,
	},

	data() {
		return {
			error: '',
			loading: true,
			fileInfo: null,

			// version object
			versionsList: [],
		}
	},

	beforeMount() {
		this.getVersions()
	},

	methods: {
		/**
		 * Update current fileInfo and fetch new data
		 * @param {Object} fileInfo the current file FileInfo
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
		},

		async getVersions() {
			this.loading = true

			try {
				const fetchVersions = await fetchFileVersions(this.fileInfo.id)
				this.versionsList = fetchVersions
				console.debug(fetchVersions)
			} catch (error) {
				this.error = t('files_versions', 'There was an error fetching the list of versions for the file {file}', {
					file: this.fileInfo.basename,
				})
				showError(this.error)
				console.error(error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
