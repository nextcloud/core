<?php
/** @var OC_Theme $theme */
/** @var array $_ */

print_unescaped($l->t("Hey there,\n\nthe admin enabled server-side-encryption. Your files were encrypted using the password '%s'.\n\nPlease login to the web interface, go to the section 'basic encryption module' of your personal settings and update your encryption password by entering this password into the 'old log-in password' field and your current login-password.\n\n", [$_['password']]));
if (isset($_['expiration'])) {
	print_unescaped($l->t("The share will expire on %s.", [$_['expiration']]));
	print_unescaped("\n\n");
}
// TRANSLATORS term at the end of a mail
p($l->t("Cheers!"));
?>

	--
<?php p($theme->getName() . ' - ' . $theme->getSlogan()); ?>
<?php print_unescaped("\n".$theme->getBaseUrl());
