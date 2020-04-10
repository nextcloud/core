<?php

declare(strict_types=1);

/**
 * @copyright 2017, Morris Jobke <hey@morrisjobke.de>
 * @copyright 2017, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author brad2014 <brad2014@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tomasz Paluszkiewicz <tomasz.paluszkiewicz@gmail.com>
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

namespace OC\Mail;

use OCP\Defaults;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IEMailTemplate;

/**
 * Class EMailTemplate
 *
 * addBodyText and addBodyButtonGroup automatically opens the body
 * addFooter, renderHtml, renderText automatically closes the body and the HTML if opened
 *
 * @package OC\Mail
 */
class EMailTemplate implements IEMailTemplate {
	/** @var Defaults */
	protected $themingDefaults;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IL10N */
	protected $l10n;
	/** @var string */
	protected $emailId;
	/** @var array */
	protected $data;

	/** @var string */
	protected $subject = '';
	/** @var string */
	protected $htmlBody = '';
	/** @var string */
	protected $plainBody = '';
	/** @var bool indicated if the footer is added */
	protected $headerAdded = false;
	/** @var bool indicated if the body is already opened */
	protected $bodyOpened = false;
	/** @var bool indicated if there is a list open in the body */
	protected $bodyListOpened = false;
	/** @var bool indicated if the footer is added */
	protected $footerAdded = false;

	protected $head = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" style="-webkit-font-smoothing:antialiased;background:#f3f3f3!important">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width">
	<title></title>
	<style type="text/css">@media only screen{html{min-height:100%;background:#F5F5F5}}@media only screen and (max-width:610px){table.body img{width:auto;height:auto}table.body center{min-width:0!important}table.body .container{width:95%!important}table.body .columns{height:auto!important;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;padding-left:30px!important;padding-right:30px!important}th.small-12{display:inline-block!important;width:100%!important}table.menu{width:100%!important}table.menu td,table.menu th{width:auto!important;display:inline-block!important}table.menu.vertical td,table.menu.vertical th{display:block!important}table.menu[align=center]{width:auto!important}}</style>
</head>
<body style="-moz-box-sizing:border-box;-ms-text-size-adjust:100%;-webkit-box-sizing:border-box;-webkit-font-smoothing:antialiased;-webkit-text-size-adjust:100%;Margin:0;background:#f3f3f3!important;box-sizing:border-box;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;min-width:100%;padding:0;text-align:left;width:100%!important">
	<span class="preheader" style="color:#F5F5F5;display:none!important;font-size:1px;line-height:1px;max-height:0;max-width:0;mso-hide:all!important;opacity:0;overflow:hidden;visibility:hidden">
	</span>
	<table class="body" style="-webkit-font-smoothing:antialiased;Margin:0;background:#f3f3f3!important;border-collapse:collapse;border-spacing:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;height:100%;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;width:100%">
		<tr style="padding:0;text-align:left;vertical-align:top">
			<td class="center" align="center" valign="top" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
				<center data-parsed="" style="min-width:580px;width:100%">
EOF;

	protected $tail = <<<EOF
					</center>
				</td>
			</tr>
		</table>
		<!-- prevent Gmail on iOS font size manipulation -->
		<div style="display:none;white-space:nowrap;font:15px courier;line-height:0">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</div>
	</body>
</html>
EOF;

	protected $header = <<<EOF
<table align="center" class="wrapper header float-center" style="Margin:0 auto;background:#8a8a8a;background-color:%s;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td class="wrapper-inner" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:20px;text-align:left;vertical-align:top;word-wrap:break-word">
			<table align="center" class="container" style="Margin:0 auto;background:0 0;border-collapse:collapse;border-spacing:0;margin:0 auto;padding:0;text-align:inherit;vertical-align:top;width:580px">
				<tbody>
				<tr style="padding:0;text-align:left;vertical-align:top">
					<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
						<table class="row collapse" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%%">
							<tbody>
							<tr style="padding:0;text-align:left;vertical-align:top">
								<center data-parsed="" style="min-width:580px;width:100%%">
									<img class="logo float-center" src="%s" alt="%s" align="center" style="-ms-interpolation-mode:bicubic;Margin:0 auto;clear:both;display:block;float:none;margin:0 auto;outline:0;text-align:center;text-decoration:none" height="50">
								</center>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
</table>
<table class="spacer float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td height="80px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:80px;font-weight:400;hyphens:auto;line-height:80px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
	</tr>
	</tbody>
</table>
EOF;

	protected $heading = <<<EOF
<table align="center" class="container main-heading float-center" style="Margin:0 auto;background:0 0!important;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:580px">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
			<h1 class="text-center" style="Margin:0;Margin-bottom:10px;color:inherit;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:24px;font-weight:400;line-height:1.3;margin:0;margin-bottom:10px;padding:0;text-align:center;word-wrap:normal">%s</h1>
		</td>
	</tr>
	</tbody>
</table>
<table class="spacer float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td height="40px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:40px;font-weight:400;hyphens:auto;line-height:40px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
	</tr>
	</tbody>
</table>
EOF;

	protected $bodyBegin = <<<EOF
<table align="center" class="wrapper content float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%">
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td class="wrapper-inner" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
			<table align="center" class="container has-shadow" style="Margin:0 auto;background:#fefefe;border-collapse:collapse;border-spacing:0;box-shadow:0 1px 2px 0 rgba(0,0,0,.2),0 1px 3px 0 rgba(0,0,0,.1);margin:0 auto;padding:0;text-align:inherit;vertical-align:top;width:580px">
				<tbody>
				<tr style="padding:0;text-align:left;vertical-align:top">
					<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
						<table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
							<tbody>
							<tr style="padding:0;text-align:left;vertical-align:top">
								<td height="60px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:60px;font-weight:400;hyphens:auto;line-height:60px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
							</tr>
							</tbody>
						</table>
EOF;

	protected $bodyText = <<<EOF
<table class="row description" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0 auto;padding:0;padding-bottom:30px;padding-left:30px;padding-right:30px;text-align:left;width:550px">
			<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
				<tr style="padding:0;text-align:left;vertical-align:top">
					<th style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0;text-align:left">
						<p class="text-left" style="Margin:0;Margin-bottom:10px;color:#777;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;margin-bottom:10px;padding:0;text-align:left">%s</p>
					</th>
					<th class="expander" style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0!important;text-align:left;visibility:hidden;width:0"></th>
				</tr>
			</table>
		</th>
	</tr>
	</tbody>
</table>
EOF;

  // note: listBegin (like bodyBegin) is not processed through sprintf, so "%" is not escaped as "%%". (bug #12151)
	protected $listBegin = <<<EOF
<table class="row description" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0 auto;padding:0;padding-bottom:30px;padding-left:30px;padding-right:30px;text-align:left;width:550px">
			<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
EOF;

	protected $listItem = <<<EOF
				<tr style="padding:0;text-align:left;vertical-align:top">
					<td style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0;text-align:left;width:15px;">
						<p class="text-left" style="Margin:0;Margin-bottom:10px;color:#777;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;margin-bottom:10px;padding:0;padding-left:10px;text-align:left">%s</p>
					</td>
					<td style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0;text-align:left">
						<p class="text-left" style="Margin:0;Margin-bottom:10px;color:#555;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;margin-bottom:10px;padding:0;padding-left:10px;text-align:left">%s</p>
					</td>
					<td class="expander" style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0!important;text-align:left;visibility:hidden;width:0"></td>
				</tr>
EOF;

	protected $listEnd = <<<EOF
			</table>
		</th>
	</tr>
	</tbody>
</table>
EOF;

	protected $buttonGroup = <<<EOF
<table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td height="50px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:50px;font-weight:400;hyphens:auto;line-height:50px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
	</tr>
	</tbody>
</table>
<table align="center" class="row btn-group" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0 auto;padding:0;padding-bottom:30px;padding-left:30px;padding-right:30px;text-align:left;width:550px">
			<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
				<tr style="padding:0;text-align:left;vertical-align:top">
					<th style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0;text-align:left">
						<center data-parsed="" style="min-width:490px;width:100%%">
							<table class="button btn default primary float-center" style="Margin:0 0 30px 0;border-collapse:collapse;border-spacing:0;display:inline-block;float:none;margin:0 0 30px 0;margin-right:15px;max-height:40px;max-width:200px;padding:0;text-align:center;vertical-align:top;width:auto;background:%1\$s;background-color:%1\$s;color:#fefefe;">
								<tr style="padding:0;text-align:left;vertical-align:top">
									<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
										<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
											<tr style="padding:0;text-align:left;vertical-align:top">
												<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border:0 solid %2\$s;border-collapse:collapse!important;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
													<a href="%3\$s" style="Margin:0;border:0 solid %4\$s;border-radius:2px;color:%5\$s;display:inline-block;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:regular;line-height:1.3;margin:0;padding:10px 25px 10px 25px;text-align:left;outline:1px solid %6\$s;text-decoration:none">%7\$s</a>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
							<table class="button btn default secondary float-center" style="Margin:0 0 30px 0;border-collapse:collapse;border-spacing:0;display:inline-block;float:none;margin:0 0 30px 0;max-height:40px;max-width:200px;padding:0;text-align:center;vertical-align:top;width:auto">
								<tr style="padding:0;text-align:left;vertical-align:top">
									<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
										<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
											<tr style="padding:0;text-align:left;vertical-align:top">
												<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:#777;border:0 solid #777;border-collapse:collapse!important;color:#fefefe;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
													<a href="%8\$s" style="Margin:0;background-color:#fff;border:0 solid #777;border-radius:2px;color:#6C6C6C!important;display:inline-block;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:regular;line-height:1.3;margin:0;outline:1px solid #CBCBCB;padding:10px 25px 10px 25px;text-align:left;text-decoration:none">%9\$s</a>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</center>
					</th>
					<th class="expander" style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0!important;text-align:left;visibility:hidden;width:0"></th>
				</tr>
			</table>
		</th>
	</tr>
	</tbody>
</table>
EOF;

	protected $button = <<<EOF
<table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td height="50px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:50px;font-weight:400;hyphens:auto;line-height:50px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
	</tr>
	</tbody>
</table>
<table align="center" class="row btn-group" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0 auto;padding:0;padding-bottom:30px;padding-left:30px;padding-right:30px;text-align:left;width:550px">
			<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
				<tr style="padding:0;text-align:left;vertical-align:top">
					<th style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0;text-align:left">
						<center data-parsed="" style="min-width:490px;width:100%%">
							<table class="button btn default primary float-center" style="Margin:0;border-collapse:collapse;border-spacing:0;display:inline-block;float:none;margin:0;max-height:40px;padding:0;text-align:center;vertical-align:top;width:auto;background:%1\$s;color:#fefefe;background-color:%1\$s;">
								<tr style="padding:0;text-align:left;vertical-align:top">
									<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
										<table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%%">
											<tr style="padding:0;text-align:left;vertical-align:top">
												<td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border:0 solid %2\$;border-collapse:collapse!important;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
													<a href="%3\$s" style="Margin:0;border:0 solid %4\$s;border-radius:2px;color:%5\$s;display:inline-block;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:regular;line-height:1.3;margin:0;padding:10px 25px 10px 25px;text-align:left;outline:1px solid %5\$s;text-decoration:none">%7\$s</a>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</center>
					</th>
					<th class="expander" style="Margin:0;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;line-height:1.3;margin:0;padding:0!important;text-align:left;visibility:hidden;width:0"></th>
				</tr>
			</table>
		</th>
	</tr>
	</tbody>
</table>
EOF;

	protected $bodyEnd = <<<EOF

					</td>
				</tr>
				</tbody>
			</table>
		</td>
	</tr>
</table>
EOF;

	protected $footer = <<<EOF
<table class="spacer float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
	<tbody>
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td height="60px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:60px;font-weight:400;hyphens:auto;line-height:60px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
	</tr>
	</tbody>
</table>
<table align="center" class="wrapper footer float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
	<tr style="padding:0;text-align:left;vertical-align:top">
		<td class="wrapper-inner" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:1.3;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
			<center data-parsed="" style="min-width:580px;width:100%%">
				<table class="spacer float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:100%%">
					<tbody>
					<tr style="padding:0;text-align:left;vertical-align:top">
						<td height="15px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#0a0a0a;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:15px;font-weight:400;hyphens:auto;line-height:15px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
					</tr>
					</tbody>
				</table>
				<p class="text-center float-center" align="center" style="Margin:0;Margin-bottom:10px;color:#C8C8C8;font-family:Lucida Grande,Geneva,Verdana,sans-serif;font-size:12px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:center">%s</p>
			</center>
		</td>
	</tr>
</table>
EOF;

	/**
	 * @param Defaults $themingDefaults
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l10n
	 * @param string $emailId
	 * @param array $data
	 */
	public function __construct(Defaults $themingDefaults,
								IURLGenerator $urlGenerator,
								IL10N $l10n,
								$emailId,
								array $data) {
		$this->themingDefaults = $themingDefaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->htmlBody .= $this->head;
		$this->emailId = $emailId;
		$this->data = $data;
	}

	/**
	 * Sets the subject of the email
	 *
	 * @param string $subject
	 */
	public function setSubject(string $subject) {
		$this->subject = $subject;
	}

	/**
	 * Adds a header to the email
	 */
	public function addHeader() {
		if ($this->headerAdded) {
			return;
		}
		$this->headerAdded = true;

		$logoUrl = $this->urlGenerator->getAbsoluteURL($this->themingDefaults->getLogo(false));
		$this->htmlBody .= vsprintf($this->header, [$this->themingDefaults->getColorPrimary(), $logoUrl, $this->themingDefaults->getName()]);
	}

	/**
	 * Adds a heading to the email
	 *
	 * @param string $title
	 * @param string|bool $plainTitle Title that is used in the plain text email
	 *   if empty the $title is used, if false none will be used
	 */
	public function addHeading(string $title, $plainTitle = '') {
		if ($this->footerAdded) {
			return;
		}
		if ($plainTitle === '') {
			$plainTitle = $title;
		}

		$this->htmlBody .= vsprintf($this->heading, [htmlspecialchars($title)]);
		if ($plainTitle !== false) {
			$this->plainBody .= $plainTitle . PHP_EOL . PHP_EOL;
		}
	}

	/**
	 * Open the HTML body when it is not already
	 */
	protected function ensureBodyIsOpened() {
		if ($this->bodyOpened) {
			return;
		}

		$this->htmlBody .= $this->bodyBegin;
		$this->bodyOpened = true;
	}

	/**
	 * Adds a paragraph to the body of the email
	 *
	 * @param string $text Note: When $plainText falls back to this, HTML is automatically escaped in the HTML email
	 * @param string|bool $plainText Text that is used in the plain text email
	 *   if empty the $text is used, if false none will be used
	 */
	public function addBodyText(string $text, $plainText = '') {
		if ($this->footerAdded) {
			return;
		}
		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}

		$this->ensureBodyListClosed();
		$this->ensureBodyIsOpened();

		$this->htmlBody .= vsprintf($this->bodyText, [$text]);
		if ($plainText !== false) {
			$this->plainBody .= $plainText . PHP_EOL . PHP_EOL;
		}
	}

	/**
	 * Adds a list item to the body of the email
	 *
	 * @param string $text Note: When $plainText falls back to this, HTML is automatically escaped in the HTML email
	 * @param string $metaInfo Note: When $plainMetaInfo falls back to this, HTML is automatically escaped in the HTML email
	 * @param string $icon Absolute path, must be 16*16 pixels
	 * @param string|bool $plainText Text that is used in the plain text email
	 *   if empty the $text is used, if false none will be used
	 * @param string|bool $plainMetaInfo Meta info that is used in the plain text email
	 *   if empty the $metaInfo is used, if false none will be used
	 * @since 12.0.0
	 */
	public function addBodyListItem(string $text, string $metaInfo = '', string $icon = '', $plainText = '', $plainMetaInfo = '') {
		$this->ensureBodyListOpened();

		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}
		if ($plainMetaInfo === '') {
			$plainMetaInfo = $metaInfo;
			$metaInfo = htmlspecialchars($metaInfo);
		}

		$htmlText = $text;
		if ($metaInfo) {
			$htmlText = '<em style="color:#777;">' . $metaInfo . '</em><br>' . $htmlText;
		}
		if ($icon !== '') {
			$icon = '<img src="' . htmlspecialchars($icon) . '" alt="&bull;">';
		} else {
			$icon = '&bull;';
		}
		$this->htmlBody .= vsprintf($this->listItem, [$icon, $htmlText]);
		if ($plainText !== false) {
			$this->plainBody .= '  * ' . $plainText;
			if ($plainMetaInfo !== false) {
				$this->plainBody .= ' (' . $plainMetaInfo . ')';
			}
			$this->plainBody .= PHP_EOL;
		}
	}

	protected function ensureBodyListOpened() {
		if ($this->bodyListOpened) {
			return;
		}

		$this->ensureBodyIsOpened();
		$this->bodyListOpened = true;
		$this->htmlBody .= $this->listBegin;
	}

	protected function ensureBodyListClosed() {
		if (!$this->bodyListOpened) {
			return;
		}

		$this->bodyListOpened = false;
		$this->htmlBody .= $this->listEnd;
	}

	/**
	 * Adds a button group of two buttons to the body of the email
	 *
	 * @param string $textLeft Text of left button; Note: When $plainTextLeft falls back to this, HTML is automatically escaped in the HTML email
	 * @param string $urlLeft URL of left button
	 * @param string $textRight Text of right button; Note: When $plainTextRight falls back to this, HTML is automatically escaped in the HTML email
	 * @param string $urlRight URL of right button
	 * @param string $plainTextLeft Text of left button that is used in the plain text version - if unset the $textLeft is used
	 * @param string $plainTextRight Text of right button that is used in the plain text version - if unset the $textRight is used
	 */
	public function addBodyButtonGroup(string $textLeft,
									   string $urlLeft,
									   string $textRight,
									   string $urlRight,
									   string $plainTextLeft = '',
									   string $plainTextRight = '') {
		if ($this->footerAdded) {
			return;
		}
		if ($plainTextLeft === '') {
			$plainTextLeft = $textLeft;
			$textLeft = htmlspecialchars($textLeft);
		}

		if ($plainTextRight === '') {
			$plainTextRight = $textRight;
			$textRight = htmlspecialchars($textRight);
		}

		$this->ensureBodyIsOpened();
		$this->ensureBodyListClosed();

		$color = $this->themingDefaults->getColorPrimary();
		$textColor = $this->themingDefaults->getTextColorPrimary();

		$this->htmlBody .= vsprintf($this->buttonGroup, [$color, $color, $urlLeft, $color, $textColor, $textColor, $textLeft, $urlRight, $textRight]);
		$this->plainBody .= $plainTextLeft . ': ' . $urlLeft . PHP_EOL;
		$this->plainBody .= $plainTextRight . ': ' . $urlRight . PHP_EOL . PHP_EOL;

	}

	/**
	 * Adds a button to the body of the email
	 *
	 * @param string $text Text of button; Note: When $plainText falls back to this, HTML is automatically escaped in the HTML email
	 * @param string $url URL of button
	 * @param string $plainText Text of button in plain text version
	 * 		if empty the $text is used, if false none will be used
	 *
	 * @since 12.0.0
	 */
	public function addBodyButton(string $text, string $url, $plainText = '') {
		if ($this->footerAdded) {
			return;
		}

		$this->ensureBodyIsOpened();
		$this->ensureBodyListClosed();

		if ($plainText === '') {
			$plainText = $text;
			$text = htmlspecialchars($text);
		}

		$color = $this->themingDefaults->getColorPrimary();
		$textColor = $this->themingDefaults->getTextColorPrimary();
		$this->htmlBody .= vsprintf($this->button, [$color, $color, $url, $color, $textColor, $textColor, $text]);

		if ($plainText !== false) {
			$this->plainBody .= $plainText . ': ';
		}

		$this->plainBody .=  $url . PHP_EOL;

	}

	/**
	 * Close the HTML body when it is open
	 */
	protected function ensureBodyIsClosed() {
		if (!$this->bodyOpened) {
			return;
		}

		$this->ensureBodyListClosed();

		$this->htmlBody .= $this->bodyEnd;
		$this->bodyOpened = false;
	}

	/**
	 * Adds a logo and a text to the footer. <br> in the text will be replaced by new lines in the plain text email
	 *
	 * @param string $text If the text is empty the default "Name - Slogan<br>This is an automatically sent email" will be used
	 */
	public function addFooter(string $text = '') {
		if($text === '') {
			$text = $this->themingDefaults->getName() . ' - ' . $this->themingDefaults->getSlogan() . '<br>' . $this->l10n->t('This is an automatically sent email, please do not reply.');
		}

		if ($this->footerAdded) {
			return;
		}
		$this->footerAdded = true;

		$this->ensureBodyIsClosed();

		$this->htmlBody .= vsprintf($this->footer, [$text]);
		$this->htmlBody .= $this->tail;
		$this->plainBody .= PHP_EOL . '-- ' . PHP_EOL;
		$this->plainBody .= str_replace('<br>', PHP_EOL, $text);
	}

	/**
	 * Returns the rendered email subject as string
	 *
	 * @return string
	 */
	public function renderSubject(): string {
		return $this->subject;
	}

	/**
	 * Returns the rendered HTML email as string
	 *
	 * @return string
	 */
	public function renderHtml(): string {
		if (!$this->footerAdded) {
			$this->footerAdded = true;
			$this->ensureBodyIsClosed();
			$this->htmlBody .= $this->tail;
		}
		return $this->htmlBody;
	}

	/**
	 * Returns the rendered plain text email as string
	 *
	 * @return string
	 */
	public function renderText(): string {
		if (!$this->footerAdded) {
			$this->footerAdded = true;
			$this->ensureBodyIsClosed();
			$this->htmlBody .= $this->tail;
		}
		return $this->plainBody;
	}
}
