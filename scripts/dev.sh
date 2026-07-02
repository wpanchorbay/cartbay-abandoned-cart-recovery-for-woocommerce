#!/bin/bash
# scripts/dev.sh
# Minimal dev runner for Linux/macOS.

set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "Starting CartBay dev tooling..."

# Prefer Bun if available; fallback to npm.
if command -v bun >/dev/null 2>&1; then
	(cd "$ROOT_DIR" && bun start)
elif command -v npm >/dev/null 2>&1; then
	(cd "$ROOT_DIR" && npm start)
else
	echo "Error: neither bun nor npm is available in PATH."
	exit 1
fi
