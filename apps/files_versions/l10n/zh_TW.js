OC.L10N.register(
    "files_versions",
    {
    "Versions" : "版本",
    "This application automatically maintains older versions of files that are changed." : "本應用程式會自動保存修改前舊版本的檔案",
    "This application automatically maintains older versions of files that are changed. When enabled, a hidden versions folder is provisioned in every user’s directory and is used to store old file versions. A user can revert to an older version through the web interface at any time, with the replaced file becoming a version. The app automatically manages the versions folder to ensure the user doesn’t run out of Quota because of versions.\n\t\tIn addition to the expiry of versions, the versions app makes certain never to use more than 50% of the user’s currently available free space. If stored versions exceed this limit, the app will delete the oldest versions first until it meets this limit. More information is available in the Versions documentation." : "此應用程式會自動維護已變更過檔案的較舊版本。啟用後，每個使用者目錄中都會有一個隱藏的版本資料夾，此資料夾用於儲存較舊版本的檔案。使用者可以在任何時候透過網路介面還原到較舊的版本，取代目前版本成為新的版本。應用程式會自動管理版本資料夾以確保使用者不會因為版本而用完配額。\n\t\t除了版本有效期限以外，版本應用程式還會確保使用者永遠不會使用超過使用者目前可用空間的 50%。如果儲存的版本超出此限制，則應用程式會先刪除最舊的版本，直到低於此限制。更多資訊請見「版本」文件。",
    "Failed to revert {file} to revision {timestamp}." : "無法還原檔案 {file} 至版本 {timestamp}",
    "_%n byte_::_%n bytes_" : ["%n bytes"],
    "Restore" : "復原",
    "No other versions available" : "沒有其他版本了"
},
"nplurals=1; plural=0;");
