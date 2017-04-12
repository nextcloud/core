OC.L10N.register(
    "encryption",
    {
    "Missing recovery key password" : "Chýba kľúč pre obnovu hesla",
    "Please repeat the recovery key password" : "Prosím zopakujte heslo kľúča pre obnovu",
    "Repeated recovery key password does not match the provided recovery key password" : "Zopakované heslo kľúča pre obnovenie nesúhlasí zo zadaným heslom",
    "Recovery key successfully enabled" : "Záchranný kľúč bol úspešne povolený",
    "Could not enable recovery key. Please check your recovery key password!" : "Nepodarilo sa povoliť záchranný kľúč. Skontrolujte prosím Vaše heslo záchranného kľúča!",
    "Recovery key successfully disabled" : "Záchranný kľúč bol úspešne zakázaný",
    "Could not disable recovery key. Please check your recovery key password!" : "Nepodarilo sa zakázať záchranný kľúč. Skontrolujte prosím Vaše heslo záchranného kľúča!",
    "Missing parameters" : "Chýbajúce parametre",
    "Please provide the old recovery password" : "Zadajte prosím staré heslo pre obnovenie",
    "Please provide a new recovery password" : "Zadajte prosím nové heslo pre obnovenie",
    "Please repeat the new recovery password" : "Zopakujte prosím nové heslo pre obnovenie",
    "Password successfully changed." : "Heslo úspešne zmenené.",
    "Could not change the password. Maybe the old password was not correct." : "Nemožno zmeniť heslo. Pravdepodobne nebolo staré heslo zadané správne.",
    "Recovery Key disabled" : "Obnovovací kľúč je zakázaný",
    "Recovery Key enabled" : "Obnovovací kľúč je povolený",
    "Could not enable the recovery key, please try again or contact your administrator" : "Nepodarilo sa zapnúť záchranný kľúč. Prosím, skúste to znova alebo kontaktujte svojho správcu",
    "Could not update the private key password." : "Nemožno aktualizovať heslo súkromného kľúča.",
    "The old password was not correct, please try again." : "Staré heslo nebolo zadané správne, prosím skúste to ešte raz.",
    "The current log-in password was not correct, please try again." : "Toto heslo nebolo správne, prosím skúste to ešte raz.",
    "Private key password successfully updated." : "Heslo súkromného kľúča je úspešne aktualizované.",
    "You need to migrate your encryption keys from the old encryption (Nextcloud <= 8.0) to the new one. Please run 'occ encryption:migrate' or contact your administrator" : "Musíte migrovať vaše šifrovacie kľúče zo starého šifrovania (Nextcloud <= 8,0) na nové. Spustite „occ encryption:migrate“ alebo sa obráťte na správcu",
    "Invalid private key for encryption app. Please update your private key password in your personal settings to recover access to your encrypted files." : "Neplatný súkromný kľúč pre šifrovanie. Aktualizujte prosím heslo vášho súkromného kľúča v osobných nastaveniach pre obnovenie prístupu k vaším šifrovaným súborom.",
    "Encryption app is enabled and ready" : "Aplikácia pre šifrovanie je povolená a pripravená",
    "Bad Signature" : "Zlý podpis",
    "Missing Signature" : "Chýbajúci podpis",
    "one-time password for server-side-encryption" : "jednorazové heslo na šifrovanie na strane servera",
    "Can not decrypt this file, probably this is a shared file. Please ask the file owner to reshare the file with you." : "Tento súbor nie je možné rozšifrovať, môže ísť o súbor sprístupnený iným používateľom. Požiadajte majiteľa súboru, aby vám ho sprístupnil ešte raz.",
    "Can not read this file, probably this is a shared file. Please ask the file owner to reshare the file with you." : "Tento súbor nie je možné prečítať, môže ísť o súbor sprístupnený iným používateľom. Požiadajte majiteľa súboru, aby vám ho sprístupnil ešte raz.",
    "Hey there,\n\nthe admin enabled server-side-encryption. Your files were encrypted using the password '%s'.\n\nPlease login to the web interface, go to the section 'basic encryption module' of your personal settings and update your encryption password by entering this password into the 'old log-in password' field and your current login-password.\n\n" : "Dobrý deň,\n\nAdministrátor povolil šifrovanie na strane servera. Vaše súbory boli zašifrované pomocou hesla '%s'.\n\nPrihláste sa prosím cez webový prehliadač, choďte do sekcie základného šifrovacieho modulu v osobných nastaveniach a zadajte horeuvedené heslo do políčka 'staré prihlasovacie heslo' a vaše súčasné prihlasovacie heslo.\n\n",
    "The share will expire on %s." : "Sprístupnenie vyprší %s.",
    "Cheers!" : "Pekný deň!",
    "Hey there,<br><br>the admin enabled server-side-encryption. Your files were encrypted using the password <strong>%s</strong>.<br><br>Please login to the web interface, go to the section \"basic encryption module\" of your personal settings and update your encryption password by entering this password into the \"old log-in password\" field and your current login-password.<br><br>" : "Dobrý deň,<br><br>Administrátor povolil šifrovanie na strane servera. Vaše súbory boli zašifrované pomocou hesla <strong>%s</strong>.<br><br>Prihláste sa prosím cez webový prehliadač, choďte do sekcie základného šifrovacieho modulu v osobných nastaveniach a zadajte horeuvedené heslo do políčka 'staré prihlasovacie heslo' a vaše súčasné prihlasovacie heslo.<br><br>",
    "Default encryption module" : "Predvolený šifrovací modul",
    "Encryption app is enabled but your keys are not initialized, please log-out and log-in again" : "Aplikácia pre šifrovanie je povolená, ale vaše kľúče nie sú inicializované. Odhláste sa a znovu sa prihláste.",
    "Encrypt the home storage" : "Šifrovať domáce úložisko",
    "Enabling this option encrypts all files stored on the main storage, otherwise only files on external storage will be encrypted" : "Zapnutím tejto voľby zašifrujete všetky súbory v hlavnom úložisku, v opačnom prípade zašifrujete iba súbory na externom úložisku.",
    "Enable recovery key" : "Povoliť obnovovací kľúč",
    "Disable recovery key" : "Zakázať obnovovací kľúč",
    "The recovery key is an extra encryption key that is used to encrypt files. It allows recovery of a user's files if the user forgets his or her password." : "Záchranný kľúč je ďalší šifrovací kľúč, ktorý sa používa na šifrovanie súborov. Umožňuje záchranu súborov používateľa ak zabudne svoje heslo.",
    "Recovery key password" : "Heslo obnovovacieho kľúča",
    "Repeat recovery key password" : "Zopakovať heslo k záchrannému kľúču",
    "Change recovery key password:" : "Zmeniť heslo obnovovacieho kľúča:",
    "Old recovery key password" : "Staré heslo k záchrannému kľúču",
    "New recovery key password" : "Nové heslo obnovovacieho kľúča",
    "Repeat new recovery key password" : "Zopakujte nové heslo obnovovacieho kľúča",
    "Change Password" : "Zmeniť heslo",
    "Basic encryption module" : "Základný šifrovací modul",
    "Your private key password no longer matches your log-in password." : "Heslo vášho súkromného kľúča sa nezhoduje v vašim prihlasovacím heslom.",
    "Set your old private key password to your current log-in password:" : "Zmeňte si vaše staré heslo súkromného kľúča na rovnaké, aké je vaše aktuálne prihlasovacie heslo:",
    " If you don't remember your old password you can ask your administrator to recover your files." : "Ak si nepamätáte svoje staré heslo, môžete požiadať administrátora o obnovenie svojich súborov.",
    "Old log-in password" : "Staré prihlasovacie heslo",
    "Current log-in password" : "Súčasné prihlasovacie heslo",
    "Update Private Key Password" : "Aktualizovať heslo súkromného kľúča",
    "Enable password recovery:" : "Povoliť obnovu hesla:",
    "Enabling this option will allow you to reobtain access to your encrypted files in case of password loss" : "Povolenie Vám umožní znovu získať prístup k Vašim zašifrovaným súborom, ak stratíte heslo",
    "Enabled" : "Povolené",
    "Disabled" : "Zakázané",
    "Encryption App is enabled but your keys are not initialized, please log-out and log-in again" : "Aplikácia na šifrovanie je zapnutá, ale vaše kľúče nie sú inicializované. Odhláste sa a znovu sa prihláste."
},
"nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;");
