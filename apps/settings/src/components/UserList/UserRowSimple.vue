<template>
	<div
		class="row"
		:class="{'disabled': loading.delete || loading.disable}"
		:data-id="user.id">
		<div class="avatar" :class="{'icon-loading-small': loading.delete || loading.disable || loading.wipe}">
			<img v-if="!loading.delete && !loading.disable && !loading.wipe"
				alt=""
				width="32"
				height="32"
				:src="generateAvatar(user.id, 32)"
				:srcset="generateAvatar(user.id, 64)+' 2x, '+generateAvatar(user.id, 128)+' 4x'">
		</div>
		<!-- dirty hack to ellipsis on two lines -->
		<div class="name">
			{{ user.id }}
			<div class="displayName subtitle">
				<div v-tooltip="user.displayname.length > 20 ? user.displayname : ''" class="cellText">
					{{ user.displayname }}
				</div>
			</div>
		</div>
		<div />
		<div class="mailAddress">
			<div v-tooltip="user.email !== null && user.email.length > 20 ? user.email : ''" class="cellText">
				{{ user.email }}
			</div>
		</div>
		<div class="groups">
			{{ userGroupsLabels }}
		</div>
		<div v-if="subAdminsGroups.length > 0 && settings.isAdmin" class="subAdminsGroups">
			{{ userSubAdminsGroupsLabels }}
		</div>
		<div class="userQuota">
			<div class="quota">
				{{ userQuota }} ({{ usedSpace }})
				<progress
					class="quota-user-progress"
					:class="{'warn': usedQuota > 80}"
					:value="usedQuota"
					max="100" />
			</div>
		</div>
		<div v-if="showConfig.showLanguages" class="languages">
			{{ userLanguage.name }}
		</div>
		<div v-if="showConfig.showUserBackend || showConfig.showStoragePath" class="userBackend">
			<div v-if="showConfig.showUserBackend" class="userBackend">
				{{ user.backend }}
			</div>
			<div v-if="showConfig.showStoragePath" v-tooltip="user.storageLocation" class="storageLocation subtitle">
				{{ user.storageLocation }}
			</div>
		</div>
		<div v-if="showConfig.showLastLogin" v-tooltip.auto="userLastLoginTooltip" class="lastLogin">
			{{ userLastLogin }}
		</div>

		<div class="userActions">
			<div v-if="canEdit && !loading.all" class="toggleUserActions">
				<Actions>
					<ActionButton icon="icon-rename" @click="toggleEdit">
						{{ t('settings', 'Edit User') }}
					</ActionButton>
				</Actions>
				<div class="userPopoverMenuWrapper">
					<button
						v-click-outside="hideMenu"
						class="icon-more"
						:aria-label="t('settings', 'Toggle user actions menu')"
						@click.prevent="$emit('toggleMenu')" />
					<div class="popovermenu" :class="{ 'open': openedMenu }" :aria-expanded="openedMenu">
						<PopoverMenu :menu="userActions" />
					</div>
				</div>
			</div>
			<div class="feedback" :style="{opacity: feedbackMessage !== '' ? 1 : 0}">
				<div class="icon-checkmark" />
				{{ feedbackMessage }}
			</div>
		</div>
	</div>
</template>

<script>
import PopoverMenu from '@nextcloud/vue/dist/Components/PopoverMenu'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ClickOutside from 'vue-click-outside'
import { getCurrentUser } from '@nextcloud/auth'
import UserRowMixin from '../../mixins/UserRowMixin'
export default {
	name: 'UserRowSimple',
	components: {
		PopoverMenu,
		ActionButton,
		Actions,
	},
	directives: {
		ClickOutside,
	},
	mixins: [UserRowMixin],
	props: {
		user: {
			type: Object,
			required: true,
		},
		loading: {
			type: Object,
			required: true,
		},
		showConfig: {
			type: Object,
			required: true,
		},
		userActions: {
			type: Array,
			required: true,
		},
		openedMenu: {
			type: Boolean,
			required: true,
		},
		feedbackMessage: {
			type: String,
			required: true,
		},
		subAdminsGroups: {
			type: Array,
			required: true,
		},
		settings: {
			type: Object,
			required: true,
		},
	},
	computed: {
		userGroupsLabels() {
			return this.userGroups
				.map(group => group.name)
				.join(', ')
		},
		userSubAdminsGroupsLabels() {
			return this.userSubAdminsGroups
				.map(group => group.name)
				.join(', ')
		},
		usedSpace() {
			if (this.user.quota.used) {
				return t('settings', '{size} used', { size: OC.Util.humanFileSize(this.user.quota.used) })
			}
			return t('settings', '{size} used', { size: OC.Util.humanFileSize(0) })
		},
		canEdit() {
			return getCurrentUser().uid !== this.user.id || this.settings.isAdmin
		},
		userQuota() {
			let quota = this.user.quota.quota

			if (quota === 'default') {
				quota = this.settings.defaultQuota
				if (quota !== 'none') {
					// convert to numeric value to match what the server would usually return
					quota = OC.Util.computerFileSize(quota)
				}
			}

			// when the default quota is unlimited, the server returns -3 here, map it to "none"
			if (quota === 'none' || quota === -3) {
				return t('settings', 'Unlimited')
			} else if (quota >= 0) {
				return OC.Util.humanFileSize(quota)
			}
			return OC.Util.humanFileSize(0)
		},
	},
	methods: {
		hideMenu() {
			this.$emit('hideMenu')
		},
		toggleEdit() {
			this.$emit('update:editing', true)
		},
	},
}
</script>

<style lang="scss">
.cellText {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.icon-more {
	background-color: var(--color-main-background);
	border: 0;
}
</style>
