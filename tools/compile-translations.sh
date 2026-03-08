#!/bin/bash
# Build script to compile WordPress translations (.po to .mo files)
# Usage: ./build/compile-translations.sh
# Or:    make translations

set -e

LANG_DIR="languages"
SUCCESS=0
FAILED=0

echo "🔨 Compiling WordPress Translations"
echo "=================================="

# Check if languages directory exists
if [ ! -d "$LANG_DIR" ]; then
    echo "❌ Error: $LANG_DIR directory not found"
    exit 1
fi

# Check if msgfmt is available
if ! command -v msgfmt &> /dev/null; then
    echo ""
    echo "❌ Error: msgfmt (GNU gettext) is not installed"
    echo ""
    echo "Install gettext tools:"
    echo "  macOS (Homebrew):  brew install gettext && brew link gettext --force"
    echo "  macOS (MacPorts):  sudo port install gettext"
    echo "  Ubuntu/Debian:     sudo apt-get install gettext"
    echo "  Fedora/RHEL:       sudo dnf install gettext"
    echo "  Windows (Chocolatey): choco install gnu-gettext"
    echo ""
    exit 1
fi

# Get msgfmt version
echo "Using: $(msgfmt --version | head -n 1)"
echo ""

# Compile all .po files
for po_file in "$LANG_DIR"/*.po; do
    if [ ! -f "$po_file" ]; then
        continue
    fi
    
    # Get the base filename without extension
    base_name=$(basename "$po_file" .po)
    mo_file="$LANG_DIR/${base_name}.mo"
    
    echo -n "Compiling $base_name... "
    
    # Compile with error checking
    if msgfmt -c -v -o "$mo_file" "$po_file" 2>&1 | grep -q "error"; then
        echo "❌ FAILED"
        FAILED=$((FAILED + 1))
    else
        # Validate the compiled .mo file
        if file "$mo_file" | grep -q "GNU MO"; then
            echo "✅ OK"
            SUCCESS=$((SUCCESS + 1))
            
            # Show file size
            size=$(du -h "$mo_file" | cut -f1)
            echo "   → $mo_file ($size)"
        else
            echo "❌ FAILED (invalid .mo file)"
            FAILED=$((FAILED + 1))
        fi
    fi
done

echo ""
echo "=================================="
echo "Results: $SUCCESS compiled, $FAILED failed"
echo ""

if [ $FAILED -gt 0 ]; then
    echo "❌ Some translations failed to compile"
    exit 1
else
    echo "✅ All translations compiled successfully"
    exit 0
fi
