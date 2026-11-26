#!/usr/bin/env bash
# Skrip uji register & login untuk project LSP (Laravel).
# Cara pakai:
#   1) Terminal 1:  php artisan serve
#   2) Terminal 2:  bash uji-auth.sh
BASE=${BASE:-http://127.0.0.1:8000/api}
OUT=$(mktemp)
STAMP=$(date +%s)
EMAIL="uji${STAMP}@mail.com"
PASS=0
FAIL=0

t() {
  local code
  code=$(curl -s -o "$OUT" -w "%{http_code}" -X "$3" "$BASE$4" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    ${6:+-H "Authorization: Bearer $6"} ${5:+-d "$5"})
  if [ "$code" = "$2" ]; then s=LOLOS; PASS=$((PASS+1)); else s=GAGAL; FAIL=$((FAIL+1)); fi
  printf "%-6s %-55s harapan=%s dapat=%s\n" "$s" "$1" "$2" "$code"
}
tok() { sed -E 's/.*"token":"([^"]+)".*/\1/' "$OUT"; }
has() {
  if grep -q "$2" "$OUT"; then s=LOLOS; PASS=$((PASS+1)); else s=GAGAL; FAIL=$((FAIL+1)); fi
  printf "%-6s %s\n" "$s" "$1"
}
reg_body() {
  echo "{\"name\":\"uji\",\"fullname\":\"Uji Coba\",\"place\":\"Bandung\",\"date_of_birth\":\"2000-05-17\",\"phone\":\"081234567890\",\"email\":\"$1\",\"password\":\"rahasia123\",\"password_confirmation\":\"rahasia123\"$2}"
}

echo "== Server: $BASE =="
t "1  Register lengkap"                          201 POST /auth/register "$(reg_body "$EMAIL")"
USER_TOKEN=$(tok)
t "2  Register dengan email yang sama"           422 POST /auth/register "$(reg_body "$EMAIL")"
t "3  Register tanpa password_confirmation"      422 POST /auth/register "{\"name\":\"u\",\"fullname\":\"U Ji\",\"place\":\"B\",\"date_of_birth\":\"2000-05-17\",\"phone\":\"081234567890\",\"email\":\"x$EMAIL\",\"password\":\"rahasia123\"}"
t "4  Register menyelipkan role=admin"           201 POST /auth/register "$(reg_body "role$EMAIL" ',"role":"admin"')"
has "4b Role tetap 'user' (tidak bisa jadi admin)" '"role":"user"'
t "5  Login benar"                               200 POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"rahasia123\"}"
USER_TOKEN=$(tok)
t "6  Login password salah"                      401 POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"salah\"}"
t "7  GET /user tanpa token"                     401 GET  /user
t "8  GET /user dengan token"                    200 GET  /user "" "$USER_TOKEN"
t "9  Logout"                                    200 POST /auth/logout "" "$USER_TOKEN"
t "9b Token lama setelah logout"                 401 GET  /user "" "$USER_TOKEN"
ADM="{\"name\":\"adm\",\"fullname\":\"Admin Uji\",\"place\":\"Jakarta\",\"date_of_birth\":\"1995-01-01\",\"email\":\"adm$EMAIL\",\"phone\":\"081234567891\",\"password\":\"rahasia123\",\"password_confirmation\":\"rahasia123\"}"
t "10 registerAdmin tanpa token"                 401 POST /super-admin/register-admin "$ADM"
t "   (login ulang user biasa)"                  200 POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"rahasia123\"}"
USER_TOKEN=$(tok)
t "11 registerAdmin pakai token user biasa"      403 POST /super-admin/register-admin "$ADM" "$USER_TOKEN"
t "   (login super admin dari seeder)"           200 POST /auth/login '{"email":"superadmin@lsp.com","password":"password123"}'
SA_TOKEN=$(tok)
t "12 registerAdmin pakai token super_admin"     201 POST /super-admin/register-admin "$ADM" "$SA_TOKEN"
t "12b register-super-admin tanpa token"         401 POST /super-admin/register-super-admin "$ADM"
t "12c register-super-admin token user biasa"    403 POST /super-admin/register-super-admin "$ADM" "$USER_TOKEN"
t "   (login admin yang baru dibuat)"            200 POST /auth/login "{\"email\":\"adm$EMAIL\",\"password\":\"rahasia123\"}"
ADMIN_TOKEN=$(tok)
t "12d register-admin token admin (bukan super)" 403 POST /super-admin/register-admin "$ADM" "$ADMIN_TOKEN"
t "12e register-super-admin token admin"         403 POST /super-admin/register-super-admin "$ADM" "$ADMIN_TOKEN"
t "13 user biasa memanggil verify-admin"         403 POST /certifications/1/verify-admin '{}' "$USER_TOKEN"
t "14 user biasa memanggil pre-assessment"       403 POST /certifications/1/pre-assessment '{}' "$USER_TOKEN"
t "15 user biasa memanggil final-decision"       403 POST /certifications/1/final-decision '{}' "$USER_TOKEN"
t "16 certifications tanpa token"                401 GET  /certifications
t "17 user biasa melihat daftar certifications"  200 GET  /certifications "" "$USER_TOKEN"
echo
echo "Ringkasan: $PASS lolos, $FAIL gagal"
rm -f "$OUT"
