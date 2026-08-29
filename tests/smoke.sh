#!/usr/bin/env bash
set -euo pipefail

for command_name in php curl; do
    command -v "$command_name" >/dev/null || {
        echo "Missing required command: $command_name" >&2
        exit 1
    }
done

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
test_dir=$(mktemp -d)
data_dir="$test_dir/data"
cookie_jar="$test_dir/cookies.txt"
server_log="$test_dir/server.log"
mkdir -p "$data_dir/files"

password='correct-horse-battery-staple'
password_hash=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$password")
port=$(php -r 'echo random_int(20000, 45000);')
base_url="http://127.0.0.1:$port"
server_pid=''

cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$test_dir"
}
trap cleanup EXIT

fail() {
    echo "FAIL: $1" >&2
    echo "Server log:" >&2
    sed -n '1,200p' "$server_log" >&2 || true
    exit 1
}

csrf_from() {
    sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

HAWPIWCLOUD_DATA_DIR="$data_dir" \
HAWPIWCLOUD_PASSWORD_HASH="$password_hash" \
php -S "127.0.0.1:$port" -t "$repo_dir" >"$server_log" 2>&1 &
server_pid=$!

for _ in $(seq 1 50); do
    if curl -sS "$base_url/login.php" >/dev/null 2>&1; then
        break
    fi
    sleep 0.1
done

for endpoint in index.php upload.php download.php delete.php; do
    status=$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/$endpoint")
    [[ "$status" == '302' ]] || fail "unauthenticated $endpoint returned $status instead of 302"
done

curl -sS -c "$cookie_jar" "$base_url/login.php" -o "$test_dir/login.html"
csrf=$(csrf_from "$test_dir/login.html")
[[ -n "$csrf" ]] || fail 'login CSRF token was not rendered'

status=$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'csrf_token=invalid' \
    --data-urlencode "password=$password" \
    "$base_url/login.php")
[[ "$status" == '403' ]] || fail "invalid login CSRF returned $status instead of 403"

status=$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -o /dev/null -w '%{http_code}' \
    --data-urlencode "csrf_token=$csrf" \
    --data-urlencode "password=$password" \
    "$base_url/login.php")
[[ "$status" == '302' ]] || fail "valid login returned $status instead of 302"

status=$(curl -sS -b "$cookie_jar" -o "$test_dir/index.html" -w '%{http_code}' "$base_url/index.php")
[[ "$status" == '200' ]] || fail "authenticated index returned $status instead of 200"
csrf=$(csrf_from "$test_dir/index.html")
[[ -n "$csrf" ]] || fail 'authenticated CSRF token was not rendered'

printf '%s\n' '<?php echo "must not execute";' >"$test_dir/payload.php"
status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' \
    -F 'csrf_token=invalid' \
    -F "fileToUpload=@$test_dir/payload.php;filename=payload.php" \
    "$base_url/upload.php")
[[ "$status" == '302' && ! -e "$data_dir/files/payload.php" ]] || fail 'invalid upload CSRF was not rejected'

status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' \
    -F "csrf_token=$csrf" \
    -F "fileToUpload[]=@$test_dir/payload.php;filename=payload.php" \
    "$base_url/upload.php")
[[ "$status" == '302' && ! -e "$data_dir/files/payload.php" ]] || fail 'malformed upload structure was not rejected'

status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' \
    -F "csrf_token=$csrf" \
    -F "fileToUpload=@$test_dir/payload.php;filename=payload.php" \
    "$base_url/upload.php")
[[ "$status" == '302' ]] || fail "valid upload returned $status instead of 302"
[[ -f "$data_dir/files/payload.php" ]] || fail 'uploaded PHP fixture was not stored privately'

status=$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/uploads/payload.php")
[[ "$status" == '404' ]] || fail "uploaded PHP fixture had a public response ($status)"

curl -sS -b "$cookie_jar" "$base_url/download.php?file=payload.php" -o "$test_dir/downloaded.php"
cmp -s "$test_dir/payload.php" "$test_dir/downloaded.php" || fail 'downloaded bytes changed'

status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' \
    -F "csrf_token=$csrf" \
    -F "fileToUpload=@$test_dir/payload.php;filename=payload.php" \
    "$base_url/upload.php")
[[ "$status" == '302' ]] || fail "duplicate upload returned $status instead of 302"
file_count=$(find "$data_dir/files" -maxdepth 1 -type f -name 'payload*.php' | wc -l)
[[ "$file_count" == '2' ]] || fail "duplicate upload produced $file_count files instead of 2"

status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'csrf_token=invalid' \
    --data-urlencode 'file=payload.php' \
    "$base_url/delete.php")
[[ "$status" == '302' && -f "$data_dir/files/payload.php" ]] || fail 'invalid delete CSRF was not rejected'

status=$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'action=logout' \
    --data-urlencode 'csrf_token=invalid' \
    "$base_url/login.php")
[[ "$status" == '302' ]] || fail "invalid logout CSRF returned $status instead of 302"
status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' "$base_url/index.php")
[[ "$status" == '200' ]] || fail 'invalid logout CSRF revoked the authenticated session'

status=$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'action=logout' \
    --data-urlencode "csrf_token=$csrf" \
    "$base_url/login.php")
[[ "$status" == '302' ]] || fail "logout returned $status instead of 302"
status=$(curl -sS -b "$cookie_jar" -o /dev/null -w '%{http_code}' "$base_url/index.php")
[[ "$status" == '302' ]] || fail 'logout did not revoke dashboard access'

rm -f "$cookie_jar"
curl -sS -c "$cookie_jar" "$base_url/login.php" -o "$test_dir/login.html"
csrf=$(csrf_from "$test_dir/login.html")
for attempt in 1 2 3 4 5; do
    status=$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -o "$test_dir/failure.html" -w '%{http_code}' \
        --data-urlencode "csrf_token=$csrf" \
        --data-urlencode 'password=wrong' \
        "$base_url/login.php")
done
[[ "$status" == '429' ]] || fail "fifth failed login returned $status instead of 429"

HAWPIWCLOUD_DATA_DIR="$repo_dir" php -r \
    '$_SERVER["DOCUMENT_ROOT"] = $argv[1]; require $argv[1] . "/bootstrap.php"; privateDataDirectory();' \
    "$repo_dir" >"$test_dir/config-error.txt" 2>"$test_dir/config-error.log"
grep -q 'belum dikonfigurasi' "$test_dir/config-error.txt" || fail 'public data path did not fail closed'

mkdir -p "$test_dir/unsafe-data"
ln -s "$repo_dir" "$test_dir/unsafe-data/files"
HAWPIWCLOUD_DATA_DIR="$test_dir/unsafe-data" php -r \
    '$_SERVER["DOCUMENT_ROOT"] = $argv[1]; require $argv[1] . "/bootstrap.php"; storageDirectory();' \
    "$repo_dir" >"$test_dir/symlink-error.txt" 2>"$test_dir/symlink-error.log"
grep -q 'belum dikonfigurasi' "$test_dir/symlink-error.txt" || fail 'public storage symlink did not fail closed'

HAWPIWCLOUD_DATA_DIR="$test_dir/missing" php -r \
    '$_SERVER["DOCUMENT_ROOT"] = $argv[1]; require $argv[1] . "/bootstrap.php"; privateDataDirectory();' \
    "$repo_dir" >"$test_dir/missing-error.txt" 2>"$test_dir/missing-error.log"
grep -q 'belum dikonfigurasi' "$test_dir/missing-error.txt" || fail 'missing data path did not fail closed'

mkdir -p "$test_dir/locked-data/files"
chmod 500 "$test_dir/locked-data" "$test_dir/locked-data/files"
HAWPIWCLOUD_DATA_DIR="$test_dir/locked-data" php -r \
    '$_SERVER["DOCUMENT_ROOT"] = $argv[1]; require $argv[1] . "/bootstrap.php"; storageDirectory();' \
    "$repo_dir" >"$test_dir/permission-error.txt" 2>"$test_dir/permission-error.log"
grep -q 'belum dikonfigurasi' "$test_dir/permission-error.txt" || fail 'unwritable data path did not fail closed'
chmod 700 "$test_dir/locked-data" "$test_dir/locked-data/files"

php -r \
    '$_SERVER["DOCUMENT_ROOT"] = $argv[1]; require $argv[1] . "/bootstrap.php"; $_SESSION["authenticated"] = true; $_SESSION["last_activity"] = time() - 1801; exit(isAuthenticated() ? 1 : 0);' \
    "$repo_dir" || fail 'idle session did not expire'

size_check_line=$(grep -n '^if (requestBodyExceededPostMaxSize())' "$repo_dir/upload.php" | cut -d: -f1)
csrf_check_line=$(grep -n '^if (!isValidCsrfToken' "$repo_dir/upload.php" | cut -d: -f1)
[[ "$size_check_line" -lt "$csrf_check_line" ]] || fail 'request-size validation must run before CSRF validation'
! grep -q 'fileChip\.innerHTML' "$repo_dir/assets/app.js" || fail 'file name is still written through innerHTML'
grep -q '<details class="faq-item">' "$repo_dir/index.php" || fail 'FAQ does not use native details elements'
! grep -q 'data-faq' "$repo_dir/index.php" "$repo_dir/assets/app.js" || fail 'obsolete JavaScript FAQ control remains'
grep -q 'id="upload-button"' "$repo_dir/index.php" || fail 'upload loading control is missing'
grep -q 'prefers-reduced-motion: reduce' "$repo_dir/assets/styles.css" || fail 'reduced-motion support is missing'

echo 'Smoke test passed.'
