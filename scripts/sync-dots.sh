#!/bin/bash
# sync-dots.sh - Synchronize dot-directories and meta files from config-assets branch

set -e

SOURCE_BRANCH="config-assets"

echo "Syncing shared assets from $SOURCE_BRANCH..."

git checkout "$SOURCE_BRANCH" -- .
git reset HEAD .
git checkout HEAD -- .gitignore 2>/dev/null || true

echo "Done. Shared assets are updated and remain untracked."
