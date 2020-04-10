<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/** @var \OCP\IL10N $l */
/** @var array $_ */

?>

<div class="section" id="backgroundjobs">
	<h2 class="inlineblock"><?php p($l->t('Background jobs'));?></h2>
	<p class="cronlog inlineblock">
		<?php if ($_['lastcron'] !== false) {
			$relative_time = relative_modified_date($_['lastcron']);
			$maxAgeRelativeTime = relative_modified_date($_['cronMaxAge']);

			$formatter = \OC::$server->getDateTimeFormatter();
			$absolute_time = $formatter->formatDateTime($_['lastcron'], 'long', 'long');
			$maxAgeAbsoluteTime = $formatter->formatDateTime($_['cronMaxAge'], 'long', 'long');
			if (time() - $_['lastcron'] > 600) { ?>
				<span class="status error"></span>
				<span class="crondate" title="<?php p($absolute_time);?>">
					<?php p($l->t("Last job execution ran %s. Something seems wrong.", [$relative_time]));?>
				</span>
			<?php } else if (time() - $_['cronMaxAge'] > 12*3600) {
					if ($_['backgroundjobs_mode'] === 'cron') { ?>
						<span class="status warning"></span>
						<span class="crondate" title="<?php p($maxAgeAbsoluteTime);?>">
							<?php p($l->t("Some jobs haven’t been executed since %s. Please consider increasing the execution frequency.", [$maxAgeRelativeTime]));?>
						</span>
					<?php } else { ?>
						<span class="status error"></span>
						<span class="crondate" title="<?php p($maxAgeAbsoluteTime);?>">
							<?php p($l->t("Some jobs didn’t execute since %s. Please consider switching to system cron.", [$maxAgeRelativeTime]));?>
						</span>
					<?php }
			} else { ?>
				<span class="status success"></span>
				<span class="crondate" title="<?php p($absolute_time);?>">
					<?php p($l->t("Last job ran %s.", [$relative_time]));?>
				</span>
			<?php }
		} else { ?>
			<span class="status error"></span>
			<?php p($l->t("Background job didn’t run yet!"));
		} ?>
	</p>
	<a target="_blank" rel="noreferrer noopener" class="icon-info"
	   title="<?php p($l->t('Open documentation'));?>"
	   href="<?php p(link_to_docs('admin-background-jobs')); ?>"></a>

	<p class="settings-hint"><?php p($l->t('For optimal performance it\'s important to configure background jobs correctly. For bigger instances \'Cron\' is the recommended setting. Please see the documentation for more information.'));?></p>
	<form action="#">
		<fieldset>
			<legend class="hidden-visually"><?php p($l->t('Pick background job setting'));?></legend>
			<p>
				<input type="radio" name="mode" value="ajax" class="radio"
					   id="backgroundjobs_ajax" <?php if ($_['backgroundjobs_mode'] === "ajax") {
					print_unescaped('checked="checked"');
				} ?>>
				<label for="backgroundjobs_ajax">AJAX</label><br/>
				<em><?php p($l->t("Execute one task with each page loaded")); ?></em>
			</p>
			<p>
				<input type="radio" name="mode" value="webcron" class="radio"
					   id="backgroundjobs_webcron" <?php if ($_['backgroundjobs_mode'] === "webcron") {
					print_unescaped('checked="checked"');
				} ?>>
				<label for="backgroundjobs_webcron">Webcron</label><br/>
				<em><?php p($l->t("cron.php is registered at a webcron service to call cron.php every 5 minutes over HTTP.")); ?></em>
			</p>
			<p>
				<input type="radio" name="mode" value="cron" class="radio"
					   id="backgroundjobs_cron" <?php if ($_['backgroundjobs_mode'] === "cron") {
					print_unescaped('checked="checked"');
				}
				if (!$_['cli_based_cron_possible']) {
					print_unescaped('disabled');
				}?>>
				<label for="backgroundjobs_cron">Cron</label><br/>
				<em><?php p($l->t("Use system cron service to call the cron.php file every 5 minutes.")); ?>
					<?php if($_['cli_based_cron_possible']) {
						p($l->t('The cron.php needs to be executed by the system user "%s".', [$_['cli_based_cron_user']]));
					} else {
						print_unescaped(str_replace(
							['{linkstart}', '{linkend}'],
							['<a href="http://php.net/manual/en/book.posix.php">', ' ↗</a>'],
							$l->t('To run this you need the PHP POSIX extension. See {linkstart}PHP documentation{linkend} for more details.')
						));
				} ?></em>

			</p>
		</fieldset>
	</form>
</div>
