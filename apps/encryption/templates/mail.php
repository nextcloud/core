<?php
/** @var OC_Theme $theme */
/** @var array $_ */
?>
<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr><td>
			<table cellspacing="0" cellpadding="0" border="0" width="600px">
				<tr>
					<td colspan="2" bgcolor="<?php p($theme->getColorPrimary());?>">
						<img src="<?php p(\OC::$server->getURLGenerator()->getAbsoluteURL(image_path('', 'logo-mail.png'))); ?>" alt="<?php p($theme->getName()); ?>"/>
					</td>
				</tr>
				<tr><td colspan="2">&nbsp;</td></tr>
				<tr>
					<td width="20px">&nbsp;</td>
					<td style="font-weight:normal; font-size:0.8em; line-height:1.2em; font-family:verdana,'arial',sans;">
						<?php
						print_unescaped($l->t('Hey there,<br><br>the admin enabled server-side-encryption. Your files were encrypted using the password <strong>%s</strong>.<br><br>Please login to the web interface, go to the section "basic encryption module" of your personal settings and update your encryption password by entering this password into the "old log-in password" field and your current login-password.<br><br>', [$_['password']]));
						// TRANSLATORS term at the end of a mail
						p($l->t('Cheers!'));
						?>
					</td>
				</tr>
				<tr><td colspan="2">&nbsp;</td></tr>
				<tr>
					<td width="20px">&nbsp;</td>
					<td style="font-weight:normal; font-size:0.8em; line-height:1.2em; font-family:verdana,'arial',sans;">--<br>
						<?php p($theme->getName()); ?> -
						<?php p($theme->getSlogan()); ?>
						<br><a href="<?php p($theme->getBaseUrl()); ?>"><?php p($theme->getBaseUrl());?></a>
					</td>
				</tr>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</table>
		</td></tr>
</table>
