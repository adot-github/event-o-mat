#!/usr/bin/env bash
# ============================================================================
#  Reorganise the per-event upload folders under wp-content/uploads/<event_uid>/
#  into three areas:
#
#     <event_uid>/assets/   icons/ partner-logos/ presenter-images/ pdf-images/
#     <event_uid>/pdf/      booklet/ diplomas/ etiketten/ invoices/ programs/
#                           tickets/ workshop-booking-lists/ workshop-flyer/
#     <event_uid>/pdf-templates/   (unchanged: *.php, content-body.css, booklet/)
#
#  Idempotent: a folder is only moved when the source still exists. An event
#  folder is any direct child of uploads/ that contains a pdf-templates/ dir
#  (this skips the WordPress media library folder, e.g. 2026/).
#
#  Usage:  bash uploads-restructure.sh /path/to/wp-content/uploads
# ============================================================================
set -euo pipefail

UPLOADS="${1:-}"
if [ -z "$UPLOADS" ] || [ ! -d "$UPLOADS" ]; then
    echo "Usage: bash uploads-restructure.sh <wp-content/uploads dir>" >&2
    exit 1
fi

move() {  # move <event_dir> <src-rel> <dst-rel>
    local ev="$1" src="$2" dst="$3"
    [ -d "$ev/$src" ] || return 0
    mkdir -p "$(dirname "$ev/$dst")"
    if [ -d "$ev/$dst" ]; then
        mv -n "$ev/$src"/* "$ev/$dst"/ 2>/dev/null || true
        rmdir "$ev/$src" 2>/dev/null || true
    else
        mv "$ev/$src" "$ev/$dst"
    fi
    echo "  $src  ->  $dst"
}

for ev in "$UPLOADS"/*/; do
    ev="${ev%/}"
    [ -d "$ev/pdf-templates" ] || continue
    echo "== $(basename "$ev") =="

    mkdir -p "$ev/assets" "$ev/pdf"

    move "$ev" "presenters"           "assets/presenter-images"
    move "$ev" "partner-logos"        "assets/partner-logos"
    move "$ev" "icons"                "assets/icons"
    move "$ev" "pdf-templates/assets" "assets/pdf-images"

    for d in booklet diplomas etiketten invoices programs tickets \
             workshop-booking-lists workshop-flyer; do
        move "$ev" "$d" "pdf/$d"
    done
done

echo "done."
