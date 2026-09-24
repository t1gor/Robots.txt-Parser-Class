#!/usr/bin/env bash
#
# Renders a changelog section with git-cliff and prints it to stdout.
# With --write it is also spliced into CHANGELOG.md at the sentinel comment,
# so the hand-written 0.3.0 and older history below it is never regenerated.
#
#   bin/changelog.sh                  # preview the unreleased section
#   bin/changelog.sh --current        # the section for the tag at HEAD
#   bin/changelog.sh --write --current   # what .github/workflows/release.yml runs
#
# git-cliff is used from PATH when present, otherwise from a pinned Docker image -
# no local toolchain is assumed.

set -euo pipefail

CLIFF_IMAGE="${CLIFF_IMAGE:-orhunp/git-cliff:2.14.2}"
MARKER='<!-- changelog-insert'
# Everything up to v0.3.0 is pre-conventional (`Bump …`, `:(`, `Fix formattig`).
FLOOR='v0.3.0'

root="$(git rev-parse --show-toplevel)"
cd "$root"

write=0
args=()
for arg in "$@"; do
	case "$arg" in
		--write) write=1 ;;
		*) args+=("$arg") ;;
	esac
done

bounded=0
for arg in ${args[@]+"${args[@]}"}; do
	case "$arg" in
		--current | --latest | --unreleased | *..*) bounded=1 ;;
	esac
done
# Unbounded runs would reach the pre-conventional history, so floor them at v0.3.0.
(( bounded )) || args+=(--unreleased "${FLOOR}..HEAD")

run_cliff() {
	if command -v git-cliff > /dev/null 2>&1; then
		git-cliff "$@"
		return
	fi
	# A worktree keeps its objects in the main checkout - mount that at the same path too.
	local common mounts=(-v "$root:$root")
	common="$(git rev-parse --path-format=absolute --git-common-dir)"
	case "$common" in "$root"/*) ;; *) mounts+=(-v "$common:$common") ;; esac
	docker run --rm -u "$(id -u):$(id -g)" "${mounts[@]}" -w "$root" "$CLIFF_IMAGE" "$@"
}

# sed drops the leading blank lines, so the splice below controls the spacing.
section="$(run_cliff --config cliff.toml "${args[@]}" | sed '/./,$!d')"

if [[ -z "${section//[[:space:]]/}" ]]; then
	echo "changelog: nothing to render for '${args[*]}'" >&2
	exit 0
fi

printf '%s\n' "$section"

(( write )) || exit 0

grep -qF -- "$MARKER" CHANGELOG.md || {
	echo "changelog: sentinel '$MARKER' not found in CHANGELOG.md" >&2
	exit 1
}

# Insert right below the sentinel: newest section first, frozen history untouched.
# Via a file rather than -v: BSD awk rejects newlines in variable assignments.
rendered="$(mktemp)"
trap 'rm -f "$rendered"' EXIT
printf '%s\n' "$section" > "$rendered"

awk -v marker="$MARKER" -v src="$rendered" '
	{ print }
	index($0, marker) && !done {
		print ""
		while ((getline line < src) > 0) print line
		done = 1
	}
' CHANGELOG.md > CHANGELOG.md.new

# The hand-written history has to come out byte for byte as it went in.
frozen() { awk '/^## \[0\.3\.0\]/ { p = 1 } p' "$1"; }
if ! frozen CHANGELOG.md | cmp -s - <(frozen CHANGELOG.md.new); then
	rm -f CHANGELOG.md.new
	echo "changelog: the splice would have altered the frozen 0.3.0 history - aborted" >&2
	exit 1
fi

mv CHANGELOG.md.new CHANGELOG.md
