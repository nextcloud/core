import './share'
import './sharebreadcrumbview'

import './style/sharebreadcrumb.scss'

import './collaborationresourceshandler.js'

// eslint-disable-next-line camelcase
__webpack_public_path__ = OC.linkTo('files_sharing', 'js/dist/')
// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(OC.requestToken)

window.OCA.Sharing = OCA.Sharing
