#!/usr/bin/env bash
# Uji dua akun (pemohon A dan pemohon B) untuk memastikan B tidak bisa
# melihat atau mengubah permohonan milik A. Juga menguji pembatasan role.
#
# Cara pakai:
#   Terminal 1:  php artisan migrate:fresh --seed && php artisan serve
#   Terminal 2:  bash uji-akses.sh
# Alamat lain:   BASE=http://127.0.0.1:8000/api bash uji-akses.sh
# Butuh: curl dan python3.

BASE=${BASE:-http://127.0.0.1:8000/api}
OUT=$(mktemp)
STAMP=$(date +%s)
PASS=0
FAIL=0

# t "nama" harapan(regex, mis. "403|404") METHOD /path 'json' [token]
t() {
  local code
  code=$(curl -s -o "$OUT" -w "%{http_code}" -X "$3" "$BASE$4" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    ${6:+-H "Authorization: Bearer $6"} ${5:+-d "$5"})
  if [[ "$code" =~ ^($2)$ ]]; then s=LOLOS; PASS=$((PASS+1)); else s=GAGAL; FAIL=$((FAIL+1)); fi
  printf "%-6s %-58s harapan=%-8s dapat=%s\n" "$s" "$1" "$2" "$code"
}

ok()  { PASS=$((PASS+1)); printf "%-6s %s\n" LOLOS "$1"; }
bad() { FAIL=$((FAIL+1)); printf "%-6s %s\n" GAGAL "$1"; }

# ambil nilai pertama dari kunci tertentu pada respons terakhir
jfind() {
python3 - "$OUT" "$1" <<'PY'
import sys, json
def f(o, k):
    if isinstance(o, dict):
        if k in o and o[k] is not None and not isinstance(o[k], (dict, list)):
            return o[k]
        for v in o.values():
            r = f(v, k)
            if r is not None:
                return r
    elif isinstance(o, list):
        for v in o:
            r = f(v, k)
            if r is not None:
                return r
try:
    d = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(0)
r = f(d, sys.argv[2])
if r is not None:
    print(r)
PY
}

# hitung berapa permohonan (objek dengan user_id) milik user tertentu di respons terakhir
count_owned() {
python3 - "$OUT" "$1" <<'PY'
import sys, json
n = 0
def walk(o, uid):
    global n
    if isinstance(o, dict):
        if str(o.get("user_id")) == uid:
            n += 1
        for v in o.values():
            walk(v, uid)
    elif isinstance(o, list):
        for v in o:
            walk(v, uid)
try:
    walk(json.load(open(sys.argv[1])), sys.argv[2])
except Exception:
    pass
print(n)
PY
}

reg_body() { # email
  echo "{\"name\":\"uji\",\"fullname\":\"Uji Coba\",\"place\":\"Bandung\",\"date_of_birth\":\"2000-05-17\",\"phone\":\"081234567890\",\"email\":\"$1\",\"password\":\"rahasia123\",\"password_confirmation\":\"rahasia123\"}"
}

echo "== Server: $BASE =="
echo "-- Persiapan: dua pemohon (A dan B) --"
EMAIL_A="a${STAMP}@mail.com"
EMAIL_B="b${STAMP}@mail.com"

t "Daftar pemohon A" "201" POST /auth/register "$(reg_body "$EMAIL_A")"
t "Login A" "200" POST /auth/login "{\"email\":\"$EMAIL_A\",\"password\":\"rahasia123\"}"
TOKEN_A=$(jfind token)
UID_A=$(jfind id)

t "Daftar pemohon B" "201" POST /auth/register "$(reg_body "$EMAIL_B")"
t "Login B" "200" POST /auth/login "{\"email\":\"$EMAIL_B\",\"password\":\"rahasia123\"}"
TOKEN_B=$(jfind token)

if [ -z "$TOKEN_A" ] || [ -z "$TOKEN_B" ] || [ -z "$UID_A" ]; then
  echo "Persiapan gagal (token atau id tidak terbaca). Hentikan uji."
  rm -f "$OUT"; exit 1
fi

t "A membuat permohonan" "200|201" POST /certifications '{"judul_skema":"Uji Akses"}' "$TOKEN_A"
CERT=$(jfind id)
if [ -z "$CERT" ]; then
  echo "Id permohonan tidak terbaca dari respons. Hentikan uji."
  rm -f "$OUT"; exit 1
fi
echo "   (id permohonan A = $CERT, id user A = $UID_A)"

echo
echo "-- Kontrol: A mengakses permohonannya sendiri (harus lolos cek kepemilikan) --"
t "A approve-apl miliknya (status Cek -> ditolak status)" "409|422" POST "/certifications/$CERT/approve-apl" '{}' "$TOKEN_A"
t "A pay miliknya (status Cek -> ditolak status)" "409|422" POST "/certifications/$CERT/pay" '{"bukti_bayar":"x"}' "$TOKEN_A"
t "A melihat daftar permohonan" "200" GET /certifications "" "$TOKEN_A"
if [ "$(count_owned "$UID_A")" -ge 1 ]; then ok "A melihat permohonannya sendiri di daftar"; else bad "A TIDAK melihat permohonannya sendiri di daftar"; fi

echo
echo "-- Uji akses: B mencoba menyentuh permohonan milik A (harus ditolak 403/404) --"
t "B GET /certifications" "200" GET /certifications "" "$TOKEN_B"
if [ "$(count_owned "$UID_A")" -eq 0 ]; then ok "Daftar B tidak memuat permohonan milik A"; else bad "BOCOR: daftar B memuat permohonan milik A"; fi
t "B submit-apl ke permohonan A"        "403|404" POST "/certifications/$CERT/submit-apl" '{"apl_01_data":{"a":1},"apl_02_data":{"a":1}}' "$TOKEN_B"
t "B approve-apl ke permohonan A"       "403|404" POST "/certifications/$CERT/approve-apl" '{}' "$TOKEN_B"
t "B pay ke permohonan A"               "403|404" POST "/certifications/$CERT/pay" '{"bukti_bayar":"x"}' "$TOKEN_B"
t "B approve-assessment ke permohonan A" "403|404" POST "/certifications/$CERT/approve-assessment" '{"kuesioner_data":{"a":1}}' "$TOKEN_B"
t "B unduh PDF APL01 milik A"           "403|404" GET  "/certifications/$CERT/pdf/APL01" "" "$TOKEN_B"
t "Tanpa token unduh PDF milik A"       "401"     GET  "/certifications/$CERT/pdf/APL01"

echo
echo "-- Uji role: pemohon tidak boleh memanggil endpoint admin, asesor, mitra (harus 403) --"
t "B review-apl01"       "403" POST "/certifications/$CERT/review-apl01" '{}' "$TOKEN_B"
t "B nominal"            "403" POST "/certifications/$CERT/nominal" '{"nominal_tagihan":1}' "$TOKEN_B"
t "B confirm-payment"    "403" POST "/certifications/$CERT/confirm-payment" '{}' "$TOKEN_B"
t "B schedule"           "403" POST "/certifications/$CERT/schedule" '{}' "$TOKEN_B"
t "B pre-assessment"     "403" POST "/certifications/$CERT/pre-assessment" '{}' "$TOKEN_B"
t "B final-decision"     "403" POST "/certifications/$CERT/final-decision" '{}' "$TOKEN_B"
t "B get-sipp (khusus mitra)" "403" POST "/certifications/$CERT/get-sipp" '{}' "$TOKEN_B"
t "B register-asesor"    "403" POST /super-admin/register-asesor "$(reg_body "x$EMAIL_B")" "$TOKEN_B"
t "B register-mitra"     "403" POST /super-admin/register-mitra "$(reg_body "y$EMAIL_B")" "$TOKEN_B"
t "B lihat daftar user"  "403" GET  /super-admin/users "" "$TOKEN_B"

echo
echo "Ringkasan: $PASS lolos, $FAIL gagal"
echo "Cara membaca:"
echo "  - GAGAL pada 'Uji akses' dengan kode 200/409/422 berarti B bisa menjangkau data A (celah IDOR). Laporkan ke Adis."
echo "  - Kode 500 -> cek storage/logs/laravel.log."
echo "  - Kontrol A harus 409/422 (bukan 403/404). Itu membuktikan cek kepemilikan dan cek status dibedakan."
rm -f "$OUT"
