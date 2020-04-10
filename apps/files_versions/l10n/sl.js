OC.L10N.register(
    "files_versions",
    {
    "Versions" : "Različice",
    "This application automatically maintains older versions of files that are changed." : "Program samodejno ustvarja zaporedne različice sprememb datotek.",
    "This application automatically maintains older versions of files that are changed. When enabled, a hidden versions folder is provisioned in every user’s directory and is used to store old file versions. A user can revert to an older version through the web interface at any time, with the replaced file becoming a version. The app automatically manages the versions folder to ensure the user doesn’t run out of Quota because of versions.\n\t\tIn addition to the expiry of versions, the versions app makes certain never to use more than 50% of the user’s currently available free space. If stored versions exceed this limit, the app will delete the oldest versions first until it meets this limit. More information is available in the Versions documentation." : "Program samodejno ustvarja različice spremenjenih datotek v skriti uporabnikovi mapi. Za uporabnika to pomeni, da lahko prek spletnega vmesnika kadarkoli obnovi starejšo različico datoteke, zamenjana datoteka pa postane ena od različic. Upravljanje vključuje tudi nadzor nad omejitvijo prostora, pri čemer se najprej brišejo najstarejše različice.\nNastavitve določajo tudi omejitev, da shranjene različice ne zasedejo več kot 50 % razpoložljivega prostora oziroma količinske omejitve. Več podrobnosti je na voljo v dokumentaciji programa.",
    "Failed to revert {file} to revision {timestamp}." : "Povrnitev datoteke {file} na različico {timestamp} je spodletelo.",
    "_%n byte_::_%n bytes_" : ["%n bajt","%n bajta","%n bajti","%n bajtov"],
    "Restore" : "Obnovi",
    "No other versions available" : "Na voljo ni nobene druge različice datoteke"
},
"nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3);");
