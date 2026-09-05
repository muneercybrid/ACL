#!/usr/bin/env bash
# Validates ACL's agent system: definitions, manifest, documentation, Git tracking.
#
# Reports every problem it finds rather than stopping at the first one, so
# deliberately no `set -e`. Exits non-zero if any check failed.
#
# Pure bash by design -- no jq and no php, so it runs on the laptop as well as in
# the codespace. See docs/agent-system/ for what each rule is protecting.

cd "$(dirname "$0")/../.." || exit 1

FAILURES=0

pass() { printf '  \033[32mOK\033[0m    %s\n' "$1"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES + 1)); }
section() { printf '\n\033[1m%s\033[0m\n' "$1"; }

LOADED_DIR=".claude/agents"
RESERVE_DIR=".claude/agents-available"
MANIFEST=".claude/agent-manifest.tsv"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# ---------------------------------------------------------------- directories

section "Agent directories"

for d in "$LOADED_DIR" "$RESERVE_DIR"; do
  if [ -d "$d" ]; then
    pass "$d exists ($(find "$d" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ') definitions)"
  else
    fail "$d is missing"
  fi
done

[ -f "$MANIFEST" ] && pass "$MANIFEST exists" || fail "$MANIFEST is missing"

find "$LOADED_DIR" "$RESERVE_DIR" -maxdepth 1 -name '*.md' 2>/dev/null | sort > "$TMP/files.txt"
AGENT_COUNT="$(wc -l < "$TMP/files.txt" | tr -d ' ')"

if [ "$AGENT_COUNT" -eq 0 ]; then
  fail "no agent definitions found -- nothing else can be validated"
  exit 1
fi

# --------------------------------------------------------------- frontmatter

section "YAML frontmatter"

FM_BAD=0
: > "$TMP/names.tsv"

while IFS= read -r f; do
  if [ "$(head -n1 "$f" | tr -d '\r')" != "---" ]; then
    fail "$f does not open with ---"
    FM_BAD=$((FM_BAD + 1))
    continue
  fi

  close="$(grep -n -m2 '^---[[:space:]]*$' "$f" | sed -n '2s/:.*//p')"
  if [ -z "$close" ]; then
    fail "$f has no closing --- for its frontmatter"
    FM_BAD=$((FM_BAD + 1))
    continue
  fi

  sed -n "2,$((close - 1))p" "$f" | tr -d '\r' > "$TMP/fm"

  name="$(sed -n 's/^name:[[:space:]]*//p' "$TMP/fm" | head -n1)"
  desc="$(sed -n 's/^description:[[:space:]]*//p' "$TMP/fm" | head -n1)"

  if [ -z "$name" ]; then
    fail "$f frontmatter has no name:"
    FM_BAD=$((FM_BAD + 1))
    continue
  fi
  if [ -z "$desc" ]; then
    fail "$f frontmatter has no description:"
    FM_BAD=$((FM_BAD + 1))
  fi

  printf '%s\t%s\n' "$name" "$f" >> "$TMP/names.tsv"
done < "$TMP/files.txt"

[ "$FM_BAD" -eq 0 ] && pass "all $AGENT_COUNT definitions carry name: and description:"

# ------------------------------------------------------------------ collisions

section "Name collisions"

cut -f1 "$TMP/names.tsv" | sort | uniq -d > "$TMP/dupnames.txt"
if [ -s "$TMP/dupnames.txt" ]; then
  while IFS= read -r n; do
    fail "duplicate agent name '$n' in: $(grep -F "$(printf '%s\t' "$n")" "$TMP/names.tsv" | cut -f2 | tr '\n' ' ')"
  done < "$TMP/dupnames.txt"
else
  pass "no duplicate name: values across both directories"
fi

# a file present in both directories is the same agent twice
comm -12 \
  <(find "$LOADED_DIR" -maxdepth 1 -name '*.md' -exec basename {} \; 2>/dev/null | sort) \
  <(find "$RESERVE_DIR" -maxdepth 1 -name '*.md' -exec basename {} \; 2>/dev/null | sort) \
  > "$TMP/bothdirs.txt"
if [ -s "$TMP/bothdirs.txt" ]; then
  while IFS= read -r b; do
    fail "$b exists in both $LOADED_DIR and $RESERVE_DIR -- activation is a move, not a copy"
  done < "$TMP/bothdirs.txt"
else
  pass "no definition exists in both directories"
fi

# ----------------------------------------------------------------- ACL agents

section "ACL agent instructions"

ACL_BAD=0
ACL_COUNT=0
for f in "$LOADED_DIR"/acl-*.md; do
  [ -e "$f" ] || continue
  ACL_COUNT=$((ACL_COUNT + 1))
  b="$(basename "$f")"

  case "$(sed -n 's/^name:[[:space:]]*//p' "$f" | head -n1)" in
    "ACL "*) ;;
    *) fail "$b: name: must start with 'ACL ' so it is distinguishable from upstream"
       ACL_BAD=$((ACL_BAD + 1)) ;;
  esac

  grep -qE '^## (What you refuse|Standing prohibitions)' "$f" \
    || { fail "$b: no refusal section -- an agent with no refusals is decoration"
         ACL_BAD=$((ACL_BAD + 1)); }

  grep -qE '(app/|docs/|resources/|database/|tests/|\.claude/)' "$f" \
    || { fail "$b: names no real repository path"
         ACL_BAD=$((ACL_BAD + 1)); }

  grep -qE '(docs/adr|ACL_DEVELOPMENT_CONSTITUTION|ACL_MASTER_SPECIFICATION|\.ai/guidelines/ACL\.md|AGENT_GOVERNANCE)' "$f" \
    || { fail "$b: cites none of ACL's authoritative documents"
         ACL_BAD=$((ACL_BAD + 1)); }
done

if [ "$ACL_COUNT" -eq 0 ]; then
  fail "no acl-*.md agents found in $LOADED_DIR"
elif [ "$ACL_BAD" -eq 0 ]; then
  pass "$ACL_COUNT ACL agents name real paths, cite authority, and state refusals"
fi

# ------------------------------------------------------------------- manifest

section "Manifest"

if [ ! -f "$MANIFEST" ]; then
  fail "cannot validate the manifest -- it does not exist"
else
  EXPECTED_HEADER="$(printf 'file\tcategory\tloaded\tupstream_path\tadapted\tsha256')"
  if [ "$(head -n1 "$MANIFEST" | tr -d '\r')" = "$EXPECTED_HEADER" ]; then
    pass "header is file/category/loaded/upstream_path/adapted/sha256"
  else
    fail "unexpected header: $(head -n1 "$MANIFEST")"
  fi

  ROWS="$(($(wc -l < "$MANIFEST" | tr -d ' ') - 1))"
  if [ "$ROWS" -eq "$AGENT_COUNT" ]; then
    pass "$ROWS rows for $AGENT_COUNT definitions"
  else
    fail "$ROWS manifest rows but $AGENT_COUNT definitions on disk"
  fi

  # rows -> files, categories, loaded column, provenance columns, hashes
  ROW_BAD=0
  HASH_BAD=0
  while IFS="$(printf '\t')" read -r file category loaded upath adapted sha; do
    [ "$file" = "file" ] && continue
    [ -z "$file" ] && continue

    if [ -f "$LOADED_DIR/$file" ]; then
      actual_loaded="yes"; path="$LOADED_DIR/$file"
    elif [ -f "$RESERVE_DIR/$file" ]; then
      actual_loaded="no";  path="$RESERVE_DIR/$file"
    else
      fail "manifest row '$file' has no file in either directory"
      ROW_BAD=$((ROW_BAD + 1))
      continue
    fi

    [ "$loaded" = "$actual_loaded" ] \
      || { fail "$file: loaded=$loaded but it lives in ${path%/*}"; ROW_BAD=$((ROW_BAD + 1)); }

    case "$category" in
      ACL|CORE|SPECIALIST|PERIODIC|NOT_REQUIRED) ;;
      *) fail "$file: category '$category' is not one of ACL/CORE/SPECIALIST/PERIODIC/NOT_REQUIRED"
         ROW_BAD=$((ROW_BAD + 1)) ;;
    esac

    case "$file" in
      acl-*)
        [ "$category" = "ACL" ] || { fail "$file: an acl-* agent must be category ACL"; ROW_BAD=$((ROW_BAD + 1)); }
        [ "$upath" = "-" ] || { fail "$file: ACL agents have no upstream_path"; ROW_BAD=$((ROW_BAD + 1)); }
        [ "$adapted" = "n-a" ] || { fail "$file: ACL agents record adapted=n-a"; ROW_BAD=$((ROW_BAD + 1)); }
        ;;
      *)
        [ "$category" != "ACL" ] || { fail "$file: category ACL but the filename is not acl-*"; ROW_BAD=$((ROW_BAD + 1)); }
        [ "$upath" != "-" ] || { fail "$file: upstream-derived agent has no upstream_path recorded"; ROW_BAD=$((ROW_BAD + 1)); }
        [ "$adapted" = "no" ] || warn "$file: adapted=$adapted -- an edited upstream file breaks byte-identity"
        ;;
    esac

    if [ -n "$sha" ]; then
      current="$(sha256sum "$path" | awk '{print $1}')"
      if [ "$current" != "$sha" ]; then
        fail "$file: sha256 drift -- recorded ${sha:0:12}..., actual ${current:0:12}..."
        HASH_BAD=$((HASH_BAD + 1))
      fi
    else
      fail "$file: no sha256 recorded"
      ROW_BAD=$((ROW_BAD + 1))
    fi
  done < "$MANIFEST"

  [ "$ROW_BAD" -eq 0 ] && pass "every row resolves to a file with a valid category and provenance"
  [ "$HASH_BAD" -eq 0 ] && pass "every definition matches its recorded sha256"

  # files -> rows
  cut -f1 "$MANIFEST" | tail -n +2 | sort > "$TMP/manifest-files.txt"
  sed 's|.*/||' "$TMP/files.txt" | sort > "$TMP/disk-files.txt"
  comm -23 "$TMP/disk-files.txt" "$TMP/manifest-files.txt" > "$TMP/unlisted.txt"
  if [ -s "$TMP/unlisted.txt" ]; then
    while IFS= read -r u; do fail "$u exists on disk but has no manifest row"; done < "$TMP/unlisted.txt"
  else
    pass "no definition is missing from the manifest"
  fi

  printf '  '
  awk -F'\t' 'NR>1 {c[$2"/"$3]++} END {for (k in c) printf "%s=%s  ", k, c[k]; print ""}' "$MANIFEST"
fi

# --------------------------------------------------------------- documentation

section "Documentation"

REQUIRED_DOCS="
CLAUDE.md
docs/ACL_DEVELOPMENT_CONSTITUTION.md
docs/agent-system/README.md
docs/agent-system/AGENT_GOVERNANCE.md
docs/agent-system/AGENT_ROUTING.md
docs/agent-system/AGENCY_AGENT_COVERAGE.md
docs/agent-system/UPSTREAM_AGENCY_AGENTS.md
docs/agent-system/AGENT_CUSTOMIZATION.md
docs/agent-system/AGENT_UPDATE_PROCESS.md
docs/agent-system/AGENT_SECURITY.md
docs/agent-system/agents/architecture.md
docs/agent-system/agents/engineering.md
docs/agent-system/agents/ai.md
docs/agent-system/agents/security.md
docs/agent-system/agents/testing.md
docs/agent-system/agents/devops.md
docs/agent-system/agents/product.md
docs/agent-system/agents/documentation.md
"

DOC_MISSING=0
for d in $REQUIRED_DOCS; do
  [ -f "$d" ] || { fail "$d is missing"; DOC_MISSING=$((DOC_MISSING + 1)); }
done
[ "$DOC_MISSING" -eq 0 ] && pass "all 18 required documents exist"

if grep -q '\.claude/agents' CLAUDE.md 2>/dev/null && grep -q 'docs/agent-system' CLAUDE.md 2>/dev/null; then
  pass "CLAUDE.md points at .claude/agents/ and docs/agent-system/"
else
  fail "CLAUDE.md must reference both .claude/agents/ and docs/agent-system/"
fi

# every relative markdown link in the agent-system docs must resolve
LINK_BAD=0
LINK_TOTAL=0
for f in CLAUDE.md docs/ACL_DEVELOPMENT_CONSTITUTION.md \
         docs/agent-system/*.md docs/agent-system/agents/*.md; do
  [ -f "$f" ] || continue
  dir="$(dirname "$f")"
  grep -oE '\]\([^)]+\)' "$f" | sed 's/^](//; s/)$//' | while IFS= read -r link; do
    case "$link" in
      http*|\#*|mailto:*) continue ;;
    esac
    target="${link%%#*}"
    [ -z "$target" ] && continue
    ( cd "$dir" && [ -e "$target" ] ) || printf '%s -> %s\n' "$f" "$link"
  done >> "$TMP/badlinks.txt"
  LINK_TOTAL=$((LINK_TOTAL + $(grep -cE '\]\([^)]+\)' "$f")))
done

if [ -s "$TMP/badlinks.txt" ]; then
  while IFS= read -r bad; do fail "broken link: $bad"; LINK_BAD=$((LINK_BAD + 1)); done < "$TMP/badlinks.txt"
else
  pass "every relative link in the agent-system docs resolves ($LINK_TOTAL links checked)"
fi

# ----------------------------------------------------------------- provenance

section "Provenance"

PROV="docs/agent-system/UPSTREAM_AGENCY_AGENTS.md"
if [ -f "$PROV" ]; then
  grep -q 'github.com/msitarzewski/agency-agents' "$PROV" \
    && pass "upstream repository recorded" \
    || fail "$PROV does not name the upstream repository"
  grep -qE '`[0-9a-f]{40}`' "$PROV" \
    && pass "upstream commit recorded as a full sha" \
    || fail "$PROV does not pin a 40-character upstream commit"
  grep -qi 'MIT' "$PROV" \
    && pass "upstream license recorded" \
    || warn "$PROV does not state the upstream license"
else
  fail "$PROV is missing -- provenance is not optional"
fi

EXCLUSIONS=".claude/agent-exclusions.txt"
if [ -f "$EXCLUSIONS" ]; then
  EXCL_BAD=0
  EXCL_N=0
  while IFS="$(printf '\t')" read -r prefix reason; do
    case "$prefix" in ''|'#'*) continue ;; esac
    EXCL_N=$((EXCL_N + 1))
    [ -n "$reason" ] || { fail "$EXCLUSIONS: '$prefix' has no reason"; EXCL_BAD=$((EXCL_BAD + 1)); }
    # an exclusion that is actually tracked is a contradiction
    if cut -f4 "$MANIFEST" 2>/dev/null | grep -qF "$prefix"; then
      fail "$EXCLUSIONS excludes '$prefix' but the manifest tracks a file under it"
      EXCL_BAD=$((EXCL_BAD + 1))
    fi
  done < "$EXCLUSIONS"
  [ "$EXCL_BAD" -eq 0 ] && pass "$EXCL_N exclusions recorded with reasons and none is tracked"
else
  fail "$EXCLUSIONS is missing -- 'do not just install everything' has to be written down"
fi

# -------------------------------------------------------------------- secrets

section "Secrets"

# Token-shaped literals. Deliberately requires realistic length: a placeholder like
# sk-REPLACE-ME is what documentation should use and must not trip the scan.
SECRET_RE='(sk-ant-[A-Za-z0-9_-]{20,}|sk-[A-Za-z0-9]{32,}|ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]{10,}|AIza[0-9A-Za-z_-]{30,})'
KEY_HEADER='-----BEGIN [A-Z ]*PRIVATE KEY-----'
KEY_BODY='^[A-Za-z0-9+/=]{40,}$'

SCAN_PATHS="$LOADED_DIR $RESERVE_DIR $MANIFEST $EXCLUSIONS CLAUDE.md docs/ACL_DEVELOPMENT_CONSTITUTION.md docs/agent-system scripts/agents"
[ -d ".claude/skills" ] && SCAN_PATHS="$SCAN_PATHS .claude/skills"

: > "$TMP/secrets.txt"
grep -rEIn "$SECRET_RE" $SCAN_PATHS >> "$TMP/secrets.txt" 2>/dev/null

# Key material, not a mention of one. A BEGIN header only counts as a finding when the
# same file also carries base64 body lines: security-senior-secops.md lists the headers
# a scanner should look for, and that upstream file must stay byte-identical.
grep -rlEI "$KEY_HEADER" $SCAN_PATHS 2>/dev/null | while IFS= read -r f; do
  grep -qE "$KEY_BODY" "$f" && grep -nE "$KEY_HEADER" "$f" | sed "s|^|$f:|"
done >> "$TMP/secrets.txt"

if [ -s "$TMP/secrets.txt" ]; then
  fail "credential-shaped strings found -- STOP, do not commit, and report without reproducing the value:"
  cut -d: -f1,2 "$TMP/secrets.txt" | sort -u | while IFS= read -r hit; do printf '        %s\n' "$hit"; done
else
  pass "no credential-shaped strings in the agent system or skills"
fi

# ---------------------------------------------------------------------- Git

section "Git tracking"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  UNTRACKED="$(git ls-files --others --exclude-standard -- "$LOADED_DIR" "$RESERVE_DIR" "$MANIFEST" docs/agent-system CLAUDE.md docs/ACL_DEVELOPMENT_CONSTITUTION.md scripts/agents | wc -l | tr -d ' ')"
  if [ "$UNTRACKED" -eq 0 ]; then
    pass "every agent-system file is tracked or staged"
  else
    fail "$UNTRACKED agent-system files are untracked -- the repository is the source of truth"
  fi

  IGNORED="$(git check-ignore "$LOADED_DIR" "$RESERVE_DIR" "$MANIFEST" 2>/dev/null | wc -l | tr -d ' ')"
  if [ "$IGNORED" -eq 0 ]; then
    pass "nothing about the agent system is git-ignored"
  else
    fail "part of the agent system is git-ignored -- it would not survive a clone"
  fi
else
  warn "not inside a Git work tree -- portability cannot be checked"
fi

# ------------------------------------------------------------------- verdict

printf '\n'
if [ "$FAILURES" -eq 0 ]; then
  printf '\033[32mAgent system valid.\033[0m %s definitions, %s loaded.\n' \
    "$AGENT_COUNT" "$(find "$LOADED_DIR" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ')"
  exit 0
fi

printf '\033[31m%s check(s) failed.\033[0m\n' "$FAILURES"
exit 1


