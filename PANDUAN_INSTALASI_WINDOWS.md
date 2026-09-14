# 📘 SOP & PANDUAN TEKNIS INSTALASI SISTEM POS LISTRIK (WINDOWS 10 / 11)

Dokumen ini adalah panduan resmi untuk teknisi dan implementator dalam melakukan instalasi, konfigurasi web server Laragon, penanganan kendala umum, hingga otomasi backup database untuk sistem **POS Listrik & Elektronik**.

---

## 📑 DAFTAR ISI
1. [Bab 1: Download & Persiapan Software Pendukung](#bab-1-download--persiapan-software-pendukung)
2. [Bab 2: Setup Lingkungan Web Server Laragon](#bab-2-setup-lingkungan-web-server-laragon)
3. [Bab 3: Deployment Source Code & Database](#bab-3-deployment-source-code--database)
4. [Bab 4: Akses Sistem & Penggunaan Multi-Device (LAN/WiFi)](#bab-4-akses-sistem--penggunaan-multi-device-lanwifi)
5. [Bab 5: Solusi Troubleshooting Kendala Umum](#bab-5-solusi-troubleshooting-kendala-umum)
6. [Bab 6: Otomatisasi Backup Harian (ZIP + Task Scheduler)](#bab-6-otomatisasi-backup-harian-zip--task-scheduler)

---

## BAB 1: DOWNLOAD & PERSIAPAN SOFTWARE PENDUKUNG

Siapkan 5 software wajib berikut sebelum melakukan instalasi:

| No | Software | Versi Wajib | Link Download Resmi & Keterangan |
| :--- | :--- | :--- | :--- |
| 1 | **Laragon Full** | v6.0+ (64-bit) | [laragon.org/download](https://laragon.org/download/)<br/>Pilih installer *Laragon Full (64-bit)*. |
| 2 | **Microsoft Visual C++** | 2015–2022 (x64) | [Download vc_redist.x64.exe](https://aka.ms/vs/17/release/vc_redist.x64.exe)<br/>Wajib diinstall untuk mencegah error *VCRUNTIME140.dll*. |
| 3 | **PHP 8.4 Windows** | VS16 x64 Thread Safe | [windows.php.net/download](https://windows.php.net/download/)<br/>Wajib pilih versi **VS16 x64 Thread Safe (Zip)**. |
| 4 | **Node.js LTS** | v22.x LTS (x64) | [nodejs.org/en/download](https://nodejs.org/en/download/)<br/>Pilih *Windows Installer (.msi) 64-bit*. |
| 5 | **Git for Windows** | v2.40+ (x64) | [git-scm.com/download/win](https://git-scm.com/download/win)<br/>Untuk clone dan update source code. |

---

## BAB 2: SETUP LINGKUNGAN WEB SERVER LARAGON

### 2.1 Ekstrak PHP 8.4 ke Folder Laragon
1. Ekstrak file zip PHP 8.4 ke folder:
   ```
   C:\laragon\bin\php\php-8.4.x-Win32-vs16-x64
   ```
   > ⚠️ **Penting:** Pastikan file `php.exe` berada langsung di dalam folder tersebut (bukan di dalam sub-folder bertingkat).

### 2.2 Mengatasi Bentrok DLL Apache (Wajib)
1. Copy file **`nghttp2.dll`** dari:
   ```
   C:\laragon\bin\php\php-8.4.x-Win32-vs16-x64\nghttp2.dll
   ```
2. Paste dan Replace (Ganti) ke folder bin Apache:
   ```
   C:\laragon\bin\apache\httpd-2.4.x-win64-VS16\bin\
   ```

### 2.3 Membersihkan Node.js Lama di Laragon
1. Buka folder `C:\laragon\bin\nodejs\`.
2. **Hapus atau Rename** folder `node-v18.x...` yang ada di dalamnya agar terminal Laragon menggunakan Node.js v22 yang baru diinstall.

### 2.4 Konfigurasi Laragon Preferences
1. Buka aplikasi **Laragon** ➜ Klik ikon **Gerigi (Settings)** di kanan atas.
2. Centang: **[✓] Run Laragon when Windows starts**
3. Centang: **[✓] Start All automatically**
4. Pada **Hostname format**, ubah menjadi: **`{name}.local`**
5. Klik kanan Laragon ➜ **PHP ➜ Version** ➜ Pilih **`php-8.4.x...`**
6. Klik tombol **Stop** lalu klik **Start All**.

---

## BAB 3: DEPLOYMENT SOURCE CODE & DATABASE

Buka **Terminal Laragon** (klik tombol Terminal di Laragon):

### 3.1 Clone Project
```bash
cd C:\laragon\www
git clone https://github.com/ferardian/pos-listrik.git
cd pos-listrik
```

### 3.2 Pembuatan Database MySQL
1. Klik tombol **Database** di Laragon (membuka HeidiSQL).
2. Buat database baru bernama: **`pos_listrik`** (Collation: `utf8mb4_unicode_ci`).

### 3.3 Konfigurasi File `.env`
1. Copy file template konfigurasi:
   ```bash
   copy .env.example .env
   ```
2. Buka file `.env` dengan Notepad dan sesuaikan baris berikut:
   ```env
   APP_NAME="POS Listrik Trisna Jaya"
   APP_ENV=local
   APP_DEBUG=false
   APP_URL=http://pos-listrik.local

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pos_listrik
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 3.4 Install Dependensi & Generate Key
```bash
composer install --optimize-autoloader --no-dev
php artisan key:generate
php artisan storage:link
```

### 3.5 Setup Database
- **Jika ada file Dump SQL lama:** Buka HeidiSQL ➜ Database `pos_listrik` ➜ File ➜ Load SQL file ➜ Pilih file `.sql` ➜ Tekan F9 (Execute). Lalu jalankan:
  ```bash
  php artisan migrate
  ```
- **Jika database baru/fresh:**
  ```bash
  php artisan migrate --seed
  ```

### 3.6 Kompilasi Frontend
```bash
npm install
npm run build
```

---

## BAB 4: AKSES SISTEM & PENGGUNAAN MULTI-DEVICE

### 4.1 Akses di Komputer Server Kasir
Buka browser (Chrome / Edge) dan buka:
👉 **`http://pos-listrik.local`**  
*(Tanpa perlu menjalankan `php artisan serve` karena sudah ditangani Apache Laragon)*.

### 4.2 Membuat Shortcut Desktop Kasir (PWA App Mode)
1. Buka `http://pos-listrik.local` di Chrome / Edge.
2. Klik titik tiga kanan atas ➜ **Save and share** ➜ **Install page as app** (atau *Create Shortcut*).
3. Centang **"Open as window"** ➜ Klik **Install**.
4. Ikon kasir akan muncul di Desktop dan bisa dibuka seperti aplikasi native Windows!

### 4.3 Akses dari HP Sales / Tablet Kasir di Jaringan WiFi Toko
1. Cek IP Komputer Server Kasir (`ipconfig` di CMD, misal `192.168.1.100`).
2. Di PC Server, jalankan:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Di HP Sales yang satu WiFi toko, buka browser:
   - **Mode Kasir POS:** `http://192.168.1.100:8000/pos`
   - **Mode Sales Lapangan:** `http://192.168.1.100:8000/mobile-sales`

---

## BAB 5: SOLUSI TROUBLESHOOTING KENDALA UMUM

| Pesan Error | Penyebab & Solusi Penanganan |
| :--- | :--- |
| **`httpd.exe Entry Point Not Found (nghttp2.dll)`** | Bentrok file DLL antara Apache dan PHP baru.<br/>**Solusi:** Copy `nghttp2.dll` dari folder PHP 8.4 ke `apache/bin/` lalu restart Laragon. |
| **`VCRUNTIME140.dll is not compatible`** | Windows belum terpasang runtime C++ 2022.<br/>**Solusi:** Install file resmi `vc_redist.x64.exe` dari Microsoft lalu restart Laragon. |
| **`Parse error: unexpected token '{' in Request.php`** | Terminal masih membaca PHP 8.2 sedangkan Symfony butuh PHP 8.4.<br/>**Solusi:** Pilih PHP 8.4 di Laragon, tutup terminal dan buka terminal baru. |
| **`SyntaxError: node:util styleText`** | Node.js aktif masih v18 lama.<br/>**Solusi:** Hapus folder `node-v18` di `C:\laragon\bin\nodejs\` agar terminal memakai Node.js v22 LTS. |
| **`ERR_CONNECTION_CLOSED / Unsupported SSL`** | Browser mencoba membuka HTTPS pada server HTTP lokal.<br/>**Solusi:** Pastikan di `.env` tertulis `APP_URL=http://pos-listrik.local` lalu jalankan `php artisan config:clear`. |

---

## BAB 6: OTOMATISASI BACKUP HARIAN (ZIP + TASK SCHEDULER)

### 6.1 Script Batch (`C:\laragon\backup_pos.bat`)
Buat file `C:\laragon\backup_pos.bat` dan isi dengan kode berikut:

```bat
@echo off
setlocal

:: 1. Format Tanggal & Jam (PowerShell Windows 11)
for /f "tokens=*" %%a in ('powershell -Command "Get-Date -Format 'yyyy-MM-dd_HH-mm'"') do set TIMESTAMP=%%a
for /f "tokens=*" %%a in ('powershell -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"') do set LOG_TIME=%%a

:: 2. Deteksi Lokasi mysqldump Laragon
for /d %%i in (C:\laragon\bin\mysql\*) do set MYSQLDUMP=%%i\bin\mysqldump.exe

:: 3. Konfigurasi Folder & Database
set BACKUP_DIR=D:\BACKUP_POS
set DB_NAME=pos_listrik
set DB_USER=root
set TEMP_SQL=%BACKUP_DIR%\temp_%DB_NAME%_%TIMESTAMP%.sql
set FINAL_ZIP=%BACKUP_DIR%\backup_%DB_NAME%_%TIMESTAMP%.zip
set LOG_FILE=%BACKUP_DIR%\backup_log.txt

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: 4. Eksekusi Dump Database
"%MYSQLDUMP%" -u %DB_USER% %DB_NAME% > "%TEMP_SQL%"

:: 5. Kompresi ke ZIP (Hemat Storage s/d 90%)
powershell -Command "Compress-Archive -Path '%TEMP_SQL%' -DestinationPath '%FINAL_ZIP%' -CompressionLevel Optimal -Force"

:: 6. Bersihkan File SQL Mentah
if exist "%TEMP_SQL%" del "%TEMP_SQL%"

:: 7. Catat ke File Log Riwayat
echo [%LOG_TIME%] SUKSES: Backup tersimpan di backup_%DB_NAME%_%TIMESTAMP%.zip >> "%LOG_FILE%"

:: 8. Munculkan Notifikasi Balon Windows di Pojok Kanan Bawah
powershell -Command "[void][reflection.assembly]::loadwithpartialname('System.Windows.Forms'); $notify = new-object system.windows.forms.notifyicon; $notify.icon = [System.Drawing.SystemIcons]::Information; $notify.visible = $true; $notify.showballoontip(5000, 'POS Listrik - Auto Backup', 'Database berhasil di-backup dan dikompres ke ZIP.', [System.Windows.Forms.ToolTipIcon]::Info); Start-Sleep -Seconds 2; $notify.dispose()" > $null 2>&1

:: 9. Hapus file backup yang umurnya lebih dari 30 hari
forfiles /p "%BACKUP_DIR%" /s /m *.zip /d -30 /c "cmd /c del @path" 2>nul
```

### 6.2 Penjadwalan di Windows Task Scheduler
1. Buka **Task Scheduler** di Windows.
2. Klik **Create Basic Task...** ➜ Beri nama **`Auto Backup POS Listrik`**.
3. Trigger: Pilih **Daily (Jam 22:00:00)** malam.
4. Action: Pilih **Start a program** ➜ Browse file `C:\laragon\backup_pos.bat`.
5. Start in (optional): Ketik `C:\laragon`.
6. Di tab **Settings** Properties Task, centang: **`Run task as soon as possible after a scheduled start is missed`**.

### 6.3 Integrasi Cloud Sync (Google Drive Desktop)
Arahkan folder backup di script ke Google Drive:
```bat
set BACKUP_DIR=G:\My Drive\BACKUP_POS
```
File ZIP database otomatis ter-upload ke cloud Google Drive setiap malam!
