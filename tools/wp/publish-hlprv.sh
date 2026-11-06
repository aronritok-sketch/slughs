#!/usr/bin/env bash
# A kész Slugshop-téma tükre a hlprv repó claude/slugshop-site ágára (slugshop/wp-theme/slugshop/). A hlprv kiadó
# workflow-ja (site-releases.yml) óránként ebből készít kiadást (slugshop-vX.Y.Z), az éles oldal alap témája
# (iu_theme) a deploy/sites.json-ban jóváhagyott verziót tölti le. Verzióemelés: style.css „Version:”.
# Futtatás a repó gyökeréből, minden téma-változás után:  bash tools/wp/publish-hlprv.sh
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HLPRV=${HLPRV:-/home/user/hlprv}
BR=claude/slugshop-site
WT=$(mktemp -d)
trap 'git -C "$HLPRV" worktree remove --force "$WT" >/dev/null 2>&1 || true; rm -rf "$WT"' EXIT
if git -C "$HLPRV" ls-remote --exit-code --heads origin "$BR" >/dev/null 2>&1; then
  git -C "$HLPRV" fetch -q origin "$BR"
  git -C "$HLPRV" worktree add -q --detach "$WT" FETCH_HEAD
else
  git -C "$HLPRV" worktree add -q --detach "$WT"
  git -C "$WT" checkout -q --orphan tmp-slugshop && git -C "$WT" rm -rq --cached . && find "$WT" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
fi
mkdir -p "$WT/slugshop/wp-theme"
rm -rf "$WT/slugshop/wp-theme/slugshop"
cp -a "$ROOT/wp-theme/slugshop" "$WT/slugshop/wp-theme/slugshop"
find "$WT/slugshop/wp-theme/slugshop" -name '.DS_Store' -delete
cat > "$WT/slugshop/README.md" <<'MD'
# Slugshop – a téma tükre

A forrás az `aronritok-sketch/slughs` repóban van (`wp-theme/slugshop`), ide a `tools/wp/publish-hlprv.sh` másolja.
Itt ne szerkeszd: a következő tükrözés felülírja. A kiadás és az éles frissítése: `deploy/README.md`, `deploy/sites.json` („slugshop”).
MD
VER=$(sed -nE 's/^Version:[[:space:]]*//p' "$ROOT/wp-theme/slugshop/style.css" | head -1 | tr -d '[:space:]')
SRC=$(git -C "$ROOT" rev-parse --short HEAD)
git -C "$WT" add -A slugshop
if git -C "$WT" diff --cached --quiet; then echo "Nincs változás ($VER)"; exit 0; fi
git -C "$WT" -c user.name="$(git -C "$ROOT" config user.name)" -c user.email="$(git -C "$ROOT" config user.email)" \
  commit -q -m "Slugshop téma $VER (slughs $SRC)"
for i in 1 2 3 4; do git -C "$WT" push -q origin "HEAD:refs/heads/$BR" && break; sleep $((2 ** i)); done
echo "Tükrözve: $BR → slugshop $VER (slughs $SRC)"
