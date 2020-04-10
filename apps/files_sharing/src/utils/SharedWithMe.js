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

/**
 * Get the shared with me title
 *
 * @param {Share} share current share
 * @returns {string} the title
 */
const shareWithTitle = function(share) {
	if (share.type === OC.Share.SHARE_TYPE_GROUP) {
		return t(
			'files_sharing',
			'Shared with you and the group {group} by {owner}',
			{
				group: share.shareWithDisplayName,
				owner: share.ownerDisplayName,
			},
			undefined,
			{ escape: false }
		)
	} else if (share.type === OC.Share.SHARE_TYPE_CIRCLE) {
		return t(
			'files_sharing',
			'Shared with you and {circle} by {owner}',
			{
				circle: share.shareWithDisplayName,
				owner: share.ownerDisplayName,
			},
			undefined,
			{ escape: false }
		)
	} else if (share.type === OC.Share.SHARE_TYPE_ROOM) {
		if (share.shareWithDisplayName) {
			return t(
				'files_sharing',
				'Shared with you and the conversation {conversation} by {owner}',
				{
					conversation: share.shareWithDisplayName,
					owner: share.ownerDisplayName,
				},
				undefined,
				{ escape: false }
			)
		} else {
			return t(
				'files_sharing',
				'Shared with you in a conversation by {owner}',
				{
					owner: share.ownerDisplayName,
				},
				undefined,
				{ escape: false }
			)
		}
	} else {
		return t(
			'files_sharing',
			'Shared with you by {owner}',
			{ owner: share.ownerDisplayName },
			undefined,
			{ escape: false }
		)
	}
}

export { shareWithTitle }
