## Submitting issues

If you have questions about how to install or use Nextcloud, please direct these to our [forum][forum]. We are also available on [IRC][irc].

### Short version

 * The [**issue templates can be found here**][templates] but be aware of the different repositories! See list below. Please always use an issue template when reporting issues.

### Guidelines
* Please search the existing issues first, it's likely that your issue was already reported or even fixed.
  - Go to one of the repositories, click "issues" and type any word in the top search/command bar.
  - You can also filter by appending e. g. "state:open" to the search string.
  - More info on [search syntax within github](https://help.github.com/articles/searching-issues)
* This repository ([server](https://github.com/nextcloud/server/issues)) is *only* for issues within the Nextcloud Server code. This also includes the apps: files, encryption, external storage, sharing, deleted files, versions, LDAP, and WebDAV Auth
* __SECURITY__: Report any potential security bug to us via [our HackerOne page](https://hackerone.com/nextcloud) following our [security policy](https://nextcloud.com/security/) instead of filing an issue in our bug tracker.
* The issues in other components should be reported in their respective repositories: You will find them in our [GitHub Organization](https://github.com/nextcloud/)

* Report the issue using one of our [templates][templates], they include all the information we need to track down the issue.

Help us to maximize the effort we can spend fixing issues and adding new features, by not reporting duplicate issues.

[templates]: ./ISSUE_TEMPLATE
[forum]: https://help.nextcloud.com/
[irc]: https://webchat.freenode.net/?channels=nextcloud

## Contributing to Source Code

Thanks for wanting to contribute source code to Nextcloud. That's great!

Please read the [Developer Manuals][devmanual] to learn how to create your first application or how to test the Nextcloud code with PHPUnit.

### Tests

In order to constantly increase the quality of our software we can no longer accept pull request which submit un-tested code.
It is a must have that changed and added code segments are unit tested.
In some areas unit testing is hard (aka almost impossible) as of today - in these areas refactoring WHILE fixing a bug is encouraged to enable unit testing.

### Sign your work

We use the Developer Certificate of Origin (DCO) as a additional safeguard
for the Nextcloud project. This is a well established and widely used
mechanism to assure contributors have confirmed their right to license
their contribution under the project's license.
Please read [contribute/developer-certificate-of-origin][dcofile].
If you can certify it, then just add a line to every git commit message:

````
  Signed-off-by: Random J Developer <random@developer.example.org>
````

Use your real name (sorry, no pseudonyms or anonymous contributions).
If you set your `user.name` and `user.email` git configs, you can sign your
commit automatically with `git commit -s`. You can also use git [aliases](https://git-scm.com/book/tr/v2/Git-Basics-Git-Aliases)
like `git config --global alias.ci 'commit -s'`. Now you can commit with
`git ci` and the commit will be signed.

### Apply a license

In case you are not sure how to add or update the license header correctly please have a look at [contribute/HowToApplyALicense.md][applyalicense]

[devmanual]: https://docs.nextcloud.com/server/latest/developer_manual/
[dcofile]: https://github.com/nextcloud/server/blob/master/contribute/developer-certificate-of-origin
[applyalicense]: https://github.com/nextcloud/server/blob/master/contribute/HowToApplyALicense.md

## Translations
Please submit translations via [Transifex][transifex].

[transifex]: https://www.transifex.com/nextcloud
