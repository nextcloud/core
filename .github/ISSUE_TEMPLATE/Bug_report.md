---
name: 🐛 Bug report
about: Help us improving by reporting a bug
labels: bug, 0. Needs triage
---

<!--
Thanks for reporting issues back to Nextcloud!

Note: This is the **issue tracker of Nextcloud**, please do NOT use this to get answers to your questions or get help for fixing your installation. This is a place to report bugs to developers, after your server has been debugged. You can find help debugging your system on our home user forums: https://help.nextcloud.com or, if you use Nextcloud in a large organization, ask our engineers on https://portal.nextcloud.com. See also  https://nextcloud.com/support for support options.

Nextcloud is an open source project backed by Nextcloud GmbH. Most of our volunteers are home users and thus primarily care about issues that affect home users. Our paid engineers prioritize issues of our customers. If you are neither a home user nor a customer, consider paying somebody to fix your issue, do it yourself or become a customer.

Guidelines for submitting issues:

* Please search the existing issues first, it's likely that your issue was already reported or even fixed.
    - Go to https://github.com/nextcloud and type any word in the top search/command bar. You probably see something like "We couldn’t find any repositories matching ..." then click "Issues" in the left navigation.
    - You can also filter by appending e. g. "state:open" to the search string.
    - More info on search syntax within github: https://help.github.com/articles/searching-issues
    
* This repository https://github.com/nextcloud/server/issues is *only* for issues within the Nextcloud Server code. This also includes the apps: files, encryption, external storage, sharing, deleted files, versions, LDAP, and WebDAV Auth
  
* SECURITY: Report any potential security bug to us via our HackerOne page (https://hackerone.com/nextcloud) following our security policy (https://nextcloud.com/security/) instead of filing an issue in our bug tracker.  

* The issues in other components should be reported in their respective repositories: You will find them in our GitHub Organization (https://github.com/nextcloud/)
  
* You can also use the Issue Template app to prefill most of the required information: https://apps.nextcloud.com/apps/issuetemplate
-->

<!--- Please keep this note for other contributors -->

### How to use GitHub

* Please use the 👍 [reaction](https://blog.github.com/2016-03-10-add-reactions-to-pull-requests-issues-and-comments/) to show that you are affected by the same issue.
* Please don't comment if you have no relevant information to add. It's just extra noise for everyone subscribed to this issue.
* Subscribe to receive notifications on status change and new comments. 


### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen

### Actual behaviour
Tell us what happens instead

### Server configuration

**Operating system:**

**Web server:**

**Database:**

**PHP version:**

**Nextcloud version:** (see Nextcloud admin page)

**Updated from an older Nextcloud/ownCloud or fresh install:**

**Where did you install Nextcloud from:**

**Signing status:**
<details>
<summary>Signing status</summary>

```
Login as admin user into your Nextcloud and access 
http://example.com/index.php/settings/integrity/failed 
paste the results here.
```
</details>

**List of activated apps:**
<details>
<summary>App list</summary>

```
If you have access to your command line run e.g.:
sudo -u www-data php occ app:list
from within your Nextcloud installation folder
```
</details>

**Nextcloud configuration:**
<details>
<summary>Config report</summary>

```
If you have access to your command line run e.g.:
sudo -u www-data php occ config:list system
from within your Nextcloud installation folder

or 

Insert your config.php content here. 
Make sure to remove all sensitive content such as passwords. (e.g. database password, passwordsalt, secret, smtp password, …)
```
</details>

**Are you using external storage, if yes which one:** local/smb/sftp/...

**Are you using encryption:** yes/no

**Are you using an external user-backend, if yes which one:** LDAP/ActiveDirectory/Webdav/...

#### LDAP configuration (delete this part if not used)
<details>
<summary>LDAP config</summary>

```
With access to your command line run e.g.:
sudo -u www-data php occ ldap:show-config
from within your Nextcloud installation folder

Without access to your command line download the data/owncloud.db to your local
computer or access your SQL server remotely and run the select query:
SELECT * FROM `oc_appconfig` WHERE `appid` = 'user_ldap';


Eventually replace sensitive data as the name/IP-address of your LDAP server or groups.
```
</details>

### Client configuration
**Browser:**

**Operating system:**

### Logs

<!--- Reports without logs might be closed as unqualified reports! -->

#### Web server error log
<details>
<summary>Web server error log</summary>

```
Insert your webserver log here
```
</details>

#### Nextcloud log (data/nextcloud.log)
<details>
<summary>Nextcloud log</summary>

```
Insert your Nextcloud log here
```
</details>

#### Browser log
<details>
<summary>Browser log</summary>

```
Insert your browser log here, this could for example include:

a) The javascript console log
b) The network log
c) ...
```
</details>
