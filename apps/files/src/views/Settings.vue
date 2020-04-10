<!--
  - @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
  -
  - @author Gary Kim <gary@garykim.dev>
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
	<div id="files-app-extra-settings">
		<template v-for="setting in settings">
			<Setting :key="setting.name" :el="setting.el" />
		</template>
	</div>
</template>

<script>
import Setting from '../components/Setting'

export default {
	name: 'Settings',
	components: {
		Setting,
	},
	data() {
		return {
			settings: OCA.Files.Settings.settings,
		}
	},
}
</script>

<style lang="scss" scoped>

</style>
