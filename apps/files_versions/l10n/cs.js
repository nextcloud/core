OC.L10N.register(
    "files_versions",
    {
    "Versions" : "Verze",
    "This application automatically maintains older versions of files that are changed." : "Tato aplikace automaticky uchovává starší verze souborů, které se mění.",
    "This application automatically maintains older versions of files that are changed. When enabled, a hidden versions folder is provisioned in every user’s directory and is used to store old file versions. A user can revert to an older version through the web interface at any time, with the replaced file becoming a version. The app automatically manages the versions folder to ensure the user doesn’t run out of Quota because of versions.\n\t\tIn addition to the expiry of versions, the versions app makes certain never to use more than 50% of the user’s currently available free space. If stored versions exceed this limit, the app will delete the oldest versions first until it meets this limit. More information is available in the Versions documentation." : "Tato aplikace automaticky uchovává starší verze souborů, které se mění. Když je zapnutá, je ve složce každého uživatele vytvořena skrytá složka pro verze a do ní jsou ukládány staré verze souborů. Uživatel se (prostřednictvím webového rozhraní) kdykoli může vrátit ke starším verzím s tím, že sám nahrazovaný nejnovější soubor se tak stane verzí. Aplikace automaticky spravuje složku s verzemi tak, aby bylo zajištěno, že uživatel kvůli starým verzím nevyčerpá přidělenou kvótu.\n\t\tKrom konců platností verzí, aplikace Verze zajišťuje, že nikdy nepoužije více než 50% volného prostoru, který má uživatel k dispozici. Pokud uložené verze přesáhnou tento limit, aplikace smaže ty nejstarší, aby se do tohoto limitu vešla. Podrobnosti nalezenete v dokumentaci k aplikaci Verze.",
    "Failed to revert {file} to revision {timestamp}." : "Nepodařilo se vrátit {file} do stavu k {timestamp}.",
    "_%n byte_::_%n bytes_" : ["%n bajt","%n bajty","%n bajtů","%n bajty"],
    "Restore" : "Obnovit",
    "No other versions available" : "Nejsou k dispozici žádné jiné verze"
},
"nplurals=4; plural=(n == 1 && n % 1 == 0) ? 0 : (n >= 2 && n <= 4 && n % 1 == 0) ? 1: (n % 1 != 0 ) ? 2 : 3;");
