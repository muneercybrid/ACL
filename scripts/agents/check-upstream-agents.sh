#!/usr/bin/env bash
# Compares ACL's tracked upstream agents against the Agency Agents repository.
#
# Read-only with respect to this repository: it writes nothing inside it and changes
# no tracked file. The clone lives outside the repo (default /tmp).
#
# Reports every difference it finds rather than stopping at the first one, so
# deliberately no `set -e`. Exit status: 0 = in sync, 1 = differences to review,
# 2 = could not compare.
#
# Pure bash -- no jq, no php. The full procedure is in
# docs/agent-system/AGENT_UPDATE_PROCESS.md.

cd "$(dirname "$0")/../.." || exit 1

MANIFEST=".claude/agent-manifest.tsv"
PROV="docs/agent-system/UPSTREAM_AGENCY_AGENTS.md"
UPSTREAM_URL="https://github.com/msitarzewski/agency-agents"
UP="${AGENCY_UPSTREAM_DIR:-/tmp/agency-agents-upstream}"

pass() { printf '  \033[32mOK\033[0m    %s\n' "$1"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$1"; }
note() { printf '        %s\n' "$1"; }
diffline() { printf '  \033[33m%-8s\033[0m %s\n' "$1" "$2"; }
section() { printf '\n\033[1m%s\033[0m\n' "$1"; }

for tool in git sha256sum awk; do
  command -v "$tool" >/dev/null 2>&1 || { printf 'FAIL  %s not found on PATH\n' "$tool"; exit 2; }
done
[ -f "$MANIFEST" ] || { printf 'FAIL  %s is missing\n' "$MANIFEST"; exit 2; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

PINNED="$(grep -oE '\b[0-9a-f]{40}\b' "$PROV" 2>/dev/null | head -n1)"

# ------------------------------------------------------------------ the clone

section "Upstream clone"

if [ -d "$UP/.git" ]; then
  if git -C "$UP" fetch --quiet origin 2>"$TMP/git.err"; then
    pass "fetched into $UP"
  else
    warn "fetch failed -- comparing against the existing clone, which may be stale"
    note "$(head -n2 "$TMP/git.err")"
  fi
else
  if git clone --quiet "$UPSTREAM_URL" "$UP" 2>"$TMP/git.err"; then
    pass "cloned $UPSTREAM_URL into $UP"
  else
    printf '  \033[31mFAIL\033[0m  cannot clone %s\n' "$UPSTREAM_URL"
    note "$(head -n3 "$TMP/git.err")"
    note "set AGENCY_UPSTREAM_DIR to an existing clone to compare offline"
    exit 2
  fi
fi

REF="${1:-}"
if [ -z "$REF" ]; then
  git -C "$UP" remote set-head origin --auto >/dev/null 2>&1
  REF="$(git -C "$UP" symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null)"
  [ -n "$REF" ] || REF="origin/main"
fi

if ! git -C "$UP" rev-parse --verify --quiet "$REF^{commit}" >/dev/null; then
  printf '  \033[31mFAIL\033[0m  %s is not a commit in the clone\n' "$REF"
  exit 2
fi

HEAD_SHA="$(git -C "$UP" rev-parse "$REF")"
pass "comparing against $REF ($(git -C "$UP" log -1 --format=%cs "$REF"), ${HEAD_SHA:0:12})"

if [ -n "$PINNED" ]; then
  if [ "$PINNED" = "$HEAD_SHA" ]; then
    pass "identical to the pinned commit in $PROV"
  else
    behind="$(git -C "$UP" rev-list --count "$PINNED..$HEAD_SHA" 2>/dev/null)"
    pass "pinned ${PINNED:0:12} is ${behind:-?} commit(s) behind $REF"
  fi
else
  warn "no 40-character commit found in $PROV -- cannot say what ACL is pinned to"
fi

# ------------------------------------------------------------- the comparison

section "Tracked agents"

CHANGED=0; REMOVED=0; DRIFT=0; SAME=0
: > "$TMP/tracked-upaths.txt"

while IFS="$(printf '\t')" read -r file category loaded upath adapted sha; do
  [ "$file" = "file" ] && continue
  [ -z "$file" ] && continue
  [ "$upath" = "-" ] && continue

  printf '%s\n' "$upath" >> "$TMP/tracked-upaths.txt"

  # DRIFT: the local file no longer matches the hash recorded for it. This means an
  # upstream file was edited locally, which the design forbids -- investigate before
  # taking any refresh.
  if [ "$loaded" = "yes" ]; then local_path=".claude/agents/$file"; else local_path=".claude/agents-available/$file"; fi
  if [ -f "$local_path" ]; then
    local_sha="$(sha256sum "$local_path" | awk '{print $1}')"
    if [ "$local_sha" != "$sha" ]; then
      diffline "DRIFT" "$local_path -- local content does not match its manifest hash"
      DRIFT=$((DRIFT + 1))
    fi
  fi

  if ! git -C "$UP" cat-file -e "$REF:$upath" 2>/dev/null; then
    diffline "REMOVED" "$upath (ACL keeps $file)"
    REMOVED=$((REMOVED + 1))
    continue
  fi

  up_sha="$(git -C "$UP" show "$REF:$upath" | sha256sum | awk '{print $1}')"
  if [ "$up_sha" = "$sha" ]; then
    SAME=$((SAME + 1))
  else
    diffline "CHANGED" "$upath"
    CHANGED=$((CHANGED + 1))
  fi
done < "$MANIFEST"

[ "$SAME" -gt 0 ] && pass "$SAME agent(s) byte-identical to $REF"

# ------------------------------------------------------------------ additions

section "New upstream agents"

EXCLUSIONS=".claude/agent-exclusions.txt"
if [ -f "$EXCLUSIONS" ]; then
  grep -vE '^[[:space:]]*(#|$)' "$EXCLUSIONS" | cut -f1 > "$TMP/excl.txt"
else
  : > "$TMP/excl.txt"
  warn "$EXCLUSIONS is missing -- every untracked upstream agent will look new"
fi

sort -u "$TMP/tracked-upaths.txt" > "$TMP/tracked-sorted.txt"
git -C "$UP" ls-tree -r --name-only "$REF" \
  | grep -E '\.md$' \
  | grep -vE '^[^/]+$' \
  | grep -vE '^(strategy|examples|scripts|\.github)/' \
  | sort -u > "$TMP/upstream-md.txt"

# an agent definition is a .md whose frontmatter carries both name: and description:
comm -23 "$TMP/upstream-md.txt" "$TMP/tracked-sorted.txt" | while IFS= read -r p; do
  git -C "$UP" show "$REF:$p" | head -n 30 > "$TMP/head.md"
  if [ "$(head -n1 "$TMP/head.md" | tr -d '\r')" = "---" ] \
     && grep -q '^name:' "$TMP/head.md" \
     && grep -q '^description:' "$TMP/head.md"; then
    printf '%s\n' "$p"
  fi
done > "$TMP/untracked.txt"

: > "$TMP/added.txt"
EXCLUDED=0
while IFS= read -r p; do
  [ -z "$p" ] && continue
  hit=""
  while IFS= read -r pre; do
    [ -z "$pre" ] && continue
    case "$p" in "$pre"*) hit="$pre"; break ;; esac
  done < "$TMP/excl.txt"
  if [ -n "$hit" ]; then
    EXCLUDED=$((EXCLUDED + 1))
  else
    printf '%s\n' "$p" >> "$TMP/added.txt"
  fi
done < "$TMP/untracked.txt"

[ "$EXCLUDED" -gt 0 ] && pass "$EXCLUDED untracked agent(s) match a recorded exclusion in $EXCLUSIONS"

ADDED=0
if [ -s "$TMP/added.txt" ]; then
  while IFS= read -r p; do diffline "ADDED" "$p"; done < "$TMP/added.txt"
  ADDED="$(wc -l < "$TMP/added.txt" | tr -d ' ')"
  note "triage each: copy to .claude/agents/ (route to it), .claude/agents-available/ (PERIODIC),"
  note "or add a prefix to $EXCLUSIONS with the reason"
else
  pass "no new upstream agent definitions outside the recorded exclusions"
fi

# ------------------------------------------------------------------- verdict

printf '\n'
TOTAL=$((CHANGED + REMOVED + ADDED + DRIFT))

if [ "$DRIFT" -gt 0 ]; then
  printf '\033[31m%s local file(s) drifted from the manifest.\033[0m ' "$DRIFT"
  printf 'Investigate that before refreshing: an upstream agent must stay byte-identical,\n'
  printf 'and an ACL customisation belongs in an acl-* agent instead.\n'
fi

if [ "$TOTAL" -eq 0 ]; then
  printf '\033[32mIn sync with %s.\033[0m Nothing to review.\n' "$REF"
  exit 0
fi

printf 'changed=%s removed=%s added=%s drift=%s\n' "$CHANGED" "$REMOVED" "$ADDED" "$DRIFT"
printf 'Read every diff before taking it -- docs/agent-system/AGENT_UPDATE_PROCESS.md step 2:\n'
printf '  diff -u .claude/agents/<file>.md %s/<upstream_path>\n' "$UP"
printf 'Hashes come from git blobs, so a CRLF checkout of this repository would report\n'
printf 'every file as changed. Keep these definitions LF.\n'
exit 1
