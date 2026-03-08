# WooCommerce AI Review Manager - Translation Files

This directory contains translation files for the WooCommerce AI Review Manager plugin.

## File Structure

- **wc-ai-review-manager.pot** - Translation template (master file with all translatable strings)
- **wc-ai-review-manager-de_DE.po** - German translation (Deutsch)
- **wc-ai-review-manager-es_ES.po** - Spanish translation (Español)
- **wc-ai-review-manager-de_DE.mo** - German compiled translation (generated from .po)
- **wc-ai-review-manager-es_ES.mo** - Spanish compiled translation (generated from .po)

## How Translations Work in WordPress

WordPress uses the GNU gettext system for translations:

1. **POT file** (Portable Object Template) - Contains all translatable strings from the source code
2. **PO files** (Portable Object) - Human-readable translation files for each language
3. **MO files** (Machine Object) - Binary compiled versions of PO files (used by WordPress)

The plugin loads translations using:
```php
load_plugin_textdomain( 'wc-ai-review-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
```

WordPress automatically loads the appropriate .mo file based on the site's language setting.

## Adding New Translations

### For Translators

1. Copy `wc-ai-review-manager.pot` to `wc-ai-review-manager-XX_YY.po` (where XX_YY is the language code)
2. Open the .po file in a translation editor like:
   - [Poedit](https://poedit.net/) (recommended)
   - [Lokalize](https://l10n.kde.org/lokalize/)
   - VS Code with "gettext" extension
   - Any text editor
3. Fill in the `msgstr` values with translations
4. Save the file
5. Generate the .mo file (see "Compiling Translations" below)

### Language Codes

WordPress uses ISO 639-1 language codes with optional country codes:
- `de_DE` - German (Germany)
- `de_AT` - German (Austria)
- `de_CH` - German (Switzerland)
- `es_ES` - Spanish (Spain)
- `es_MX` - Spanish (Mexico)
- `fr_FR` - French (France)
- `it_IT` - Italian (Italy)
- `nl_NL` - Dutch (Netherlands)
- `pt_BR` - Portuguese (Brazil)
- `pt_PT` - Portuguese (Portugal)
- `ja` - Japanese
- `zh_CN` - Chinese (Simplified)
- `zh_TW` - Chinese (Traditional)

See the full list: https://make.wordpress.org/polyglots/teams/

## Compiling Translations (.po to .mo)

### Using Poedit (GUI)
1. Open the .po file in Poedit
2. Click "File" → "Compile MO"
3. The .mo file is automatically saved

### Using Command Line (msgfmt)

If you have `msgfmt` installed (part of GNU gettext tools):

```bash
# Install on macOS
brew install gettext
brew link gettext --force

# Or using MacPorts
sudo port install gettext

# Compile a single translation
msgfmt -o wc-ai-review-manager-de_DE.mo wc-ai-review-manager-de_DE.po

# Compile all translations
for po_file in *.po; do
  mo_file="${po_file%.po}.mo"
  msgfmt -o "$mo_file" "$po_file"
done
```

### Using a PHP Script

```php
<?php
// php compile-translations.php
$files = glob('wc-ai-review-manager-*.po');

foreach ($files as $po_file) {
    $mo_file = str_replace('.po', '.mo', $po_file);
    
    // Read PO file and parse
    $po_content = file_get_contents($po_file);
    
    // Use Poedit or msgfmt CLI
    $output = shell_exec("msgfmt -o '$mo_file' '$po_file'");
    
    if (file_exists($mo_file)) {
        echo "✓ Compiled: $mo_file\n";
    } else {
        echo "✗ Failed: $mo_file\n";
    }
}
?>
```

### Using npm Package

```bash
# Install gettext-translator or similar
npm install -g gettext-translator

# Compile
node -e "const { gettext } = require('gettext-translator'); ..."
```

## Current Translations

### English (en_US)
- Status: Default
- Strings: ~80 unique translatable strings
- Coverage: 100% (system language)

### German (de_DE)
- Status: ✅ Complete
- Translator: Loki
- Completion: 100% (all 80+ strings translated)
- Date: 2026-03-08

### Spanish (es_ES)
- Status: ✅ Complete
- Translator: Loki
- Completion: 100% (all 80+ strings translated)
- Date: 2026-03-08

## Testing Translations

### In WordPress

1. Go to **WordPress Admin → Settings → General**
2. Change "Site Language" to your target language (e.g., "Deutsch - Germany")
3. Make sure the .mo file exists in the languages directory
4. Visit the plugin pages:
   - WooCommerce Settings → AI Review Manager
   - WooCommerce → Email Templates
   - Dashboard → Sentiment Analytics
5. Verify strings are translated correctly

### Command Line Check

```bash
# List all strings in the plugin
grep -rho "_e(\|__(\|esc_html_e(\|esc_html__(" includes/ --include="*.php"

# Verify translation file is valid
msgfmt -c -v -o /dev/null wc-ai-review-manager-de_DE.mo

# Extract statistics
file wc-ai-review-manager-de_DE.mo
```

## Submitting Translations

To contribute a new translation:

1. Create a .po file with the language code (e.g., `wc-ai-review-manager-fr_FR.po`)
2. Translate all strings in the msgstr lines
3. Compile to .mo file
4. Test in a WordPress installation
5. Submit a Pull Request with both .po and .mo files
6. Include information:
   - Language and region (e.g., "French - France")
   - Translator name/contact
   - Translation notes or context

## Translation Best Practices

### For Translators

1. **Preserve placeholders**: Keep `%s`, `%d`, `<strong>`, `</strong>` exactly as they appear
   - Bad: `Total analizadas: %d` (missing placeholder)
   - Good: `Total de reseñas analizadas: <strong>%d</strong>`

2. **Maintain tone**: Match the friendly, professional tone of the English strings

3. **Use consistent terminology**:
   - "Review" vs "Review invitation" vs "Sentiment"
   - Create a glossary for your language

4. **Context matters**: The first line shows context:
   ```
   #: includes/class-dashboard.php:23
   msgctxt "Dashboard page title"
   msgid "AI Review Manager"
   ```

5. **Pluralization**: Some strings use `nplurals` for singular/plural forms
   ```php
   _n( 'One review', '%d reviews', $count, 'wc-ai-review-manager' )
   ```

### For Developers

When adding new strings to the plugin:

1. Use standard WordPress i18n functions:
   - `__()` or `esc_html__()` for general strings
   - `_e()` or `esc_html_e()` for direct output
   - `_n()` for plurals
   - `_x()` or `_nx()` for context

2. Always include the text domain: `'wc-ai-review-manager'`

3. Regenerate the .pot file periodically:
   ```bash
   # Using wp-cli (if available)
   wp i18n make-pot . languages/wc-ai-review-manager.pot
   
   # Or manually using the tools above
   ```

4. Update .po files to include new strings:
   ```bash
   # In Poedit: File → Update from POT
   # Or using msgmerge:
   msgmerge -U wc-ai-review-manager-de_DE.po wc-ai-review-manager.pot
   
   # Then recompile .mo files
   ```

## Troubleshooting

### Translations Not Showing

1. **Check the .mo file exists**:
   - File path must be: `languages/wc-ai-review-manager-LANGUAGE.mo`
   - Language code must match WordPress locale (e.g., "de_DE" not "de")

2. **Verify WordPress language setting**:
   - WordPress Admin → Settings → General → Site Language
   - Must match exactly with .mo file language code

3. **Verify plugin text domain**:
   - All strings must use: `'wc-ai-review-manager'`
   - Mismatched text domain = untranslated strings

4. **Check .mo file validity**:
   ```bash
   file wc-ai-review-manager-de_DE.mo
   # Should output: "GNU MO file, version 0"
   ```

5. **Test with WP-CLI**:
   ```bash
   wp language list
   wp language core list
   ```

### Compilation Errors

- **"Bad file descriptor"** - .po file has syntax errors
  - Open in Poedit and save again (it will fix errors)
  
- **"No such file or directory"** - msgfmt not installed
  - Install gettext tools: `brew install gettext`

- **Character encoding issues** - .po file encoding must be UTF-8
  - Open in text editor and ensure UTF-8 (no BOM) encoding

## Resources

- [WordPress Plugin Localization Guide](https://developer.wordpress.org/plugins/internationalization/localization/)
- [GNU gettext Reference](https://www.gnu.org/software/gettext/manual/)
- [Poedit Documentation](https://poedit.net/manual)
- [WordPress Polyglots](https://make.wordpress.org/polyglots/)
- [ISO 639 Language Codes](https://en.wikipedia.org/wiki/ISO_639)

## License

Translations are released under the same license as the plugin: **GPL v2 or later**

---

**Translation Status:** Ready for community contributions

For translation updates or to add a new language, please visit the [project repository](https://github.com/valhalla-open-org/wc-ai-review-manager) and submit a Pull Request.
