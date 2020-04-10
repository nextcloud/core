<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<div />
</template>
<script>
export default {
	name: 'LegacyView',
	props: {
		component: {
			type: Object,
			required: true,
		},
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},
	watch: {
		fileInfo(fileInfo) {
			// update the backbone model FileInfo
			this.setFileInfo(fileInfo)
		},
	},
	mounted() {
		// append the backbone element and set the FileInfo
		this.component.$el.replaceAll(this.$el)
		this.setFileInfo(this.fileInfo)
	},
	methods: {
		setFileInfo(fileInfo) {
			this.component.setFileInfo(new OCA.Files.FileInfoModel(fileInfo))
		},
	},
}
</script>
<style>
</style>
