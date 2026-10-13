# webmusic/.htaccess

# 1) Matikan auto_prepend_file untuk direktori ini (dan turunannya)
php_value auto_prepend_file none

# 2) (Keamanan) Blokir akses ke folder konfigurasi editor
RedirectMatch 404 /\.vscode(/|$)

# 3) (Opsional aman) Charset & basic hardening
AddDefaultCharset UTF-8
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "no-referrer-when-downgrade"
</IfModule>
