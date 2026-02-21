#!/usr/bin/env bash
set -euo pipefail

#
# Release automation script
# Usage: ./scripts/release.sh [version]
#
# If no version is provided, it will be auto-detected from commit history.
#

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[release]${NC} $*"; }
warn()  { echo -e "${YELLOW}[release]${NC} $*"; }
error() { echo -e "${RED}[release]${NC} $*" >&2; exit 1; }

# -------------------------------------------------------------------
# Pre-flight checks
# -------------------------------------------------------------------

command -v git-cliff >/dev/null 2>&1 || error "git-cliff is not installed. Run: brew install git-cliff"
command -v gh >/dev/null 2>&1        || error "GitHub CLI (gh) is not installed. Run: brew install gh"
command -v jq >/dev/null 2>&1        || error "jq is not installed. Run: brew install jq"

# Clean working tree
if [[ -n "$(git status --porcelain)" ]]; then
    error "Working tree is not clean. Commit or stash changes first."
fi

# Must be on main
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$BRANCH" != "main" ]]; then
    error "You must be on the 'main' branch to release (currently on '$BRANCH')."
fi

# Up to date with remote
git fetch origin main --quiet
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
if [[ "$LOCAL" != "$REMOTE" ]]; then
    error "Local branch is not up to date with origin/main. Pull or push first."
fi

# -------------------------------------------------------------------
# Determine version
# -------------------------------------------------------------------

if [[ -n "${1:-}" ]]; then
    VERSION="$1"
    # Strip leading v if provided
    VERSION="${VERSION#v}"
    info "Using manually specified version: $VERSION"
else
    BUMPED=$(git-cliff --bumped-version 2>/dev/null || true)
    if [[ -z "$BUMPED" ]]; then
        error "Could not determine next version. Are there any conventional commits since the last tag?"
    fi
    VERSION="${BUMPED#v}"
    info "Auto-detected version: $VERSION"
fi

TAG="v$VERSION"

# Check tag doesn't already exist
if git rev-parse "$TAG" >/dev/null 2>&1; then
    error "Tag $TAG already exists."
fi

# -------------------------------------------------------------------
# Generate changelog
# -------------------------------------------------------------------

info "Generating changelog..."
git-cliff --tag "$TAG" -o CHANGELOG.md

# -------------------------------------------------------------------
# Bump version in files
# -------------------------------------------------------------------

if [[ -f "nativephp.json" ]]; then
    info "Bumping version in nativephp.json..."
    jq --arg v "$VERSION" '.version = $v' nativephp.json > nativephp.json.tmp
    mv nativephp.json.tmp nativephp.json
fi

# -------------------------------------------------------------------
# Commit, tag, push
# -------------------------------------------------------------------

info "Committing release..."
git add CHANGELOG.md
[[ -f "nativephp.json" ]] && git add nativephp.json
git commit -m "chore(release): v$VERSION"

info "Tagging $TAG..."
git tag "$TAG"

info "Pushing to origin..."
git push origin main
git push origin "$TAG"

# -------------------------------------------------------------------
# GitHub release
# -------------------------------------------------------------------

info "Creating GitHub release..."
RELEASE_NOTES=$(git-cliff --latest --strip header)
gh release create "$TAG" --title "$TAG" --notes "$RELEASE_NOTES"

info "Released $TAG"
