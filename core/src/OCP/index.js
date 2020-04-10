import * as AppConfig from './appconfig'
import * as Comments from './comments'
import Loader from './loader'
import { loadState } from '@nextcloud/initial-state'
import Collaboration from './collaboration'
import * as WhatsNew from './whatsnew'
import Toast from './toast'

/** @namespace OCP */
export default {
	AppConfig,
	Collaboration,
	Comments,
	InitialState: {
		/**
		 * @deprecated 18.0.0 add https://www.npmjs.com/package/@nextcloud/initial-state to your app
		 */
		loadState,
	},
	Loader,
	/**
	 * @deprecated 19.0.0 use the `@nextcloud/dialogs` package instead
	 */
	Toast,
	WhatsNew,
}
