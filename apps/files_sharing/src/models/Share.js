/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

export default class Share {

	#share;

	/**
	 * Create the share object
	 *
	 * @param {Object} ocsData ocs request response
	 */
	constructor(ocsData) {
		if (ocsData.ocs && ocsData.ocs.data && ocsData.ocs.data[0]) {
			ocsData = ocsData.ocs.data[0]
		}

		// convert int into boolean
		ocsData.hide_download = !!ocsData.hide_download
		ocsData.mail_send = !!ocsData.mail_send

		// store state
		this.#share = ocsData
	}

	/**
	 * Get the share state
	 * ! used for reactivity purpose
	 * Do not remove. It allow vuejs to
	 * inject its watchers into the #share
	 * state and make the whole class reactive
	 *
	 * @returns {Object} the share raw state
	 * @readonly
	 * @memberof Sidebar
	 */
	get state() {
		return this.#share
	}

	/**
	 * get the share id
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get id() {
		return this.#share.id
	}

	/**
	 * Get the share type
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get type() {
		return this.#share.share_type
	}

	/**
	 * Get the share permissions
	 * See OC.PERMISSION_* variables
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get permissions() {
		return this.#share.permissions
	}

	/**
	 * Set the share permissions
	 * See OC.PERMISSION_* variables
	 *
	 * @param {int} permissions valid permission, See OC.PERMISSION_* variables
	 * @memberof Share
	 */
	set permissions(permissions) {
		this.#share.permissions = permissions
	}

	// SHARE OWNER --------------------------------------------------
	/**
	 * Get the share owner uid
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get owner() {
		return this.#share.uid_owner
	}

	/**
	 * Get the share owner's display name
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get ownerDisplayName() {
		return this.#share.displayname_owner
	}

	// SHARED WITH --------------------------------------------------
	/**
	 * Get the share with entity uid
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get shareWith() {
		return this.#share.share_with
	}

	/**
	 * Get the share with entity display name
	 * fallback to its uid if none
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get shareWithDisplayName() {
		return this.#share.share_with_displayname
			|| this.#share.share_with
	}

	/**
	 * Get the share with avatar if any
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get shareWithAvatar() {
		return this.#share.share_with_avatar
	}

	// SHARED FILE OR FOLDER OWNER ----------------------------------
	/**
	 * Get the shared item owner uid
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get uidFileOwner() {
		return this.#share.uid_file_owner
	}

	/**
	 * Get the shared item display name
	 * fallback to its uid if none
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get displaynameFileOwner() {
		return this.#share.displayname_file_owner
			|| this.#share.uid_file_owner
	}

	// TIME DATA ----------------------------------------------------
	/**
	 * Get the share creation timestamp
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get createdTime() {
		return this.#share.stime
	}

	/**
	 * Get the expiration date as a string format
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get expireDate() {
		return this.#share.expiration
	}

	/**
	 * Set the expiration date as a string format
	 * e.g. YYYY-MM-DD
	 *
	 * @param {string} date the share expiration date
	 * @memberof Share
	 */
	set expireDate(date) {
		this.#share.expiration = date
	}

	// EXTRA DATA ---------------------------------------------------
	/**
	 * Get the public share token
	 *
	 * @returns {string} the token
	 * @readonly
	 * @memberof Share
	 */
	get token() {
		return this.#share.token
	}

	/**
	 * Get the share note if any
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get note() {
		return this.#share.note
	}

	/**
	 * Set the share note if any
	 *
	 * @param {string} note the note
	 * @memberof Share
	 */
	set note(note) {
		this.#share.note = note
	}

	/**
	 * Have a mail been sent
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get mailSend() {
		return this.#share.mail_send === true
	}

	/**
	 * Hide the download button on public page
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get hideDownload() {
		return this.#share.hide_download === true
	}

	/**
	 * Hide the download button on public page
	 *
	 * @param {boolean} state hide the button ?
	 * @memberof Share
	 */
	set hideDownload(state) {
		this.#share.hide_download = state === true
	}

	/**
	 * Password protection of the share
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get password() {
		return this.#share.password
	}

	/**
	 * Password protection of the share
	 *
	 * @param {string} password the share password
	 * @memberof Share
	 */
	set password(password) {
		this.#share.password = password
	}

	/**
	 * Password protection by Talk of the share
	 *
	 * @returns {Boolean}
	 * @readonly
	 * @memberof Share
	 */
	get sendPasswordByTalk() {
		return this.#share.send_password_by_talk
	}

	/**
	 * Password protection by Talk of the share
	 *
	 * @param {Boolean} sendPasswordByTalk whether to send the password by Talk
	 *        or not
	 * @memberof Share
	 */
	set sendPasswordByTalk(sendPasswordByTalk) {
		this.#share.send_password_by_talk = sendPasswordByTalk
	}

	// SHARED ITEM DATA ---------------------------------------------
	/**
	 * Get the shared item absolute full path
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get path() {
		return this.#share.path
	}

	/**
	 * Return the item type: file or folder
	 *
	 * @returns {string} 'folder' or 'file'
	 * @readonly
	 * @memberof Share
	 */
	get itemType() {
		return this.#share.item_type
	}

	/**
	 * Get the shared item mimetype
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get mimetype() {
		return this.#share.mimetype
	}

	/**
	 * Get the shared item id
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get fileSource() {
		return this.#share.file_source
	}

	/**
	 * Get the target path on the receiving end
	 * e.g the file /xxx/aaa will be shared in
	 * the receiving root as /aaa, the fileTarget is /aaa
	 *
	 * @returns {string}
	 * @readonly
	 * @memberof Share
	 */
	get fileTarget() {
		return this.#share.file_target
	}

	/**
	 * Get the parent folder id if any
	 *
	 * @returns {int}
	 * @readonly
	 * @memberof Share
	 */
	get fileParent() {
		return this.#share.file_parent
	}

	// PERMISSIONS Shortcuts
	/**
	 * Does this share have CREATE permissions
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get hasCreatePermission() {
		return !!((this.permissions & OC.PERMISSION_CREATE))
	}

	/**
	 * Does this share have DELETE permissions
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get hasDeletePermission() {
		return !!((this.permissions & OC.PERMISSION_DELETE))
	}

	/**
	 * Does this share have UPDATE permissions
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get hasUpdatePermission() {
		return !!((this.permissions & OC.PERMISSION_UPDATE))
	}

	/**
	 * Does this share have SHARE permissions
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get hasSharePermission() {
		return !!((this.permissions & OC.PERMISSION_SHARE))
	}

	// PERMISSIONS Shortcuts for the CURRENT USER
	// ! the permissions above are the share settings,
	// ! meaning the permissions for the recipient
	/**
	 * Can the current user EDIT this share ?
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get canEdit() {
		return this.#share.can_edit === true
	}

	/**
	 * Can the current user DELETE this share ?
	 *
	 * @returns {boolean}
	 * @readonly
	 * @memberof Share
	 */
	get canDelete() {
		return this.#share.can_delete === true
	}

	// TODO: SORT THOSE PROPERTIES
	get label() {
		return this.#share.label
	}

	get parent() {
		return this.#share.parent
	}

	get storageId() {
		return this.#share.storage_id
	}

	get storage() {
		return this.#share.storage
	}

	get itemSource() {
		return this.#share.item_source
	}

}
