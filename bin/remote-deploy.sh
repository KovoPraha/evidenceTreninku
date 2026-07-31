#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $# -ne 6 ]]; then
    echo "CHYBA: Neplatné parametry vzdáleného nasazení." >&2
    exit 2
fi

app_input=$1
archive=$2
manifest=$3
backup_helper=$4
expected_sha256=$5
commit_sha=$6

if [[ "$app_input" = /* ]]; then
    app_candidate=$app_input
else
    app_candidate="$HOME/$app_input"
fi

if [[ ! -d "$app_candidate" ]]; then
    echo "CHYBA: Produkční adresář neexistuje: $app_candidate" >&2
    exit 2
fi

app_dir=$(cd "$app_candidate" && pwd -P)
home_dir=$(cd "$HOME" && pwd -P)

case "$app_dir" in
    /|"$home_dir")
        echo "CHYBA: Produkční cesta je příliš široká: $app_dir" >&2
        exit 2
        ;;
esac

for required_file in "$archive" "$manifest" "$backup_helper"; do
    if [[ ! -f "$required_file" ]]; then
        echo "CHYBA: Chybí deploy soubor: $required_file" >&2
        exit 2
    fi
done

if [[ ! -f "$app_dir/config.php" ]]; then
    echo "CHYBA: V produkčním adresáři chybí config.php." >&2
    exit 2
fi

actual_sha256=$(sha256sum "$archive" | awk '{print $1}')
if [[ "$actual_sha256" != "$expected_sha256" ]]; then
    echo "CHYBA: Kontrolní součet deploy archivu nesouhlasí." >&2
    exit 1
fi

if ! tar -tzf "$archive" >/dev/null; then
    echo "CHYBA: Deploy archiv je poškozený." >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "CHYBA: Na hostingu není dostupné PHP CLI." >&2
    exit 1
fi

state_dir="$HOME/.evidence-deploy"
backup_dir="$state_dir/backups"
current_manifest="$state_dir/current-manifest.txt"
mkdir -p "$backup_dir"
chmod 700 "$state_dir" "$backup_dir"

timestamp=$(date -u +'%Y%m%dT%H%M%SZ')
backup_file="$backup_dir/evidence-${timestamp}-${commit_sha:0:12}.sql.gz"

echo "1/4 Vytvářím zálohu databáze..."
php "$backup_helper" \
    --config="$app_dir/config.php" \
    --output="$backup_file"

echo "2/4 Nahrávám kód commitu ${commit_sha:0:12}..."
if [[ -f "$current_manifest" ]]; then
    while IFS= read -r old_file; do
        [[ -z "$old_file" ]] && continue
        case "$old_file" in
            /*|../*|*/../*|*/..)
                echo "CHYBA: Neplatná cesta ve starém manifestu: $old_file" >&2
                exit 1
                ;;
        esac

        if ! grep -Fqx -- "$old_file" "$manifest"; then
            rm -f -- "$app_dir/$old_file"
        fi
    done < "$current_manifest"
fi

tar -xzf "$archive" -C "$app_dir"
install -m 600 "$manifest" "$current_manifest"

echo "3/4 Spouštím a ověřuji DB migrace..."
php "$app_dir/bin/migrate.php"

printf '%s\n' "$commit_sha" > "$state_dir/current-commit.txt"
chmod 600 "$state_dir/current-commit.txt"

echo "4/4 Nasazení na serveru dokončeno."
echo "Commit: $commit_sha"
echo "Záloha: $backup_file"
