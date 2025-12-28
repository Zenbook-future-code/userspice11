#!/bin/bash

# Define the source directory (where git is initialized)
SOURCE_DIR="/var/www/html/us5"

# Define the target patch directory
PATCH_DIR="$SOURCE_DIR/patch"

# Define the specific directories to scan (relative to SOURCE_DIR)
DIRS_TO_SCAN=("users" "usersc")

# Define manual exclusion patterns for files you DON'T want in the patch
# These patterns will be used with 'grep -v' to exclude matching paths.
EXCLUDE_PATTERNS=(
    "users/init.php"
    "\.zip$"              # Matches any file ending in .zip
    "\.sql$"              # Matches any file ending in .sql
    "\.htaccess$"         # Matches any file named .htaccess
    "errors.log$"         # Matches any file named errors.log
    "\.logs\.php$"        # Matches any file ending in .logs.php
    "usersc/includes/totp_key.php"
)

# Combine patterns into a single grep -v string (using | as OR)
EXCLUDE_FILTER=$(IFS='|'; echo "${EXCLUDE_PATTERNS[*]}")

# --- Setup ---

# Navigate to the source directory
cd "$SOURCE_DIR" || { echo "Error: Could not navigate to $SOURCE_DIR"; exit 1; }

# Create the patch directory if it doesn't exist
mkdir -p "$PATCH_DIR"

echo "🔍 Scanning for modified and new files in: ${DIRS_TO_SCAN[*]}"
echo "🚫 Excluding files matching pattern: $EXCLUDE_FILTER"

# --- Find and Copy Files ---

# 1. 'git ls-files -m -o' lists Modified (-m) and Untracked (-o) files.
# 2. '--exclude-standard' ensures files in .gitignore are excluded from -o.
# 3. 'grep -v' manually excludes the patterns defined above.

git ls-files -m -o --exclude-standard -- ${DIRS_TO_SCAN[*]} |
grep -E -v "$EXCLUDE_FILTER" |
while read FILE_PATH; do
    # Define the full path for the target copy, preserving the directory structure
    TARGET_PATH="$PATCH_DIR/$FILE_PATH"

    # Create the necessary directory structure in the patch folder
    mkdir -p "$(dirname "$TARGET_PATH")"

    # Copy the file (Note: standard 'cp' will overwrite existing files without prompting)
    cp "$FILE_PATH" "$TARGET_PATH"
    echo "  Copied (Overwrote if existed): $FILE_PATH -> $TARGET_PATH"
done

echo "✅ Patch creation complete. Check the contents of $PATCH_DIR"