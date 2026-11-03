# PANDUAN DEPLOY REKAPMOBILE KE GITHUB, VERCEL & GOOGLE SPREADSHEET

Panduan ini menjelaskan langkah demi langkah cara menghubungkan database **`rekapmobile`** ke **Google Spreadsheet** (via Google Apps Script) dan men-deploy aplikasi ke **GitHub & Vercel** secara gratis agar dapat diakses kapan saja melalui URL publik (contoh: `https://rekapmobile.vercel.app`).

---

## 📑 1. Setup Database di Google Spreadsheet

1. Buka [Google Sheets](https://sheets.new) di browser Anda dan buat Spreadsheet baru.
2. Beri nama file, misalnya: **`Database Rekap Mobile`**.
3. Klik menu **Ekstensi (Extensions) > Apps Script**.
4. Hapus seluruh baris kode default yang ada di editor.
5. Salin dan tempelkan seluruh isi file [google_apps_script/Code.gs](google_apps_script/Code.gs).
6. Di bagian atas editor, pilih fungsi **`setupDatabase`** dari menu dropdown, lalu klik tombol **Run (Jalankan)**.
   - *Catatan: Google akan meminta verifikasi izin akun pertama kali. Klik "Advanced" > "Go to ... (unsafe)" > "Allow".*
   - Skrip akan otomatis membuat sheet `rekap_harian`, `rekap_absensi`, dan `sudah_dicetak` lengkap dengan header yang terkunci rapi.
7. Klik tombol biru **Deploy (Terapkan) > New deployment (Deployment baru)**:
   - Pilih jenis: **Web app (Aplikasi Web)**.
   - **Description**: `RekapMobile API v1`
   - **Execute as**: `Me (emailanda@gmail.com)`
   - **Who has access**: `Anyone (Siapa saja)`
8. Klik **Deploy**, lalu salin **Web App URL** yang muncul (format: `https://script.google.com/macros/s/.../exec`).

---

## 🚀 2. Migrasikan Data MySQL Lokal ke Google Sheets

1. Buka aplikasi lokal di browser: `http://localhost/rekapmobile/integrasi_sheets.php`.
2. Tempelkan URL Google Apps Script yang Anda salin tadi ke kolom **URL Web App**, lalu klik **Simpan URL**.
3. Status koneksi akan langsung berubah menjadi hijau: **Terhubung ke Google Spreadsheet!**.
4. Klik tombol hijau: **"Ekspor Seluruh Data ke Google Sheets"**.
   - Seluruh 687 data harian, 37 absensi, dan 4.727 data sudah dicetak dari MySQL lokal akan otomatis tersalin ke Google Sheets Anda dalam hitungan detik.

---

## 🐙 3. Simpan dan Push ke GitHub

1. Buka terminal (PowerShell atau Git Bash) di folder `C:\xampp\htdocs\rekapmobile`:
   ```bash
   cd C:\xampp\htdocs\rekapmobile
   git init
   git add .
   git commit -m "feat: setup rekapmobile with vercel and google sheets"
   ```
2. Buat repository baru di [GitHub](https://github.com/new) dengan nama **`rekapmobile`** (pilih Public atau Private).
3. Hubungkan remote repository dan lakukan push:
   ```bash
   git branch -M main
   git remote add origin https://github.com/USERNAME/rekapmobile.git
   git push -u origin main
   ```

---

## ⚡ 4. Deploy ke Vercel (Gratis)

1. Buka [Vercel](https://vercel.com) dan login menggunakan akun GitHub Anda.
2. Klik **Add New... > Project**.
3. Pilih repository **`rekapmobile`** yang baru saja Anda push, lalu klik **Import**.
4. Pada bagian **Environment Variables**, tambahkan 2 variabel berikut:
   - **`GAS_URL`**: `https://script.google.com/macros/s/.../exec` *(URL Web App Google Apps Script Anda)*
   - **`DB_MODE`**: `sheets`
5. Klik **Deploy**.
6. Selesai! Dalam 1–2 menit, website Anda sudah aktif dan live dengan domain gratis seperti:
   `https://rekapmobile.vercel.app`

---

## 💡 Arsitektur & Keuntungan
- **Gratis 100% Selamanya**: Vercel & Google Sheets tidak memerlukan biaya server atau database hosting.
- **Tersinkronisasi Realtime**: Setiap data yang Anda simpan akan tersimpan di Google Spreadsheet yang dapat dilihat atau dibagikan dengan tim.
- **Super Cepat**: Aplikasi menggunakan in-memory SQLite cache otomatis di Vercel, sehingga semua 8 jenis laporan, filter periode, dan sorting A-Z tetap berjalan kilat (sub-detik).

