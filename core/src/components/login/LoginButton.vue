<!--
  - @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div id="submit-wrapper" @click="$emit('click')">
		<input id="submit-form"
			type="submit"
			class="login primary"
			title=""
			:value="!loading ? t('core', 'Log in') : t('core', 'Logging in …')">
		<div class="submit-icon"
			:class="{
				'icon-confirm-white': !loading,
				'icon-loading-small': loading && invertedColors,
				'icon-loading-small-dark': loading && !invertedColors,
			}" />
	</div>
</template>

<script>
export default {
	name: 'LoginButton',
	props: {
		loading: {
			type: Boolean,
			required: true,
		},
		invertedColors: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style scoped>

</style>
