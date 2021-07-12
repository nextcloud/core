## Spin-up a quick nextcloud development instance 🚀

### 1) Install the required packages ⛑

* [php](https://www.php.net/manual/en/install.php)
* php-gd
* php-sqlite

---

### 2) Edit php.ini file to enable the installed extensions 🧰

```
sudo nano /etc/php/php.ini
```

Comment out `extension=gd` and `extension=pdo_sqlite`

---

### 3) Get nextcloud ☁️

Clone the server repository:

```
git clone https://github.com/nextcloud/server.git
```

Update third party modules:

```
cd server  
```

```
git submodule update --init
```

---

## 4) Start server 🌐

From within the server folder, execute:

```
 php -S 0.0.0.0:80
```

---

## 5) Type 'localhost' in your browser and enjoy contributing! 🥂 🎉

---

### Install apps 👾

\- If you want to install apps, you can directly go into your apps management;

\- **but** if you want to work on apps development, just clone their git repository into the apps folder in the server folder;

### Note: this is not an ideal permanent development environment. For a more stable and reliable platform, [please follow these instructions](https://docs.nextcloud.com/server/latest/developer_manual/general/devenv.html))