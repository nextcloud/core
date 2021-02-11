<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\App\AppStore\Fetcher;

use OC\App\AppStore\Fetcher\AppFetcher;
use OC\App\CompareVersion;
use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\ILogger;
use Test\TestCase;

class AppFetcherTest extends TestCase {
	/** @var IAppData|\PHPUnit\Framework\MockObject\MockObject */
	protected $appData;
	/** @var IClientService|\PHPUnit\Framework\MockObject\MockObject */
	protected $clientService;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $timeFactory;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var CompareVersion|\PHPUnit\Framework\MockObject\MockObject */
	protected $compareVersion;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var AppFetcher */
	protected $fetcher;
	/** @var string */
	public static $responseJson = <<<'EOD'
[{"id":"direct_menu","categories":["customization"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/juliushaertl/direct_menu/issues","website":"","created":"2016-10-01T09:16:06.030994Z","lastModified":"2016-10-06T14:01:05.584192Z","releases":[{"version":"0.9.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/juliushaertl/direct_menu/releases/download/v0.9.2/direct_menu.tar.gz","created":"2016-10-06T14:01:05.578297Z","licenses":["agpl"],"lastModified":"2016-10-06T14:01:05.643813Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"ERBS9G5bZ3vwCizz2Ht5DehsVJmb63bzF3aYcH7xjbDVMPagOFWdUAiLDwTeZR1n\ni4gdZ73J/IjHQQJoOPwtCjgbZgLPFqL5x13CLUO9mb/33dZe/+gqEDc/3AuJ4TlA\nXUdLxHRb1bwIlJOwuSr/E24452VG20WUhLXBoM0Zm7WcMxvJWo2zAWnuqnLX3dy9\ncPB4PX+6JU2lUMINj8OYQmM1QnqvjG8YV0cYHbBbnSicOGwXEnni7mojsC8T0cn7\nYEJ2O2iO9hh3fvFEXUzDcL7tDQ5bZqm63Oa991bsAJxFo/RbzeJRh//DcOrd8Ufn\nu2SqRhwybS8j4YvfjAL9RPdRfPLwf6X2gx/Y6QFrKHH0QMI/9J/ZFyoUQcqKbsHV\n85O+yuWoqVmza71tkp4n9PuMdprCinaVvHbHbNGUf2SIh9BWuEQuVvvnvB+ZW8XY\n+Cl+unzk3WgOgT0iY3uEmsQcrLIo4DSKhcNgD1NS13fR/JTSavvmOqBarUMFZfVC\nbkR1DTBCyDjdpNBidpa3/26675dz5IT5Zedp4BBBREQzX08cIhJx5mgqDdX3CU09\nuWtzoaLi71/1BWTFAN+Y9VyfZ8/Z3Pg3vKedRJ565mztIj0geL3riEsC5YnPS0+C\n+a3B9sDiiOa101EORzX3lrPqL7reEPdCxrIwN+hKFBQ=","translations":{}}],"screenshots":[{"url":"https://bitgrid.net/~jus/direct_menu_nc.png"}],"translations":{"en":{"name":"Direct Menu","summary":"Provide easy access to all apps in the header.","description":"Provide easy access to all apps in the header."}},"isFeatured":false,"authors":[{"name":"Julius Härtl","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEBjCCAu4CAhADMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwOTE0MTI1MDU0WhcNMjYxMjIxMTI1MDU0WjAWMRQwEgYD\r\nVQQDDAtkaXJlY3RfbWVudTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIB\r\nAMkzWsAkKP/40ktvJMpnr0IJNVoPOR0hvh24igcDskL1WKiD2eiRUenj5LE0Nvn+\r\nsiGmWsAqRVpdiz+Y8ghQqQMzKi43IrRN0AxlCrHWrSqBZT3wIAUcFz4RzEoFxc1N\r\nUZzWma6ljukGnvt4V1ZyT+H/cjqxUkBhh/y9SS0jUen1a1grND6Rw54X46V2dlCu\r\nFH+pLsfPJJGw+QLeTGHn7dqdv18cYMAlWDCzPixVnNiCXHZcUtKMmstU2xU4R2e6\r\nzimp2rgkE4TNHrafpjH8xGdNi2FG5Dmokob/L5Q2r8jyNaW7UsFfrvLTRj371b3/\r\n2FhhxoGUvDzaG2An02Exwm52LJfdTVMHAMPZub5poHfy5vAEdZGPQ/m02l8ZK/Y2\r\n7yT807GlfPMXfdfjCxR6wNtmv7rvBDdrUZmIRNJfpFSdvlH/+MOTWnabyfQv2K4Q\r\nBIwltX6Elh0lh4ntvt1ZVtvFv+PL1Dc7QLV+w19+/LJA0mnsh7GIFYKFlbA65gA0\r\nc/w+uqDy0+5MxkR9WGPpd79KRA1tKWTis4Ny1lApK5y3zIsVGa3DfBHXcwqkWHbV\r\nwIpyuyyDsFtC1b9LTFONX7iU9cbNk5C5GTM331MdA2kLcD/D5k42GNTBSca7MkPx\r\nFx/ETSn0Ct167el30symf2AxvXjw+mBYPN71rVTMDwe9AgMBAAEwDQYJKoZIhvcN\r\nAQELBQADggEBAC0fJKnbEhXA8M283jA9GxABxLyTBcQyVVNnz2L/bYYNi81Y9iZv\r\n+U0S3qaIfoqNcV9FTKAutbsKvWyolnI7MRRK6feNuFfoP2jKubM1CnawpyT/RF2Q\r\ne/zxnB1EmeI2X5D2xceJDLB7Fy5W0EGrLixRIdFaSUommWFUm9E2hSIaNlziSBdc\r\n1J/mOQeNYO5zg5ouEt1rzQW4Mhh1I2uNQmGe4ip+Jl/2LAv3FZuu4NrSEcoXH3ro\r\nG2dF9Gtu4GiQ5fuaJknaxlgXHovfqeZwZJX9o4M+Ug81AqiY7XjdiaCPdh0Tthcx\r\n2OmWZo7UBREWenjKyFZZ/iKoqH5sdenBtpo=\r\n-----END CERTIFICATE-----"},{"id":"apporder","categories":["customization"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/juliushaertl/apporder/issues","website":"","created":"2016-10-01T09:16:47.111889Z","lastModified":"2016-10-12T19:50:16.038821Z","releases":[{"version":"0.3.3","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/juliushaertl/apporder/releases/download/0.3.3/apporder.tar.gz","created":"2016-10-12T19:14:10.802359Z","licenses":["agpl"],"lastModified":"2016-10-12T19:50:16.104357Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"nhlT9lhrmBxIsqh/e3RLm2NDw/U8ZvvoMyYQTLMM3H19DQmVcPYPYC9QWVTsowUzXblVaOXVGylbpKma9yOlOAqJtF3qyXecLl4+tA/Awb6BBhKPgHFdcLDAL5yy1K7/uei3SgEojMlJoI9vEK5I1C5YTh43yNH+//Im6MAuNYFUTlMXK426zdOe6ogpCI5GlYdXopqYANxcpT+WXWET6DDSM5Ev+MYwMcSAY4r8+tvARRU4ZAenRgjkBT6R5z6cD76emRax14tbV6vkjjwRcO+dQtM0tFPbd+5fsNInbauv50VzIMgjA6RnKTOI17gRsSdGlsV4vZn2aIxEPWauu6T/UohMvAE9HMn13vtbpPBSFwJAktj6yHASYGzupNQLprA0+OdyALoLZPpQAKNEXA42a4EVISBKu0EmduHJlUPeqhnIGkkGgVNWS8AWqzP2nFoPdXBgUWateiMcBTHxgEKDac5YmNc9lsXpzf1OxBaXHBhGYKuXPwIfyH3jTWb1OdwixJEuRe9dl63T9qOTRre8QWns/bMqKLibGfMtFhVB21ARJayBuX70eVvabG/2N7Y5t1zUlFygIKu51tvo3AVCRDdRrFWDvkAjxzIz5FIdALVZ+DReFYu/r4WF/w3V9rInFuEDSwb/OH4r8sQycs07tSlMyA74Y3FpjKTBxso=","translations":{}},{"version":"0.3.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/juliushaertl/apporder/releases/download/0.3.2/apporder.tar.gz","created":"2016-10-06T14:00:51.532409Z","licenses":["agpl"],"lastModified":"2016-10-06T14:00:51.598455Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"gRVFOtj9414ZNSdRH/qNB2SwVZUQh+gaFnNLFjjXjJ1MdRMCISzvwb+QU1qYuK/y\nuL8K0pn1+fFQf8A3VsC2pb6yaLQ5U9C3Guf886Flf4qtYw1P8UWRA9yOJ+6Md+PH\n6pTEiDIdm4xbmM0KkBhsE5kL8dvLIq4EwwcAh2Qq8fjytzAk1YiP+KrTaYrVwskM\nDmm0lgP4NVnjRBTX9myW6tr6N3w0tq2jJ/+a/vEDJv+5ozKJx8N5gbJNdrtI4k7I\nyaQNWJ7cngtAHmUREeoBggV5uJayDceu83PPQR6N9/WVyNyZjw1Q8/Q6e/NyiXT2\no8mGv5tHl3DBOVuv8v7gBQgDh6ppp12M81aiCZymn2XIgjw50VQ+K15KHnLHuFFw\nwuHZVcoQ7b6oR4K4TURSfPFUeNgGS4R9v6gjg1RUcSm1Pnryc6pYnh10AUY8dk6q\n1CZ6Upt6SScP2ZEGWsFwVjjQhY/ZJmNfnhaGoFOZ5L9CnzyNCkGXFg0rL36i6djb\naqFy/z+Brnklng5ct6XWoP7uDt5BaHznQj1NHSfHn0GUQ0N968zWm9vQvy+dyXyC\nxR7vKeu2ppZ2ydoeQ9CVwfhOEsGs5OvrpapQdh9KbUHcX7b7ql01J7/P6dFuNuHe\n+7/y4ex3sEVg5YBmDtF8iZ6d7zsHd6peL1s1EsLnenQ=","translations":{}}],"screenshots":[{"url":"https://bitgrid.net/~jus/apporder-nc.gif"}],"translations":{"en":{"name":"AppOrder","summary":"Sort apps in the menu with drag and drop","description":"\nEnable sorting for icons inside the app menu. The order will be saved for each\nuser individually. Administrators can define a custom default order.\nAppOrder works with the default owncloud menu as well as with the direct_menu\napp.\n\n## Set a default order for all new users\n\nGo to the Admin settings > Additional settings and drag the icons under App order.\n\n## Use first app as default app\n\nYou can easily let Nextcloud redirect your user to the first app in their\npersonal order by changing the following parameter in your config/config.php:\n\n'defaultapp' => 'apporder',\n\nUsers will now get redirected to the first app of the default order or to the\nfirst app of the user order.\n    "}},"isFeatured":false,"authors":[{"name":"Julius Härtl","mail":"jus@bitgrid.net","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEAzCCAusCAhAEMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwOTE0MTI1MjQ4WhcNMjYxMjIxMTI1MjQ4WjATMREwDwYD\r\nVQQDDAhhcHBvcmRlcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAKVK\r\nKn5jivCu+eRfe5BECjDOzNaGHlpiegb49Hf4nh0W7DqcoLHip5c1O2BcEYdH6rkw\r\n20WclvjoQpgavG5aFXzXzur6eKTT5TpgY5oZTLoWjbx4e+fKdhyDPTpqNZzs1pxz\r\nsZLDL/ElpbSErE0s+QK/pzP11WNPylAkI9AKSyDMO3Mbllg8I8Bt+bT7LJKYOO/T\r\nLhv9m0anLZ4HrdfimhVIoMiu3RpyRigk8titXZA94+F8Fbf7ZQ9f14Y/v3+rfJFQ\r\nii9cDoox5uUrjplH2LrMr5BodfCfydLu4uVpPWYkdccgvcZ1sugjvNXyCQgdzQDK\r\npOpiwVkkiQFaQJYbGSDblFWPl/cLtA/P/qS7s8tWyTQuc1rYlEpCHG/fG8ZFkSVK\r\n9eCMGxK908VB4IU2DHZHOHi7JvtOz8X/Ak6pIIFdARoW3rfKlrz6DD4T9jEgYq0n\r\nRe7YwCKEIU3liZJ+qG6LCa+rMlp/7sCzAmqBhaaaJyX4nnZCa2Q2cNZpItEAdwVc\r\nqxLYL1FiNFMSeeYhzJJoq5iMC3vp2LScUJJNoXZj9zv+uqTNGHr+bimchR2rHUBo\r\nPzDLFJmat03KdWcMYxcK5mxJNGHpgyqM7gySlbppY/cgAospE8/ygU2FlFWIC9N0\r\neDaY+T8QA1msnzsfMhYuOI8CRYigan1agGOMDgGxAgMBAAEwDQYJKoZIhvcNAQEL\r\nBQADggEBAGsECd+meXHg1rr8Wb6qrkDz/uxkY1J+pa5WxnkVcB6QrF3+HDtLMvYm\r\nTTS02ffLLyATNTOALZFSy4fh4At4SrNzl8dUaapgqk1T8f+y1FhfpZrEBsarrq+2\r\nCSKtBro2jcnxzI3BvHdQcx4RAGo8sUzaqKBmsy+JmAqpCSk8f1zHR94x4Akp7n44\r\n8Ha7u1GcHMPzSeScRMGJX/x06B45cLVGHH5GF2Bu/8JaCSEAsgETCMkc/XFMYrRd\r\nTu+WGOL2Ee5U4k4XFdzeSLODWby08iU+Gx3bXTR6WIvXCYeIVsCPKK/luvfGkiSR\r\nCpW1GUIA1cyulT4uyHf9g6BMdYVOsFQ=\r\n-----END CERTIFICATE-----"},{"id":"twofactor_totp","categories":["tools"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"","website":"","created":"2016-10-08T14:13:54.356716Z","lastModified":"2016-10-12T14:38:56.186269Z","releases":[{"version":"0.4.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0 <7.1.0","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/ChristophWurst/twofactor_totp/releases/download/0.4.1/twofactor_totp.tar.gz","created":"2016-10-12T14:38:56.174612Z","licenses":["agpl"],"lastModified":"2016-10-12T14:38:56.248223Z","isNightly":false,"rawPhpVersionSpec":">=5.4 <=7.0","rawPlatformVersionSpec":">=10 <=11","signature":"bnwWxmHEn8xkoWbtwhC1kIrJ0dQfAI3PUtU62k+Tru/BHt1G2aVxqO8bCdghojZ7\nzdFMlIJw4kekYFsVfLk8jzjUTZKVbNVKCdkHrVTQ0bUUryMAMLqGQ3PSRI5NX6D5\nFpkvwO1coYwU0XVWF8KAS0meX0ztSkT3Mv96LLrxr8F8SrB/MGmKIE4WTjt1fAIa\nZLAVEUo/3sNFTGLYBtL3wjctrkZvJltP8abeRfls9FkRHu+rN7R3uLFzk42uZn3X\nWpt5BBmlYm5ORbnJ2ApsxEkMNK+rOy8GIePaz5277ozTNrOnO04id1FXnS9mIsKD\n20nRzjekZH+nneQYoCTfnEFg2QXpW+a+zINbqCD5hivEU8utdpDAHFpNjIJdjXcS\n8MiCA/yvtwRnfqJ5Fy9BxJ6Gt05/GPUqT8DS7P1I1N+qxhsvFEdxhrm2yIOhif8o\nh7ro5ls+d3OQ8i3i4vdZm821Ytxdu/DQBHiVoOBarvFWwWAv2zd2VAvpTmk6J5yv\n3y+csRqpEJYd9fcVMPsTu7WBRRrpBsAqdAHJcZEwak2kz1kdOgSf8FIzP1z6Q71d\nMl2RKcPeutMHHSLiGIN/h7fM5aSs49wGgGZmfz28fHVd7/U0HFSMYmkT/GMq5tMP\nIyc+QZAN4qbX8G0k/QSTkK/L4lOT2hQiQqiSqmWItMk=","translations":{}}],"screenshots":[],"translations":{"en":{"name":"Two Factor TOTP Provider","summary":"A Two-Factor-Auth Provider for TOTP (e.g. Google Authenticator)","description":"A Two-Factor-Auth Provider for TOTP (e.g. Google Authenticator)"}},"isFeatured":true,"authors":[{"name":"Christoph Wurst","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIECTCCAvECAhASMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYxMDEyMDkzNDMxWhcNMjcwMTE4MDkzNDMxWjAZMRcwFQYD\r\nVQQDDA50d29mYWN0b3JfdG90cDCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoC\r\nggIBALC1K94104L/nOtmTygx7QNjUcnHs3yrn71mw4pMxTlonXOnMTpwxsfL1Hhu\r\n/5GMSgupTbQPlevSl6J86UMs455/sPShd6ifmAuhb8VFaAsjpizjs0RMaUg1sjmF\r\nuV18PD9FXLourx51V/c4MG5kpavlV+bLUrVMAjbsJY2+k30tCC/XkP5u8jUWmM/T\r\n5REChn7/obPgaeddhuJoILYhKEW3VNrR8Fm9SYiviB3FLhM7URDZ97IBnXYqbvbT\r\nZnvq+E74Zc7HgYwQwrjU/AqQAInhNpAR4ZM6CkWWWWaL96O1q3lCfKJNaxqC0Kg/\r\nkGn/pxYkl9062jtMUz60s9OPDyuisfyl68UyM68Ozyz4SMRLmDVbewOqQAwmAbtz\r\n8p9AQrX3Pr9tXhARR4pDSsQz1z+8ExEd6EKbhMyiTtHtZQ1Vm9qfoR52snpznb5N\r\ne4TcT2qHAkOWV9+a9ESXmQz2bNjgThxEl5edTVY9m4t248lK5aBTGq5ZKGULNHSQ\r\nGGpr/ftMFpII45tSvadexUvzcR/BHt3QwBAlPmA4rWtjmOMuJGDGk+mKw4pUgtT8\r\nKvUMPQpnrbXSjKctxb3V5Ppg0UGntlSG71aVdxY1raLvKSmYeoMxUTnNeS6UYAF6\r\nI3FiuPnrjVFsZa2gwZfG8NmUPVPdv1O/IvLbToXvyieo8MbZAgMBAAEwDQYJKoZI\r\nhvcNAQELBQADggEBAEb6ajdng0bnNRuqL/GbmDC2hyy3exqPoZB/P5u0nZZzDZ18\r\nLFgiWr8DOYvS+9i6kdwWscMwNJsLEUQ2rdrAi+fGr6dlazn3sCCXrskLURKn5qCU\r\nfIFZbr2bGjSg93JGnvNorfsdJkwpFW2Z9gOwMwa9tAzSkR9CsSdOeYrmdtBdodAR\r\ndIu2MkhxAZk9FZfnFkjTaAXcBHafJce7H/IEjHDEoIkFp5KnAQLHsJb4n8JeXmi9\r\nVMgQ6yUWNuzOQMZpMIV7RMOUZHvxiX/ZWUFzXNYX0GYub6p4O2uh3LJE+xXyDf77\r\nRBO7PLY3m4TXCeKesxZlkoGke+lnq7B8tkADdPI=\r\n-----END CERTIFICATE-----"},{"id":"contacts","categories":["office","organization","social"],"userDocs":"https://docs.nextcloud.com/server/11/user_manual/pim/contacts.html","adminDocs":"https://docs.nextcloud.com/server/11/admin_manual/configuration_server/occ_command.html?highlight=occ%20commands#dav-label","developerDocs":"https://github.com/nextcloud/contacts#building-the-app","issueTracker":"https://github.com/nextcloud/contacts/issues","website":"https://github.com/nextcloud/contacts#readme","created":"2016-10-30T14:00:58.922766Z","lastModified":"2016-11-22T22:08:01.904319Z","releases":[{"version":"1.5.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/contacts/releases/download/v1.5.0/contacts.tar.gz","created":"2016-11-22T22:08:01.861942Z","licenses":["agpl"],"lastModified":"2016-11-22T22:08:02.306939Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"ZqqhqtbHcNB+rzGCQ7FDIjjvHjit+dhAE1UhFgiXApkx3tmPP4nJOBAGNjHe+2Ao\nVcTIX2SrWEfieRrA4Gp+0k7pUPWag1Z0T1OVOwO4cmS1AVFyGIOE1bRvDhMfsWTU\n4CI4oXaKBFAY6mtnf7VJ7EeIdNqhImkohyWDQ88NiPRLM1XNkJJk6AvZBcT0fvCv\no145X4dLpbixSXsN99QFNJ/oXvK+9tBGwTd5i/WnNFY90vcNRLia8aRo7SA0YJRx\nLnxnj2HMqwTTDQEKE+1elYKWsqQ2DeqwScP97UIKe5bZXnrwOi9kH9PDmR4abtzd\nlHL8E1Wgw25ePDeHG7APrx0tVOJy1bP+g8vcarpGynWZoizDkBvYZD+xtxizpBXC\nJsDOSzczApptY6dnOtv0Vat8oh/Z/F99gBUahEu4WZ16ZgR1nj40PDK1Snl18Cgk\nMe1EZcde8SLEpTbCWYIfIw/O9Fkp5cWD/dAqoiO6g+gNxSZ/gGp57qoGfFxn7d/x\nH3aH8GljatAFjrwItw1JzR0THt0ukkOK+bw/pfCslk10sjHMitmz/GXa4qMS91DZ\nBKLUd0dSfQUQzkfwcojImbzJRvca4/DYe3mfG7+RCH0tDL6t72dKL9joB++u5R1u\nVZPgkToexlXcKWpiDB8H2/SEShKr4udAOjR5de9CYWM=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/nextcloud/screenshots/master/apps/Contacts/contacts.png"}],"translations":{"en":{"name":"Contacts","summary":"The new and improved app for your Contacts.","description":"The new and improved app for your Contacts."}},"isFeatured":true,"authors":[{"name":"Alexander Weidinger","mail":"","homepage":""},{"name":"Jan-Christoph Borchardt","mail":"","homepage":""},{"name":"Hendrik Leppelsack","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEAzCCAusCAhATMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYxMDEyMjAzNzIyWhcNMjcwMTE4MjAzNzIyWjATMREwDwYD\r\nVQQDDAhjb250YWN0czCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBANzx\r\n/zJF+5/s4lOJLWIlfKQgTy+UpvIpiUXCgrsHsDZTx+hjQAhIWukH88a+7NVAL7Ys\r\nkQNC0Tlm755FJi/T6EdR7edOwIRdo2ZwakOWLZXd209+6cCd2UloHL0bgnbWepTl\r\nR/4YgbLg/G+FVKCfkEiYc3PuDZ3EVrcwQFcg7h74X9ne6CHH0Z1WQLydxJuVEb2n\r\nX9I+nIRpPHcVostkSiUmzHR7C5TjTIo2PzzgnCU6GC0iBa6z6dDYfz24QEp/b8UA\r\nZaLhjkyKghVGMnoF/s9KPgH4NM8pvvtadQN8pxlOPju4wbmKPUrsXo4ujurDXbbc\r\nYkzNt8ojobGwdTXoyDogAsGZLQd2FQksWpRvY+I3zVPokBfPMdUPLllG5VcV0VA5\r\nDRK+h2ms+XmspdBvGonjF+XdbFm9hEmDoFmoi9aU6C6AdofjmG/e9+pw/20dXUWk\r\nmMorWwXQ5yLmIn5LnpRXrOuK7CS28VRhBYuVNMlsyKhzU0rophbsD9OFXxYLjr6s\r\n7UPNwZ5h+kjXZDBKD89QctBSViT8RhLe8nulRIm0iJn1sb9hca/CF63KmsFzENfK\r\nQeM6MO0H34PB84iNyz5AX1OIy+1wHD4Wrzt9O/i2LkWK6tBhL69aZiBqdLXWKffj\r\nARDCxxIfews51EZFyHzwsw65I97y46aBKxY382q7AgMBAAEwDQYJKoZIhvcNAQEL\r\nBQADggEBACLypX0spxAVAwQIS9dlC9bh1X/XdW2nAvSju2taUTBzbp074SnW6niI\r\nbnY4ihYs4yOuGvzXxnp/OlvWH7qhOIchJUq/XPcEFMa7P03XjVpcNnD3k0zQWlZb\r\ntGonX9EUOeLZKdqI4fkrCkMLScfjgJzoHGYQrm8vlIg0IVuRLCKd5+x4bS7KagbG\r\niuPit2pjkw3nWz0JRHneRXz/BNoAWBnJiV7JMF2xwBAHN4ghTM8NSJzrGTurmpMI\r\nGld7yCP47xNPaAZEC66odcClvNtJ2Clgp8739jD6uJJCqcKDejeef0VU1PG7AXId\r\n52bVrGMxJwOuL1393vKxGH0PHDzcB1M=\r\n-----END CERTIFICATE-----"},{"id":"mail","categories":["tools"],"userDocs":"","adminDocs":"https://github.com/nextcloud/mail#readme","developerDocs":"","issueTracker":"","website":"","created":"2016-10-19T19:41:41.710285Z","lastModified":"2016-10-19T19:57:33.689238Z","releases":[{"version":"0.6.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0 <7.1.0","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/mail/releases/download/v0.6.0/mail.tar.gz","created":"2016-10-19T19:57:33.676730Z","licenses":["agpl"],"lastModified":"2016-10-19T19:57:33.834580Z","isNightly":false,"rawPhpVersionSpec":">=5.4 <=7.0","rawPlatformVersionSpec":">=10 <=11","signature":"VbMsvDpt+gSPeFM8LrZXEK10rk8kkLlgCcblgqNdCSeGZeVpwDAYv3CccVSLa0+l\nlTSqQ0VIoH+OIU6vIQNBKHmSCzTplk7OrY0+L5FajXx8LnBaOh892GfGSlEt1neN\nKyM0i0uOjO/xpCP/NoUlgkz6hnmYY5XEdN6DTsJtJ/XZhDQ45IYuIkMkHE/eFehS\n0JnOagIz+PSipeBY2Ry+tV8YbRa7bC1JAvZzlod0dyI015AHZESeitRUY+MwMWkt\nN/me7g7/Kev0wggIQQZm9aYcw63GMk/1VHUPB7Y0ESW9tx2nR5+KwTDn/Jy4DGf1\nrg8h0t5I+aPhHOBLrpczH0qaZWY2lsVZWq8KWjJI9aR9P0v2f2aXixXzD/Cuz1cK\nhvhKWkOSla4D+/FxeyHGjQvdXMG8gXm0ZmTimKChCoVuCbncDd8pzkdyNoGXcvuk\nsP8OrkQFooL4E7S4BWfdSiN/a8jUITJQkuXp/OVrVGeCupLWJh7qegUw6DvoqyGy\nD4c6b+qYn68kx3CLaPPiz+tFAZQZQdj7+Kx/lohso8yTnVSiGYrMj4IvvCbpsQjg\nWF3WSqF/K/tTnPYTWb9NUPSihTbVNv6AXOfTsPEp/ba2YSS5DjvjVjkr5vhR9eg1\nikQ3Cw6lW3vaA4LVCC+hFkMRnI4N0bo5qQavP3PnZPc=","translations":{"en":{"changelog":"### Added\n- Alias support\n  [#1523](https://github.com/owncloud/mail/pull/1523) @tahaalibra\n- New incoming messages are prefetched\n  [#1631](https://github.com/owncloud/mail/pull/1631) @ChristophWurst\n- Custom app folder support\n  [#1627](https://github.com/owncloud/mail/pull/1627) @juliushaertl\n- Improved search\n  [#1609](https://github.com/owncloud/mail/pull/1609) @ChristophWurst\n- Scroll to refresh\n  [#1595](https://github.com/owncloud/mail/pull/1593) @ChristophWurst\n- Shortcuts to star and mark messages as unread\n  [#1590](https://github.com/owncloud/mail/pull/1590) @ChristophWurst\n- Shortcuts to select previous/next messsage\n  [#1557](https://github.com/owncloud/mail/pull/1557) @ChristophWurst\n\n## Changed\n- Minimum server is Nextcloud 10/ownCloud 9.1\n  [#84](https://github.com/nextcloud/mail/pull/84) @ChristophWurst\n- Use session storage instead of local storage for client-side cache\n  [#1612](https://github.com/owncloud/mail/pull/1612) @ChristophWurst\n- When deleting the current message, the next one is selected immediatelly\n  [#1585](https://github.com/owncloud/mail/pull/1585) @ChristophWurst\n\n## Fixed\n- Client error while composing a new message\n  [#1609](https://github.com/owncloud/mail/pull/1609) @ChristophWurst\n- Delay app start until page has finished loading\n  [#1634](https://github.com/owncloud/mail/pull/1634) @ChristophWurst\n- Auto-redirection of HTML mail links\n  [#1603](https://github.com/owncloud/mail/pull/1603) @ChristophWurst\n- Update folder counters when reading/deleting messages\n  [#1585](https://github.com/owncloud/mail/pull/1585)"}}}],"screenshots":[],"translations":{"en":{"name":"Mail","summary":"Easy to use email client which connects to your mail server via IMAP and SMTP.","description":"Easy to use email client which connects to your mail server via IMAP and SMTP."}},"isFeatured":false,"authors":[{"name":"Christoph Wurst, Thomas Müller, Jan-Christoph Borchardt, Steffen Lindner & many more …","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIID/zCCAucCAhAVMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYxMDE5MTkzMDM0WhcNMjcwMTI1MTkzMDM0WjAPMQ0wCwYD\nVQQDDARtYWlsMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAp++RuliQ\nlBeeiPtP0ecBn00OaU1UCpft/NVI5pnSiT9nU4l2kc5IvKjA8UxDB3gWfYTOeBFh\ntUHQ2P6UKCmHZT9sApHhqLu2n0V+YhlFIViuaxndSID/M414cl56xOYQusV3Pcae\no2dOSeRRzLab3tEaVHlkBSFkGmAwPZItsmTklvV3h1sUysDicYgfXPCkf7K+JgWA\nBP7vsWC8B7MDRhcB3enYv5tTcpsyvtGX7bb1oTIWVypcmKsGYfTX12VNBxKzNBIG\n8pwdb8Xo0o14TytWsWN7mSHf1XbwfwYMjDWOlMqiRc+mcoKMBH41TfM/CXslSivI\nsyvxasEaFdlj8lmKPENdzw1OfYRs43usIf4szwyt4rb8ocXfDipnY3P2hccN6YcZ\nl8y8Vsr69ASluDj2A2Pl5vH6xp6tNybZRnN5G6sghhaYaLNDU/TdMyYzz4AY33Ra\nHSaMypfcXjd76Aj8jZvcwk1BH+ZsvFqNK7ZKCb7WVcMH8KRcU1sxZ4rp9vviM2fL\nL7EVtznm3bSI9jjHXbiwq7RvNRRy+F6YRpAdWGwTU8uUkDabPFi41FikYyzNWauK\nJhlDJXl514XjKyMVBjAZYVr5gZZkO1J7C4XzLFbC5UzYNSzW5Iwx/1j5OeYJRxh6\n5rhiUwR+COT1wdVsl6khMC8MfBR4unSd338CAwEAATANBgkqhkiG9w0BAQsFAAOC\nAQEATBvpqz75PUOFPy7Tsj9bJPaKOlvBSklHH7s43fDDmQbJwswXarZi3gNdKf8D\nyO/ACZvO8ANWAWL/WahkOyQtKOYzffaABGcEIP7636jzBdKtgwSGzW3fMwDghG10\nqBr2dE6ruOEdSpuZxgMgh2EulgknZUXaHAMI2HjjtAMOBScLQVjOgUqiOHmICrXy\nZETmzhx0BXDt5enJYs8R2KMYJNIme1easQRYmWKliXogNY09W7ifT9FHtVW1HX+K\nxRS4JXbapjolkxyGSpP+iYSgItVnYzl6o9KZResR4yDsBv7G/8fpV4GQU9IS3zLD\nPiZOosVHWJdpUKCw9V4P1prGTQ==\n-----END CERTIFICATE-----"},{"id":"audioplayer","categories":["multimedia"],"userDocs":"https://github.com/rello/audioplayer/wiki#user-documentation","adminDocs":"https://github.com/rello/audioplayer/wiki#admin-documentation","developerDocs":"","issueTracker":"https://github.com/rello/audioplayer/issues","website":"https://github.com/rello/audioplayer","created":"2016-09-16T05:44:24.857567Z","lastModified":"2016-11-17T22:34:34.637028Z","releases":[{"version":"1.3.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/Rello/audioplayer/releases/download/1.3.1/audioplayer-1.3.1.tar.gz","created":"2016-11-17T22:34:34.215350Z","licenses":["agpl"],"lastModified":"2016-11-17T22:34:34.867778Z","isNightly":false,"rawPhpVersionSpec":">=5.4","rawPlatformVersionSpec":">=9 <=11","signature":"p6Zz0IEFrxvw6y/3jHgGWWCxR6qpMzvU2HKfxcIVsK6sJnoRUhWLeAXwZ432fH2a S2llj+IGS9OvW+5VQElrXgPtEjDK1BT00DRJnp5RFCRlUv0LNoedJMzx6B6AHqPP JBufk3cG1O/CO0M0L1ITGSmSOzfKvWTRo3lxVGF792NyBaP/SyZCkH1N1TzBQzUi Ywl3+HiglPcXbHjtJm/arnKorbJWVKoaN93xFuaBapd2ozQSpi0fE0uGRsici+U7 HNa1M5WFE1rzUJoufE0E9246At07rFY1e+TdNEq8IlLgCXg5vGCKkEyuWpWno6aX LfRaIiT9x39UTAwNvuDKS0c+n4uWDYPsGfKhDx9N7CXpUrthfXVEWRzZEXG7as10 6ANvrRPJemSZH8FUSrdJhD7k12qa9R825y7mIG68Li8P71V92EOxFfo9tNXqXwBt VuDGxBqByFVPqSCj5I8hrzJzQl2Xt40g8+8ZcSF96RMg/pM+bwRMTv+mz0V+vQQ4 DWjqnWVPalaJ1PPD5/QFFErtXuNRbyxKZ6BMWxfJlLM9Kz66P75K+8fYaSwz+2KG NxY7I3svzS2K9LGH3fBLUy1t3Hl+c3zGFq/ll1MJrf9NswV4yxt2WmISfM/KDww8 yELkGs2cRzbw2tCvnmYlJJhIqRLkyFAbDzWRH/XdVx4=","translations":{"en":{"changelog":"2016-11-17\n- fix: one-click-play for wav not working\n- fix: wrong sql statement for PostgreSQL [#90](https://github.com/rello/audioplayer/issues/90)"}}},{"version":"1.3.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/Rello/audioplayer/releases/download/1.3.0/audioplayer-1.3.0.tar.gz","created":"2016-11-15T18:11:19.539636Z","licenses":["agpl"],"lastModified":"2016-11-15T18:11:19.592881Z","isNightly":false,"rawPhpVersionSpec":">=5.4","rawPlatformVersionSpec":">=9 <=11","signature":"lbp7wd3JhMHW5mC8kVnQFvcwzf3aTIhYhq3ak/C/vfDXJDIPFuQ1odVRWtaHXEKQ XmKYIoTobV1TAU5q9G0O0Kds73T/XtHG4ATLxMZE8RsUWNSj5v3H4YDub6A0uoX6 rzyLEYV6SGEtdPFMwLcUjDExKpzAzKpgxcd9uyz2NhcHJEO8FJmirn34bm69//TO vjjiMW4zpL+dho+7LQbOX+L1SmwmdBqwypE9zzeuIuhUWDEQtImHAvjIO6Temajm lX0H5JaowJa8kvP6Jkh3KAvsHQ4sJklvWTPGcv0gboN+o6CmjWNOb+3LeSH0nhe6 BmiPloUDJcPQwq2gQejH2pY+qJEdRcULSKS09/dRbE3gOSlG36FThN0INpv6uNP4 qVIiYs3/SEHMmlS5CHvJDt2S2XN9LT9IX7QPeuS/0CMcuopaG/+cdC4KscVCq4D4 bllgew9asiBqix8iV8C4oerYOiC5vWcgBrZhGShoJT1Qq+NKz+H10dFgjFCAZuPj nVagJkbXmf2NdcvpSC7qsufhyEZyCSp+I7QEYsbo1PW3aUU35Syt47lgeVFX0hVQ jC1wMIhEW5Rm2nCkRSZkRupKDQ+N6uWuB0lykeMV2ShcDvvUZrhN3c49sooWgigB yIqKryzM4fLErjjNHdYgwCq6bbgPDLK3ic9b3B4rF3E=","translations":{"en":{"changelog":"2016-11-15\n- fix: handling of temporary scanner files [#68](https://github.com/rello/audioplayer/issues/68)\n- fix: simpler analysis of incorrect files in scanner [#57](https://github.com/rello/audioplayer/issues/57)\n- fix: album sorted correctly by artist and album [#80](https://github.com/rello/audioplayer/issues/80)\n- fix: neutral cover for unknown album [#16](https://github.com/rello/audioplayer/issues/16)\n- fix: error message from ID3 editor shown in front-end [#77](https://github.com/rello/audioplayer/issues/77)\n- enhancement: occ support for library scan and reset [#72](https://github.com/rello/audioplayer/issues/72)\n- enhancement: select a dedicated folder for scanning in personal settings [#79](https://github.com/rello/audioplayer/issues/79)\n- enhancement: exclude folders from scanning via .noaudio file [#79](https://github.com/rello/audioplayer/issues/79)\n- enhancement: significantly reduce database reads during scanning [#79](https://github.com/rello/audioplayer/issues/79)\n- enhancement: cleanup of classes; move from \\OC\\Files\\View to \\OCP\\Files\\IRootFolder [#72](https://github.com/rello/audioplayer/issues/72)"}}},{"version":"1.2.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/Rello/audioplayer/releases/download/1.2.2/audioplayer-1.2.2.tar.gz","created":"2016-10-06T21:21:05.414691Z","licenses":["agpl"],"lastModified":"2016-10-06T21:21:05.483224Z","isNightly":false,"rawPhpVersionSpec":">=5.4","rawPlatformVersionSpec":">=9 <=11","signature":"toeS45z50Lm0djgrQokOTN7gA8a113IZtiKKiLsGUKWrCV/6AKJBmEFcSun6rhLH\nbz/RtIdFKwQql6O3E0m1Zch2y1A8aLWHzFTO+5orLCVi7y15SshrJYbb9aI5Pj3i\nSR7+kMHGS8uNx2uIn3B4mO6UYF8AzCfp+ule18DOjnpu86KWvEOGtFXsQkLtgepp\nbJhOGWW/uOVIos/T1xPP4GCmtVmgn7U3b9q0pMYRH7ONXEiNsfQxDrR66EZH3Jfo\nlVyM9UvQmMKoDSdBUlvLlhCEGJGqFOD7hFntLYloI4oXv9uGqcagaJVh3TkEysY2\nMbBZpVhch5zRJ/HGlZOvmEzZ8Inxkk3iap+JmJ5/gZTefwfKUyYHALsILlh820U2\nNA/1B5A015XH5a5uflGE/tnlPtrOeecIN4mg+1njo2RG89HJWJNHM2ZDO4SkXjSR\njgygmAS5aR5+KKifiA/pwjhLozDWPU4lNzsj3Foz3bx3Okopy7eq83LORqieT4Jp\nFvP64q/45LOSRBWIWLitYzRzZp7HYywMsnz12WpxtqxIjO7+7y/ByeWWOBNU1IJC\nK2D+035ZGWZr0CxDJte33WOISwjVoSwrcov++O3BQW8lM5IkcDNcJFyzNPKAXcQU\nPUXmQpYurHoIw6odAYcbrG6iOiSesuNOf2kQWbjV3/c=","translations":{"en":{"changelog":"2016-09-18\n- fix: icon issues with alternative apps folder [#65](https://github.com/rello/audioplayer/issues/65)"}}}],"screenshots":[{"url":"https://github.com/rello/screenshots/raw/master/audioplayer_main.png"},{"url":"https://github.com/rello/screenshots/raw/master/audioplayer_lists.png"},{"url":"https://github.com/rello/screenshots/raw/master/audioplayer_share.png"}],"translations":{"en":{"name":"Audio Player","summary":"Audio Player for ownCloud and Nextcloud","description":"Audio Player for MP3, MP4, Ogg, and Wave with a lot of flexibility for all your needs."}},"isFeatured":false,"authors":[{"name":"Marcel Scherello","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEBjCCAu4CAhAIMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwOTE1MjExMjA4WhcNMjYxMjIyMjExMjA4WjAWMRQwEgYD\r\nVQQDDAthdWRpb3BsYXllcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIB\r\nALyC+iLscLs62NeNmUXEBmg+xMuUtDmZKr+xzJWtl6SSNRz+8K1JygvUIXFJ3RIL\r\nCYA3xyq8/wyZH1gNrLKyz5eTeYawG+eT3ges/FT6MWGUbZoRrBrikVcLC94QzxTH\r\nxOl8Dn+SCV/2bhcvPTQdhK+dqtvGilOtjHa40iMrk9gSdlKVys5CK/xdlEp8uiMa\r\nkz1WENn8MVCCJV58bAUbaCupDWXR9CCoSsw8XinNsCenZ2B2XlnmbM44280w0ojs\r\n72rfQRgj3yDG+ZUUyUOuxIuodu8liXYciLf0ph6t/f/qoSmctbBdsR5Fl1Upj1Ac\r\nqeHb5Yf/B3Vi6Mn3XfDx0H2EHk1v9Dhzxay+v9BHUzyIX2iH/q+7TE0/Jzo5AwBW\r\nvFKWXvG7wXaALcHYZf5v/M93IE0iCHsv2EsZKQPBnzXVGmp4DwFSP4po1B7hcog1\r\ngAMaellAzzvUAizgCovN6Qct3qDEANYniPlvtnlcaQGonajW4N019kFQRHLIzPFR\r\njab5iUMMwSnT8FhZO2ZOWuWhJven+gXjxC8mfMVgBfZnAVgydNfx9rN+KzTc88ke\r\nobUdZ0OOeBzA7pIxGEFg9V6KTEEWZ+qH048vxXz4HI9B1I+2wQLBrZl8CvweEZ5U\r\n5ID8XrrE/UaNZ1CvLKtCgB24gj/m1Elkh7wA3gEcEo2JAgMBAAEwDQYJKoZIhvcN\r\nAQELBQADggEBACtgUp+FCmjWIkQUuWSdzKWdO+IH4v9wBIrF9mo0OLIakFyDYyM5\r\nLlkYZXbplGXd4cfn3ruIqJNzlIb4xa5CU0bM4TMbD4oOSlLMKM/EamKPHI3bzr++\r\nzi7mQDFxmAE6FWSMBgKKUb4tqLc5oBap8e12tPEZl/UR6d9iUB2ltvrm3T3vrjjl\r\n2Worm0eYBNqnagXmX5+wS11AQqeJemGqRy5e1yXRlTgB0IJhH0dCsFNwifEigutp\r\nFNvGFVBn4r5qCiChEoq+rCXHRjPi/eCfbW21XeLFDiLxapcZyc85JIcA7znUYoFe\r\nP7Y/ekMscwWhLbF91OaQlcWpRtEMyde/DaI=\r\n-----END CERTIFICATE-----"},{"id":"calendar","categories":["organization"],"userDocs":"https://docs.nextcloud.com/server/10/user_manual/pim/calendar.html","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/nextcloud/calendar/issues","website":"https://github.com/nextcloud/calendar/","created":"2016-10-01T12:40:39.060903Z","lastModified":"2016-11-22T20:31:13.029921Z","releases":[{"version":"1.4.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/calendar/releases/download/v1.4.1/calendar.tar.gz","created":"2016-11-22T20:31:13.020268Z","licenses":["agpl"],"lastModified":"2016-11-22T20:31:13.087340Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"nThwe9CJBCan9nuDLdhfBiQyPhmum6Aa0UcYsIDdhGMw+C2acf81KhEmBJuTTWxo\nWGby6WcrcJJmeuCW+ePU91ju7Pd76RirprhVXIEceIDzSCxin+K0oZCZ1IGVIJjP\nIkVehTsLuCeTBbjvz1b3k5QFyhUhvd32Xt7k5d7VARyI4OqnqYYNBtH9vvgeRrFw\nAxsQr4o4axof6i3iykLg6WfWarYArY4dIuu5DkPuGPWf2bbgjwWEra4sQejhOs7G\nsk1xcsfYv2NpArIbpw/wnATdjiax+Gjz1URMD3NgL5ky0ecuZmNvN25QErg3nlVr\nhh1FBfA5pvCJbkJ6nr5bU4bKaffwDX1nr5h77FS5zzn0Pyd7ZIExmVmNtaeJfnfV\n5vnclapzXMNU+R6t/ATJQd1srvSJhyljQapzsqnrjNMEUojOEvqqygJp0KwNVPqs\n3g9XGSoOnO+WULWBeISW7UVOg8BOF8pwvHIU2++bSzOdpypW0Eq6p2DPWO6qL/H1\neFLKrUg3EvnTjvknbBxMB55h9jNJr0SAlkrmyEVm6+CE3BwRWpKB+cJMBuGiwPwv\nr/ASRiJrkDThbNWAUtX70rUmCqDV6/MujLXViqOc/Q2OHvcXd1oGDccJSQT92/1z\n7nonnedyYQIDqUt7u68WL8JRxp7pFsEqKLVuWSgxW3c=","translations":{}},{"version":"1.4.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/calendar/releases/download/v1.4.0/calendar.tar.gz","created":"2016-10-06T19:58:12.724588Z","licenses":["agpl"],"lastModified":"2016-10-06T19:58:12.790604Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"b//hJbICFMLR0Va1BGMzpLpaPREOo9QhjgfrHvDOfXVpddhvCM8ocz74X1s5hKyy\nGg67EE0pOp0dBf6RyJjduI+Dz1wQX55My7J9G1vXGCN30C/8zAcKSJoA218IWcub\nICavLkz2PkiitIOSZyBCAfEiSAeHPop/JGkq3KxQDC7QwFC78BnE9/adD9tO55c/\nDGLhvP/uTJIeH8RUifikTqVMmPH+aP3uPbZzl+AxhUezRiNCpEtZPA5QGqtQdJU4\nFc6x3d9y4IWbJV4TEIAP8jdfqtLVUQ6szFVnN8Oi1wtN9e8LIylBSYbmIZRj0+qh\nZcgntzEq6U843ZwXcAnL5jNYV0m+KNI+EkXFeWHkjvbwfCdvGPBvgFVbhc0YPzXU\nqHOe4Lvcx9X20ALG/MacV9zX69GzNnWgbBp9RnIHuaSRPFEKrNXUeXl2THuKsTyQ\nF9QtTwS5U5DcMyTO2RAN45NrRxIh8IL4stoIg5rmF7/ZaOm/Jza2gnUquOTarDE/\ntiWnNW5kWUAWyYYHvQgQix/of9qXvc2hhZaw0y623WDNrEwA+rngnjDMLA/vNv3B\nhgwQ6NbCOuHWsRK3S8DcJFpB9Kj/i7CDvDLEuJYnjSTvQ/q1XqawbJPDoRlydX43\n3/L0LvHvKVakYybv2OE5gy6bQ2Dw8e7D27DtZ6XTaBY=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/1.png"},{"url":"https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/2.png"},{"url":"https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/3.png"},{"url":"https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/4.png"}],"translations":{"en":{"name":"Calendar","summary":"Calendar GUI for Nextcloud's CalDAV server","description":"The Nextcloud calendar app is a user interface for Nextcloud's CalDAV server.\n\nIt integrates with other apps, allows you to manage calendars and events, display external calendars and invite attendees to your events"}},"isFeatured":true,"authors":[{"name":"Georg Ehrke","mail":"","homepage":"https://georg.coffee"},{"name":"Raghu Nayyar","mail":"","homepage":"http://raghunayyar.com"},{"name":"Thomas Citharel","mail":"","homepage":"https://tcit.fr"}],"ratingRecent":0.944444444444444,"ratingOverall":0.944444444444444,"ratingNumRecent":9,"ratingNumOverall":9,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEAzCCAusCAhARMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYxMDAzMTMyNjQwWhcNMjcwMTA5MTMyNjQwWjATMREwDwYD\r\nVQQDEwhjYWxlbmRhcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAMm6\r\nFTeqgzCXprkU83VM4/DrZWn3kqtfaR/edkC4gYT3ug7RHa/Uv1C/S++vr7pvgpnk\r\nYzQoavl/0Qlh5sKEYX+0ud/LQDoiidwBRDckFUQ1bRfVLxAD9UAVvDRHxDqJMOx2\r\ngZArbeQ3ztdSHZp4ThzBFWq2FILsJD86weG7LwHjzhW6SWgLb/YTLbuuW6tRCDVV\r\nbtB0I/a0vCwj2u91Chw3u6pWWjPakc9DQrIDH4HCIBKQ4zVrYDxAmJDRFGDvVVWx\r\nuIAeux8sd8drqSMqAhX+XMcZPRD71NQTWbCupSwWO8kgjmZnBpIiBNpzvMQzJf3A\r\nQloZtjZ2RDXAQG88eTeT8pp8yEOCEoDLpGdraKxJrh/z2Dsk30JP3lOiNYJ9vBaB\r\nC8NJbJ3oAlG7THwUaFF9fsdAKoTwzs5Xms04TI7W/v4Z/GClOzMymnR1T4sR72Oy\r\n3WaMNHv/1QGffvQn2/TtZt23Ou3P083xWx2vn5FgTcac8+x85vRgWsVCA4hq9v6m\r\nAlktB0+UWDEXpDTKD9BdFNWM8Ig9jQf7EJrvTLNnS7FIJZMB4GK8lpvPxyvACWnh\r\nR2hQOe987Zvl3B1JZNO5RvtSeYld9Y9UfMgW1aPRweDNjSuZYAKlugx1ZoyI5HyA\r\nQjfzAwicIMwZsCJDV/P5ZO8FE+23rdWaoJczpBqDAgMBAAEwDQYJKoZIhvcNAQEL\r\nBQADggEBAHQXwvj8q5khWR/ilg3JGYpmMNBYHE9OeDaOcNArkKaGMd478SDPOXeu\r\nyW7hCvNEpiTk5g0h3g3yleZFws0xH8fPsQgZANgvQXb3RCcD61NL77d0cMTr7Xzr\r\nN3Lq/ML1YLc/WwL4uV1XvpMQMwALFL1p63BU2c0ysO31zbLOjMKAJi0hHFDYz5ZQ\r\nD3xxtc17ll3B5IqrMnMHRqmOQ39Sbe56Y7T4agaIz/sUWpseo85D5kt7UAIOR+Mr\r\nQ0Bl/QinETk72afGR46Qvc7tC1t9JjQQD3AUbEGuJdGvXjJJ9GREYu01XoODmPdT\r\njXXOI8XIOK6kxXhPHUc3iWu9b4KqGm0=\r\n-----END CERTIFICATE-----"},{"id":"gpxpod","categories":["multimedia","tools"],"userDocs":"https://gitlab.com/eneiluj/gpxpod-oc/wikis/userdoc","adminDocs":"https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc","developerDocs":"https://gitlab.com/eneiluj/gpxpod-oc/wikis/devdoc","issueTracker":"https://gitlab.com/eneiluj/gpxpod-oc/issues","website":"https://gitlab.com/eneiluj/gpxpod-oc","created":"2016-10-31T10:57:44.387319Z","lastModified":"2016-11-23T17:27:37.793159Z","releases":[{"version":"1.0.8","phpExtensions":[],"databases":[{"id":"pgsql","versionSpec":">=9.4.0","rawVersionSpec":">=9.4"},{"id":"sqlite","versionSpec":"*","rawVersionSpec":"*"},{"id":"mysql","versionSpec":">=5.5.0","rawVersionSpec":">=5.5"}],"shellCommands":[],"phpVersionSpec":">=5.6.0","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://gitlab.com/eneiluj/gpxpod-oc/uploads/963bbf246412bcbe8979bccadb3b8d03/gpxpod-1.0.8.tar.gz","created":"2016-11-23T17:27:37.783365Z","licenses":["agpl"],"lastModified":"2016-11-23T17:27:37.862469Z","isNightly":false,"rawPhpVersionSpec":">=5.6","rawPlatformVersionSpec":">=9.0","signature":"hqhMh1l/mnwbYf4uPzEjjLFtZWHidzgR57X471OuXv2K/s87T5WhIkTSKk+2r8sp\nS7CtrF5+Pc5AgCCHvwzawN3e2+4eO4cK0+HD9CCzygzzHZEbSjufNHMMQucVoSD8\nPqR6MV9azzUpwHa/5d8fp3cFLVAle+aG0o4v5eHky9c7eaKxVJcgfjw3pjDE73N6\ngJVdtw1jf1kOFYk5pZQxDfBKFDrO5BRo5ZfZGuOuP2u/SmTwj42oTZiT7oTVWhqd\nLvJw+2TPv7B8s0Gin+J5e9K1Rs6CEWQ6WBxM+NhS5KgWB5Ig3pwm0QvMgza2cvoh\nlwVobOotfKLUBJzg0+wR7B2YH9Ao+m94h93vg7H0OKPReoTKhlDj2UExoTyeurV8\nhJdQv8sKVAxjC7/xrVaGSjM4YxFdBpzq8Zl8z4zq1o2voH5+u4ko3c62C1loDpsC\n8KrL1t6A7QpMk/XAMrPqwEPmFqlLEdv6FhzpOGyt4IEVnv6vdMTShcYw3tPvU/mD\njPtiVwpo8gWbGVIfpmwBg4wPaTrWK8V3+/1iTahIQHZfu4Lebb5mzht80HLQIcd8\n+oB4cGDEX4Rix1WxnCmE5ZzURY8xQXcvqYN+mTrUDh/3OtxQPSm5yC945SGoFNpr\nBYxfEyQcwulZrOMBdY0Ssj1AB5NOeC9OHwjJrnVe7dQ=","translations":{"en":{"changelog":"### Added\n- save/restore options for logged user\n- option to choose picture style (popup/small/big marker)\n  [#25](https://gitlab.com/eneiluj/gpxpod-oc/issues/25) @eneiluj\n- add average speed and average moving speed in comparison table\n\n### Changed\n\n### Fixed\n- bug when python PIL is not available\n- deletion of bad parameter given to getGeoPicsFromFolder() in controller\n  [#20](https://gitlab.com/eneiluj/gpxpod-oc/issues/20) @eneiluj\n- bug in file cleaning, bad use of array\\_unique\n  [#22](https://gitlab.com/eneiluj/gpxpod-oc/issues/22) @eneiluj\n- python script do not need to be exectuable now\n  [#23](https://gitlab.com/eneiluj/gpxpod-oc/issues/23) @eneiluj\n- jquery.colorbox was brought by \"First run wizard\" app, now included\n  [#21](https://gitlab.com/eneiluj/gpxpod-oc/issues/21) @eneiluj\n- avoid JS error when failed to get options values by ajax"}}},{"version":"1.0.8","phpExtensions":[],"databases":[{"id":"pgsql","versionSpec":">=9.4.0","rawVersionSpec":">=9.4"},{"id":"sqlite","versionSpec":"*","rawVersionSpec":"*"},{"id":"mysql","versionSpec":">=5.5.0","rawVersionSpec":">=5.5"}],"shellCommands":[],"phpVersionSpec":">=5.6.0","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://pluton.cassio.pe/~julien/gpxpod-nightly.tar.gz","created":"2016-11-16T14:06:33.937534Z","licenses":["agpl"],"lastModified":"2016-11-16T14:06:33.971502Z","isNightly":true,"rawPhpVersionSpec":">=5.6","rawPlatformVersionSpec":">=9.0","signature":"JtUhKRDFGYDx9xtHjdfEUFOb0O4idexUYw6ixlBhKPP8Dn7NfyBfV6KH6MJTIVLU\nQ5Jw6tv/Nr1YDOvVikcWPG0p23mQdn1+7w8DzzIGKmknxCat9/vKr83oJZdWYxS7\nTJ4I7qTvWNlbMfK8OEdl13VJXgc6ftX+1isluLYqLjEm3aBFCS+/awYNMmXO55a1\nyG0NgJRu3pw1CBCMhDaRLsunhpRNDVLsamZj1SPmeT8qy0I/arFaG6hQnAo6JosE\ndi1XkvK6TEt9g16L6eizd+JpGE7xiWFP9ZEmMmmQSOLQYwU5Sk1YWcrW3EX4vtz5\nWnEIC0SENyyAyzBO6YJfu/EP2lLnlbNJiuc4zzKLqRw/zyz3j+imJLcXHIA78ZkQ\nuyUOBkkk3xeyBGeUcYfDuBqYQOQs+F/7+cNMsIBKJhx9ef3OPURBc7X16upk3mxV\n6GsOktbHkgUwWk3WiXRriBIqbAZocvDp0+PN++PAEZVWFEZEJzztd4Fxaeo+QSN5\n5Pz/9yXYRsoSPZv82Tlh7dx5tIPUvYb+UsANh5eGWUGufTSwgYBN0H2KT/iO35D7\nkDzNyh1qNakfBhAgPjrC2p4mBKBJJjlM0D9erDwr5D4GSTW2fp92vlRHeD0X8sqo\n3kBbwGuWnmhdJhbd7zYy0jVM6tVX/zgbhycimNALG0I=","translations":{"en":{"changelog":"### Added\n- save/restore options for logged user\n- option to choose picture style (popup/small/big marker)\n  [#25](https://gitlab.com/eneiluj/gpxpod-oc/issues/25) @eneiluj\n\n### Changed\n\n### Fixed\n- bug when python PIL is not available\n- deletion of bad parameter given to getGeoPicsFromFolder() in controller\n  [#20](https://gitlab.com/eneiluj/gpxpod-oc/issues/20) @eneiluj\n- bug in file cleaning, bad use of array\\_unique\n  [#22](https://gitlab.com/eneiluj/gpxpod-oc/issues/22) @eneiluj\n- python script do not need to be exectuable now\n  [#23](https://gitlab.com/eneiluj/gpxpod-oc/issues/23) @eneiluj\n- jquery.colorbox was brought by \"First run wizard\" app, now included\n  [#21](https://gitlab.com/eneiluj/gpxpod-oc/issues/21) @eneiluj"}}},{"version":"1.0.7","phpExtensions":[],"databases":[{"id":"pgsql","versionSpec":">=9.4.0","rawVersionSpec":">=9.4"},{"id":"sqlite","versionSpec":"*","rawVersionSpec":"*"},{"id":"mysql","versionSpec":">=5.5.0","rawVersionSpec":">=5.5"}],"shellCommands":[],"phpVersionSpec":">=5.6.0","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://pluton.cassio.pe/~julien/gpxpod-1.0.7.tar.gz","created":"2016-11-14T00:57:37.521001Z","licenses":["agpl"],"lastModified":"2016-11-14T20:35:45.363487Z","isNightly":false,"rawPhpVersionSpec":">=5.6","rawPlatformVersionSpec":">=9.0","signature":"SigBof6QJZ9IMZyFgc+B3LO2+EXaAPvnxUHjJQjIl3jLzomocpDGR6WjO6gtvB81\nzXUHjJ8+huc+P9TvgjUGRTmn9a/29HZ4IKTXnYBKIUY7wSLcJNMbJSp2Zd3OFHAG\nJwRaEdh/cIRk2X6NE1VT6dFCxB+LhTM4BXOEwuNYQvU1lZDVQgTz/r68zFLWBt6R\nqhBCNJHrVp87ecS4+XaGq/CfT4k1ihiOv+f4eX9iaPzUhxBJ71iYKF7wHpDoVmIk\nNrzWFUJH3BLBuW9oiC0PApli6Xu5RXrWUsOV7OAmxXgylRCPuTFwe09hw16JMbiS\nii8WFiUtp4qW53+7eoS7Fllm7CRi/Dg6Jvjtp3msrf1m+OiYM7dLyoKw22/S4P/a\nBIErZpSCHaCfrZ+DBXrAYcas27GWE7HizzG3yXk3aDJMa0Otcsq56bSPo01JDfNx\nm1y9iuwmlon8zKKoxsJCwxaFDnQpqazaLcUO0ATHUk8LdomTA7MCXVvNFPaO86Ea\n16iyw7Cfs0k3GrvN71+SdpvWss359CEEwBMpDwJZqwSFbLRyHtogUgbRWLIJqR4n\n5uVvJqirxWkr/EtXw6UkDWAI3ZoMhMRtjn4H4ekANP5mC8R0yp+UuFs2RkEC5uA0\nKzzh73WmxmpeUl6jcMZ49gXn3PTCC2fJNrdmSYch5Dc=","translations":{"en":{"changelog":"### Added\n- option to choose waypoint style\n- show elevation, lat, lng in waypoint popup\n- ability to display geotagged jpg pictures on the map\n- pictures slideshow with colorbox\n- pictures work in public dir link\n- use NC/OC thumbnails to display pictures on the map\n- options block hidden by default\n\n### Fixed\n- fix bug in geojson generation for waypoint-only files"}}},{"version":"1.0.6","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.6.0","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://pluton.cassio.pe/~julien/gpxpod-1.0.6.tar.gz","created":"2016-11-07T12:11:00.619161Z","licenses":["agpl"],"lastModified":"2016-11-07T12:11:00.699921Z","isNightly":false,"rawPhpVersionSpec":">=5.6","rawPlatformVersionSpec":">=9.0","signature":"WBts2fm2rW/7LMHYjcx9a0k0WTXV6PnGRxTl+158cjfV7ruMpNvhK58iTjrox69k\nFWAoIi1wNAlLBu9Xet1j7HKi4TC9q61IEN+lPlnwBCu0uHawiqS2gqB4i8A019Ei\noLsgAPWh8ndy6+gyUtPhVLVduLH76aT6KTwAiHPknV0zPtxsUy1P6nbbNOS5A5rG\nSQBqljy0TbcjOctTudEPp1IqjJIwcd12eZ9MLG4CEIO13n53pMAsuXJf4jnKSCm0\ngimvsFOwFRjBab3ZPwtOqeVw6aIh/lYF3U3/k8YBpaDN74m30nDtkp8teXBgshSY\nVYvX3yOAYe0PIR419IX0eoHb61K0VfZYvPT4FsOqjDr0zlVB8Rjq+6SiK4vMD2+6\neGE0aHbjR9HV5jymUnFYdm/hlhcZGaKrAiQKfBY6Vh0SWKfIv7bdGfQYauePAdZt\njlsV8lIwOy7FGAeP81CcjzWWfDeBgYr+MSzfoDNoTi41MvUaT14iWPIU/s5P1/Qv\nALGxgsbmB19wEgNbdh1UtTUY3xARLpWPYdUqU7yDcsddX9vCoCG2G5wCcbYJRj8o\nC+H7wdgPJoiMY/p4Go/lyWkvmzhfXrOeXytZIFXjb3ERVd1vD9WSt1DSy/7gsFYt\nxzzOPgqMvL3BbeluNuzNv366oT872s3OuFKa1ZOYY7A=","translations":{}},{"version":"1.0.5","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.6.0","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://pluton.cassio.pe/~julien/gpxpod-1.0.5.tar.gz","created":"2016-10-31T11:08:41.017766Z","licenses":["agpl"],"lastModified":"2016-10-31T11:08:41.068782Z","isNightly":false,"rawPhpVersionSpec":">=5.6","rawPlatformVersionSpec":">=9.0","signature":"UXeZVh5f0/WZE+r/dHWc1pu9q4qr/zACX6XraMhhIuKIp7vNCwUESeqJtVc99QZw\nw9kJp0isvib6l0zbQBRS1vI7xwKKBQyeaEhIciEs1JjsaCiato1Gyi26N+fY2N0Z\nFWlTwCsF3DdlwERXTYfeCpsOWCoxLxHKhnJIjUc0PVme/Ste4zxYj+5Su1RpadSw\n4vGnkW8zy/0tzua50NQCrOg+B4jXzH9kMWAP47w3sdP5CYalHSHl8EX0D1RjgGU5\n7vZYX3wF853FvQDbL4JXXCJupj3wZe8py8McWpQIcgz1p3KmE7A7d/rdNWExT+T+\nDxtStJ56qTRMz4aFwoSFxJrrEfgHIsE9Gv+Vo7nshCUYA8gkfHeckiaUtH1EiFTh\nVNeO6mTIqGpRosFvfUrZMKcuF5j74vGQjNM1o+M5N31gtLoPSkU605f/U4v+j2oC\n3/N1rYF2SEDFO0EgAGXaXVhB0ltSDkHJw6vZJ1L8Qz6tooUMDxaMri8vycA6LHvE\nqN+z+S6TXMfLvN/6ATYPGhicrWmkMT/k7v1az/hcnfH+zRyLZyFx94s88JWF7Jf+\nI+tgDbfqTMAIcytJUC+KfdQW1ueXh5F0owrOYM6jgBRvhqj1T8s+Twgw8orGmRPe\n9h8G9Z3wZAooQvmC0KdVhLuOeIkqt/S5krELNFFBRnk=","translations":{}}],"screenshots":[{"url":"https://gitlab.com/eneiluj/gpxpod-oc/uploads/db5af6ba7ae1cd4d22ea81d418f5c762/screen1.jpg"},{"url":"https://gitlab.com/eneiluj/gpxpod-oc/uploads/123588561a8067185572a8d1887ef906/screen2.jpg"},{"url":"https://gitlab.com/eneiluj/gpxpod-oc/uploads/427688b80bf8428dd45bd15d69b19075/screen3.jpg"}],"translations":{"en":{"name":"GpxPod","summary":"Display, analyse, compare and share gpx tracks","description":"\n# GpxPod owncloud/nextcloud application\n\nIf you want to help to translate this app in your language, take the english=>french files in \"l10n\" directory as examples.\n\nThis app's purpose is to display gpx, kml and tcx files collections,\nview elevation profiles and tracks stats, filter tracks,\n color tracks by speed, slope, elevation and compare divergent parts of similar tracks.\n\nIt's compatible with SQLite, MySQL and PostgreSQL databases.\n\nIt works with gpx/kml/tcx files anywhere in your files, files shared with you, files in folders shared with you.\nkml and tcx files will be displayed only if GpsBabel is found on the server system.\nElevations can be corrected for entire folders or specific track if SRTM.py (gpxelevations) is found.\nPersonal map tile servers can be added.\nIt works with encrypted data folder (server side encryption).\nA public link pointing to a specific track/folder can be shared if the corresponding gpx file/folder is already shared by public link.\n!!! GpxPod now uses the owncloud database to store meta-information. If you want to get rid of the .geojson, .geojson.colored and .markers produced by previous gpxpod versions, there are two buttons at the bottom of the \"Settings\" tab in user interface. !!!\nGeolocated pictures can be displayed if python PIL is installed on the server.\n\nGpxPod proudly uses Leaflet with lots of plugins to display the map.\n\nThis app is tested under Owncloud/Nextcloud 9.0/10 with Firefox and Chromium.\nThis app is under development.\n\nLink to Owncloud application website : https://apps.owncloud.com/content/show.php/GpxPod?content=174248\n\n## Install\n\nNo special installation instruction except :\n!! Server needs python2.x or 3.x \"gpxpy\" and \"geojson\" module to work !!\nThey may be installed with pip.\n\nFor example, on Debian-like systems :\n\n```\nsudo apt-get install python-pip\nsudo pip install gpxpy geojson\n```\nor on Redhat-like systems :\n```\nsudo yum install python-pip\nsudo pip install gpxpy geojson\n```\n\nThen put gpxpod directory in the Owncloud/Nextcloud apps to install.\nThere are several ways to do that.\n\n### Clone the git repository\n\n```\ncd /path/to/owncloud/apps\ngit clone https://gitlab.com/eneiluj/gpxpod-oc.git gpxpod\n```\n\n### Download from apps.owncloud.org\n\nExtract gpxpod archive you just downloaded from apps.owncloud.org :\n```\ncd /path/to/owncloud/apps\ntar xvf 174733-gpxpod-1.0.0.tar.gz\n```\n\n### Post install precautions\n\nJust in case, make python scripts executables :\n```\ncd /path/to/owncloud/apps\nchmod +x gpxpod/*.py\n```\n\n## Known issues\n\n* bad management of file names including simple or double quotes\n* _WARNING_, kml conversion will NOT work with recent kml files using the proprietary \"gx:track\" extension tag.\n\nAny feedback will be appreciated.\n\n    "}},"isFeatured":false,"authors":[{"name":"Julien Veyssier","mail":"","homepage":""},{"name":"Fritz Kleinschroth","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIIEATCCAukCAhAaMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYxMDMxMTA1MTI2WhcNMjcwMjA2MTA1MTI2WjARMQ8wDQYD\nVQQDDAZncHhwb2QwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQCq9p5l\nzNzR98l/xPgrplWrGQBhF6aQSLpnIyCdLAYKk+CmXn47W1pHh5CRYyCCyB8YPBBG\nTwbpP69pP1updfK2vUt+ShYmCXLxOLB3qEdhnwgqFfwpC48Ocev9d6XcacYp7jwu\nRNtv0ocMkm5o0TWWupcutQWkmqzCVq+OkrqM0xrf3mfPymuM6edEREshukoL86Ei\ngTuMMGT0XO99LikszvdceNQYEATix1MHzSVhkE7jHCNBXb95H6nQGr0v7R1MIbrI\nGFlgqxwwNNKwBFNfPMWZVnKwz9hoIwW6WOuu7ntwVcPqwB/gUsRZJTu7EjIW0trX\nnhA6xLlc4X66W1sdUCkJOxsV+Y21akz6wynI0SzIfjALLI2Ls4QWrPM8GOX8nPVM\nm+Y5WXzqLJScdWYoefFJKS7kxwUJRewREB9ykCG5OdDubV+Iu/6jh6HWx3h4p3ih\nqkDypPWoxpfLgA8VZkLD1RRKGkRa858QBGdF/RHbYT3JfLEp9l9gJVKZE/yw7HKk\nwsZ/T6CMpLyorpd1XWtp2wLX8lr3pp9ecVDOdAMSqD2thDMDsZA82JrJ/vITwkCF\nBlqtDZmT0UnpxYNYTfYBam5Cd00jsqCt+Hr+QkODNe8Yae9c/D0zE3h2Vt7g9H+W\n7Ei+rF5nDYTBAApoETxK7+aUZpycBf3THAJOcwIDAQABMA0GCSqGSIb3DQEBCwUA\nA4IBAQBbCGAEwg3M5QJDMnZgu0zNOH2f9bamAS9ksyCZqzLoeQic1W7GYYe9NqAi\n7lO5jXRZpTN4L133IUQPtxCxuDooD2vFmCne92tLxJbc7uqlSVfhL8uMVOlnrA99\nKTAhySTZU5so8/OibrngnBmcdWwbhaWoCQ671M8aXM1wg2FVYDqB2GP3RvbpW11L\nOc+4tfh4mO4TwXygf7KYMOJyJW8mNNY7PZ+XW2Qe3vSXR3DuN8H8fgMh5wppXPJf\nE0+yNs42hwFjSojtI8BCb0s5DTleaakpDo8HQGNzEXP8tBlUYudtjzdP0jxFXbFa\nsT9pcMdeJ0/t5HqJSx1EjUCLYS4y\n-----END CERTIFICATE-----"},{"id":"ownpad","categories":["tools"],"userDocs":"https://github.com/otetard/ownpad/blob/master/README.md#mimetype-detection","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/otetard/ownpad/issues","website":"","created":"2016-09-29T15:58:52.814912Z","lastModified":"2016-11-19T17:37:52.278497Z","releases":[{"version":"0.5.6","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/otetard/ownpad/releases/download/v0.5.6/ownpad.tar.gz","created":"2016-11-19T17:37:52.234684Z","licenses":["agpl"],"lastModified":"2016-11-19T17:37:52.423930Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"dh+Txg1iVfqXr8+cxplNQuBZGErSnXUo0ewGwnybNMJqp8/EjEo72+zPpW3dVnhY\n67YCvhrm2bo+VRdFFymEfymzSJu9nWVFkGJhEwvTxPyIdAtuD5YAVrzmnR6L+H7m\n7Q1nXE63ICPCAQpHkxIfIXLh25OhWeyofBB8AVsjDUNn58FEYJ8fFkr6dCgPriZS\nsM2J+xtZMDYufy+xFMsVf/Q3WopjFuBjMC3qOecW76ZTwtREaswOC2RtpzUku2r1\nsogrfFlFer3Ii9/CWgOktnLfjB1DzbTwdEkM2xNVBRJgdMXt2VLA9FsxFFkjmr5A\nl7x9cNLWA8RLpOIpIMBbaef75u5HgRBvSvq114UsA9GCu/EYbIgD8YxEt7xuKd4t\nenksJB5gJ2IQNdHrPbsil59AsJ/dismDN6ktYgWQEk5dINzvm9EAvucueW0Gt+Jr\nqEC5WBgJucsFxSvkHh52v43M8jgPYBfHWEL/M/+377z3+mbuIh+BcQ+vcDdiqxTF\no3n0+gw3QYIhLEe75sUhxG6ynVUdW25AKKju1kVj3KJnZTBH1R8t8/zy4DnJG8d4\nuRGqyU4BXpZjEC3nVlsC7vCncWWhxl0WZQ/MWKqsfjVAU4I88E518D6NioqMnPEJ\niCZ2x+69UCDEQyfCSKajgAYT17r3OhZei8F9KSCH8Vw=","translations":{}},{"version":"0.5.5","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <11.0.0","minIntSize":32,"download":"https://github.com/otetard/ownpad/releases/download/v0.5.5/ownpad.tar.gz","created":"2016-10-06T07:51:05.278637Z","licenses":["agpl"],"lastModified":"2016-10-06T07:51:05.348825Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=10","signature":"nYsQ9U5r7uXwtcquaWGm2XMJBNYCcA95aUx9gLZ/wEmjCHqId7+MzcCdBnom33+j\nat2XR2a4R96UAUP78bfSC4Yb7nPepFT51Y8CSpV3mDT85/+SgJdq500fXldq+qGY\nffXLneadAztyOfZO9TXljaCLdREYI0LJIGVENsxBQVKM/eyGIuZO7fF70cH5vbfS\ns37+BXB+fxcDTlP2Xuulra8HsNoS81bzjsdVMLM7B7QwwO6rZ1zd5c3UzQ1LmY5g\npQUBNd0KjfHfZ6+Fd64XZO6NGfgucGNmL3lgxdsfUqKiLtikvFxK39dYW5MckV8p\nvLoS2nZ7cgETQmAW9Ahn3ro7gXWcPxzL41oWtZOOHRRC2Yz5zlZ3Bky1o+bF9g5a\nYdDF13zV6utUkhlplZhWbjKaXa04rzOvmut8Iqhx/tmDtZRYtaQXJZWutVJYtPC3\nH86uJJnUHHNFHeoT560mp1Hq0dTeR+G+yWsPacPD1rTYgZOUVEtj3Y+YdbTODR2o\nOdGzeYFl+6CL/OcY4wPGRUCTFwvc31lIUd4DK5SPfN+IGtuuXhAqVhwy6lpkcKRs\ncj8sBoVXbMvEtMnt5uARBvA4tyVffUL4oyoIsUnvXz4u+q4WVt3T17swLm6HjGVC\nNVqU0srHN7EeBRhHlXP1CrKQWGQlS4k9j9Li4Iw+X8s=","translations":{}}],"screenshots":[],"translations":{"en":{"name":"Ownpad","summary":"\n    Create and open Etherpad and Ethercalc documents.\n  ","description":"\n    Ownpad is an ownCloud application that allows to create and open\n    Etherpad and Ethercalc documents.\n\n    This application requires to have access to an instance of\n    Etherpad and/or Ethercalc to work properly.\n  "}},"isFeatured":false,"authors":[{"name":"Olivier Tétard","mail":"olivier.tetard@miskin.fr","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIIEATCCAukCAhAPMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYwOTI5MTU1NDA3WhcNMjcwMTA1MTU1NDA3WjARMQ8wDQYD\nVQQDDAZvd25wYWQwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQC6CY7I\nHRJTaqDu376vt+kruX+QOL864joScxRuh3IOVcQktCvxasuA0EtrX7TCAQrV1tBK\nfkqJxU9uOV54RTgyh30yH/ZtnF2bYQwViGM06Snc0riqWydFrN5fxK52dpZWs63o\nUFCNhHxrX4aUGyfXu5nQMISLm4QHoZ3LDLofk1ZsiK62fM/Jz8N2PM8qeHzf1ATo\nSKcAOd3UeaS9C8bv2DuiZM7unkSO/tjrBzkMiq8ds9sIzBBsyk6BRh2HQjHPOtmO\ned+pS9mIZmc2xhssXoHL4IfZwTqwhktpsaTl7v0ROw2dwDATz/QoKMkUpboQ5lkz\nwgLQhoIZw6uAZ1R/Qjze59I3iU8zIo9quDarHBotZNXboYCmg9FRfE4mHtegVaa8\nv1a1JvFQ5gvsWEsKSV6Bzb65GTp4KG4q7YnUrzh6HJyDCGLvLlWm5OWsFj6sNzXX\nwLOv6JLORMbF4ZIo2iybb3x7gdfCu9JxMZ4JtOUC8KSJ6+ub15C1Aia3lN68dNts\nY6KwUF1Ted0o4OQPAulq5pUc+g6dTYmIKsavIiPKhMtl86AbUK50vRTeuGdFsT7X\nav73IanPdFI9bKth+tajgvB6dxcVnvBXbrsLUyEcsxsxtBJvQcMYS4aZ6ZJYLTep\n7AdK0Zt1iMdXB8+4PCps4rcG6bYB/uJeEAVm7QIDAQABMA0GCSqGSIb3DQEBCwUA\nA4IBAQCM10O+sCYhIExnx01vGzKlnRS7MSQNx8ZMmbR5Elfz4AVJAEJ96ytS2DXH\n2c+hcD0wAenXQEFk920AEqFQBT8DP34p0FmF83aMHW08ovzFiu4MdlhcqrLnko0h\ncZTXHVyS/8JZh+o6SVm8R0/BBLF1MQQ5TqRkJehbmk6gL0+MSYxehUDKWTjJITkR\nifneTw/Ba1d0AXBOq0c0HFyGxMPIlWe4qn5LtxH5t0wyVGeSj4jyv4nvd3ZGuAgY\nEUa2uYht/z475k4+vf0YhV98iQH07GnmlfD2TDZgmOCQGKlNfJh1v88OZyLLa3dz\ngRHzGwKbAiJ8T8bbpZ3e2ozXxADr\n-----END CERTIFICATE-----"},{"id":"announcementcenter","categories":["organization"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/nextcloud/announcementcenter/issues","website":"https://github.com/nextcloud/announcementcenter","created":"2016-09-14T10:38:53.939634Z","lastModified":"2016-11-24T11:21:50.324839Z","releases":[{"version":"2.0.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=10.0.0 <11.0.0","minIntSize":32,"download":"https://github.com/nextcloud/announcementcenter/releases/download/v2.0.1/announcementcenter-2.0.1.tar.gz","created":"2016-11-24T11:21:50.317635Z","licenses":["agpl"],"lastModified":"2016-11-24T11:21:50.386203Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=10 <=10","signature":"lmqeE6xBqUJfhuXPbjCfuWiIP0FEB4V/SsF/OvYar6rLpvDpJVf3DJoeIoxXurRP\nE9/xCcNN44P8PreRRDnFLCa0XsKOtwoGa56Lxk7IKvtiQG6xu4J6PKM+q/tIeF9K\nakw0LQXtjZB5InPhnCDDbY5YS9jgGEBylSHsgNgrElipcW+BzOBu1Amw4FECVlQw\ncQ83bio+YPZvsnE5+v3/bAx0m6QNxfyN9Sn6rMEqRkY3jfA3vejXGQH/XkputfV+\n5hOz48KbOVg7cKxg+ieJlSwC0aYjb+RXiopjc3icCoIF1llltOOeSsVYSflOb080\nupociPgQ6qIab/VNNXa2YQ==","translations":{}},{"version":"2.0.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/announcementcenter/releases/download/v2.0.0/announcementcenter-2.0.0.tar.gz","created":"2016-10-06T12:41:56.195206Z","licenses":["agpl"],"lastModified":"2016-10-06T12:41:56.263124Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=10 <=11","signature":"NVWYz73KtuoZ7ti2sluztJO5aFUc7PzhlDcg0VWyAQd1H7sk5wjw7i0bhrjw8O7M\nLsrb+PegnsL9eMlYM2WrRom+RF1PDP482xymZf1T8vh8qcTCm3TK89xSuiSm8yoA\niWUb/Uv/ODj74wVDWqWxAFKaAG/FestCB3InOOZQqQZLzlAV0U9ziYDGNzBjFqof\n9rLNxJ2IOqZOA7hhMIKhSrpA0KkSfNhBsVf8CWClYnVkZQiq0LoYkHkHIlXmXUr3\nOfQFKEjtsx+bNLa6CkAaocHGHJXAofX3GQZ9cjBsjZqiTfbXfcVk0kRfz7pwL92L\nI1McfJYvgMxDQG5bjRpNJw==","translations":{}}],"screenshots":[{"url":"https://github.com/nextcloud/announcementcenter/raw/stable10/docs/AnnouncementCenterFrontpage.png"}],"translations":{"en":{"name":"Announcement Center","summary":"An announcement center for Nextcloud","description":"An announcement center for Nextcloud"}},"isFeatured":true,"authors":[{"name":"Joas Schilling","mail":"","homepage":""}],"ratingRecent":0.75,"ratingOverall":0.75,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIDDTCCAfUCAhABMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwODIzMDkyNTQ0WhcNMjYxMTI5MDkyNTQ0WjAdMRswGQYD\r\nVQQDDBJhbm5vdW5jZW1lbnRjZW50ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAw\r\nggEKAoIBAQDPx4Hp1HdBo5v7bDEiVcv2UrVjNW+fPPKS/5rwbagtPcE/1v3WDcwX\r\nvFwaXk8qCn2UpPSQ2b1rTuTDm51G1ZmEZhNiio+rBfEe9F+3tLsq9lElqIPKhkAq\r\nEUVI6dcN+jSqvLmLhuwloEoQQSYaLrX75mY3lGqTb83h1l2Pk/brVixuVf4vJW31\r\nTgeieuGKnC+keLzKOrvTHffJakU8ktwB2Nuu1o+jN5a7u1bxKkP3LjEWPjq236hk\r\nAoOcW/wi1dUEyUKUZsZQeJyvTJh1UXdLHKwYywtUu1/VLZ1IUtNyPBfiQ8ukPp3T\r\nTnSSmG3ZnvsfM6DmAvLZ8bBQkMBzEcTLAgMBAAEwDQYJKoZIhvcNAQELBQADggEB\r\nAAB3i2NgiZ4rpNag7cXYdaFxAxdDWnke1+LX2V2R3hzGmx73/W6cKLpo3JBn9+zT\r\n1aEjlqkt0yHu4aAPVYQzOa5zIV8mjP84p3ODSyV9J8lfjFNXT7wdA8+9PVx3lVki\r\n2ONoCNBh1kOxnxI4+BsMlQfF00ZbBSuGcMm3Ep3lTFWXzuUn3MQITzPwkL5LkW6a\r\nsli/yAYQRTVDsXD8A3ACYT7BG31jGxyXtIHzqCci0MhZFdKKayMYkwfjZchIUtGN\r\nJJoU8LQoHwGRtp3wutk0GlFzpEQEvSsn/Lsvvot5IfIe46tnzA6MVj5s64s5G8+Q\r\nphhXFlzXqO/VxquPdbfYjvU=\r\n-----END CERTIFICATE-----"},{"id":"ocsms","categories":["tools"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/nerzhul/ocsms/issues","website":"https://github.com/nerzhul/ocsms","created":"2016-09-19T21:56:04.745481Z","lastModified":"2016-11-11T16:29:55.864273Z","releases":[{"version":"1.10.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0","minIntSize":32,"download":"https://ftp.unix-experience.fr/owncloud-sms/v1.10.1.tar.gz","created":"2016-11-11T16:29:55.856768Z","licenses":["agpl"],"lastModified":"2016-11-11T16:29:55.947926Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9.0","signature":"hVzbkmmtJCtiOkZGe1mkWElqS3IPQ8wLtSzikVvoKmcg+Zq3YLQjpQWzy0t3UVjo\n9I/BfnL0bF+kjtGc9xF6M8IQaFqPrfJmN+lNT8WYIKLI97TTsLmJGg8Q8PAux3nY\n8/NxMjWdByMw9nVBClKo0o9eSW4+EnaUSJ62Gl/XWjq728kbB16WZm+iesk8LjJJ\nqqAgczWGwz6lkZTCN5o9n0a/YoLJTf4iT+OItHZyS609Cqaxx9CAmZPj/Xn5g1fm\n8hqO2ITAXLoBj4rYS/QsZTMcubtGkJ8fq3XYKVSv2UXZfvGsNWbbGV7puKN33uWJ\n5MrdoMlJ8XnJRPDlCBcb00LY+AB+hAMooLnNy765/Ew6ztp4KNLEPWGG+Ut8/Lkk\n0jIULl1RF/FjlW8P26NfwH36K30RCJFY06OFcWnxGBkkQaNFORDIsKcqTAxkl4x5\nnfKBkNdQZppCVfOSKOZj4NkWfWx75Ouq1S0QksmOsMZoOcjy1TbBKR8h6dt9DQub\nWpYBL0QwyQShGp0Vb1qCKkP69ZQAHVUJNzIFPz9LyoguvFyv8iZmAPLYDnFBvlf2\nnSHtA19rnJmZ4H7RJ02r6BdkstxISvEiHU7RLjNQxcb+DptIWX5C03wH87HTNIhr\nvptPorEoSY1KwW9fqUvvLE/c+vfkr5cvIEwZlyVKVXU=","translations":{}}],"screenshots":[],"translations":{"en":{"name":"ownCloud SMS","summary":"A app to sync SMS with your ownCloud","description":"A app to sync SMS with your ownCloud"}},"isFeatured":false,"authors":[{"name":"Loic Blot","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEADCCAugCAhALMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwOTE5MjE1MzU5WhcNMjYxMjI2MjE1MzU5WjAQMQ4wDAYD\r\nVQQDDAVvY3NtczCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBANqZVIzM\r\nwBJuacuvgYKr2KnXuYhjKjZ58nfspSebsaGLr0ifBbo8L+NH5eaynnLCNhpegmu0\r\nO8D+KrbM1LtIkcdg1/eFpN5dTc6G2OAc9H9stmHs9nroF6MNhszgdZCz8Q8xkSoD\r\nGdSm8hdPg5GcfLrH27UilGtzdQlWJ1DralLMt3l+SfGJo152c/dc+e6SuT8+EbY2\r\nCeLdH5ImasXNpUgY+tAoEt2ZvhBrUghykBJTJVOjwL1jGLT37ybMtV4FBKo6hpeg\r\ntq/YzEk1ijBAC4pmoNWixDKCdolpVJVz0fijI9mlda3llurcp8eMhxfYJ9soXLHp\r\njvLX02YY6RfPcyy48uWVk4IEt9BvZWEVAAp7hCGA2yXrVSsR37E6sDbLsBcKav9A\r\n6dkGAgfmGkr2WT6O1/EhK/MakmnYO4WD1B+E7PnxtP/wOa+aQBmntQcd7igDiwzG\r\n6h05NYAWcRhqfZ4KWYsq0t0SezMbuHOhwzzi22q8wijC5YZbmhKSh+b3N8XwYKDi\r\nZaw+fSahPbRWaLyR3wn9zh7vKCwqrG3ugrNo6CtyoACAnmxKZ97ROFJIQTe3ndLL\r\nmv7Wy8iCZLhRYUaW/GKrF11AFwBVec9xmvkgU+PIKq2HSjwi9sCF+pFyhVjmq29C\r\nmZEPKUV7ySIpNHXpsXm8kTJJfqjSdb2ECbLfAgMBAAEwDQYJKoZIhvcNAQELBQAD\r\nggEBABvn97e8Nw8KAscf6FX/nZ99rEX+3IrZxTC8fmBgNwAvlbF2A+QZQcFI4G9/\r\n85nHK117+u7XDuwWl4QG3flWlI0hDE59Ud9Bd4AiTQ12VoXlNdYoTg/mXARxVozb\r\nKYqZ+1xRQclZKb2AqW8YiGo18okIKovn9VVRAFYPYx4O3Ve1FjgfsaMlIZLiXUFm\r\nkk+2qWo6kYsdU9FABLo6izx7RFOMbnYNre5FmDrWP1Dga/U7ErK/Dilh8g9b3HrP\r\nwP8OIZhdtFWw21wDTfyqrb9EhC/tsjPVP9u+bqyognHeiMhjbVYRbSvz5o8T7Mhj\r\nbxalCt4/LnMIfMwVyIvye7Uy2GY=\r\n-----END CERTIFICATE-----"},{"id":"rainloop","categories":["social","tools"],"userDocs":"","adminDocs":"","developerDocs":"","issueTracker":"https://github.com/RainLoop/rainloop-webmail/issues","website":"http://www.rainloop.net/","created":"2016-10-20T04:17:37.217555Z","lastModified":"2016-11-18T11:36:04.309739Z","releases":[{"version":"4.26.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":">=5.4.0","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/pierre-alain-b/rainloop-nextcloud/releases/download/v4.26.0/rainloop-4.26.0.tar.gz","created":"2016-10-20T04:28:21.491747Z","licenses":["agpl"],"lastModified":"2016-11-18T11:36:04.619927Z","isNightly":false,"rawPhpVersionSpec":">=5.4","rawPlatformVersionSpec":">=10 <=11","signature":"nTYIVSB6mIwKtXIrKoVGsOGFflpLjed8jFem1VLQNtXQj4bztnNrdc4YaPIn0yzM\nyLpMSqRDNzdYNFuOeDiyKLPJPTA++MotLCNjEe7kxUekek+m+qzgnGBdcT7RQT6R\np9xWGecnVx94d6aA55uiRhgQRyHpdDMMLCOz1be+HvpwHy69DRFZ1+SPmGUt6eW0\nu5yS0vHCu1K22cbrVNXFKjxAOlGcIDm61oQuz7ycl3uAujZO4rZbWt55jilgKGak\new559A5gTp9W+j+TWKIcg6LIZ9zLRlGjcQrWJrsc+OBZQcqiYimSFyO6HhfT9TPS\nPof//I+dSsd+H0SRGGeL8DvSnK+NKZL1q5EX5pziqsv6nZFITpCDwmAN+I8AnXXL\nSNkFi53M8RZTOABpD2x7YPYP1cEvwrRweqV/C/oHcYnpfh7D2DjFeWwXsjeAXrHY\nhgFhPrg+7rf7g6UmJFOCp0dC9sBdyQ3KtJkv7bGqPr854r2cdA7xW0QHWQ2in9qQ\nLhIczc32ECi3ZVVgyF8zyT4Y/3MRS05oX3FHvHyt88mjni6bVaO78F7ZRSha8gHh\nNOAkku7AMXPvUCHaZP2iVCCoAViEso8GeR3O8xh2G42Ai61RLYwx8LB1+23EoJTr\nmfFuRYNSg+qAKCokXNnh+lDlwu4AkaQo3vtKGPXvU7A=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/pierre-alain-b/rainloop-nextcloud/master/screenshots/2016.10.20-screenshot.jpg"}],"translations":{"en":{"name":"RainLoop","summary":"RainLoop Webmail","description":"Simple, modern and fast web-based email client."}},"isFeatured":false,"authors":[{"name":"RainLoop Team","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIIEAzCCAusCAhAXMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYxMDE5MTkzNDEwWhcNMjcwMTI1MTkzNDEwWjATMREwDwYD\nVQQDDAhyYWlubG9vcDCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBANqB\n5jnF9qZ/qjckt0kRjpHCOMtJumW/KiQoMeNP5nGv4ad0DS3KemOapUef8Zn7qCYb\nMnODhK7HBwPifFzI1j8bnT2hP6E0geFLb0MdN59d2NF0n4CCs1+BnepQPJ1kFbPK\n35wQRi0RDeTf/GQ+/owEVCU9a9W1P/VUXk8Z0vMoQxCXEdRqnB63SgsKl7DB9G/C\n4SYrgGor+OHVGl4ntMZhJujiM996DttrNK3iZRGkQ07L+lfUIwQ52XOhQNRdic4p\nB03lw7PpChwPGMv/EEvdR5HpCJQBJniqJbbu3Jh8bMBKTE/8fCzN3vMXICB2g3Bq\nlKkZW6fnJRGsrZ79fsQnl+WBPNSrWRLOxOfe1fyCFV1ljFB4nTH7uF3pC8ZRgJes\nkHIESHz3GJm28hn4+17ESMGHBCbs7L9FK2GY31cobU0VRntLxpSG+d9njbIAgMG1\nS7U+oKVFQhSVpdXNOaUNqhcQ3HkbQTLEP0k53A/lhLQb2+KPd8nntaELjwNyrmZg\nsVMgHj/zdlvrbguZjZFzUzDBFvkuv/5M58lNT/D1C6ufVp/R6eLsYI+nnk1ojAjz\nl7N6U8X5SXpD+Bm7+Kn1PH+bHl7cViCx8oXJXO2RhP+COXckw7BDZKtjItYHNG7M\npFwgYqWpvCu9LN6IN5a/eLqSI76dOOP3iYbaTH+NAgMBAAEwDQYJKoZIhvcNAQEL\nBQADggEBAGB0Vq0l6ndGTgNbZxSEFyBR3u3tiR3pWK81DYjsui7qBoO6P/BaGmf+\nraSwHPaBOwA9XNS8jcGLh5xdqY2p/m0dTS64xNjVL9nweWsG+FwVnPANo8C4nXdm\n9ajJ4cdg54stQK8qn1uh/xPcd23GKfYJazjYSwYmZ3pXXdzlGN9NxkeYJQxJ6B+5\npzAeVGiABI/e5URpxzz2UayRX7EE+vtpe3B84hzkLqsq0N39ZN6KLfaTyEBGLzqE\niLYeXQTV0XSRs8xVt+iyGlj7nPkv2DR0oCqRpWUFWeSBI//niDG5WxS3qg8kacSW\nfDSYhSN+IjrnIkwNtc8V9t7/GeQB5FE=\n-----END CERTIFICATE-----"},{"id":"richdocuments","categories":["integration","office"],"userDocs":"https://nextcloud.com/collaboraonline/","adminDocs":"https://nextcloud.com/collaboraonline/","developerDocs":"","issueTracker":"https://github.com/owncloud/richdocuments/issues","website":"","created":"2016-10-31T08:55:45.631429Z","lastModified":"2016-11-24T12:13:53.905352Z","releases":[{"version":"1.1.3","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=8.2.0 <9.3.0","minIntSize":32,"download":"https://github.com/owncloud/richdocuments/releases/download/1.1.3/richdocuments.tar.gz","created":"2016-10-31T09:03:40.389355Z","licenses":["agpl"],"lastModified":"2016-10-31T09:03:40.439510Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=8.2 <=9.2","signature":"s5giQeiU2zwV5X6FmxWXiG9LtNDeKBlFqK+hfvGNbGZ+nic77Y+AnXHodV4lb3Ko\n0C0ThFLuafaZRdp9rBIN2K/acCfCYKJewGuYErb7FlEl+P9J4OQbb9pva0htZJw6\niuG5eyeTufi5MKB4vuj4+jo9zhepOFAtZMa7o+ZCfJkt8vDBuq5AXxomEiZRtW+n\nf9PPUnq0z7DJVwINhHvvBZJlSLjkpJ6VIHAr+/ElWr8O/mDKq5S5ohbvpDcPqR7b\njnsBckFDLFUz1FX9dA0JCJEKMMfkcfGqZcjH17NdjKAxRW2soN5cEKluu5MkOhz9\nFEPKfshzbrfUIm5MaFGv6w==","translations":{}},{"version":"1.1.14","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/owncloud/richdocuments/releases/download/1.1.14/richdocuments.tar.gz","created":"2016-11-24T12:10:13.337165Z","licenses":["agpl"],"lastModified":"2016-11-24T12:13:53.963638Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"prDGlfRPxqT6LP0BsAFPwGww7P4Bngha2N4u5B6+F02N+RVOjGtTcXKqvM1KjZb1\nCo7qJvgJmjpvIvDmB+rup02i8ObfwP2ct6UdsD7ouzOWJG2sJANXK31bHyvOmQ2h\nvKu5eNcOkf+WFyFKYi51TbsfWn2+1Wge3WWujKAVcEvqtcOOz+uMWNtqzBptEupk\nE1aaRnQfTx488YB8Ubul06LIY0PNCHgGCWPgy817tOVT7JA+V0P0FFonl/PXE0dr\nWgtxRJmvGaNiFzYq+kQmdKMfayZTm3kdVgP0W52t5wp878K0i4s2KPg5lANvjTz7\nDCT+VV2IGIE52o4RpMUGyQ==","translations":{}}],"screenshots":[{"url":"https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-document.png"},{"url":"https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-app.png"},{"url":"https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-presentation.png"},{"url":"https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-spreadsheet.png"}],"translations":{"en":{"name":"Collabora Online","summary":"Edit office documents directly in your browser.","description":"Collabora Online allows you to to work with all kinds of office documents directly in your browser. This application requires Collabora Cloudsuite to be installed on one of your servers, please read the documentation to learn more about that."}},"isFeatured":false,"authors":[{"name":"Collabora Productivity based on work of Frank Karlitschek, Victor Dubiniuk","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIIDCDCCAfACAhAZMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYxMDMxMDg1NDExWhcNMjcwMjA2MDg1NDExWjAYMRYwFAYD\nVQQDEw1yaWNoZG9jdW1lbnRzMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKC\nAQEA1jk29m6JykcJ2Ld0YEpjPMYh6kwxY6GysNJnfkA/th7tPWL3+vBJ9oTYyVnZ\njwAE1Cqwfa9MyBKMZ2IdfIqtT8PeWzuFP7Ib942EdxUpwwh9F3lykeGsj0h4zQwX\nF9OooiS99PfLX+JpkKm15Ujb00iLB6xQmq0+3NeOT1CTD1ziJ1ueOcxBKMwaFp2a\nPuz3F5ywqCvpmxG/OBuOs0LI3/zStXhBNbUMxBrWblr7zaVNJXl/I2JCKj8Wah/H\nXUEEGbW15fAUP1f+90eQSxpEoCZDBHXOQCTJYzySGv+BjU+qlI9/gS0QbrsiyzUf\nO5lyvi8LvUZBzpBw+yg1U75rqQIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQA9jU3m\nZmD0ywO3MUsG/GLigFtcWi/p7zp2BliR+NpuY2qNFYDcsIb8ZUudmUc/cJRRctzy\nAPaLLj/d+h5RFaxjTVvim1PSe6M7urK/IMSvyUVYCeQRYpG8ZJixKTCOVIBaWHMz\nxTfc51tm9EPlpJpK6JtaWrYYoWGE3k9sINdJ4JkvKkE2CBAqVhX6ZGyEQ0bnEhtk\nRu1DXn+LW7TJ4NZ8VtLWvmW/6Kfmi7dQ1V++Kmn0lO5ntRt5altePbStCHC8bhGp\nmyBOrjhrJgLIwvgH26MYZhdiSkFzoE38nMPZdrUmUDxcPCwucWJqgzDPudguFthj\nWCVZ3TTG/2z3+tWM\n-----END CERTIFICATE-----"},{"id":"ocr","categories":["files","tools"],"userDocs":"https://janis91.github.io/ocr/","adminDocs":"https://github.com/janis91/ocr/wiki","developerDocs":"https://github.com/janis91/ocr/wiki","issueTracker":"https://github.com/janis91/ocr/issues","website":"https://janis91.github.io/ocr/","created":"2016-09-19T12:07:49.220376Z","lastModified":"2016-11-21T11:22:21.024501Z","releases":[{"version":"1.0.0","phpExtensions":[],"databases":[{"id":"pgsql","versionSpec":"*","rawVersionSpec":"*"},{"id":"mysql","versionSpec":"*","rawVersionSpec":"*"},{"id":"sqlite","versionSpec":"*","rawVersionSpec":"*"}],"shellCommands":["ocrmypdf","tesseract"],"phpVersionSpec":">=5.6.0 <8.0.0","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/janis91/ocr/releases/download/v1.0.0/ocr.tar.gz","created":"2016-10-24T06:50:43.283900Z","licenses":["agpl"],"lastModified":"2016-11-21T11:22:21.269108Z","isNightly":false,"rawPhpVersionSpec":">=5.6 <=7","rawPlatformVersionSpec":">=10 <=11","signature":"CBJkCIiUKyf2NuWfz2zJ3grhf8p7wJes7DPV/OxUzhlxIH0Fh7K54+U5A9JOOi6f\nWPhjXG1ylkyIVY1glr/B8svWNsD4jAclpnUi1/9ZW5UPT8LnRBfTbtF9Uoj0OgNs\ntsGQYbpuREoHnjbJWTRe0kq1OsOfX44xuf8PuX43B+lpQPW4iRSSz3ZIhdPcDGq1\n7pyqQM7gdKhBQ6/tOiwd7Enyt5Hi4V6jhwhUOCYeTNiLD2V3yKL+qA9DzpXUfNNw\nLGTjcaMrifibHQIZBZWbPPMmCfMJZ7GO9oR4gWHwkhWqt0yVWAJXAHJBLd5vXC5I\njtRTXRpHO/k6Dtqem8tZCVoDE5MAC7fDZ/0XzoFiXHciP6MenVasVcXo6xJOJc5y\nGsrecNftUEhP/ngxA6lMBVkLmmdpiexVisvsavPi64i34OUA6qOuxjgNVBDwg56i\n2lOEVvHa3nn0UX7ZZoQ/Nu6Mz7J3Hx/VDlttPuWe42eeJAphyDGubT1M62gW8dVB\nD3tJOF7spnK6I3BhVLviou/zs30AIRVBDTU0Orzx78cbInwy6/vyJib2a1olAaHz\nv05SzlQRnBWM4jYBe0mA/2ds9AO6VrXGrT/iLlHemj6JYoGBM185TGewA7OJyX3a\nHSlSDqaremmi+aS3onx3AKhXykDxTRkMVarePwTzzFs=","translations":{}},{"version":"0.8.8","phpExtensions":[],"databases":[{"id":"pgsql","versionSpec":"*","rawVersionSpec":"*"},{"id":"mysql","versionSpec":"*","rawVersionSpec":"*"},{"id":"sqlite","versionSpec":"*","rawVersionSpec":"*"}],"shellCommands":["ocrmypdf","tesseract"],"phpVersionSpec":">=5.6.0 <8.0.0","platformVersionSpec":">=10.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/janis91/ocr/releases/download/v0.8.8-beta/ocr-0.8.8-beta.tar.gz","created":"2016-10-10T18:01:16.076330Z","licenses":["agpl"],"lastModified":"2016-10-10T18:01:16.169733Z","isNightly":false,"rawPhpVersionSpec":">=5.6 <=7","rawPlatformVersionSpec":">=10 <=11","signature":"uEvhHfQCrzb6z+QuOoO8rYXiMsZFkrFWEqDvTyOTSgFKvo7dVoj3EfDfaApgcKEB\nIM/SqjLSO0lNhrp8F2mST3twbvLDprKsfrDWKFE6eiH0yKl2aNB+cHWE27utARaX\n/QZBD114vbWeDnbaBa4b9OwtBXDqKJrnO1LmqSLFP8guAlVTkU1jSPkRTpmwAcAZ\nJA/aiN/D2LSGfiNp/YdeTuzU+gPINIs9dCb6+PPkyam8PCBaXUSSaW+c0lAQHln+\ntb3EXxZ5YXdjPWrpEyHvFLk1N8s/w615QoMxr5fEs1M8D29aGbcL/wu7LXH4X0Yn\noiWwIFbpfrpJQlrIFumxWZR74JXiNr9J7ijnQ7SjxdHCcrLxMdnZ2cwq4iD6PnYm\nnIojhlhPOqUIzsWYCYutLWDQbUQz9eyWbj/+7XL+5KjetUUr/MnCu6xJ27IqFbAX\nHc8KRCO+9I0/qMQ2/rCZXBMeo39MGhhkgkVl5YAKwC1IEN/jlfyVNXZwYlfcHLKj\n6aNQ4zN6gGOerWWZ8qXtOeNJN+qv0nmXUKrZdnZUAhxOdB4G9Ym+JujxJZ9yNIWV\nsiqAI9J+OIzCwv/dzZhaHadONoo/RTF+Fl6Hy56HyBtMehb8w9p8ksVediqf33yt\nFAE/tzKtNK5NiRd+8MZkq/GbocaFUv3C7Y6pLMpTE1c=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc1.png"},{"url":"https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc2.png"},{"url":"https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc3.png"}],"translations":{"en":{"name":"OCR","summary":"Character recoginition for your images and pdf files.","description":"# Description\n\nNextcloud OCR (optical character recoginition) processing for images and PDF with tesseract-ocr and OCRmyPDF brings OCR capability to your Nextcloud 10.\nThe app uses tesseract-ocr, OCRmyPDF and a php internal message queueing service in order to process images (png, jpeg, tiff) and PDF (currently not all PDF-types are supported, for more information see [here](https://github.com/jbarlow83/OCRmyPDF)) asynchronously and save the output file to the same folder in nextcloud, so you are able to search in it.\nThe source data won&#39;t get lost. Instead:\n - in case of a PDF a copy will be saved with an extra layer of the processed text, so that you are able to search in it.\n - in case of a image the result of the OCR processing will be saved in a .txt file next to the image (same folder).\n\n**One big feature is the asynchronous ocr processing brought by the internal php message queueing system (Semaphore functions), which supports workers to handle tasks asynchronous from the rest of nextcloud.**\n\n## Prerequisites, Requirements and Dependencies\nThe OCR app has some prerequisites:\n - **[Nextcloud 10](https://nextcloud.com/)** or higher\n - **Linux** server as environment. (tested with Debian 8, Raspbian and Ubuntu 14.04 (Trusty))\n - **[OCRmyPDF](https://github.com/jbarlow83/OCRmyPDF)** &gt;v2.x (tested with v4.1.3 (v4 is recommended))\n - **[tesseract-ocr](https://github.com/tesseract-ocr/tesseract)** &gt;v3.02.02 with corresponding language files (e.g. tesseract-ocr-eng)\n\nFor further information see the homepage or the appropriate documentation."},"de":{"name":"OCR","summary":"Schrifterkennung für Bilder (mit Text) und PDF Dateien.","description":"# Beschreibung\n\nOCR (Automatische Texterkennung) für Bilder (mit Text) und PDF Dateien mithilfe von tesseract-ocr und OCRmyPDF ermöglicht Ihnen automatische Schrifterkennung direkt in Ihrer Nextcloud 10.\nDie App nutzt Tesseract-ocr, OCRmyPDF und den internen Message Queueing Service von PHP, um so asynchron (im Hintegrund) Bilder (PNG, JPEG, TIFF) und PDFs (aktuell werden nicht alle Typen unterstützt, näheres [hier](https://github.com/jbarlow83/OCRmyPDF)) zu verarbeiten. Das Ergebnis, welches jetzt durchsuchbar, kopierbar und ähnliches ist, wird anschließend im selben Ordner gespeichert, wie die Ursprungsdatei.\nDie Ursuprungsdatei geht dabei nicht verloren:\n - im Falle einer PDF wird eine Kopie mit einer zusätzlichen Textebene gespeichert, damit sie durchsuchbar und kopierbar wird.\n - im Falle eines Bildes wird das Resultat in einer txt-Datei gespeichert.\n\n**Ein großer Vorteil ist, dass das Ausführen und Verarbeiten asynchron im Hintergrund stattfindet. Dies geschieht mithilfe der PHP internernen Unterstützung einer Message Queue (Semaphore Funktionen). Die Aufgaben werden somit getrennt von der Nextcloud in einem eigenen Arbeits-Prozess (Worker) abgearbeitet.**\n\n## Anforderungen und Abhängigkeiten\nFür die OCR App müssen folgende Anforderungen erfüllt sein:\n - **[Nextcloud 10](https://nextcloud.com/)** oder höher\n - **Linux** server als Betriebssystem. (getestet mit Debian 8, Raspbian und Ubuntu 14.04 (Trusty))\n - **[OCRmyPDF](https://github.com/jbarlow83/OCRmyPDF)** &gt;v2.x (getestet mit v4.1.3 (v4 empfohlen))\n - **[tesseract-ocr](https://github.com/tesseract-ocr/tesseract)** &gt;v3.02.02 mit den dazugehörigen Übersetzungs- und Sprachdateien (z. B. tesseract-ocr-deu)\n\nFür weiter Informationen besuchen Sie die Homepage oder lesen Sie die zutreffende Dokumentation."}},"isFeatured":false,"authors":[{"name":"Janis Koehr","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIID/jCCAuYCAhAKMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYwOTE5MTEzNTAxWhcNMjYxMjI2MTEzNTAxWjAOMQwwCgYD\nVQQDDANvY3IwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQDDpXiwec4f\nXAT//7YBPSb4z6ZsBJSMfBq0VTL/HagjJnQ7BL+WagzWlS69IStNDqlIlenamYRX\n4B40heJIUinzYKjTRbr5UAw6MX29HibZccm/qgrk36o1XTgIsoRhmvSxbXDVIo1k\nbDOJN8gc2Gvswa8X+uOe9pfcDgAdqGxOvFnoKW89GnB01pCNT+xakNErGAFqVLsr\n2AeademAZnbxJ1cB54tQn2Bygb/7DKKY8EmFfIq6/27n9Jbph1FG9HIlWRT4/M2H\nU2pG3cCScWMEBPsW7kpfpnzLk7Q30Oj6k/rEYjJgmNYgg6oVnn0D9uRmhBYBnGyx\nMab1ilsK53lyuzQY0pmU8V5ULqpnNFAK6DVFfofEamDUhBPO+TZXEA5cZmuULRpf\nQQXmGpUQSyV6pS9WirMIqXFp9wmQ4vtjMdhu/6CP7cmtYZdq9uOhWEHbQM0mZUkb\n8hMjeItPx9XITI7Cge1JUOI8ZIwiB3USnQXcMd3v82l++/VgqHB7s5OaKPhygsWI\nM6RCoBcGiuQB5/fEUOg5ACOpGVyJiBda0Mi57AdoxdJmfnr7Bxcf2tAWIJL9Y7T3\nE1+V2BMxJOWwvVz26Cq83F41yXK2hJS+SbfQTqNUR8Cfh50CS9POvgRxNrJK9yvI\nkKle3ITRtGVM1XU0njWjnsdGg3D3O2mmjQIDAQABMA0GCSqGSIb3DQEBCwUAA4IB\nAQAbFddMbgfPI1szT57V1FKZrOrdYqQ7qjewlIQOzshGydbMtqS/9XL5hYocJCMt\nY6w+C/i6iEzO2Jx8D/k4rcZMXoVR6y3ZvO0Ke0gzSRsU+5eYj2FK1VV+cNIQW5Iu\nCYYIVa7pVPVHdeQH2Bba680bLV0HMF6b1fI9IwkfdCAinvCYZLjyEXZlmB7YjyA8\nHR7qPCNz4uG2Va7mlUHE3UYUYnlv8JFOV3YdbVL0nxhWwIdzSri5sxFIhdlabpzY\nyA1z/MCBEyTRo80jxFmL+MpwbsdbUJi7Qxlnd56zb6HHDGrLHXZTh9LXgyVbnhWL\nkxomWjIXQh4aMHQL4QF7U4EK\n-----END CERTIFICATE-----"},{"id":"spreedme","categories":["tools"],"userDocs":"https://github.com/strukturag/nextcloud-spreedme/blob/master/README.md","adminDocs":"https://github.com/strukturag/nextcloud-spreedme/blob/master/README.md","developerDocs":"","issueTracker":"https://github.com/strukturag/nextcloud-spreedme/issues","website":"","created":"2016-09-27T08:43:07.835196Z","lastModified":"2016-11-21T16:51:23.703819Z","releases":[{"version":"0.3.4","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://apps.owncloud.com/CONTENT/content-files/174436-spreedme.tar.gz","created":"2016-11-21T16:51:23.689599Z","licenses":["agpl"],"lastModified":"2016-11-21T16:51:23.826509Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"Mhy3hXeGWlIujx1Op39MMRdqHYOo360BCwr4FPWoTNNggH3aS0gWlh48DAfGYK9W\netNiOqIuRyA0NrVlsqR2vDILgFtODJSbKPyHd3PQn3hcGsjogjQ+dkKciLNLinw7\nOhbv6aDdRFLBeRHpX/7wOnWL5W3ko/gyn0Awvi88M9+nC5aARtqncQqPy2SxDGzH\nKlOZHSNDnEQCGMhA8hNWWKdVwNUJHod/wmBWpW5QVNSJq5DqrKZcNwpGM2UUJoql\nEqUMwDXk5uVH5r5k62Tr9kguDWoUEG1OqQSyeMY24AmA64tq/HSlAdZ+CX32bc4E\nZvm+n8poJBrdSVmWEaa4ZfYaLFdOc6Kcuid1B1Sv9kPhD9WD6T1sicdzjDzcorBK\n/MLReCuSb2E8aPTnFWRoAZ4xCUGs1IXzX5fmxI8VdzwR42R6RhGJ/rqMuZRFenZF\nbOks45K5gE1da4QpkYOUQa3GVMNPqPiT3CqjmJ8tjxq7bGpb6v+YoCLACjjPpPZL\n2Y28qLxwHVaINDFUUxD75WWdrlulRbqHwiSw8jolP9qrpXhDuLAqYam9tRwV5K5R\n8uNawnFwWkicBEYkN/WtBTouWzehOPn38tHXov6SyEyD6lkuxUBZrsGQ2ru+t33U\nk0kKCbV0GFw43I+3Ji5DiB4TUVNZYVoPG1B7Qve+UfA=","translations":{}},{"version":"0.3.3","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <11.0.0","minIntSize":32,"download":"https://apps.owncloud.com/CONTENT/content-files/174436-spreedme.tar.gz","created":"2016-10-20T09:09:26.520692Z","licenses":["agpl"],"lastModified":"2016-10-20T09:09:26.666738Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=10","signature":"D62Ck7JUnrYbrfFlX7xXVaYUMZIh2acmykIKapqfemD/tuX5Bvb08GYGTeFG61MA\nQzsqcIylDfGnC1UJbf8yWEX7PbyJD5w/R4WlbFv34njDvM8rBs4HpzSjkqQoykOF\nZpYAjH2ydfKqtZadgoIRm7et5B8X2AeoGg11ec52DId5t1wAEBcDIv824CDBUt8t\n0pVY8Z8n1MUYwU7DCjCbPy23br2+EBODFCHp+cFfMBLg3F0BJI5nY3Q8ku+0tqMR\n0NDxQcscNZ2Ck/wpVDWylfhpS+ICIxSMiyq7urP593mRrK3399SUyaMqDfgl/pxo\nqTzdBxHLaAqcnKZYglbqp+Gxbyj4teqCod8TiSMlp90VaxhC72ACuVQQRWQKuTNI\nZeW3YweWB5d7VErqBNmQR1tGnX5YFFHiKo41fVDQFsrOqHx4zP6AeU3nkl2ol/r/\n3pg553so1MOxMkyLEhGYGMfrdQqVEtajNWHUdj3B73LS+M3jcjBFIdOD+AGXPtDX\njCRymt07c1znhkL+aT8yY5SHMVbKBZj9mExL49hcLjAYYc4U++60uq9MFH5r9g4T\ndph+yT6VVEM/UH2HjvKsHv2wm937sNgG3EXQdh79JU8nCXIz7cVrJ8f5/9r6n1VP\nBbjtfDAPEjmfVCXX2gmgLuZHV+GMhLBS9bTh+61AhhE=","translations":{}},{"version":"0.3.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <11.0.0","minIntSize":32,"download":"https://apps.owncloud.com/CONTENT/content-files/174436-spreedme.tar.gz","created":"2016-10-06T08:14:05.212553Z","licenses":["agpl"],"lastModified":"2016-10-06T08:14:05.278533Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=10","signature":"X9zXDyMBPoXPMpZ+XXWK3MLufjY2MG8lJ+93LiW3rv0iq9yd8PafK4IvP9czO6o9\nf/vNilq+1mfl6mjTvL6QF5+sySlzbRGbT3uTBwYXyYL07HVYgl1ZLrwe0kxvxqaW\nxTvPem7+HzwClI3VnWc7ylQfzGrcYIaSIg7nNq1GOHocsgZVNyj/nVW/eQx24MjZ\nuLzZs9SJqYoBGq+mo63vRswhqv5OzGebo+G6dHm0hvRSOw9qsWCDWBugiSRU8zU4\nD9PQ0e8WbyrIrQhBoUvvkuijO3zCySx606S1HUyaHmbJzMc4Fbpwz6ggmi6IRBbT\nFBKB1DWJDwN/7mY4fhS4KhircVnAHDqiBVCTu5i3pSfMPrwFymcmbn9OBuFHAFuZ\nd9PZvWQg4v32w+Q/NNvZVW5lRi+l0w5DEqNREaj79hljk2reZMaB65lvtV9NCYw+\nHmwWqsGqZ1SgGZuhYkOzEIkCfJ2fF/VpyavJ4X6bHP9yYdkt1pxnSSoZ2HC8mkG4\nBnPf28mEXRcY8EJv0rEePqKSBIhAt8yfEW+joH/8nupe1qOdfPvP08ifLad5m76s\nt23UzlSljzq9kVO+d16z2uagKomN9USZaNnJcUDVblfjvCPpdiHLfRPEJnRsDZCm\nNffFWEMcz+TWmwBboZgTRO9v0bPDEuwfCCEW0zy8rT0=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/conference.gif"},{"url":"https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/presentation.png"},{"url":"https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/import.png"},{"url":"https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/users.png"}],"translations":{"en":{"name":"Spreed.ME","summary":"Audio-, video- and text chat for your Nextcloud","description":"Securely communicate with your friends and family using rich audio-, video- and text chat, and much more right from your Nextcloud – in your browser"}},"isFeatured":false,"authors":[{"name":"struktur AG","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\r\nMIIEAzCCAusCAhANMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\r\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\r\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\r\ndXRob3JpdHkwHhcNMTYwOTI2MTYxNzMzWhcNMjcwMTAyMTYxNzMzWjATMREwDwYD\r\nVQQDEwhzcHJlZWRtZTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAKLx\r\n2dCPBLIgX948BnOdLij0YyI2+FKD6uZOvzxMaoi3rlxNf8MJgraNMzTBWEXtxT5b\r\n7ZISNp89WEXhaQ1dwwCocodd/xow4Ek63m5nUvTZXsm+YSbMgrFbxzsBhYU7KuIE\r\nT/jhKdzYgemzErwwN/gtwkLMfPo3jkgg6c8NPPohYv6k7V4VnsqtJ0JS0kX19FqM\r\nMiNz9XkcncBHy9x0BSxy4+YnwbFcgIx/MtYKlBL8NkPuuJaB/6C1O+IPYhdEdnpX\r\n+RaIue71nSStOYOqT4YDqHAIw7EmqgA1my09mmK+0Pn92GJVEAEN7JGBSQ+F32RI\r\ndB3ivGAOVtUtVvJlepWdbHxj1xqeP+LCjWzHMLQjm0TyH8VqU4Cg/wxwAEFnBATH\r\naOaWwrggzY2d9KBo1mp0k71NArLbBdlHykFU4bgiSDWrXXMz0fZzLQVwGI0Eqcxc\r\nouf6t0kvrK8oKjrnso+FjBoT7lHV/H6ny4ufxIEDAJ/FEBV/gMizt5fDZ+DvmMw4\r\nq+a088/lXoiI/vWPoGfOa77H5BQOt3y70Pmwv2uVYp46dtU8oat+ZvyW9iMmgP1h\r\nJSEHj1WGGGlp45d10l4OghwfTB0OSuPUYwWR+lZnV8sukGvQzC9iRV1DGl/rREMC\r\ncQ5ajRAtO5NPnThvN5/Zuh4n8JoDc0GK4jEZsIivAgMBAAEwDQYJKoZIhvcNAQEL\r\nBQADggEBAGHMRbPV0WTI9r1w6m2iJRrMbZtbBb+mQr8NtOoXQwvSXWT1lXMP2N8u\r\nLQ1a8U5UaUjeg7TnoUWTEOqU05HpwA8GZtdWZqPPQpe691kMNvfqF64g0le2kzOL\r\nhuMP9kpDGzSD8pEKf1ihxvEWNUBmwewrZTC3+b4gM+MJ3BBCfb5SCzMURLirfFST\r\naxCNzc7veb2M98hS73w5ZE6vO+C/wz0GTsxuK0AoLitApT5naQnjvxSvSsjFPEGD\r\nsUNUEU2Decyp0jxLVnrrpz6Y5UupfBR0V8yAv1t5Od/mCKLc5DxHsDWiKOpsob9U\r\nJN+bdzJil2NNftihD4Dm7Ha7OS3O8W0=\r\n-----END CERTIFICATE-----"},{"id":"nextant","categories":["files","tools"],"userDocs":"","adminDocs":"https://github.com/nextcloud/nextant/wiki","developerDocs":"","issueTracker":"https://github.com/nextcloud/nextant/issues","website":"https://github.com/nextcloud/nextant/wiki","created":"2016-09-14T14:34:35.977699Z","lastModified":"2016-11-22T16:02:57.758477Z","releases":[{"version":"0.6.6","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.6/nextant-0.6.6.tar.gz","created":"2016-11-16T15:11:14.344704Z","licenses":["agpl"],"lastModified":"2016-11-16T20:39:59.030384Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"aOZeEeThyZ0V/vXBcn6c+Z0vyCsZcN6nfSJ8oWEea4zXh4g705Si+MFZESqix3M2\nOPCnA/U8eASwdRTAEwQJrW5ECmu1THXSIsrzQzc9kFycvyOGzCgAWtuu0ayzZD2/\nU5aDWlzpLHC1Czg9QJ5UnfZR0AfChWQ402N1YzGqMShdJv6AHXFrVE+uYnIyxuYI\noPJQBUYbQwthVUjpYwFwSxw50YU17gmx5RZ0Y0OPz3i/EiuEUrxopXtfDVYAuCML\npDw37LOTRQ2JqxSU3teALh8LcrwJbTeOP0n4bTeV+vU3jvtiaEoRrwfVrK41F701\nQymGXy1/EFG0kxPGS2dRNPBAXYLZfeoWlROl3D5BWlbsCcXKU1S+22yn0TEdS7x1\nY44x8jRKnBddDE7qkn+QoQYHNNcxOREsFFLmIoyCUpdNOdDX2PvTFUYkIqdnXaJy\noAKv2GkvWPQ0aiiBtA1i4oXuzvHW/M2wOrK7v7DCpNfILrD/sjxpljxcX082nRCd\n9P3iPd2hQ6yOM9fG21LVN74b6wggI81BzFf/xJPd4ZqYLjfeG/yqd0zaiMOzMm1W\nse+kc/a4iB3BoCNX3E942pBBzew4ya8LkCXdCHUUsuelDf1va1ikTh/G7D84ll9/\n2avNqQnUh3hgOnxFCLI/5VrbqxfSTVdO6O/LTuAmwgw=","translations":{}},{"version":"0.6.5","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.5/nextant-0.6.5.tar.gz","created":"2016-11-09T16:58:06.856332Z","licenses":["agpl"],"lastModified":"2016-11-09T16:58:07.139404Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"DVOIbLEVggiLkNkuPW+pXqu8WYT15unUsMoqHSw81NiU6HF0Nuf3XiwnHJJDDqo5\nyX+QyHADO4ZiQpvXhGigvwD2eS1jVLatAztyxE0tEQv5eBU/7R0jQYxI8YCnC/jE\nZDa0qs+TI58EkDek0LBzueVQqrLubKgGU9P+E9H8gbWi1JHvl/2LdY7CplPqaJ+J\nMRokobPntzgx9m4DZC1RsCoXzSON7o2gp2cmunPJiXVHPhMUfIfolGEbNGJ1/xdp\nra7Y7XkPnDx4po98a38UpUh1x/2m5goOV54em35bWbh4ShNDykiE5ttz6tOywlYN\ngxceSsStTKyqscVaOV2Xu6Ive0pY9CInvrSfRnRktIWBYDyWdbK9sJuqs/s69kqn\nKQ/SjzE2smw0zwOUMnSaz0Jzr1vdPFgNu2xDYAVQO5G03V+wQ5AhxuhBz5Xp5Fll\nLaOhymZLCC7lq0DUqkeTeQ2QCfiG23hvG2VUPsIqW7xFe2YzKHZVXi9JuH//Gwym\nDmJmcyZDMvNwNiObx3ZRKsZNH2XwbldFZ9nTpb9AafyoSR/qbwd473NewaDLRTiY\nLrOP5Wx1xx6DOkRmDF2l2iT1bSJ6eoAoWQU2I0aYRH9NAr2Ugd4f2Um4o61EJaL+\nRHT9cERRySEloU/NdgmZEOi+CG0rEu+9LC5G/jGlHE8=","translations":{}},{"version":"0.6.4","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.4/nextant-0.6.4.tar.gz","created":"2016-11-05T18:17:47.622023Z","licenses":["agpl"],"lastModified":"2016-11-05T18:17:47.678445Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"RdkvnhNjw+kAnT6Or3+N9FzAv9DjJ9BAlmgANMwZZcaqo1gZRFewsGD2Rx1KEb9X\numSC28tl2B5/3r/+dprVJmOnYJny/7+kDmI/d+vigKfnaQJOUZ0ya5+f72pFgow7\nth8fw9rX/3+zIBs2IeEN66cis8ioFq97BJDsnDMBDr7wl7CnFJjYe6eviWuiFTnC\n4sBXlYjHoaIRRu561nwAooV+WXmWsparYPVhj2cXdyP/CnWo5HSF5jA51WCsz7Up\n7a0URXdh85xmxEbZtnjUymTW2BIegdxj9Erbfiuy/m3ApgnP+DiEQRcM13J7pXqg\n4cgFOBSzKDZgim599WBW2uNO1ztnDYOzz47GXDfJhcdvKiZZoQpUF9W4LMRtheMz\nxD9YArO3j3d+VOInSYU2Rpvhugwo1LExhwnRdT4+cOZfEeq0VojiM7yBZLDdEjIb\nGdYUJtNWSU0F6cPab2Au8FgZymLrHL9EpGvxuA1oAwtRxHAgMElJG2O6Jp89gGl9\nh/AptypeTvzNEc9/Kg24ueBKqmSUd5a45pZ3gM2pNATJhUK7fzLb/K6cq/kEzZtj\nOPra1ZfP0/20u8VP32Rgw1cFmIjqt8DFzUmOMpMfyoJkdldtTwQtGk+yIvtN1zp6\nT2zDknAKA2N/rZ/0SJl8KxVVNLhQWtYBJ+rFAdapuxI=","translations":{}},{"version":"0.6.3","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.3/nextant-0.6.3.tar.gz","created":"2016-11-03T21:51:27.654342Z","licenses":["agpl"],"lastModified":"2016-11-04T18:25:35.697130Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"Hf5GB4xd+rVXzWvVpGbbF5tqmnI+DGHlNHdLYPImFLS/Z4K5wKeNp80E5sd/RkAi\nhyuEsdWHlGDVMT6s7oeCmH/ClyWqLNSz9VH4KYqyFgD4+usHZp9PrEeEKbvPDPKv\nD3eB7Ats34cWcpf4E1oR5wsPicgmdgIgb2uMXzc/1G9xUBRWzocwCstzjEEAB/VJ\nvJuHvhDTGG294P4gOb82MxKCQ8LZ4i1QXzOf/mLETOubiUbZtJgTReYvpdAo2Wct\nbdfDFw13LYZkCf71r9jIQ3PSPlIpD+0BwAlE1fi0Br9dP2JjIfiKN6CGVaki6O0v\nKR42jtcE9xXiAop0Ym1FYMWJvQUy5PFLMwYDfEd6CvfEFJl+fi+RjXNDNZcTyw00\nHa48sACoGzxwts2JknWMU57mwvi0Z4uwpE0cFp/PRzBsXmSlCzWHjJhu7+2qambE\nAabdP9nH2NvqJHUJyPsxtDSrSWCBY4CoL3wYu36UrIA4NepyudMPNe9fhKTEU0bg\n8DLclw6hYdj5p9Zj3OUuwOZLz6r85KwnooTsUjOYkBXvdUuHWkgONqqZlPMApS4i\nChRQ7ysHAinPyyzsvr0PR9g6J52CSCO/7qwSJy6yqSzuSWrbZUa4FVTcKIwWJJPu\nJ2XzB4rWVG1wWjzMM6MIvFbO2HY9upzh651OdOwsYvk=","translations":{}},{"version":"0.6.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.2/nextant-0.6.2.tar.gz","created":"2016-11-01T11:24:58.054892Z","licenses":["agpl"],"lastModified":"2016-11-01T11:24:58.151609Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"h8KgwMh2RGGIp7q/II23VSfE5Ibkha7p/C1kvIfG6QIIc2Zu/Mm3Oekynnysh5ZJ\nZuMTaeWbejbBAmlnxW+AwBWa/s2PoMhv7foVvdtg76l9/qr+9jGvUM7D1LbbBvAy\n/XW6JSrhhBZHOohdU7kwV5Xnwsn/NC/zUe0G4eZ+9fc9uSntkob9wnWvUs2daAeD\nY6Mi7Xt/XAIX65OIVTKfC6ah1SlbXgtJ2i2j4G32I9jRhmbkpt/UJEangn90fHnY\nQXNJ85OyV0aNMafNHoMSL3uLscdvjp0Hy8w4iBeavsRlCs0GiUoG1+YdwTwmC9EM\n4CjbMfRJ0DYK7u697TOmS8MQzk8O7f5THtjeokZlrom2YnV9t6gLvjnxl/+gXPdJ\nmgLixnA8P6tPsff9pquHKQZsvxjv6vVa2DVJc8VpcqJRih7yj/3V7rxesEP7MUnP\nznIOcwzTsKXHuAnvujpCwyQj3QtpQK2XJPQ5WkKgwbTdvriVJfnzPironhcHo1vC\nbuUDOdhL59aySChw2cZGD9lCWaxYR7I1BDDzWKNl9Qg0AZ2auyGUGTv8P2vi5qUB\n0CmnkzkZurR5ju6Nb9/2lcZvda7QJdIdMtm2Wnn+Ak/Z3Y4IehB5PPDP5/MMAhQY\nXE8Jqe0mGtiU/O2346L5IThkS58JlRmep4kYv+II9zE=","translations":{}},{"version":"0.6.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.1/nextant-0.6.1.tar.gz","created":"2016-10-27T21:16:47.066097Z","licenses":["agpl"],"lastModified":"2016-10-27T21:16:47.125641Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"axSX4Kz2P4PbKU676DibjPZsxk8hCIG3lLOmeMXoiBhp3ka4wJ8u5tNwTzgY8/MV\n3mjXe5DNttD66SqmiRNSPKbotYHVFFW3wFK+Dgh/++n/KTomGYUeIwt88Z9ecbuG\nNT6U46jFrfZBYzRHWzbgiJ4c7MCoid9cfmoB7HDuQSyw+E0S2vbLL8+jzMcIzF+5\nTh/sJEterXCyrWSciw/9x98F+4svNbskdEIvrOox3+K6UIhpsojqJR2+bQhG3rsM\nPerOb6J+bzHdLV1ZL/4nWEz1F30T7B08QxY/4pHD68JFQcdtzmHMIhXfCoRvWhN2\nVRnizx3IXBALY4F49Ql6bjsnr6BCp+LroM5RSQ3eupDcqejDJLbjPz8xfOMOwlx7\nz84Xd0MUyqaEkxg1ihsWLbPlYACUZ2aoDkSQUIbfZTTiov7eqTM8UBc/UqVID/LU\nyEW4gjFZzQy6kX76XRAGq1vebKFjCU63asCnVyJhF/YQVTu1hPGbFslkRKnYuh8W\ne4MeaNfbdjcSEX+7oTcPJz6V09pOPvukXL0M1m7lS9PhTisI6oGj8c33GPYp/DSK\n6SGk+ukbt1mwFuFKdTvAMxo1lk96D+pKUv4MX/ralaaoIAmwPTGsSQ04RyL454ae\nU6q8PApwrVyPHYwMBPtXGoQMyb2ZV9rylazYbKCQ8I0=","translations":{}},{"version":"0.6.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/v0.6.0/nextant-0.6.0.tar.gz","created":"2016-10-26T01:46:48.419025Z","licenses":["agpl"],"lastModified":"2016-10-26T01:46:48.521063Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"W2TmbX/NbbfPPjIJLalO0kCDhhQF1pEohH/CxO9bY+yR+a5NKiTbpAhG2McqpTSj\nmgC4J8/thmwGlWGC+VW+KlpXzzjc7wCgMGMKViOpGD3pIy8p8U5MXTqVgjjrEb9g\nKgr9uErXzxJ5oDfkx8Uh1bUeBJTsAAivGJqMlhBYFGxw8BSB09sfOZytNxo4wbwZ\nNAcYP1qvkUQ8CVR0nInSTRfLvAp5+e/8xxyYZwJIWrdNgpoP0CxiwzKP/VSjBk/U\nsPU4R72UQnVZB0InRCeh/KNTwu1YiPkUKm+mNmt2hCfN7Fm6bY2rUMH7oa8KTkqn\nh52FhbaYRSAR1OPdC5RnHiQWlPh71gq+4Xqgp19Eawnl/QiVteVifSjNQZ+Ban8M\nRyw/PxHnzIWg/OAjx81Jx9mXjUIHSeUxMTJTKTp+lEqIAzjku0iHeU5UPGCfE+VM\nwmopZfnlfk2nLkUwjQXLCIcnZD1ME1m0h/H4Ad0Q/qXpBScUp47npkxg2b8zPIhk\n3aXUmNPLgwyXPWvAuaBK/WwpNefUnqWFns8t2Alpzg/EpC2PrZqciCNDcRFEycoa\nX+JsFyD7eYA7Dc9iIf09gXZX+tZ+Jinb+iPDwYrl1u/7IIBoBlUGCgo+cF7/dL9S\nc3vYeWw6MCH8Sv+ckgS2g726BfdN5EjB/8cb071b4lE=","translations":{}},{"version":"0.5.1","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/daita/nextant/releases/download/0.5.1/nextant-0.5.1.tar.gz","created":"2016-10-17T15:23:04.515057Z","licenses":["agpl"],"lastModified":"2016-10-17T15:23:04.576640Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"Pp3rC/9RmAYURneGpGit4HZ2t1qH9A9nwsUnGDgRuJ0akIii7CtJC+n8l1b9k73/\nhxMYnp2JOBu2HWKgFp9a3yeo1xphI5hOUdVQ1UZAWxIQyDI1FZVYDr81l7GCdkqm\n2lMxgviFADSYURCgEnAsj9Nt0NZ6LDcJZiJg3iPAjkDI0U+KnEBjtx/XRFqGUnfp\nCUZ/XLHh/hvoEitSHepTBDCMKkTNjbDEwBYfA2rAoz4zbMR5zKLU+1r1DIUgWSRe\nbk2i8TaTDVL4cbb6MhkGKwkujb+Atikvkpi45o7+fyQMs84c6fjjh/TZJaC+dMyG\n1GCikMPwwtEPjXtnLzynERAxJOd5mP4Ee4sD8ZrnzNUehpyFR88pwWU6r+dmiebb\nnsYlGkhIu2aIO2O4HZ4sUTsO5sfjZ9me7jsafhgJl6iG4IBeHa/L1MsSbhsh6mvH\nYsz4Xsluwr0QcFLmSDDQQYynyogKfJZG2xQsadM0GETWgXE44dVeRvMUsILfB4uZ\nmfKgd23SgaOhYC8m4bg5Hxnkl+xHJnyGZ6jhqg7bhuKwsoLymc18Vmjmb7a45HGV\nXbL5CTmw9yaPOBS3v7v91TnlB+0lhlzbKzZ0xIhY55qsTC76uScbTLwndPqNGaF7\n2koxRbJ3jcmf/Z2MLymdRi2BTZbZkPkxgVrSf9plaR0=","translations":{}},{"version":"0.5.0","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/daita/nextant/releases/download/0.5.0/nextant-0.5.0.tar.gz","created":"2016-10-11T11:47:46.191539Z","licenses":["agpl"],"lastModified":"2016-10-11T11:55:40.393000Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"qzQJSLK8nkrQedwwxUdhxL8bq3aXyppAlWJo+n3GqSvqA8uNTzl3Wsci0LsnBV0E\nDvDNW8K0enhl563F/bywQrDhgKl8jTX/CA5KCxqO9P+tvE80zAfMqiRnQayVcWuY\nSWX6RqfI/kqiWyN1SsFp2EDlas6eb+xfIoiJamlfsN0qzHYOFt5W77wmw2Bn9dB5\n9nwHHyC0z60Pf2pPduc/KuZ/971WrDFaIapL7Gm+z9XoaKSwUT575VtS+RNJkOKy\niBrwnHdc8x/62HPFOXsUYjCt2aEmLzPCQN8Ke5pd3596hm5wbAVzTHuxf2H35tb3\nljfGqAZ5AJX2Xd13d4aHXFdSEILwv6IFq2fx0hO3vkvFEoKF5oQ2t3hi++Mw/h8R\n15OKZCOC1bFH3gTwdshmnHSTSr3LxDeTF60aH16wpXcehQuJHagpb/aG8mPD1w+v\n759/mekqa4LYlqT9TLWTqX3UPeQXYIwcPijG84IvW1BDK1M4Mj2Vqsld4jXwG6CP\nORIL8xoQbA52dQI1Y19JXcU9zxIb6GaHYgpV0wejatsZRfhPv2Yd0CBShF4HY7aw\nnfcm88pqzOKNvjnLZjTFQwuJ0AUUSOsWhgZzYt8tATJ2KDZ+lxz+WAMGXJAC/ciY\ntrrglY7YxwJNLbYp+arE0sowZx+IOVaSZBvmUGHiEBY=","translations":{}},{"version":"0.4.2","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/daita/nextant/releases/download/0.4.2/nextant-0.4.2.tar.gz","created":"2016-10-06T10:31:12.482416Z","licenses":["agpl"],"lastModified":"2016-10-06T10:31:12.551117Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"QHJhYcBMi5HyPofshZ7EdcXsOfRrl7o48Y3BBBq8CkmYtFDbekghdJqjFbwwZa5u\n8FtgMVwssql/RSDNP6M2Zc/MpQ3K9gDm+DyxE5KRmtMEpzHB+oD+1DxP7kAoyW8/\nnBr5iiQSdLCelddMcTwbdxskLFUXIs3cFuLGhMvr8pdQOAgfxte5lolrj4/8EsJ0\n0yUImgIYG4NlgmvCygGApdpVaOcK7XVtv4oH+x43JmX9YZ3Ce0DQPYPUbFTCf4ZS\nS075j1vcnPx2cRFGxc+YpKzYVVYoy7ZdB75Hq+kaei/atxrTyV5+gcCrVPnH1RkX\n6G8rgu5l8FoGJYopi8/9dd8LsGLFx53CHMdEVob3tFR0WeK4TJAGJa403zE6S3hM\nxr86WCedmjuti0uOhSQr5AEVAoXZ/JUWQMMsPAUMuKEYVjKfmve6TlcNMC2oM5XT\nXcOf4OP3pcQq4ViN53o4Pj6NGSci6IzD6xLeAxKZUoTX37ArVKH6RHS5Najc193H\nRhYRnfE7D5YOr1u10HaZCFCVJif2MgMP0/uH2759NoRjXFowrh7Z6dW7JQG5lbHN\ne0jjJH1Y8m8H1peGGcmM0YxFiOVZ0ER7P+AxT4Cbau/cVhaS8vnPF2a2a6YFRiFS\nVH4aqazvvXrtifDr3lfvlyPCrP/570nwvOJgZGk+K/Y=","translations":{}},{"version":"0.10.0-alpha","phpExtensions":[],"databases":[],"shellCommands":[],"phpVersionSpec":"*","platformVersionSpec":">=9.0.0 <12.0.0","minIntSize":32,"download":"https://github.com/nextcloud/nextant/releases/download/0.10.0/nextant-master-0.10.0.tar.gz","created":"2016-11-22T16:02:57.740378Z","licenses":["agpl"],"lastModified":"2016-11-22T16:02:57.900805Z","isNightly":false,"rawPhpVersionSpec":"*","rawPlatformVersionSpec":">=9 <=11","signature":"kDO3xbPpoUdl2qo362tXfJIqKeiKE12M8FkMXbdKiRNzuQyvDOehQq3dErALZDOr\nLG47Tpez/Kn9Fsx1y4dQDx0w9SD3pyoqvjj1O6KkTYN6srpitQcj9EzEItCY+MZd\ngRSc7Px9MzxpFpgwtuThGVlSt5kLMd0vjWFLVcv7k07rZfWEsTwXd24INIDtlr1A\nC7hyLB777nEOGa7oAzx8nr+FJcIbmu9opVZk8AL40FOFDNBgfAy2AS9hGZreUmRV\ndV9Zh7UAR+RsEeO51xcU/CKqC8Jb0jL1gkYyUaQy16oe1HF9bRs1PWuNL5mYwsmy\nZNn0ay/a7mb7hxJbza1F3lbgBtodvbgUm7+So/QvaR29CSAqqoUXeQy6FfSBVWhZ\nYlTxdQfKcBcUPFO14BBk/O5p5iQaG8JCLJ/EZGDPDIVDMn7crGQ4oLZJv80eqTeB\n7ueDmWaD3gQ9j2XKsMk1uLSyY4unt9AaTofBylsKD1SjLhyxofGZym4jc2+M1GnQ\nyLcoEMSexnKuextu4nzrA0y5k3z9tvO07R29lwT1GFp6oGAakMLob/6XrGsm3YQY\nRQXgfXKFw8XmXBumqG8ayEIyvQ/O8nO6r4R1H8a7ooj6oWa3PhPsen+gLE0SpJPZ\nz3e2TLliwC4VtZp69H7u3px5Qn1Fc6RMDTh571edCr8=","translations":{}}],"screenshots":[{"url":"https://raw.githubusercontent.com/nextcloud/nextant/master/screenshots/displayResult.jpg"},{"url":"https://raw.githubusercontent.com/nextcloud/nextant/master/screenshots/admin.jpg"}],"translations":{"en":{"name":"Nextant","summary":"Navigate through your cloud using Solr","description":"\n\t     Navigate through your cloud using Solr\n\n\n**Nextant** performs fast and concise _Full-Text Search_ within:\n\n- your own files,\n- shared files,\n- external storage,\n- bookmarks\n\n\n### Recognized file format:\n- plain text,\n- rtf,\n- pdf,\n- html,\n- openoffice,\n- microsoft office,\n- image JPEG and TIFF (will requiert Tesseract installed)\n- pdf with no text layer (will also requiert Tesseract) _[work in progress]_\n\n\n\n## Installation\n\n- [You first need to install a Solr servlet](https://github.com/nextcloud/nextant/wiki)\n- Download the .zip from the appstore, unzip and place this app in **nextcloud/apps/** (or clone the github and build the app yourself)\n- Enable the app in the app list,\n- Edit the settings in the administration page.\n- Extract the current files from your cloud using the **./occ nextant:index** commands\n- Have a look to this [explanation on how Nextant works](https://github.com/nextcloud/nextant/wiki/Extracting,-Live-Update)\n- _(Optional)_ [Installing Tesseract](https://github.com/tesseract-ocr/tesseract/wiki) ([Optical Character Recognition](https://en.wikipedia.org/wiki/Optical_character_recognition) (OCR) Engine) will allow Nextant to extract text from images and pdfs without text layer.\n\n\n\t"}},"isFeatured":false,"authors":[{"name":"Maxence Lange","mail":"","homepage":""}],"ratingRecent":0.5,"ratingOverall":0.5,"ratingNumRecent":0,"ratingNumOverall":0,"certificate":"-----BEGIN CERTIFICATE-----\nMIIEAjCCAuoCAhAFMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD\nVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI\nMTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB\ndXRob3JpdHkwHhcNMTYwOTE0MTI1NDQwWhcNMjYxMjIxMTI1NDQwWjASMRAwDgYD\nVQQDDAduZXh0YW50MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAsbnQ\n+9acjKHfcrUj4yqBpD++GmQ5z2Sp8C8uOz4ZbLyM9bUXEYHo4a4u3CdC49kGUkb3\np5MkEAEzslTWDi1eh5MZgPWpbPgItsDsXY1o55O3jtxNkzSG5/yYcPQcuKtIOm9S\n7DY0K+UQt3nK+RrXEZfARMNrzFbEzpE3b7w901Yl5n+m/B8rhW4pqg8uSfx3u04J\nwduV1fHwoHUB0Ox5HyWib4Pq1XppNh7xdc2Fg93JxshwuCPJyOOzrFTnxC7s1yzQ\nUvaqkjPW5QeQRunQjZ2XtpYH8f8v01W18bNEiHwqtFwuDEyCVx1rvEMgUDVXdPkP\ngZrlB5TzGmz0U3HzYvf6205WuzfHxz7kPj502wP51PoZBKpniggKzmuXkx6BpsZC\nZX45VpDHdiATLwRj1t2bMs3C01nzpIWO5ZwFtkepH3Y+mvwX5lDh/XDsqJC2Yo8o\nWMmniWNW7dspufYOsBUqqYGP7rkailgVT4oYk6D1j6oFZ5SSpfPF5lsyYedDSM6y\nbIGVkSF+sjLK6R9BenBijKceAKsS//WwRYCBPC+JHlsYpXKW12bL+C47Kj2/N6d4\nrYryzV6ofVSF6pwIq0oEjoyfBfNpYavf3xrRkSSmIIlPSnMY7DT1xkGD5retxSm6\n+WIfkWKRZpv2S6PhMHGLspYc4H5Dj8c48rG5Co8CAwEAATANBgkqhkiG9w0BAQsF\nAAOCAQEAOZUwyPaUi+1BOUgQJMWqYRoTVZUyBshTXSC7jSwa97b7qADV9ooA6TYF\nzgsPcE41k7jRkUbnjcY45RGtL3vqsgZbx5TjPa5fGMxlqJ6eYBOY61Q6VIHEVm3u\nxnPEO9dsMoDBijvo5D7KtE+Ccs907Rq70kCsbrdgPHkyb5tDSnCKogN1LiQrg1EP\nmy7Z1C7jG9/h57vx0+QBMDCYnTmqLsvMKqo27uHskzAiB7VXLEdSZ2FtMGHkLUQO\n0bfhnvTZ2VhMmK83t7ovo71An4ycmsolGD/MA0vNI78VrVISrdI8rRh2WntUnCBU\nEJL3BaQAQaASSsvFrcozYxrQG4VzEg==\n-----END CERTIFICATE-----"}]
EOD;
	public static $expectedResponse = [
		'data' =>
			 [
			 	0 =>
			 		 [
			 		 	'id' => 'direct_menu',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'customization',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/juliushaertl/direct_menu/issues',
			 		 	'website' => '',
			 		 	'created' => '2016-10-01T09:16:06.030994Z',
			 		 	'lastModified' => '2016-10-06T14:01:05.584192Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.9.2',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/juliushaertl/direct_menu/releases/download/v0.9.2/direct_menu.tar.gz',
			 		 		 		 	'created' => '2016-10-06T14:01:05.578297Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-10-06T14:01:05.643813Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'ERBS9G5bZ3vwCizz2Ht5DehsVJmb63bzF3aYcH7xjbDVMPagOFWdUAiLDwTeZR1n
i4gdZ73J/IjHQQJoOPwtCjgbZgLPFqL5x13CLUO9mb/33dZe/+gqEDc/3AuJ4TlA
XUdLxHRb1bwIlJOwuSr/E24452VG20WUhLXBoM0Zm7WcMxvJWo2zAWnuqnLX3dy9
cPB4PX+6JU2lUMINj8OYQmM1QnqvjG8YV0cYHbBbnSicOGwXEnni7mojsC8T0cn7
YEJ2O2iO9hh3fvFEXUzDcL7tDQ5bZqm63Oa991bsAJxFo/RbzeJRh//DcOrd8Ufn
u2SqRhwybS8j4YvfjAL9RPdRfPLwf6X2gx/Y6QFrKHH0QMI/9J/ZFyoUQcqKbsHV
85O+yuWoqVmza71tkp4n9PuMdprCinaVvHbHbNGUf2SIh9BWuEQuVvvnvB+ZW8XY
+Cl+unzk3WgOgT0iY3uEmsQcrLIo4DSKhcNgD1NS13fR/JTSavvmOqBarUMFZfVC
bkR1DTBCyDjdpNBidpa3/26675dz5IT5Zedp4BBBREQzX08cIhJx5mgqDdX3CU09
uWtzoaLi71/1BWTFAN+Y9VyfZ8/Z3Pg3vKedRJ565mztIj0geL3riEsC5YnPS0+C
+a3B9sDiiOa101EORzX3lrPqL7reEPdCxrIwN+hKFBQ=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://bitgrid.net/~jus/direct_menu_nc.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Direct Menu',
			 		 		 		 	'summary' => 'Provide easy access to all apps in the header.',
			 		 		 		 	'description' => 'Provide easy access to all apps in the header.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Julius Härtl',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEBjCCAu4CAhADMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTE0MTI1MDU0WhcNMjYxMjIxMTI1MDU0WjAWMRQwEgYD
VQQDDAtkaXJlY3RfbWVudTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIB
AMkzWsAkKP/40ktvJMpnr0IJNVoPOR0hvh24igcDskL1WKiD2eiRUenj5LE0Nvn+
siGmWsAqRVpdiz+Y8ghQqQMzKi43IrRN0AxlCrHWrSqBZT3wIAUcFz4RzEoFxc1N
UZzWma6ljukGnvt4V1ZyT+H/cjqxUkBhh/y9SS0jUen1a1grND6Rw54X46V2dlCu
FH+pLsfPJJGw+QLeTGHn7dqdv18cYMAlWDCzPixVnNiCXHZcUtKMmstU2xU4R2e6
zimp2rgkE4TNHrafpjH8xGdNi2FG5Dmokob/L5Q2r8jyNaW7UsFfrvLTRj371b3/
2FhhxoGUvDzaG2An02Exwm52LJfdTVMHAMPZub5poHfy5vAEdZGPQ/m02l8ZK/Y2
7yT807GlfPMXfdfjCxR6wNtmv7rvBDdrUZmIRNJfpFSdvlH/+MOTWnabyfQv2K4Q
BIwltX6Elh0lh4ntvt1ZVtvFv+PL1Dc7QLV+w19+/LJA0mnsh7GIFYKFlbA65gA0
c/w+uqDy0+5MxkR9WGPpd79KRA1tKWTis4Ny1lApK5y3zIsVGa3DfBHXcwqkWHbV
wIpyuyyDsFtC1b9LTFONX7iU9cbNk5C5GTM331MdA2kLcD/D5k42GNTBSca7MkPx
Fx/ETSn0Ct167el30symf2AxvXjw+mBYPN71rVTMDwe9AgMBAAEwDQYJKoZIhvcN
AQELBQADggEBAC0fJKnbEhXA8M283jA9GxABxLyTBcQyVVNnz2L/bYYNi81Y9iZv
+U0S3qaIfoqNcV9FTKAutbsKvWyolnI7MRRK6feNuFfoP2jKubM1CnawpyT/RF2Q
e/zxnB1EmeI2X5D2xceJDLB7Fy5W0EGrLixRIdFaSUommWFUm9E2hSIaNlziSBdc
1J/mOQeNYO5zg5ouEt1rzQW4Mhh1I2uNQmGe4ip+Jl/2LAv3FZuu4NrSEcoXH3ro
G2dF9Gtu4GiQ5fuaJknaxlgXHovfqeZwZJX9o4M+Ug81AqiY7XjdiaCPdh0Tthcx
2OmWZo7UBREWenjKyFZZ/iKoqH5sdenBtpo=
-----END CERTIFICATE-----',
			 		 ],
			 	1 =>
			 		 [
			 		 	'id' => 'apporder',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'customization',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/juliushaertl/apporder/issues',
			 		 	'website' => '',
			 		 	'created' => '2016-10-01T09:16:47.111889Z',
			 		 	'lastModified' => '2016-10-12T19:50:16.038821Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.3.3',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/juliushaertl/apporder/releases/download/0.3.3/apporder.tar.gz',
			 		 		 		 	'created' => '2016-10-12T19:14:10.802359Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-10-12T19:50:16.104357Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'nhlT9lhrmBxIsqh/e3RLm2NDw/U8ZvvoMyYQTLMM3H19DQmVcPYPYC9QWVTsowUzXblVaOXVGylbpKma9yOlOAqJtF3qyXecLl4+tA/Awb6BBhKPgHFdcLDAL5yy1K7/uei3SgEojMlJoI9vEK5I1C5YTh43yNH+//Im6MAuNYFUTlMXK426zdOe6ogpCI5GlYdXopqYANxcpT+WXWET6DDSM5Ev+MYwMcSAY4r8+tvARRU4ZAenRgjkBT6R5z6cD76emRax14tbV6vkjjwRcO+dQtM0tFPbd+5fsNInbauv50VzIMgjA6RnKTOI17gRsSdGlsV4vZn2aIxEPWauu6T/UohMvAE9HMn13vtbpPBSFwJAktj6yHASYGzupNQLprA0+OdyALoLZPpQAKNEXA42a4EVISBKu0EmduHJlUPeqhnIGkkGgVNWS8AWqzP2nFoPdXBgUWateiMcBTHxgEKDac5YmNc9lsXpzf1OxBaXHBhGYKuXPwIfyH3jTWb1OdwixJEuRe9dl63T9qOTRre8QWns/bMqKLibGfMtFhVB21ARJayBuX70eVvabG/2N7Y5t1zUlFygIKu51tvo3AVCRDdRrFWDvkAjxzIz5FIdALVZ+DReFYu/r4WF/w3V9rInFuEDSwb/OH4r8sQycs07tSlMyA74Y3FpjKTBxso=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://bitgrid.net/~jus/apporder-nc.gif',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'AppOrder',
			 		 		 		 	'summary' => 'Sort apps in the menu with drag and drop',
			 		 		 		 	'description' => '
Enable sorting for icons inside the app menu. The order will be saved for each
user individually. Administrators can define a custom default order.
AppOrder works with the default owncloud menu as well as with the direct_menu
app.

## Set a default order for all new users

Go to the Admin settings > Additional settings and drag the icons under App order.

## Use first app as default app

You can easily let Nextcloud redirect your user to the first app in their
personal order by changing the following parameter in your config/config.php:

\'defaultapp\' => \'apporder\',

Users will now get redirected to the first app of the default order or to the
first app of the user order.
    ',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Julius Härtl',
			 		 		 		 	'mail' => 'jus@bitgrid.net',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAzCCAusCAhAEMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTE0MTI1MjQ4WhcNMjYxMjIxMTI1MjQ4WjATMREwDwYD
VQQDDAhhcHBvcmRlcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAKVK
Kn5jivCu+eRfe5BECjDOzNaGHlpiegb49Hf4nh0W7DqcoLHip5c1O2BcEYdH6rkw
20WclvjoQpgavG5aFXzXzur6eKTT5TpgY5oZTLoWjbx4e+fKdhyDPTpqNZzs1pxz
sZLDL/ElpbSErE0s+QK/pzP11WNPylAkI9AKSyDMO3Mbllg8I8Bt+bT7LJKYOO/T
Lhv9m0anLZ4HrdfimhVIoMiu3RpyRigk8titXZA94+F8Fbf7ZQ9f14Y/v3+rfJFQ
ii9cDoox5uUrjplH2LrMr5BodfCfydLu4uVpPWYkdccgvcZ1sugjvNXyCQgdzQDK
pOpiwVkkiQFaQJYbGSDblFWPl/cLtA/P/qS7s8tWyTQuc1rYlEpCHG/fG8ZFkSVK
9eCMGxK908VB4IU2DHZHOHi7JvtOz8X/Ak6pIIFdARoW3rfKlrz6DD4T9jEgYq0n
Re7YwCKEIU3liZJ+qG6LCa+rMlp/7sCzAmqBhaaaJyX4nnZCa2Q2cNZpItEAdwVc
qxLYL1FiNFMSeeYhzJJoq5iMC3vp2LScUJJNoXZj9zv+uqTNGHr+bimchR2rHUBo
PzDLFJmat03KdWcMYxcK5mxJNGHpgyqM7gySlbppY/cgAospE8/ygU2FlFWIC9N0
eDaY+T8QA1msnzsfMhYuOI8CRYigan1agGOMDgGxAgMBAAEwDQYJKoZIhvcNAQEL
BQADggEBAGsECd+meXHg1rr8Wb6qrkDz/uxkY1J+pa5WxnkVcB6QrF3+HDtLMvYm
TTS02ffLLyATNTOALZFSy4fh4At4SrNzl8dUaapgqk1T8f+y1FhfpZrEBsarrq+2
CSKtBro2jcnxzI3BvHdQcx4RAGo8sUzaqKBmsy+JmAqpCSk8f1zHR94x4Akp7n44
8Ha7u1GcHMPzSeScRMGJX/x06B45cLVGHH5GF2Bu/8JaCSEAsgETCMkc/XFMYrRd
Tu+WGOL2Ee5U4k4XFdzeSLODWby08iU+Gx3bXTR6WIvXCYeIVsCPKK/luvfGkiSR
CpW1GUIA1cyulT4uyHf9g6BMdYVOsFQ=
-----END CERTIFICATE-----',
			 		 ],
			 	2 =>
			 		 [
			 		 	'id' => 'twofactor_totp',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'tools',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => '',
			 		 	'website' => '',
			 		 	'created' => '2016-10-08T14:13:54.356716Z',
			 		 	'lastModified' => '2016-10-12T14:38:56.186269Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.4.1',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '>=5.4.0 <7.1.0',
			 		 		 		 	'platformVersionSpec' => '>=10.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/ChristophWurst/twofactor_totp/releases/download/0.4.1/twofactor_totp.tar.gz',
			 		 		 		 	'created' => '2016-10-12T14:38:56.174612Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-10-12T14:38:56.248223Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '>=5.4 <=7.0',
			 		 		 		 	'rawPlatformVersionSpec' => '>=10 <=11',
			 		 		 		 	'signature' => 'bnwWxmHEn8xkoWbtwhC1kIrJ0dQfAI3PUtU62k+Tru/BHt1G2aVxqO8bCdghojZ7
zdFMlIJw4kekYFsVfLk8jzjUTZKVbNVKCdkHrVTQ0bUUryMAMLqGQ3PSRI5NX6D5
FpkvwO1coYwU0XVWF8KAS0meX0ztSkT3Mv96LLrxr8F8SrB/MGmKIE4WTjt1fAIa
ZLAVEUo/3sNFTGLYBtL3wjctrkZvJltP8abeRfls9FkRHu+rN7R3uLFzk42uZn3X
Wpt5BBmlYm5ORbnJ2ApsxEkMNK+rOy8GIePaz5277ozTNrOnO04id1FXnS9mIsKD
20nRzjekZH+nneQYoCTfnEFg2QXpW+a+zINbqCD5hivEU8utdpDAHFpNjIJdjXcS
8MiCA/yvtwRnfqJ5Fy9BxJ6Gt05/GPUqT8DS7P1I1N+qxhsvFEdxhrm2yIOhif8o
h7ro5ls+d3OQ8i3i4vdZm821Ytxdu/DQBHiVoOBarvFWwWAv2zd2VAvpTmk6J5yv
3y+csRqpEJYd9fcVMPsTu7WBRRrpBsAqdAHJcZEwak2kz1kdOgSf8FIzP1z6Q71d
Ml2RKcPeutMHHSLiGIN/h7fM5aSs49wGgGZmfz28fHVd7/U0HFSMYmkT/GMq5tMP
Iyc+QZAN4qbX8G0k/QSTkK/L4lOT2hQiQqiSqmWItMk=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Two Factor TOTP Provider',
			 		 		 		 	'summary' => 'A Two-Factor-Auth Provider for TOTP (e.g. Google Authenticator)',
			 		 		 		 	'description' => 'A Two-Factor-Auth Provider for TOTP (e.g. Google Authenticator)',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => true,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Christoph Wurst',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIECTCCAvECAhASMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDEyMDkzNDMxWhcNMjcwMTE4MDkzNDMxWjAZMRcwFQYD
VQQDDA50d29mYWN0b3JfdG90cDCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoC
ggIBALC1K94104L/nOtmTygx7QNjUcnHs3yrn71mw4pMxTlonXOnMTpwxsfL1Hhu
/5GMSgupTbQPlevSl6J86UMs455/sPShd6ifmAuhb8VFaAsjpizjs0RMaUg1sjmF
uV18PD9FXLourx51V/c4MG5kpavlV+bLUrVMAjbsJY2+k30tCC/XkP5u8jUWmM/T
5REChn7/obPgaeddhuJoILYhKEW3VNrR8Fm9SYiviB3FLhM7URDZ97IBnXYqbvbT
Znvq+E74Zc7HgYwQwrjU/AqQAInhNpAR4ZM6CkWWWWaL96O1q3lCfKJNaxqC0Kg/
kGn/pxYkl9062jtMUz60s9OPDyuisfyl68UyM68Ozyz4SMRLmDVbewOqQAwmAbtz
8p9AQrX3Pr9tXhARR4pDSsQz1z+8ExEd6EKbhMyiTtHtZQ1Vm9qfoR52snpznb5N
e4TcT2qHAkOWV9+a9ESXmQz2bNjgThxEl5edTVY9m4t248lK5aBTGq5ZKGULNHSQ
GGpr/ftMFpII45tSvadexUvzcR/BHt3QwBAlPmA4rWtjmOMuJGDGk+mKw4pUgtT8
KvUMPQpnrbXSjKctxb3V5Ppg0UGntlSG71aVdxY1raLvKSmYeoMxUTnNeS6UYAF6
I3FiuPnrjVFsZa2gwZfG8NmUPVPdv1O/IvLbToXvyieo8MbZAgMBAAEwDQYJKoZI
hvcNAQELBQADggEBAEb6ajdng0bnNRuqL/GbmDC2hyy3exqPoZB/P5u0nZZzDZ18
LFgiWr8DOYvS+9i6kdwWscMwNJsLEUQ2rdrAi+fGr6dlazn3sCCXrskLURKn5qCU
fIFZbr2bGjSg93JGnvNorfsdJkwpFW2Z9gOwMwa9tAzSkR9CsSdOeYrmdtBdodAR
dIu2MkhxAZk9FZfnFkjTaAXcBHafJce7H/IEjHDEoIkFp5KnAQLHsJb4n8JeXmi9
VMgQ6yUWNuzOQMZpMIV7RMOUZHvxiX/ZWUFzXNYX0GYub6p4O2uh3LJE+xXyDf77
RBO7PLY3m4TXCeKesxZlkoGke+lnq7B8tkADdPI=
-----END CERTIFICATE-----',
			 		 ],
			 	3 =>
			 		 [
			 		 	'id' => 'contacts',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'office',
			 		 		 	1 => 'organization',
			 		 		 	2 => 'social',
			 		 		 ],
			 		 	'userDocs' => 'https://docs.nextcloud.com/server/11/user_manual/pim/contacts.html',
			 		 	'adminDocs' => 'https://docs.nextcloud.com/server/11/admin_manual/configuration_server/occ_command.html?highlight=occ%20commands#dav-label',
			 		 	'developerDocs' => 'https://github.com/nextcloud/contacts#building-the-app',
			 		 	'issueTracker' => 'https://github.com/nextcloud/contacts/issues',
			 		 	'website' => 'https://github.com/nextcloud/contacts#readme',
			 		 	'created' => '2016-10-30T14:00:58.922766Z',
			 		 	'lastModified' => '2016-11-22T22:08:01.904319Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '1.5.0',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/nextcloud/contacts/releases/download/v1.5.0/contacts.tar.gz',
			 		 		 		 	'created' => '2016-11-22T22:08:01.861942Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-22T22:08:02.306939Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'ZqqhqtbHcNB+rzGCQ7FDIjjvHjit+dhAE1UhFgiXApkx3tmPP4nJOBAGNjHe+2Ao
VcTIX2SrWEfieRrA4Gp+0k7pUPWag1Z0T1OVOwO4cmS1AVFyGIOE1bRvDhMfsWTU
4CI4oXaKBFAY6mtnf7VJ7EeIdNqhImkohyWDQ88NiPRLM1XNkJJk6AvZBcT0fvCv
o145X4dLpbixSXsN99QFNJ/oXvK+9tBGwTd5i/WnNFY90vcNRLia8aRo7SA0YJRx
Lnxnj2HMqwTTDQEKE+1elYKWsqQ2DeqwScP97UIKe5bZXnrwOi9kH9PDmR4abtzd
lHL8E1Wgw25ePDeHG7APrx0tVOJy1bP+g8vcarpGynWZoizDkBvYZD+xtxizpBXC
JsDOSzczApptY6dnOtv0Vat8oh/Z/F99gBUahEu4WZ16ZgR1nj40PDK1Snl18Cgk
Me1EZcde8SLEpTbCWYIfIw/O9Fkp5cWD/dAqoiO6g+gNxSZ/gGp57qoGfFxn7d/x
H3aH8GljatAFjrwItw1JzR0THt0ukkOK+bw/pfCslk10sjHMitmz/GXa4qMS91DZ
BKLUd0dSfQUQzkfwcojImbzJRvca4/DYe3mfG7+RCH0tDL6t72dKL9joB++u5R1u
VZPgkToexlXcKWpiDB8H2/SEShKr4udAOjR5de9CYWM=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/screenshots/master/apps/Contacts/contacts.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Contacts',
			 		 		 		 	'summary' => 'The new and improved app for your Contacts.',
			 		 		 		 	'description' => 'The new and improved app for your Contacts.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => true,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Alexander Weidinger',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Jan-Christoph Borchardt',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Hendrik Leppelsack',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAzCCAusCAhATMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDEyMjAzNzIyWhcNMjcwMTE4MjAzNzIyWjATMREwDwYD
VQQDDAhjb250YWN0czCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBANzx
/zJF+5/s4lOJLWIlfKQgTy+UpvIpiUXCgrsHsDZTx+hjQAhIWukH88a+7NVAL7Ys
kQNC0Tlm755FJi/T6EdR7edOwIRdo2ZwakOWLZXd209+6cCd2UloHL0bgnbWepTl
R/4YgbLg/G+FVKCfkEiYc3PuDZ3EVrcwQFcg7h74X9ne6CHH0Z1WQLydxJuVEb2n
X9I+nIRpPHcVostkSiUmzHR7C5TjTIo2PzzgnCU6GC0iBa6z6dDYfz24QEp/b8UA
ZaLhjkyKghVGMnoF/s9KPgH4NM8pvvtadQN8pxlOPju4wbmKPUrsXo4ujurDXbbc
YkzNt8ojobGwdTXoyDogAsGZLQd2FQksWpRvY+I3zVPokBfPMdUPLllG5VcV0VA5
DRK+h2ms+XmspdBvGonjF+XdbFm9hEmDoFmoi9aU6C6AdofjmG/e9+pw/20dXUWk
mMorWwXQ5yLmIn5LnpRXrOuK7CS28VRhBYuVNMlsyKhzU0rophbsD9OFXxYLjr6s
7UPNwZ5h+kjXZDBKD89QctBSViT8RhLe8nulRIm0iJn1sb9hca/CF63KmsFzENfK
QeM6MO0H34PB84iNyz5AX1OIy+1wHD4Wrzt9O/i2LkWK6tBhL69aZiBqdLXWKffj
ARDCxxIfews51EZFyHzwsw65I97y46aBKxY382q7AgMBAAEwDQYJKoZIhvcNAQEL
BQADggEBACLypX0spxAVAwQIS9dlC9bh1X/XdW2nAvSju2taUTBzbp074SnW6niI
bnY4ihYs4yOuGvzXxnp/OlvWH7qhOIchJUq/XPcEFMa7P03XjVpcNnD3k0zQWlZb
tGonX9EUOeLZKdqI4fkrCkMLScfjgJzoHGYQrm8vlIg0IVuRLCKd5+x4bS7KagbG
iuPit2pjkw3nWz0JRHneRXz/BNoAWBnJiV7JMF2xwBAHN4ghTM8NSJzrGTurmpMI
Gld7yCP47xNPaAZEC66odcClvNtJ2Clgp8739jD6uJJCqcKDejeef0VU1PG7AXId
52bVrGMxJwOuL1393vKxGH0PHDzcB1M=
-----END CERTIFICATE-----',
			 		 ],
			 	4 =>
			 		 [
			 		 	'id' => 'mail',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'tools',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => 'https://github.com/nextcloud/mail#readme',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => '',
			 		 	'website' => '',
			 		 	'created' => '2016-10-19T19:41:41.710285Z',
			 		 	'lastModified' => '2016-10-19T19:57:33.689238Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.6.0',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '>=5.4.0 <7.1.0',
			 		 		 		 	'platformVersionSpec' => '>=10.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/nextcloud/mail/releases/download/v0.6.0/mail.tar.gz',
			 		 		 		 	'created' => '2016-10-19T19:57:33.676730Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-10-19T19:57:33.834580Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '>=5.4 <=7.0',
			 		 		 		 	'rawPlatformVersionSpec' => '>=10 <=11',
			 		 		 		 	'signature' => 'VbMsvDpt+gSPeFM8LrZXEK10rk8kkLlgCcblgqNdCSeGZeVpwDAYv3CccVSLa0+l
lTSqQ0VIoH+OIU6vIQNBKHmSCzTplk7OrY0+L5FajXx8LnBaOh892GfGSlEt1neN
KyM0i0uOjO/xpCP/NoUlgkz6hnmYY5XEdN6DTsJtJ/XZhDQ45IYuIkMkHE/eFehS
0JnOagIz+PSipeBY2Ry+tV8YbRa7bC1JAvZzlod0dyI015AHZESeitRUY+MwMWkt
N/me7g7/Kev0wggIQQZm9aYcw63GMk/1VHUPB7Y0ESW9tx2nR5+KwTDn/Jy4DGf1
rg8h0t5I+aPhHOBLrpczH0qaZWY2lsVZWq8KWjJI9aR9P0v2f2aXixXzD/Cuz1cK
hvhKWkOSla4D+/FxeyHGjQvdXMG8gXm0ZmTimKChCoVuCbncDd8pzkdyNoGXcvuk
sP8OrkQFooL4E7S4BWfdSiN/a8jUITJQkuXp/OVrVGeCupLWJh7qegUw6DvoqyGy
D4c6b+qYn68kx3CLaPPiz+tFAZQZQdj7+Kx/lohso8yTnVSiGYrMj4IvvCbpsQjg
WF3WSqF/K/tTnPYTWb9NUPSihTbVNv6AXOfTsPEp/ba2YSS5DjvjVjkr5vhR9eg1
ikQ3Cw6lW3vaA4LVCC+hFkMRnI4N0bo5qQavP3PnZPc=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 	'en' =>
			 		 		 		 		 		 [
			 		 		 		 		 		 	'changelog' => '### Added
- Alias support
  [#1523](https://github.com/owncloud/mail/pull/1523) @tahaalibra
- New incoming messages are prefetched
  [#1631](https://github.com/owncloud/mail/pull/1631) @ChristophWurst
- Custom app folder support
  [#1627](https://github.com/owncloud/mail/pull/1627) @juliushaertl
- Improved search
  [#1609](https://github.com/owncloud/mail/pull/1609) @ChristophWurst
- Scroll to refresh
  [#1595](https://github.com/owncloud/mail/pull/1593) @ChristophWurst
- Shortcuts to star and mark messages as unread
  [#1590](https://github.com/owncloud/mail/pull/1590) @ChristophWurst
- Shortcuts to select previous/next messsage
  [#1557](https://github.com/owncloud/mail/pull/1557) @ChristophWurst

## Changed
- Minimum server is Nextcloud 10/ownCloud 9.1
  [#84](https://github.com/nextcloud/mail/pull/84) @ChristophWurst
- Use session storage instead of local storage for client-side cache
  [#1612](https://github.com/owncloud/mail/pull/1612) @ChristophWurst
- When deleting the current message, the next one is selected immediatelly
  [#1585](https://github.com/owncloud/mail/pull/1585) @ChristophWurst

## Fixed
- Client error while composing a new message
  [#1609](https://github.com/owncloud/mail/pull/1609) @ChristophWurst
- Delay app start until page has finished loading
  [#1634](https://github.com/owncloud/mail/pull/1634) @ChristophWurst
- Auto-redirection of HTML mail links
  [#1603](https://github.com/owncloud/mail/pull/1603) @ChristophWurst
- Update folder counters when reading/deleting messages
  [#1585](https://github.com/owncloud/mail/pull/1585)',
			 		 		 		 		 		 ],
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Mail',
			 		 		 		 	'summary' => 'Easy to use email client which connects to your mail server via IMAP and SMTP.',
			 		 		 		 	'description' => 'Easy to use email client which connects to your mail server via IMAP and SMTP.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Christoph Wurst, Thomas Müller, Jan-Christoph Borchardt, Steffen Lindner & many more …',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIID/zCCAucCAhAVMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDE5MTkzMDM0WhcNMjcwMTI1MTkzMDM0WjAPMQ0wCwYD
VQQDDARtYWlsMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAp++RuliQ
lBeeiPtP0ecBn00OaU1UCpft/NVI5pnSiT9nU4l2kc5IvKjA8UxDB3gWfYTOeBFh
tUHQ2P6UKCmHZT9sApHhqLu2n0V+YhlFIViuaxndSID/M414cl56xOYQusV3Pcae
o2dOSeRRzLab3tEaVHlkBSFkGmAwPZItsmTklvV3h1sUysDicYgfXPCkf7K+JgWA
BP7vsWC8B7MDRhcB3enYv5tTcpsyvtGX7bb1oTIWVypcmKsGYfTX12VNBxKzNBIG
8pwdb8Xo0o14TytWsWN7mSHf1XbwfwYMjDWOlMqiRc+mcoKMBH41TfM/CXslSivI
syvxasEaFdlj8lmKPENdzw1OfYRs43usIf4szwyt4rb8ocXfDipnY3P2hccN6YcZ
l8y8Vsr69ASluDj2A2Pl5vH6xp6tNybZRnN5G6sghhaYaLNDU/TdMyYzz4AY33Ra
HSaMypfcXjd76Aj8jZvcwk1BH+ZsvFqNK7ZKCb7WVcMH8KRcU1sxZ4rp9vviM2fL
L7EVtznm3bSI9jjHXbiwq7RvNRRy+F6YRpAdWGwTU8uUkDabPFi41FikYyzNWauK
JhlDJXl514XjKyMVBjAZYVr5gZZkO1J7C4XzLFbC5UzYNSzW5Iwx/1j5OeYJRxh6
5rhiUwR+COT1wdVsl6khMC8MfBR4unSd338CAwEAATANBgkqhkiG9w0BAQsFAAOC
AQEATBvpqz75PUOFPy7Tsj9bJPaKOlvBSklHH7s43fDDmQbJwswXarZi3gNdKf8D
yO/ACZvO8ANWAWL/WahkOyQtKOYzffaABGcEIP7636jzBdKtgwSGzW3fMwDghG10
qBr2dE6ruOEdSpuZxgMgh2EulgknZUXaHAMI2HjjtAMOBScLQVjOgUqiOHmICrXy
ZETmzhx0BXDt5enJYs8R2KMYJNIme1easQRYmWKliXogNY09W7ifT9FHtVW1HX+K
xRS4JXbapjolkxyGSpP+iYSgItVnYzl6o9KZResR4yDsBv7G/8fpV4GQU9IS3zLD
PiZOosVHWJdpUKCw9V4P1prGTQ==
-----END CERTIFICATE-----',
			 		 ],
			 	5 =>
			 		 [
			 		 	'id' => 'audioplayer',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'multimedia',
			 		 		 ],
			 		 	'userDocs' => 'https://github.com/rello/audioplayer/wiki#user-documentation',
			 		 	'adminDocs' => 'https://github.com/rello/audioplayer/wiki#admin-documentation',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/rello/audioplayer/issues',
			 		 	'website' => 'https://github.com/rello/audioplayer',
			 		 	'created' => '2016-09-16T05:44:24.857567Z',
			 		 	'lastModified' => '2016-11-17T22:34:34.637028Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '1.3.1',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '>=5.4.0',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/Rello/audioplayer/releases/download/1.3.1/audioplayer-1.3.1.tar.gz',
			 		 		 		 	'created' => '2016-11-17T22:34:34.215350Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-17T22:34:34.867778Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '>=5.4',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'p6Zz0IEFrxvw6y/3jHgGWWCxR6qpMzvU2HKfxcIVsK6sJnoRUhWLeAXwZ432fH2a S2llj+IGS9OvW+5VQElrXgPtEjDK1BT00DRJnp5RFCRlUv0LNoedJMzx6B6AHqPP JBufk3cG1O/CO0M0L1ITGSmSOzfKvWTRo3lxVGF792NyBaP/SyZCkH1N1TzBQzUi Ywl3+HiglPcXbHjtJm/arnKorbJWVKoaN93xFuaBapd2ozQSpi0fE0uGRsici+U7 HNa1M5WFE1rzUJoufE0E9246At07rFY1e+TdNEq8IlLgCXg5vGCKkEyuWpWno6aX LfRaIiT9x39UTAwNvuDKS0c+n4uWDYPsGfKhDx9N7CXpUrthfXVEWRzZEXG7as10 6ANvrRPJemSZH8FUSrdJhD7k12qa9R825y7mIG68Li8P71V92EOxFfo9tNXqXwBt VuDGxBqByFVPqSCj5I8hrzJzQl2Xt40g8+8ZcSF96RMg/pM+bwRMTv+mz0V+vQQ4 DWjqnWVPalaJ1PPD5/QFFErtXuNRbyxKZ6BMWxfJlLM9Kz66P75K+8fYaSwz+2KG NxY7I3svzS2K9LGH3fBLUy1t3Hl+c3zGFq/ll1MJrf9NswV4yxt2WmISfM/KDww8 yELkGs2cRzbw2tCvnmYlJJhIqRLkyFAbDzWRH/XdVx4=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 	'en' =>
			 		 		 		 		 		 [
			 		 		 		 		 		 	'changelog' => '2016-11-17
- fix: one-click-play for wav not working
- fix: wrong sql statement for PostgreSQL [#90](https://github.com/rello/audioplayer/issues/90)',
			 		 		 		 		 		 ],
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://github.com/rello/screenshots/raw/master/audioplayer_main.png',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://github.com/rello/screenshots/raw/master/audioplayer_lists.png',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://github.com/rello/screenshots/raw/master/audioplayer_share.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Audio Player',
			 		 		 		 	'summary' => 'Audio Player for ownCloud and Nextcloud',
			 		 		 		 	'description' => 'Audio Player for MP3, MP4, Ogg, and Wave with a lot of flexibility for all your needs.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Marcel Scherello',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEBjCCAu4CAhAIMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTE1MjExMjA4WhcNMjYxMjIyMjExMjA4WjAWMRQwEgYD
VQQDDAthdWRpb3BsYXllcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIB
ALyC+iLscLs62NeNmUXEBmg+xMuUtDmZKr+xzJWtl6SSNRz+8K1JygvUIXFJ3RIL
CYA3xyq8/wyZH1gNrLKyz5eTeYawG+eT3ges/FT6MWGUbZoRrBrikVcLC94QzxTH
xOl8Dn+SCV/2bhcvPTQdhK+dqtvGilOtjHa40iMrk9gSdlKVys5CK/xdlEp8uiMa
kz1WENn8MVCCJV58bAUbaCupDWXR9CCoSsw8XinNsCenZ2B2XlnmbM44280w0ojs
72rfQRgj3yDG+ZUUyUOuxIuodu8liXYciLf0ph6t/f/qoSmctbBdsR5Fl1Upj1Ac
qeHb5Yf/B3Vi6Mn3XfDx0H2EHk1v9Dhzxay+v9BHUzyIX2iH/q+7TE0/Jzo5AwBW
vFKWXvG7wXaALcHYZf5v/M93IE0iCHsv2EsZKQPBnzXVGmp4DwFSP4po1B7hcog1
gAMaellAzzvUAizgCovN6Qct3qDEANYniPlvtnlcaQGonajW4N019kFQRHLIzPFR
jab5iUMMwSnT8FhZO2ZOWuWhJven+gXjxC8mfMVgBfZnAVgydNfx9rN+KzTc88ke
obUdZ0OOeBzA7pIxGEFg9V6KTEEWZ+qH048vxXz4HI9B1I+2wQLBrZl8CvweEZ5U
5ID8XrrE/UaNZ1CvLKtCgB24gj/m1Elkh7wA3gEcEo2JAgMBAAEwDQYJKoZIhvcN
AQELBQADggEBACtgUp+FCmjWIkQUuWSdzKWdO+IH4v9wBIrF9mo0OLIakFyDYyM5
LlkYZXbplGXd4cfn3ruIqJNzlIb4xa5CU0bM4TMbD4oOSlLMKM/EamKPHI3bzr++
zi7mQDFxmAE6FWSMBgKKUb4tqLc5oBap8e12tPEZl/UR6d9iUB2ltvrm3T3vrjjl
2Worm0eYBNqnagXmX5+wS11AQqeJemGqRy5e1yXRlTgB0IJhH0dCsFNwifEigutp
FNvGFVBn4r5qCiChEoq+rCXHRjPi/eCfbW21XeLFDiLxapcZyc85JIcA7znUYoFe
P7Y/ekMscwWhLbF91OaQlcWpRtEMyde/DaI=
-----END CERTIFICATE-----',
			 		 ],
			 	6 =>
			 		 [
			 		 	'id' => 'calendar',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'organization',
			 		 		 ],
			 		 	'userDocs' => 'https://docs.nextcloud.com/server/10/user_manual/pim/calendar.html',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/nextcloud/calendar/issues',
			 		 	'website' => 'https://github.com/nextcloud/calendar/',
			 		 	'created' => '2016-10-01T12:40:39.060903Z',
			 		 	'lastModified' => '2016-11-22T20:31:13.029921Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '1.4.1',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/nextcloud/calendar/releases/download/v1.4.1/calendar.tar.gz',
			 		 		 		 	'created' => '2016-11-22T20:31:13.020268Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-22T20:31:13.087340Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'nThwe9CJBCan9nuDLdhfBiQyPhmum6Aa0UcYsIDdhGMw+C2acf81KhEmBJuTTWxo
WGby6WcrcJJmeuCW+ePU91ju7Pd76RirprhVXIEceIDzSCxin+K0oZCZ1IGVIJjP
IkVehTsLuCeTBbjvz1b3k5QFyhUhvd32Xt7k5d7VARyI4OqnqYYNBtH9vvgeRrFw
AxsQr4o4axof6i3iykLg6WfWarYArY4dIuu5DkPuGPWf2bbgjwWEra4sQejhOs7G
sk1xcsfYv2NpArIbpw/wnATdjiax+Gjz1URMD3NgL5ky0ecuZmNvN25QErg3nlVr
hh1FBfA5pvCJbkJ6nr5bU4bKaffwDX1nr5h77FS5zzn0Pyd7ZIExmVmNtaeJfnfV
5vnclapzXMNU+R6t/ATJQd1srvSJhyljQapzsqnrjNMEUojOEvqqygJp0KwNVPqs
3g9XGSoOnO+WULWBeISW7UVOg8BOF8pwvHIU2++bSzOdpypW0Eq6p2DPWO6qL/H1
eFLKrUg3EvnTjvknbBxMB55h9jNJr0SAlkrmyEVm6+CE3BwRWpKB+cJMBuGiwPwv
r/ASRiJrkDThbNWAUtX70rUmCqDV6/MujLXViqOc/Q2OHvcXd1oGDccJSQT92/1z
7nonnedyYQIDqUt7u68WL8JRxp7pFsEqKLVuWSgxW3c=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/1.png',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/2.png',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/3.png',
			 		 		 		 ],
			 		 		 	3 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/calendar/master/screenshots/4.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Calendar',
			 		 		 		 	'summary' => 'Calendar GUI for Nextcloud\'s CalDAV server',
			 		 		 		 	'description' => 'The Nextcloud calendar app is a user interface for Nextcloud\'s CalDAV server.

It integrates with other apps, allows you to manage calendars and events, display external calendars and invite attendees to your events',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => true,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Georg Ehrke',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => 'https://georg.coffee',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Raghu Nayyar',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => 'http://raghunayyar.com',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Thomas Citharel',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => 'https://tcit.fr',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.94444444444444398,
			 		 	'ratingOverall' => 0.94444444444444398,
			 		 	'ratingNumRecent' => 9,
			 		 	'ratingNumOverall' => 9,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAzCCAusCAhARMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDAzMTMyNjQwWhcNMjcwMTA5MTMyNjQwWjATMREwDwYD
VQQDEwhjYWxlbmRhcjCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAMm6
FTeqgzCXprkU83VM4/DrZWn3kqtfaR/edkC4gYT3ug7RHa/Uv1C/S++vr7pvgpnk
YzQoavl/0Qlh5sKEYX+0ud/LQDoiidwBRDckFUQ1bRfVLxAD9UAVvDRHxDqJMOx2
gZArbeQ3ztdSHZp4ThzBFWq2FILsJD86weG7LwHjzhW6SWgLb/YTLbuuW6tRCDVV
btB0I/a0vCwj2u91Chw3u6pWWjPakc9DQrIDH4HCIBKQ4zVrYDxAmJDRFGDvVVWx
uIAeux8sd8drqSMqAhX+XMcZPRD71NQTWbCupSwWO8kgjmZnBpIiBNpzvMQzJf3A
QloZtjZ2RDXAQG88eTeT8pp8yEOCEoDLpGdraKxJrh/z2Dsk30JP3lOiNYJ9vBaB
C8NJbJ3oAlG7THwUaFF9fsdAKoTwzs5Xms04TI7W/v4Z/GClOzMymnR1T4sR72Oy
3WaMNHv/1QGffvQn2/TtZt23Ou3P083xWx2vn5FgTcac8+x85vRgWsVCA4hq9v6m
AlktB0+UWDEXpDTKD9BdFNWM8Ig9jQf7EJrvTLNnS7FIJZMB4GK8lpvPxyvACWnh
R2hQOe987Zvl3B1JZNO5RvtSeYld9Y9UfMgW1aPRweDNjSuZYAKlugx1ZoyI5HyA
QjfzAwicIMwZsCJDV/P5ZO8FE+23rdWaoJczpBqDAgMBAAEwDQYJKoZIhvcNAQEL
BQADggEBAHQXwvj8q5khWR/ilg3JGYpmMNBYHE9OeDaOcNArkKaGMd478SDPOXeu
yW7hCvNEpiTk5g0h3g3yleZFws0xH8fPsQgZANgvQXb3RCcD61NL77d0cMTr7Xzr
N3Lq/ML1YLc/WwL4uV1XvpMQMwALFL1p63BU2c0ysO31zbLOjMKAJi0hHFDYz5ZQ
D3xxtc17ll3B5IqrMnMHRqmOQ39Sbe56Y7T4agaIz/sUWpseo85D5kt7UAIOR+Mr
Q0Bl/QinETk72afGR46Qvc7tC1t9JjQQD3AUbEGuJdGvXjJJ9GREYu01XoODmPdT
jXXOI8XIOK6kxXhPHUc3iWu9b4KqGm0=
-----END CERTIFICATE-----',
			 		 ],
			 	8 =>
			 		 [
			 		 	'id' => 'ownpad',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'tools',
			 		 		 ],
			 		 	'userDocs' => 'https://github.com/otetard/ownpad/blob/master/README.md#mimetype-detection',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/otetard/ownpad/issues',
			 		 	'website' => '',
			 		 	'created' => '2016-09-29T15:58:52.814912Z',
			 		 	'lastModified' => '2016-11-19T17:37:52.278497Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.5.6',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/otetard/ownpad/releases/download/v0.5.6/ownpad.tar.gz',
			 		 		 		 	'created' => '2016-11-19T17:37:52.234684Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-19T17:37:52.423930Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'dh+Txg1iVfqXr8+cxplNQuBZGErSnXUo0ewGwnybNMJqp8/EjEo72+zPpW3dVnhY
67YCvhrm2bo+VRdFFymEfymzSJu9nWVFkGJhEwvTxPyIdAtuD5YAVrzmnR6L+H7m
7Q1nXE63ICPCAQpHkxIfIXLh25OhWeyofBB8AVsjDUNn58FEYJ8fFkr6dCgPriZS
sM2J+xtZMDYufy+xFMsVf/Q3WopjFuBjMC3qOecW76ZTwtREaswOC2RtpzUku2r1
sogrfFlFer3Ii9/CWgOktnLfjB1DzbTwdEkM2xNVBRJgdMXt2VLA9FsxFFkjmr5A
l7x9cNLWA8RLpOIpIMBbaef75u5HgRBvSvq114UsA9GCu/EYbIgD8YxEt7xuKd4t
enksJB5gJ2IQNdHrPbsil59AsJ/dismDN6ktYgWQEk5dINzvm9EAvucueW0Gt+Jr
qEC5WBgJucsFxSvkHh52v43M8jgPYBfHWEL/M/+377z3+mbuIh+BcQ+vcDdiqxTF
o3n0+gw3QYIhLEe75sUhxG6ynVUdW25AKKju1kVj3KJnZTBH1R8t8/zy4DnJG8d4
uRGqyU4BXpZjEC3nVlsC7vCncWWhxl0WZQ/MWKqsfjVAU4I88E518D6NioqMnPEJ
iCZ2x+69UCDEQyfCSKajgAYT17r3OhZei8F9KSCH8Vw=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Ownpad',
			 		 		 		 	'summary' => '
    Create and open Etherpad and Ethercalc documents.
  ',
			 		 		 		 	'description' => '
    Ownpad is an ownCloud application that allows to create and open
    Etherpad and Ethercalc documents.

    This application requires to have access to an instance of
    Etherpad and/or Ethercalc to work properly.
  ',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Olivier Tétard',
			 		 		 		 	'mail' => 'olivier.tetard@miskin.fr',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEATCCAukCAhAPMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTI5MTU1NDA3WhcNMjcwMTA1MTU1NDA3WjARMQ8wDQYD
VQQDDAZvd25wYWQwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQC6CY7I
HRJTaqDu376vt+kruX+QOL864joScxRuh3IOVcQktCvxasuA0EtrX7TCAQrV1tBK
fkqJxU9uOV54RTgyh30yH/ZtnF2bYQwViGM06Snc0riqWydFrN5fxK52dpZWs63o
UFCNhHxrX4aUGyfXu5nQMISLm4QHoZ3LDLofk1ZsiK62fM/Jz8N2PM8qeHzf1ATo
SKcAOd3UeaS9C8bv2DuiZM7unkSO/tjrBzkMiq8ds9sIzBBsyk6BRh2HQjHPOtmO
ed+pS9mIZmc2xhssXoHL4IfZwTqwhktpsaTl7v0ROw2dwDATz/QoKMkUpboQ5lkz
wgLQhoIZw6uAZ1R/Qjze59I3iU8zIo9quDarHBotZNXboYCmg9FRfE4mHtegVaa8
v1a1JvFQ5gvsWEsKSV6Bzb65GTp4KG4q7YnUrzh6HJyDCGLvLlWm5OWsFj6sNzXX
wLOv6JLORMbF4ZIo2iybb3x7gdfCu9JxMZ4JtOUC8KSJ6+ub15C1Aia3lN68dNts
Y6KwUF1Ted0o4OQPAulq5pUc+g6dTYmIKsavIiPKhMtl86AbUK50vRTeuGdFsT7X
av73IanPdFI9bKth+tajgvB6dxcVnvBXbrsLUyEcsxsxtBJvQcMYS4aZ6ZJYLTep
7AdK0Zt1iMdXB8+4PCps4rcG6bYB/uJeEAVm7QIDAQABMA0GCSqGSIb3DQEBCwUA
A4IBAQCM10O+sCYhIExnx01vGzKlnRS7MSQNx8ZMmbR5Elfz4AVJAEJ96ytS2DXH
2c+hcD0wAenXQEFk920AEqFQBT8DP34p0FmF83aMHW08ovzFiu4MdlhcqrLnko0h
cZTXHVyS/8JZh+o6SVm8R0/BBLF1MQQ5TqRkJehbmk6gL0+MSYxehUDKWTjJITkR
ifneTw/Ba1d0AXBOq0c0HFyGxMPIlWe4qn5LtxH5t0wyVGeSj4jyv4nvd3ZGuAgY
EUa2uYht/z475k4+vf0YhV98iQH07GnmlfD2TDZgmOCQGKlNfJh1v88OZyLLa3dz
gRHzGwKbAiJ8T8bbpZ3e2ozXxADr
-----END CERTIFICATE-----',
			 		 ],
			 	9 =>
			 		 [
			 		 	'id' => 'announcementcenter',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'organization',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/nextcloud/announcementcenter/issues',
			 		 	'website' => 'https://github.com/nextcloud/announcementcenter',
			 		 	'created' => '2016-09-14T10:38:53.939634Z',
			 		 	'lastModified' => '2016-11-24T11:21:50.324839Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '2.0.0',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=10.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/nextcloud/announcementcenter/releases/download/v2.0.0/announcementcenter-2.0.0.tar.gz',
			 		 		 		 	'created' => '2016-10-06T12:41:56.195206Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-10-06T12:41:56.263124Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=10 <=11',
			 		 		 		 	'signature' => 'NVWYz73KtuoZ7ti2sluztJO5aFUc7PzhlDcg0VWyAQd1H7sk5wjw7i0bhrjw8O7M
Lsrb+PegnsL9eMlYM2WrRom+RF1PDP482xymZf1T8vh8qcTCm3TK89xSuiSm8yoA
iWUb/Uv/ODj74wVDWqWxAFKaAG/FestCB3InOOZQqQZLzlAV0U9ziYDGNzBjFqof
9rLNxJ2IOqZOA7hhMIKhSrpA0KkSfNhBsVf8CWClYnVkZQiq0LoYkHkHIlXmXUr3
OfQFKEjtsx+bNLa6CkAaocHGHJXAofX3GQZ9cjBsjZqiTfbXfcVk0kRfz7pwL92L
I1McfJYvgMxDQG5bjRpNJw==',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://github.com/nextcloud/announcementcenter/raw/stable10/docs/AnnouncementCenterFrontpage.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Announcement Center',
			 		 		 		 	'summary' => 'An announcement center for Nextcloud',
			 		 		 		 	'description' => 'An announcement center for Nextcloud',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => true,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Joas Schilling',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.75,
			 		 	'ratingOverall' => 0.75,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIDDTCCAfUCAhABMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwODIzMDkyNTQ0WhcNMjYxMTI5MDkyNTQ0WjAdMRswGQYD
VQQDDBJhbm5vdW5jZW1lbnRjZW50ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAw
ggEKAoIBAQDPx4Hp1HdBo5v7bDEiVcv2UrVjNW+fPPKS/5rwbagtPcE/1v3WDcwX
vFwaXk8qCn2UpPSQ2b1rTuTDm51G1ZmEZhNiio+rBfEe9F+3tLsq9lElqIPKhkAq
EUVI6dcN+jSqvLmLhuwloEoQQSYaLrX75mY3lGqTb83h1l2Pk/brVixuVf4vJW31
TgeieuGKnC+keLzKOrvTHffJakU8ktwB2Nuu1o+jN5a7u1bxKkP3LjEWPjq236hk
AoOcW/wi1dUEyUKUZsZQeJyvTJh1UXdLHKwYywtUu1/VLZ1IUtNyPBfiQ8ukPp3T
TnSSmG3ZnvsfM6DmAvLZ8bBQkMBzEcTLAgMBAAEwDQYJKoZIhvcNAQELBQADggEB
AAB3i2NgiZ4rpNag7cXYdaFxAxdDWnke1+LX2V2R3hzGmx73/W6cKLpo3JBn9+zT
1aEjlqkt0yHu4aAPVYQzOa5zIV8mjP84p3ODSyV9J8lfjFNXT7wdA8+9PVx3lVki
2ONoCNBh1kOxnxI4+BsMlQfF00ZbBSuGcMm3Ep3lTFWXzuUn3MQITzPwkL5LkW6a
sli/yAYQRTVDsXD8A3ACYT7BG31jGxyXtIHzqCci0MhZFdKKayMYkwfjZchIUtGN
JJoU8LQoHwGRtp3wutk0GlFzpEQEvSsn/Lsvvot5IfIe46tnzA6MVj5s64s5G8+Q
phhXFlzXqO/VxquPdbfYjvU=
-----END CERTIFICATE-----',
			 		 ],
			 	11 =>
			 		 [
			 		 	'id' => 'rainloop',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'social',
			 		 		 	1 => 'tools',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => '',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/RainLoop/rainloop-webmail/issues',
			 		 	'website' => 'http://www.rainloop.net/',
			 		 	'created' => '2016-10-20T04:17:37.217555Z',
			 		 	'lastModified' => '2016-11-18T11:36:04.309739Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '4.26.0',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '>=5.4.0',
			 		 		 		 	'platformVersionSpec' => '>=10.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/pierre-alain-b/rainloop-nextcloud/releases/download/v4.26.0/rainloop-4.26.0.tar.gz',
			 		 		 		 	'created' => '2016-10-20T04:28:21.491747Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-18T11:36:04.619927Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '>=5.4',
			 		 		 		 	'rawPlatformVersionSpec' => '>=10 <=11',
			 		 		 		 	'signature' => 'nTYIVSB6mIwKtXIrKoVGsOGFflpLjed8jFem1VLQNtXQj4bztnNrdc4YaPIn0yzM
yLpMSqRDNzdYNFuOeDiyKLPJPTA++MotLCNjEe7kxUekek+m+qzgnGBdcT7RQT6R
p9xWGecnVx94d6aA55uiRhgQRyHpdDMMLCOz1be+HvpwHy69DRFZ1+SPmGUt6eW0
u5yS0vHCu1K22cbrVNXFKjxAOlGcIDm61oQuz7ycl3uAujZO4rZbWt55jilgKGak
ew559A5gTp9W+j+TWKIcg6LIZ9zLRlGjcQrWJrsc+OBZQcqiYimSFyO6HhfT9TPS
Pof//I+dSsd+H0SRGGeL8DvSnK+NKZL1q5EX5pziqsv6nZFITpCDwmAN+I8AnXXL
SNkFi53M8RZTOABpD2x7YPYP1cEvwrRweqV/C/oHcYnpfh7D2DjFeWwXsjeAXrHY
hgFhPrg+7rf7g6UmJFOCp0dC9sBdyQ3KtJkv7bGqPr854r2cdA7xW0QHWQ2in9qQ
LhIczc32ECi3ZVVgyF8zyT4Y/3MRS05oX3FHvHyt88mjni6bVaO78F7ZRSha8gHh
NOAkku7AMXPvUCHaZP2iVCCoAViEso8GeR3O8xh2G42Ai61RLYwx8LB1+23EoJTr
mfFuRYNSg+qAKCokXNnh+lDlwu4AkaQo3vtKGPXvU7A=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/pierre-alain-b/rainloop-nextcloud/master/screenshots/2016.10.20-screenshot.jpg',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'RainLoop',
			 		 		 		 	'summary' => 'RainLoop Webmail',
			 		 		 		 	'description' => 'Simple, modern and fast web-based email client.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'RainLoop Team',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAzCCAusCAhAXMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDE5MTkzNDEwWhcNMjcwMTI1MTkzNDEwWjATMREwDwYD
VQQDDAhyYWlubG9vcDCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBANqB
5jnF9qZ/qjckt0kRjpHCOMtJumW/KiQoMeNP5nGv4ad0DS3KemOapUef8Zn7qCYb
MnODhK7HBwPifFzI1j8bnT2hP6E0geFLb0MdN59d2NF0n4CCs1+BnepQPJ1kFbPK
35wQRi0RDeTf/GQ+/owEVCU9a9W1P/VUXk8Z0vMoQxCXEdRqnB63SgsKl7DB9G/C
4SYrgGor+OHVGl4ntMZhJujiM996DttrNK3iZRGkQ07L+lfUIwQ52XOhQNRdic4p
B03lw7PpChwPGMv/EEvdR5HpCJQBJniqJbbu3Jh8bMBKTE/8fCzN3vMXICB2g3Bq
lKkZW6fnJRGsrZ79fsQnl+WBPNSrWRLOxOfe1fyCFV1ljFB4nTH7uF3pC8ZRgJes
kHIESHz3GJm28hn4+17ESMGHBCbs7L9FK2GY31cobU0VRntLxpSG+d9njbIAgMG1
S7U+oKVFQhSVpdXNOaUNqhcQ3HkbQTLEP0k53A/lhLQb2+KPd8nntaELjwNyrmZg
sVMgHj/zdlvrbguZjZFzUzDBFvkuv/5M58lNT/D1C6ufVp/R6eLsYI+nnk1ojAjz
l7N6U8X5SXpD+Bm7+Kn1PH+bHl7cViCx8oXJXO2RhP+COXckw7BDZKtjItYHNG7M
pFwgYqWpvCu9LN6IN5a/eLqSI76dOOP3iYbaTH+NAgMBAAEwDQYJKoZIhvcNAQEL
BQADggEBAGB0Vq0l6ndGTgNbZxSEFyBR3u3tiR3pWK81DYjsui7qBoO6P/BaGmf+
raSwHPaBOwA9XNS8jcGLh5xdqY2p/m0dTS64xNjVL9nweWsG+FwVnPANo8C4nXdm
9ajJ4cdg54stQK8qn1uh/xPcd23GKfYJazjYSwYmZ3pXXdzlGN9NxkeYJQxJ6B+5
pzAeVGiABI/e5URpxzz2UayRX7EE+vtpe3B84hzkLqsq0N39ZN6KLfaTyEBGLzqE
iLYeXQTV0XSRs8xVt+iyGlj7nPkv2DR0oCqRpWUFWeSBI//niDG5WxS3qg8kacSW
fDSYhSN+IjrnIkwNtc8V9t7/GeQB5FE=
-----END CERTIFICATE-----',
			 		 ],
			 	12 =>
			 		 [
			 		 	'id' => 'richdocuments',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'integration',
			 		 		 	1 => 'office',
			 		 		 ],
			 		 	'userDocs' => 'https://nextcloud.com/collaboraonline/',
			 		 	'adminDocs' => 'https://nextcloud.com/collaboraonline/',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/owncloud/richdocuments/issues',
			 		 	'website' => '',
			 		 	'created' => '2016-10-31T08:55:45.631429Z',
			 		 	'lastModified' => '2016-11-24T12:13:53.905352Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '1.1.14',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/owncloud/richdocuments/releases/download/1.1.14/richdocuments.tar.gz',
			 		 		 		 	'created' => '2016-11-24T12:10:13.337165Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-24T12:13:53.963638Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'prDGlfRPxqT6LP0BsAFPwGww7P4Bngha2N4u5B6+F02N+RVOjGtTcXKqvM1KjZb1
Co7qJvgJmjpvIvDmB+rup02i8ObfwP2ct6UdsD7ouzOWJG2sJANXK31bHyvOmQ2h
vKu5eNcOkf+WFyFKYi51TbsfWn2+1Wge3WWujKAVcEvqtcOOz+uMWNtqzBptEupk
E1aaRnQfTx488YB8Ubul06LIY0PNCHgGCWPgy817tOVT7JA+V0P0FFonl/PXE0dr
WgtxRJmvGaNiFzYq+kQmdKMfayZTm3kdVgP0W52t5wp878K0i4s2KPg5lANvjTz7
DCT+VV2IGIE52o4RpMUGyQ==',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-document.png',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-app.png',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-presentation.png',
			 		 		 		 ],
			 		 		 	3 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://nextcloud.com/wp-content/themes/next/assets/img/features/collabora-spreadsheet.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Collabora Online',
			 		 		 		 	'summary' => 'Edit office documents directly in your browser.',
			 		 		 		 	'description' => 'Collabora Online allows you to to work with all kinds of office documents directly in your browser. This application requires Collabora Cloudsuite to be installed on one of your servers, please read the documentation to learn more about that.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Collabora Productivity based on work of Frank Karlitschek, Victor Dubiniuk',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIDCDCCAfACAhAZMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDMxMDg1NDExWhcNMjcwMjA2MDg1NDExWjAYMRYwFAYD
VQQDEw1yaWNoZG9jdW1lbnRzMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKC
AQEA1jk29m6JykcJ2Ld0YEpjPMYh6kwxY6GysNJnfkA/th7tPWL3+vBJ9oTYyVnZ
jwAE1Cqwfa9MyBKMZ2IdfIqtT8PeWzuFP7Ib942EdxUpwwh9F3lykeGsj0h4zQwX
F9OooiS99PfLX+JpkKm15Ujb00iLB6xQmq0+3NeOT1CTD1ziJ1ueOcxBKMwaFp2a
Puz3F5ywqCvpmxG/OBuOs0LI3/zStXhBNbUMxBrWblr7zaVNJXl/I2JCKj8Wah/H
XUEEGbW15fAUP1f+90eQSxpEoCZDBHXOQCTJYzySGv+BjU+qlI9/gS0QbrsiyzUf
O5lyvi8LvUZBzpBw+yg1U75rqQIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQA9jU3m
ZmD0ywO3MUsG/GLigFtcWi/p7zp2BliR+NpuY2qNFYDcsIb8ZUudmUc/cJRRctzy
APaLLj/d+h5RFaxjTVvim1PSe6M7urK/IMSvyUVYCeQRYpG8ZJixKTCOVIBaWHMz
xTfc51tm9EPlpJpK6JtaWrYYoWGE3k9sINdJ4JkvKkE2CBAqVhX6ZGyEQ0bnEhtk
Ru1DXn+LW7TJ4NZ8VtLWvmW/6Kfmi7dQ1V++Kmn0lO5ntRt5altePbStCHC8bhGp
myBOrjhrJgLIwvgH26MYZhdiSkFzoE38nMPZdrUmUDxcPCwucWJqgzDPudguFthj
WCVZ3TTG/2z3+tWM
-----END CERTIFICATE-----',
			 		 ],
			 	13 =>
			 		 [
			 		 	'id' => 'ocr',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'files',
			 		 		 	1 => 'tools',
			 		 		 ],
			 		 	'userDocs' => 'https://janis91.github.io/ocr/',
			 		 	'adminDocs' => 'https://github.com/janis91/ocr/wiki',
			 		 	'developerDocs' => 'https://github.com/janis91/ocr/wiki',
			 		 	'issueTracker' => 'https://github.com/janis91/ocr/issues',
			 		 	'website' => 'https://janis91.github.io/ocr/',
			 		 	'created' => '2016-09-19T12:07:49.220376Z',
			 		 	'lastModified' => '2016-11-21T11:22:21.024501Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '1.0.0',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 	0 =>
			 		 		 		 		 		 [
			 		 		 		 		 		 	'id' => 'pgsql',
			 		 		 		 		 		 	'versionSpec' => '*',
			 		 		 		 		 		 	'rawVersionSpec' => '*',
			 		 		 		 		 		 ],
			 		 		 		 		 	1 =>
			 		 		 		 		 		 [
			 		 		 		 		 		 	'id' => 'mysql',
			 		 		 		 		 		 	'versionSpec' => '*',
			 		 		 		 		 		 	'rawVersionSpec' => '*',
			 		 		 		 		 		 ],
			 		 		 		 		 	2 =>
			 		 		 		 		 		 [
			 		 		 		 		 		 	'id' => 'sqlite',
			 		 		 		 		 		 	'versionSpec' => '*',
			 		 		 		 		 		 	'rawVersionSpec' => '*',
			 		 		 		 		 		 ],
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'ocrmypdf',
			 		 		 		 		 	1 => 'tesseract',
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '>=5.6.0 <8.0.0',
			 		 		 		 	'platformVersionSpec' => '>=10.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/janis91/ocr/releases/download/v1.0.0/ocr.tar.gz',
			 		 		 		 	'created' => '2016-10-24T06:50:43.283900Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-21T11:22:21.269108Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '>=5.6 <=7',
			 		 		 		 	'rawPlatformVersionSpec' => '>=10 <=11',
			 		 		 		 	'signature' => 'CBJkCIiUKyf2NuWfz2zJ3grhf8p7wJes7DPV/OxUzhlxIH0Fh7K54+U5A9JOOi6f
WPhjXG1ylkyIVY1glr/B8svWNsD4jAclpnUi1/9ZW5UPT8LnRBfTbtF9Uoj0OgNs
tsGQYbpuREoHnjbJWTRe0kq1OsOfX44xuf8PuX43B+lpQPW4iRSSz3ZIhdPcDGq1
7pyqQM7gdKhBQ6/tOiwd7Enyt5Hi4V6jhwhUOCYeTNiLD2V3yKL+qA9DzpXUfNNw
LGTjcaMrifibHQIZBZWbPPMmCfMJZ7GO9oR4gWHwkhWqt0yVWAJXAHJBLd5vXC5I
jtRTXRpHO/k6Dtqem8tZCVoDE5MAC7fDZ/0XzoFiXHciP6MenVasVcXo6xJOJc5y
GsrecNftUEhP/ngxA6lMBVkLmmdpiexVisvsavPi64i34OUA6qOuxjgNVBDwg56i
2lOEVvHa3nn0UX7ZZoQ/Nu6Mz7J3Hx/VDlttPuWe42eeJAphyDGubT1M62gW8dVB
D3tJOF7spnK6I3BhVLviou/zs30AIRVBDTU0Orzx78cbInwy6/vyJib2a1olAaHz
v05SzlQRnBWM4jYBe0mA/2ds9AO6VrXGrT/iLlHemj6JYoGBM185TGewA7OJyX3a
HSlSDqaremmi+aS3onx3AKhXykDxTRkMVarePwTzzFs=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc1.png',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc2.png',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/janis91/ocr/master/screenshots/sc3.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'OCR',
			 		 		 		 	'summary' => 'Character recoginition for your images and pdf files.',
			 		 		 		 	'description' => '# Description

Nextcloud OCR (optical character recoginition) processing for images and PDF with tesseract-ocr and OCRmyPDF brings OCR capability to your Nextcloud 10.
The app uses tesseract-ocr, OCRmyPDF and a php internal message queueing service in order to process images (png, jpeg, tiff) and PDF (currently not all PDF-types are supported, for more information see [here](https://github.com/jbarlow83/OCRmyPDF)) asynchronously and save the output file to the same folder in nextcloud, so you are able to search in it.
The source data won&#39;t get lost. Instead:
 - in case of a PDF a copy will be saved with an extra layer of the processed text, so that you are able to search in it.
 - in case of a image the result of the OCR processing will be saved in a .txt file next to the image (same folder).

**One big feature is the asynchronous ocr processing brought by the internal php message queueing system (Semaphore functions), which supports workers to handle tasks asynchronous from the rest of nextcloud.**

## Prerequisites, Requirements and Dependencies
The OCR app has some prerequisites:
 - **[Nextcloud 10](https://nextcloud.com/)** or higher
 - **Linux** server as environment. (tested with Debian 8, Raspbian and Ubuntu 14.04 (Trusty))
 - **[OCRmyPDF](https://github.com/jbarlow83/OCRmyPDF)** &gt;v2.x (tested with v4.1.3 (v4 is recommended))
 - **[tesseract-ocr](https://github.com/tesseract-ocr/tesseract)** &gt;v3.02.02 with corresponding language files (e.g. tesseract-ocr-eng)

For further information see the homepage or the appropriate documentation.',
			 		 		 		 ],
			 		 		 	'de' =>
			 		 		 		 [
			 		 		 		 	'name' => 'OCR',
			 		 		 		 	'summary' => 'Schrifterkennung für Bilder (mit Text) und PDF Dateien.',
			 		 		 		 	'description' => '# Beschreibung

OCR (Automatische Texterkennung) für Bilder (mit Text) und PDF Dateien mithilfe von tesseract-ocr und OCRmyPDF ermöglicht Ihnen automatische Schrifterkennung direkt in Ihrer Nextcloud 10.
Die App nutzt Tesseract-ocr, OCRmyPDF und den internen Message Queueing Service von PHP, um so asynchron (im Hintegrund) Bilder (PNG, JPEG, TIFF) und PDFs (aktuell werden nicht alle Typen unterstützt, näheres [hier](https://github.com/jbarlow83/OCRmyPDF)) zu verarbeiten. Das Ergebnis, welches jetzt durchsuchbar, kopierbar und ähnliches ist, wird anschließend im selben Ordner gespeichert, wie die Ursprungsdatei.
Die Ursuprungsdatei geht dabei nicht verloren:
 - im Falle einer PDF wird eine Kopie mit einer zusätzlichen Textebene gespeichert, damit sie durchsuchbar und kopierbar wird.
 - im Falle eines Bildes wird das Resultat in einer txt-Datei gespeichert.

**Ein großer Vorteil ist, dass das Ausführen und Verarbeiten asynchron im Hintergrund stattfindet. Dies geschieht mithilfe der PHP internernen Unterstützung einer Message Queue (Semaphore Funktionen). Die Aufgaben werden somit getrennt von der Nextcloud in einem eigenen Arbeits-Prozess (Worker) abgearbeitet.**

## Anforderungen und Abhängigkeiten
Für die OCR App müssen folgende Anforderungen erfüllt sein:
 - **[Nextcloud 10](https://nextcloud.com/)** oder höher
 - **Linux** server als Betriebssystem. (getestet mit Debian 8, Raspbian und Ubuntu 14.04 (Trusty))
 - **[OCRmyPDF](https://github.com/jbarlow83/OCRmyPDF)** &gt;v2.x (getestet mit v4.1.3 (v4 empfohlen))
 - **[tesseract-ocr](https://github.com/tesseract-ocr/tesseract)** &gt;v3.02.02 mit den dazugehörigen Übersetzungs- und Sprachdateien (z. B. tesseract-ocr-deu)

Für weiter Informationen besuchen Sie die Homepage oder lesen Sie die zutreffende Dokumentation.',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Janis Koehr',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIID/jCCAuYCAhAKMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTE5MTEzNTAxWhcNMjYxMjI2MTEzNTAxWjAOMQwwCgYD
VQQDDANvY3IwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQDDpXiwec4f
XAT//7YBPSb4z6ZsBJSMfBq0VTL/HagjJnQ7BL+WagzWlS69IStNDqlIlenamYRX
4B40heJIUinzYKjTRbr5UAw6MX29HibZccm/qgrk36o1XTgIsoRhmvSxbXDVIo1k
bDOJN8gc2Gvswa8X+uOe9pfcDgAdqGxOvFnoKW89GnB01pCNT+xakNErGAFqVLsr
2AeademAZnbxJ1cB54tQn2Bygb/7DKKY8EmFfIq6/27n9Jbph1FG9HIlWRT4/M2H
U2pG3cCScWMEBPsW7kpfpnzLk7Q30Oj6k/rEYjJgmNYgg6oVnn0D9uRmhBYBnGyx
Mab1ilsK53lyuzQY0pmU8V5ULqpnNFAK6DVFfofEamDUhBPO+TZXEA5cZmuULRpf
QQXmGpUQSyV6pS9WirMIqXFp9wmQ4vtjMdhu/6CP7cmtYZdq9uOhWEHbQM0mZUkb
8hMjeItPx9XITI7Cge1JUOI8ZIwiB3USnQXcMd3v82l++/VgqHB7s5OaKPhygsWI
M6RCoBcGiuQB5/fEUOg5ACOpGVyJiBda0Mi57AdoxdJmfnr7Bxcf2tAWIJL9Y7T3
E1+V2BMxJOWwvVz26Cq83F41yXK2hJS+SbfQTqNUR8Cfh50CS9POvgRxNrJK9yvI
kKle3ITRtGVM1XU0njWjnsdGg3D3O2mmjQIDAQABMA0GCSqGSIb3DQEBCwUAA4IB
AQAbFddMbgfPI1szT57V1FKZrOrdYqQ7qjewlIQOzshGydbMtqS/9XL5hYocJCMt
Y6w+C/i6iEzO2Jx8D/k4rcZMXoVR6y3ZvO0Ke0gzSRsU+5eYj2FK1VV+cNIQW5Iu
CYYIVa7pVPVHdeQH2Bba680bLV0HMF6b1fI9IwkfdCAinvCYZLjyEXZlmB7YjyA8
HR7qPCNz4uG2Va7mlUHE3UYUYnlv8JFOV3YdbVL0nxhWwIdzSri5sxFIhdlabpzY
yA1z/MCBEyTRo80jxFmL+MpwbsdbUJi7Qxlnd56zb6HHDGrLHXZTh9LXgyVbnhWL
kxomWjIXQh4aMHQL4QF7U4EK
-----END CERTIFICATE-----',
			 		 ],
			 	14 =>
			 		 [
			 		 	'id' => 'spreedme',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'tools',
			 		 		 ],
			 		 	'userDocs' => 'https://github.com/strukturag/nextcloud-spreedme/blob/master/README.md',
			 		 	'adminDocs' => 'https://github.com/strukturag/nextcloud-spreedme/blob/master/README.md',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/strukturag/nextcloud-spreedme/issues',
			 		 	'website' => '',
			 		 	'created' => '2016-09-27T08:43:07.835196Z',
			 		 	'lastModified' => '2016-11-21T16:51:23.703819Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.3.4',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://apps.owncloud.com/CONTENT/content-files/174436-spreedme.tar.gz',
			 		 		 		 	'created' => '2016-11-21T16:51:23.689599Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-21T16:51:23.826509Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'Mhy3hXeGWlIujx1Op39MMRdqHYOo360BCwr4FPWoTNNggH3aS0gWlh48DAfGYK9W
etNiOqIuRyA0NrVlsqR2vDILgFtODJSbKPyHd3PQn3hcGsjogjQ+dkKciLNLinw7
Ohbv6aDdRFLBeRHpX/7wOnWL5W3ko/gyn0Awvi88M9+nC5aARtqncQqPy2SxDGzH
KlOZHSNDnEQCGMhA8hNWWKdVwNUJHod/wmBWpW5QVNSJq5DqrKZcNwpGM2UUJoql
EqUMwDXk5uVH5r5k62Tr9kguDWoUEG1OqQSyeMY24AmA64tq/HSlAdZ+CX32bc4E
Zvm+n8poJBrdSVmWEaa4ZfYaLFdOc6Kcuid1B1Sv9kPhD9WD6T1sicdzjDzcorBK
/MLReCuSb2E8aPTnFWRoAZ4xCUGs1IXzX5fmxI8VdzwR42R6RhGJ/rqMuZRFenZF
bOks45K5gE1da4QpkYOUQa3GVMNPqPiT3CqjmJ8tjxq7bGpb6v+YoCLACjjPpPZL
2Y28qLxwHVaINDFUUxD75WWdrlulRbqHwiSw8jolP9qrpXhDuLAqYam9tRwV5K5R
8uNawnFwWkicBEYkN/WtBTouWzehOPn38tHXov6SyEyD6lkuxUBZrsGQ2ru+t33U
k0kKCbV0GFw43I+3Ji5DiB4TUVNZYVoPG1B7Qve+UfA=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/conference.gif',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/presentation.png',
			 		 		 		 ],
			 		 		 	2 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/import.png',
			 		 		 		 ],
			 		 		 	3 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/strukturag/nextcloud-spreedme/master/screenshots/appstore/users.png',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Spreed.ME',
			 		 		 		 	'summary' => 'Audio-, video- and text chat for your Nextcloud',
			 		 		 		 	'description' => 'Securely communicate with your friends and family using rich audio-, video- and text chat, and much more right from your Nextcloud – in your browser',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'struktur AG',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAzCCAusCAhANMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTI2MTYxNzMzWhcNMjcwMTAyMTYxNzMzWjATMREwDwYD
VQQDEwhzcHJlZWRtZTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAKLx
2dCPBLIgX948BnOdLij0YyI2+FKD6uZOvzxMaoi3rlxNf8MJgraNMzTBWEXtxT5b
7ZISNp89WEXhaQ1dwwCocodd/xow4Ek63m5nUvTZXsm+YSbMgrFbxzsBhYU7KuIE
T/jhKdzYgemzErwwN/gtwkLMfPo3jkgg6c8NPPohYv6k7V4VnsqtJ0JS0kX19FqM
MiNz9XkcncBHy9x0BSxy4+YnwbFcgIx/MtYKlBL8NkPuuJaB/6C1O+IPYhdEdnpX
+RaIue71nSStOYOqT4YDqHAIw7EmqgA1my09mmK+0Pn92GJVEAEN7JGBSQ+F32RI
dB3ivGAOVtUtVvJlepWdbHxj1xqeP+LCjWzHMLQjm0TyH8VqU4Cg/wxwAEFnBATH
aOaWwrggzY2d9KBo1mp0k71NArLbBdlHykFU4bgiSDWrXXMz0fZzLQVwGI0Eqcxc
ouf6t0kvrK8oKjrnso+FjBoT7lHV/H6ny4ufxIEDAJ/FEBV/gMizt5fDZ+DvmMw4
q+a088/lXoiI/vWPoGfOa77H5BQOt3y70Pmwv2uVYp46dtU8oat+ZvyW9iMmgP1h
JSEHj1WGGGlp45d10l4OghwfTB0OSuPUYwWR+lZnV8sukGvQzC9iRV1DGl/rREMC
cQ5ajRAtO5NPnThvN5/Zuh4n8JoDc0GK4jEZsIivAgMBAAEwDQYJKoZIhvcNAQEL
BQADggEBAGHMRbPV0WTI9r1w6m2iJRrMbZtbBb+mQr8NtOoXQwvSXWT1lXMP2N8u
LQ1a8U5UaUjeg7TnoUWTEOqU05HpwA8GZtdWZqPPQpe691kMNvfqF64g0le2kzOL
huMP9kpDGzSD8pEKf1ihxvEWNUBmwewrZTC3+b4gM+MJ3BBCfb5SCzMURLirfFST
axCNzc7veb2M98hS73w5ZE6vO+C/wz0GTsxuK0AoLitApT5naQnjvxSvSsjFPEGD
sUNUEU2Decyp0jxLVnrrpz6Y5UupfBR0V8yAv1t5Od/mCKLc5DxHsDWiKOpsob9U
JN+bdzJil2NNftihD4Dm7Ha7OS3O8W0=
-----END CERTIFICATE-----',
			 		 ],
			 	15 =>
			 		 [
			 		 	'id' => 'nextant',
			 		 	'categories' =>
			 		 		 [
			 		 		 	0 => 'files',
			 		 		 	1 => 'tools',
			 		 		 ],
			 		 	'userDocs' => '',
			 		 	'adminDocs' => 'https://github.com/nextcloud/nextant/wiki',
			 		 	'developerDocs' => '',
			 		 	'issueTracker' => 'https://github.com/nextcloud/nextant/issues',
			 		 	'website' => 'https://github.com/nextcloud/nextant/wiki',
			 		 	'created' => '2016-09-14T14:34:35.977699Z',
			 		 	'lastModified' => '2016-11-22T16:02:57.758477Z',
			 		 	'releases' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'version' => '0.6.6',
			 		 		 		 	'phpExtensions' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'databases' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'shellCommands' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 	'phpVersionSpec' => '*',
			 		 		 		 	'platformVersionSpec' => '>=9.0.0 <12.0.0',
			 		 		 		 	'minIntSize' => 32,
			 		 		 		 	'download' => 'https://github.com/nextcloud/nextant/releases/download/v0.6.6/nextant-0.6.6.tar.gz',
			 		 		 		 	'created' => '2016-11-16T15:11:14.344704Z',
			 		 		 		 	'licenses' =>
			 		 		 		 		 [
			 		 		 		 		 	0 => 'agpl',
			 		 		 		 		 ],
			 		 		 		 	'lastModified' => '2016-11-16T20:39:59.030384Z',
			 		 		 		 	'isNightly' => false,
			 		 		 		 	'rawPhpVersionSpec' => '*',
			 		 		 		 	'rawPlatformVersionSpec' => '>=9 <=11',
			 		 		 		 	'signature' => 'aOZeEeThyZ0V/vXBcn6c+Z0vyCsZcN6nfSJ8oWEea4zXh4g705Si+MFZESqix3M2
OPCnA/U8eASwdRTAEwQJrW5ECmu1THXSIsrzQzc9kFycvyOGzCgAWtuu0ayzZD2/
U5aDWlzpLHC1Czg9QJ5UnfZR0AfChWQ402N1YzGqMShdJv6AHXFrVE+uYnIyxuYI
oPJQBUYbQwthVUjpYwFwSxw50YU17gmx5RZ0Y0OPz3i/EiuEUrxopXtfDVYAuCML
pDw37LOTRQ2JqxSU3teALh8LcrwJbTeOP0n4bTeV+vU3jvtiaEoRrwfVrK41F701
QymGXy1/EFG0kxPGS2dRNPBAXYLZfeoWlROl3D5BWlbsCcXKU1S+22yn0TEdS7x1
Y44x8jRKnBddDE7qkn+QoQYHNNcxOREsFFLmIoyCUpdNOdDX2PvTFUYkIqdnXaJy
oAKv2GkvWPQ0aiiBtA1i4oXuzvHW/M2wOrK7v7DCpNfILrD/sjxpljxcX082nRCd
9P3iPd2hQ6yOM9fG21LVN74b6wggI81BzFf/xJPd4ZqYLjfeG/yqd0zaiMOzMm1W
se+kc/a4iB3BoCNX3E942pBBzew4ya8LkCXdCHUUsuelDf1va1ikTh/G7D84ll9/
2avNqQnUh3hgOnxFCLI/5VrbqxfSTVdO6O/LTuAmwgw=',
			 		 		 		 	'translations' =>
			 		 		 		 		 [
			 		 		 		 		 ],
			 		 		 		 ],
			 		 		 ],
			 		 	'screenshots' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/nextant/master/screenshots/displayResult.jpg',
			 		 		 		 ],
			 		 		 	1 =>
			 		 		 		 [
			 		 		 		 	'url' => 'https://raw.githubusercontent.com/nextcloud/nextant/master/screenshots/admin.jpg',
			 		 		 		 ],
			 		 		 ],
			 		 	'translations' =>
			 		 		 [
			 		 		 	'en' =>
			 		 		 		 [
			 		 		 		 	'name' => 'Nextant',
			 		 		 		 	'summary' => 'Navigate through your cloud using Solr',
			 		 		 		 	'description' => '
	     Navigate through your cloud using Solr


**Nextant** performs fast and concise _Full-Text Search_ within:

- your own files,
- shared files,
- external storage,
- bookmarks


### Recognized file format:
- plain text,
- rtf,
- pdf,
- html,
- openoffice,
- microsoft office,
- image JPEG and TIFF (will requiert Tesseract installed)
- pdf with no text layer (will also requiert Tesseract) _[work in progress]_



## Installation

- [You first need to install a Solr servlet](https://github.com/nextcloud/nextant/wiki)
- Download the .zip from the appstore, unzip and place this app in **nextcloud/apps/** (or clone the github and build the app yourself)
- Enable the app in the app list,
- Edit the settings in the administration page.
- Extract the current files from your cloud using the **./occ nextant:index** commands
- Have a look to this [explanation on how Nextant works](https://github.com/nextcloud/nextant/wiki/Extracting,-Live-Update)
- _(Optional)_ [Installing Tesseract](https://github.com/tesseract-ocr/tesseract/wiki) ([Optical Character Recognition](https://en.wikipedia.org/wiki/Optical_character_recognition) (OCR) Engine) will allow Nextant to extract text from images and pdfs without text layer.


	',
			 		 		 		 ],
			 		 		 ],
			 		 	'isFeatured' => false,
			 		 	'authors' =>
			 		 		 [
			 		 		 	0 =>
			 		 		 		 [
			 		 		 		 	'name' => 'Maxence Lange',
			 		 		 		 	'mail' => '',
			 		 		 		 	'homepage' => '',
			 		 		 		 ],
			 		 		 ],
			 		 	'ratingRecent' => 0.5,
			 		 	'ratingOverall' => 0.5,
			 		 	'ratingNumRecent' => 0,
			 		 	'ratingNumOverall' => 0,
			 		 	'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAFMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYwOTE0MTI1NDQwWhcNMjYxMjIxMTI1NDQwWjASMRAwDgYD
VQQDDAduZXh0YW50MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAsbnQ
+9acjKHfcrUj4yqBpD++GmQ5z2Sp8C8uOz4ZbLyM9bUXEYHo4a4u3CdC49kGUkb3
p5MkEAEzslTWDi1eh5MZgPWpbPgItsDsXY1o55O3jtxNkzSG5/yYcPQcuKtIOm9S
7DY0K+UQt3nK+RrXEZfARMNrzFbEzpE3b7w901Yl5n+m/B8rhW4pqg8uSfx3u04J
wduV1fHwoHUB0Ox5HyWib4Pq1XppNh7xdc2Fg93JxshwuCPJyOOzrFTnxC7s1yzQ
UvaqkjPW5QeQRunQjZ2XtpYH8f8v01W18bNEiHwqtFwuDEyCVx1rvEMgUDVXdPkP
gZrlB5TzGmz0U3HzYvf6205WuzfHxz7kPj502wP51PoZBKpniggKzmuXkx6BpsZC
ZX45VpDHdiATLwRj1t2bMs3C01nzpIWO5ZwFtkepH3Y+mvwX5lDh/XDsqJC2Yo8o
WMmniWNW7dspufYOsBUqqYGP7rkailgVT4oYk6D1j6oFZ5SSpfPF5lsyYedDSM6y
bIGVkSF+sjLK6R9BenBijKceAKsS//WwRYCBPC+JHlsYpXKW12bL+C47Kj2/N6d4
rYryzV6ofVSF6pwIq0oEjoyfBfNpYavf3xrRkSSmIIlPSnMY7DT1xkGD5retxSm6
+WIfkWKRZpv2S6PhMHGLspYc4H5Dj8c48rG5Co8CAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEAOZUwyPaUi+1BOUgQJMWqYRoTVZUyBshTXSC7jSwa97b7qADV9ooA6TYF
zgsPcE41k7jRkUbnjcY45RGtL3vqsgZbx5TjPa5fGMxlqJ6eYBOY61Q6VIHEVm3u
xnPEO9dsMoDBijvo5D7KtE+Ccs907Rq70kCsbrdgPHkyb5tDSnCKogN1LiQrg1EP
my7Z1C7jG9/h57vx0+QBMDCYnTmqLsvMKqo27uHskzAiB7VXLEdSZ2FtMGHkLUQO
0bfhnvTZ2VhMmK83t7ovo71An4ycmsolGD/MA0vNI78VrVISrdI8rRh2WntUnCBU
EJL3BaQAQaASSsvFrcozYxrQG4VzEg==
-----END CERTIFICATE-----',
			 		 ],
			 ],
		'timestamp' => 1234,
		'ncversion' => '11.0.0.2',
		'ETag' => '"myETag"',
	];


	protected function setUp(): void {
		parent::setUp();

		/** @var Factory|\PHPUnit\Framework\MockObject\MockObject $factory */
		$factory = $this->createMock(Factory::class);
		$this->appData = $this->createMock(AppData::class);
		$factory->expects($this->once())
			->method('get')
			->with('appstore')
			->willReturn($this->appData);
		$this->clientService = $this->createMock(IClientService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->compareVersion = new CompareVersion();
		$this->logger = $this->createMock(ILogger::class);

		$this->fetcher = new AppFetcher(
			$factory,
			$this->clientService,
			$this->timeFactory,
			$this->config,
			$this->compareVersion,
			$this->logger
		);
	}

	public function testGetWithFilter() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '11.0.0.2';
				} elseif ($key === 'appstoreurl' && $default === 'https://apps.nextcloud.com/api/v1') {
					return 'https://custom.appsstore.endpoint/api/v1';
				} else {
					return $default;
				}
			});

		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder
			->expects($this->at(0))
			->method('getFile')
			->with('apps.json')
			->willThrowException(new NotFoundException());
		$folder
			->expects($this->at(1))
			->method('newFile')
			->with('apps.json')
			->willReturn($file);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->method('get')
			->with('https://custom.appsstore.endpoint/api/v1/apps.json')
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn(self::$responseJson);
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1234);

		$dataToPut = self::$expectedResponse;
		$dataToPut['data'] = array_values($dataToPut['data']);
		$originalArray = json_decode(self::$responseJson, true);
		$mappedOriginalArray = [];
		foreach ($originalArray as $key => $value) {
			foreach ($value as $releaseKey => $releaseValue) {
				if ($releaseKey === 'id') {
					$mappedOriginalArray[$releaseValue] = $originalArray[$key];
				}
			}
		}
		foreach ($dataToPut['data'] as $key => $appValue) {
			foreach ($appValue as $appKey => $value) {
				if ($appKey === 'certificate' || $appKey === 'description') {
					$dataToPut['data'][$key][$appKey] = $mappedOriginalArray[$appValue['id']][$appKey];
				}
			}
		}

		$file
			->expects($this->at(0))
			->method('putContent');
		$file
			->method('getContent')
			->willReturn(json_encode(self::$expectedResponse));

		$this->assertEquals(self::$expectedResponse['data'], $this->fetcher->get());
	}

	public function testAppstoreDisabled() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'appstoreenabled') {
					return false;
				} elseif ($var === 'version') {
					return '11.0.0.2';
				}
				return $default;
			});
		$this->appData
			->expects($this->never())
			->method('getFolder');

		$this->assertEquals([], $this->fetcher->get());
	}


	public function testNoInternet() {
		$this->config
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) {
				if ($var === 'has_internet_connection') {
					return false;
				} elseif ($var === 'version') {
					return '11.0.0.2';
				}
				return $default;
			});
		$this->appData
			->expects($this->never())
			->method('getFolder');

		$this->assertEquals([], $this->fetcher->get());
	}

	public function testSetVersion() {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) {
				if ($key === 'appstoreenabled') {
					return true;
				} elseif ($key === 'version') {
					return '10.0.7.2';
				} elseif ($key === 'appstoreurl' && $default === 'https://apps.nextcloud.com/api/v1') {
					return 'https://custom.appsstore.endpoint/api/v1';
				} else {
					return $default;
				}
			});

		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder
			->expects($this->at(0))
			->method('getFile')
			->with('future-apps.json')
			->willThrowException(new NotFoundException());
		$folder
			->expects($this->at(1))
			->method('newFile')
			->with('future-apps.json')
			->willReturn($file);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('/')
			->willReturn($folder);
		$client = $this->createMock(IClient::class);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);
		$response = $this->createMock(IResponse::class);
		$client
			->method('get')
			->with('https://custom.appsstore.endpoint/api/v1/apps.json')
			->willReturn($response);
		$response
			->expects($this->once())
			->method('getBody')
			->willReturn(self::$responseJson);
		$response->method('getHeader')
			->with($this->equalTo('ETag'))
			->willReturn('"myETag"');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(1234);

		$dataToPut = self::$expectedResponse;
		$dataToPut['data'] = array_values($dataToPut['data']);
		$originalArray = json_decode(self::$responseJson, true);
		$mappedOriginalArray = [];
		foreach ($originalArray as $key => $value) {
			foreach ($value as $releaseKey => $releaseValue) {
				if ($releaseKey === 'id') {
					$mappedOriginalArray[$releaseValue] = $originalArray[$key];
				}
			}
		}
		foreach ($dataToPut['data'] as $key => $appValue) {
			foreach ($appValue as $appKey => $value) {
				if ($appKey === 'certificate' || $appKey === 'description') {
					$dataToPut['data'][$key][$appKey] = $mappedOriginalArray[$appValue['id']][$appKey];
				}
			}
		}

		$file
			->expects($this->at(0))
			->method('putContent');
		$file
			->method('getContent')
			->willReturn(json_encode(self::$expectedResponse));

		$this->fetcher->setVersion('11.0.0.2', 'future-apps.json', false);
		$this->assertEquals(self::$expectedResponse['data'], $this->fetcher->get());
	}
}
