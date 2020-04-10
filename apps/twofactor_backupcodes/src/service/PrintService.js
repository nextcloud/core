export function print(data) {
	const name = OC.theme.name || 'Nextcloud'
	const newTab = window.open('', t('twofactor_backupcodes', '{name} backup codes', { name: name }))
	newTab.document.write('<h1>' + t('twofactor_backupcodes', '{name} backup codes', { name: name }) + '</h1>')
	newTab.document.write('<pre>' + data + '</pre>')
	newTab.print()
	newTab.close()
}
