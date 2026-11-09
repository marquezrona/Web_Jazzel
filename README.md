<div align="center">
  <img src="public/assets/img/jazzel-monogram.png" alt="Logo Toko Jazzel" width="140">

  # Sistem Kasir Toko Jazzel

  Aplikasi kasir berbasis web untuk mengelola produk, transaksi penjualan, dan laporan toko.
</div>

---

## Tentang Proyek

Sistem Kasir Toko Jazzel membantu admin dan kasir menjalankan kegiatan toko melalui satu aplikasi. Admin dapat mengelola barang dan akun kasir, sedangkan kasir dapat membuat transaksi, menahan transaksi untuk dilanjutkan, serta melihat riwayat dan laporan penjualannya.

## Fitur

- **Dashboard admin** — ringkasan persediaan, barang aktif, stok rendah, dan grafik penjualan.
- **Kelola barang** — tambah, lihat, ubah, hapus, cari barang, dan atur status aktif.
- **Manajemen kasir** — admin dapat membuat akun kasir.
- **Transaksi kasir** — pencarian barang, pengelolaan keranjang, pembayaran tunai, QRIS simulasi, transfer, dan kartu NFC.
- **Member NFC** — daftarkan pelanggan sebagai member dengan kartu NFC, isi saldo melalui metode pembayaran demo, dan gunakan kartu untuk berbelanja sesuai saldo.
- **Informasi member admin** — admin dapat melihat daftar member, UID kartu, saldo terkini, dan aktivitas kartu terakhir.
- **Transaksi tertahan** — simpan transaksi sementara di browser dan lanjutkan pembayarannya nanti.
- **Riwayat transaksi** — pencarian dan filter riwayat transaksi.
- **Laporan penjualan** — ringkasan dan filter laporan berdasarkan hari, minggu, dan bulan.
- **Pengaturan toko** — ubah identitas toko dan logo.
- **Cetak struk** — mendukung printer thermal ESC/POS pada Windows.

> Pembayaran QRIS, transfer, dan isi saldo pada fitur NFC merupakan **simulasi**, bukan pemrosesan pembayaran sungguhan. Daftarkan pelanggan sebagai member dan berikan UID kartu melalui menu **Member NFC**, lalu gunakan formulir isi saldo pada halaman tersebut. Member dapat memilih NFC atau metode lain; pilih nama member pada transaksi agar penjualan non-NFC tetap tercatat ke member yang benar. Reader NFC USB seperti pada contoh dapat digunakan jika beroperasi sebagai keyboard dan kolom UID sedang aktif; UID juga dapat dimasukkan manual. Transaksi tertahan disimpan di browser yang digunakan dan tidak otomatis tersedia di perangkat lain.

## Teknologi

- PHP 8.2+
- Laravel 12
- MySQL
- Node.js dan npm
- Vite
- Tailwind CSS
- Alpine.js
- [`mike42/escpos-php`](https://github.com/mike42/escpos-php) untuk cetak struk

## Persyaratan

Pastikan perangkat pengembangan sudah memiliki:

- PHP 8.2 atau lebih baru beserta ekstensi yang dibutuhkan Laravel.
- Ekstensi PHP GD untuk pemrosesan gambar produk.
- Composer.
- MySQL.
- Node.js dan npm.

## Instalasi dan Menjalankan Aplikasi

Contoh berikut menggunakan PowerShell di Windows.

1. **Clone repository dan masuk ke folder proyek**

   ```powershell
   git clone https://github.com/marquezrona/projek_web_kasir-.git
   cd projek_web_kasir-
   ```

2. **Pasang dependency PHP dan siapkan konfigurasi**

   ```powershell
   composer install
   Copy-Item .env.example .env
   ```

3. **Buat application key, jalankan migrasi dan data awal, lalu siapkan penyimpanan file publik**

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

   Seeder membuat akun contoh untuk pengembangan lokal. Ganti kata sandi bawaan dan jangan gunakan akun contoh tersebut untuk deployment.

4. **Pasang dependency frontend dan build aset**

   ```powershell
   npm install
   npm run build
   ```

5. **Jalankan server lokal**

   ```powershell
   php artisan serve
   ```

   Buka alamat yang ditampilkan oleh Artisan, biasanya [http://127.0.0.1:8000](http://127.0.0.1:8000).

Untuk menjalankan Vite dalam mode pengembangan, gunakan terminal terpisah:

```powershell
npm run dev
```

## Deployment Hosting

Langkah berikut berlaku umum untuk server Linux/VPS. Pada shared hosting, gunakan Terminal/cPanel untuk menjalankan perintah yang diizinkan penyedia hosting. Pastikan PHP 8.2+, seluruh ekstensi PHP yang diwajibkan Composer (termasuk GD), Composer, MySQL/MariaDB, dan akses tulis ke folder `storage` serta `bootstrap/cache` tersedia.

1. Arahkan document root domain ke folder `public` aplikasi. Jangan arahkan domain ke root repository.
2. Pasang dependency PHP production:

   ```sh
   composer install --no-dev --optimize-autoloader
   ```

3. Buat `.env` dari `.env.example`, lalu isi konfigurasi server. Minimal:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://domain-anda
   AUTH_REGISTRATION_ENABLED=false
   DB_CONNECTION=mysql
   DB_HOST=host-database-dari-provider
   DB_PORT=3306
   DB_DATABASE=nama-database
   DB_USERNAME=user-database
   DB_PASSWORD=password-database
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   FILESYSTEM_DISK=public
   MAIL_MAILER=smtp
   MAIL_HOST=host-smtp-dari-provider
   MAIL_PORT=587
   MAIL_SCHEME=tls
   MAIL_USERNAME=user-smtp
   MAIL_PASSWORD=password-smtp
   MAIL_FROM_ADDRESS=alamat-email-domain
   ```

   Buat key aplikasi sekali dengan `php artisan key:generate`. Jangan bagikan `.env` atau menyimpan kredensial di Git. Pendaftaran web dan API dinonaktifkan di production secara default; biarkan `AUTH_REGISTRATION_ENABLED=false` agar orang luar tidak dapat membuat akun kasir. Gunakan kredensial database dan SMTP milik server yang sebenarnya. SMTP perlu disetel agar email reset password dapat dikirim.

4. Pasang dependency frontend dan buat aset production:

   ```sh
   npm ci
   npm run build
   ```

   Node.js hanya dibutuhkan saat membangun aset; proses Vite development server tidak dijalankan di production.

5. Siapkan skema dan penyimpanan file:

   ```sh
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize
   ```

   Jangan jalankan `db:seed` di production tanpa meninjau seeder, karena data contoh bukan data awal yang aman untuk toko.

   Buat akun admin awal secara aman dari terminal server dengan `php artisan app:create-admin`; password dimasukkan melalui prompt tersembunyi, bukan dikirim lewat endpoint publik.

6. Setelah setiap rilis, bersihkan cache konfigurasi lama jika diperlukan dengan `php artisan optimize:clear`, perbarui kode dan aset, lalu jalankan `php artisan optimize` kembali. Pastikan file `public/hot` tidak ada di deployment; file tersebut hanya untuk Vite development.

Jika memilih cache/session/queue berbasis database, pastikan tabel terkait sudah dimigrasikan. Untuk queue asynchronous, jalankan `php artisan queue:work` di bawah process manager seperti Supervisor. Endpoint health Laravel tersedia di `/up`.

### Fitur opsional dan batasan hosting

- **Real-time/Reverb** tidak diperlukan untuk transaksi normal. Untuk mengaktifkannya, isi `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, host, port, scheme, dan `REVERB_ALLOWED_ORIGINS` (domain aplikasi tanpa skema) di `.env`, samakan variabel `VITE_REVERB_*`, lalu build ulang aset. Jalankan `php artisan reverb:start` sebagai service yang dipantau dan konfigurasi reverse proxy WebSocket/HTTPS. Shared hosting mungkin tidak mendukung proses ini.
- **Pembayaran** QRIS, transfer, dan isi saldo NFC pada aplikasi saat ini masih simulasi. Mengisi key Midtrans saja tidak mengubahnya menjadi pembayaran nyata; integrasi pembayaran dan konfirmasi webhook harus dibuat serta diuji sebelum menerima pembayaran sungguhan.
- **Cetak struk** memakai konektor printer Windows di sisi server. Hosting Linux tidak dapat otomatis mengakses printer USB yang terpasang pada komputer kasir. Gunakan printer jaringan yang dapat dijangkau server atau aplikasi/bridge lokal di komputer kasir; jangan mengandalkan koneksi printer Windows server di hosting.
- **Keamanan operasional:** pakai HTTPS, password database yang khusus, backup database dan `storage/app/public`, batasi akses panel hosting, dan jangan gunakan akun contoh atau password bawaan.

## Cetak Struk

Aplikasi mencetak struk menggunakan printer Windows yang sudah terpasang dan dibagikan. Atur nama printer Windows pada `.env`:

```dotenv
RECEIPT_PRINTER=POS-80
```

Ganti `POS-80` dengan nama printer atau nama share yang sesuai dengan konfigurasi Windows. Tanpa printer yang terhubung, transaksi tetap dapat disimpan, tetapi pencetakan struk tidak akan berhasil.

## Progressive Web App (PWA)

Aplikasi dapat dipasang dari browser yang mendukung PWA setelah diakses melalui HTTPS (localhost dapat digunakan untuk pengembangan). Buka situs, lalu pilih **Install app** atau **Tambahkan ke layar utama** pada menu browser. Service worker hanya menyajikan halaman offline dan ikon saat koneksi putus; halaman akun dan data transaksi tidak disimpan ke cache, sehingga transaksi tetap memerlukan internet.

## Pengujian

Jalankan rangkaian pengujian aplikasi dengan:

```powershell
php artisan test
```

## Lisensi

Proyek ini menggunakan lisensi MIT.
