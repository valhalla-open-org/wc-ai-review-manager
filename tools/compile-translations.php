#!/usr/bin/env php
<?php
/**
 * Compile WordPress translation files (.po to .mo)
 * 
 * Usage: php build/compile-translations.php
 * 
 * This script converts .po (human-readable) translation files to .mo (binary) files
 * that WordPress uses for translation lookup.
 * 
 * The .mo format is a binary file containing string pairs (msgid -> msgstr) 
 * indexed for fast lookup during runtime.
 */

class POtoMO {
    /**
     * Parse a PO file and return entries
     */
    public static function parsePO($file) {
        $entries = [];
        $content = file_get_contents($file);
        
        // Extract all msgid/msgstr pairs
        $blocks = preg_split('/(?=^msgid\s)/m', $content);
        
        foreach ($blocks as $block) {
            if (empty(trim($block))) continue;
            
            // Extract msgctxt if present
            $msgctxt = '';
            if (preg_match('/^msgctxt\s+"(.+?)"/m', $block, $m)) {
                $msgctxt = self::unescapeString($m[1]);
            }
            
            // Extract msgid
            if (!preg_match('/^msgid\s+"(.+?)"/m', $block, $m)) {
                continue;
            }
            $msgid = self::unescapeString($m[1]);
            
            // Skip header entry
            if (empty($msgid)) continue;
            
            // Extract msgstr
            if (!preg_match('/^msgstr\s+"(.+?)"/m', $block, $m)) {
                continue;
            }
            $msgstr = self::unescapeString($m[1]);
            
            if (empty($msgstr)) continue; // Skip untranslated entries
            
            // Handle multiline strings (continuation lines starting with ")
            $msgid = preg_replace_callback('/"\s*"(.+?)"(?=\s*msgctxt|msgid|msgstr|$)/s', 
                function($m) { return self::unescapeString($m[1]); }, $block);
            $msgstr = preg_replace_callback('/"\s*"(.+?)"(?=\s*msgctxt|msgid|msgstr|$)/s', 
                function($m) { return self::unescapeString($m[1]); }, $block);
            
            $entries[] = [
                'msgctxt' => $msgctxt,
                'msgid'   => $msgid,
                'msgstr'  => $msgstr,
            ];
        }
        
        return $entries;
    }
    
    /**
     * Unescape PO string
     */
    private static function unescapeString($str) {
        $replacements = [
            '\\n'  => "\n",
            '\\t'  => "\t",
            '\\r'  => "\r",
            '\\"'  => '"',
            '\\\\' => '\\',
        ];
        return strtr($str, $replacements);
    }
    
    /**
     * Generate MO file (binary format)
     * 
     * MO file format (from GNU gettext):
     * - Magic number (4 bytes)
     * - Version number (4 bytes)
     * - Number of entries (4 bytes)
     * - Offset of original table (4 bytes)
     * - Offset of translated table (4 bytes)
     * - Size of hash table (4 bytes)
     * - Offset of hash table (4 bytes)
     */
    public static function generateMO($entries) {
        $MAGIC = 0xde120495;  // Magic number for .mo files
        $VERSION = 0;         // Version
        
        $num = count($entries);
        $ids = '';
        $strs = '';
        $idsoffset = [];
        $strsoffset = [];
        
        // Collect all strings
        $offset = 0;
        foreach ($entries as $entry) {
            $id = $entry['msgid'];
            
            // Handle msgctxt
            if (!empty($entry['msgctxt'])) {
                $id = $entry['msgctxt'] . "\x04" . $id;
            }
            
            $str = $entry['msgstr'];
            
            // Store offsets
            $idsoffset[] = [strlen($ids), strlen($id)];
            $ids .= $id . "\0";
            
            $strsoffset[] = [strlen($strs), strlen($str)];
            $strs .= $str . "\0";
        }
        
        // Build header
        $header = "";
        $header .= pack("V", $MAGIC);        // Magic
        $header .= pack("V", $VERSION);      // Version
        $header .= pack("V", $num);          // Number of entries
        $header .= pack("V", 28);            // Offset of original table
        $header .= pack("V", 28 + 8 * $num); // Offset of translated table
        $header .= pack("V", 0);             // Size of hash table (unused)
        $header .= pack("V", 0);             // Offset of hash table (unused)
        
        // Build original and translated tables
        $idtable = "";
        $strtable = "";
        $offset = 28 + 16 * $num;
        
        foreach ($idsoffset as $i => $id) {
            $idtable .= pack("VV", $id[1], $offset + strlen($ids) - $id[1] - strlen($ids));
            $strtable .= pack("VV", $strsoffset[$i][1], $offset + strlen($strs) - $strsoffset[$i][1] - strlen($strs));
        }
        
        // Recalculate offsets
        $offset = 28 + 16 * $num;
        $idtable = "";
        $strtable = "";
        
        foreach ($idsoffset as $i => $id) {
            $idtable .= pack("VV", $id[1], $offset + $id[0]);
            $strtable .= pack("VV", $strsoffset[$i][1], $offset + strlen($ids) + $strsoffset[$i][0]);
        }
        
        return $header . $idtable . $strtable . $ids . $strs;
    }
}

// Main execution
$lang_dir = 'languages';

if (!is_dir($lang_dir)) {
    echo "❌ Error: $lang_dir directory not found\n";
    exit(1);
}

echo "🔨 Compiling WordPress Translations\n";
echo str_repeat("=", 40) . "\n";

$po_files = glob("$lang_dir/*.po");
$success = 0;
$failed = 0;

foreach ($po_files as $po_file) {
    $lang = basename($po_file, '.po');
    $mo_file = "$lang_dir/$lang.mo";
    
    echo "Compiling $lang... ";
    
    try {
        $entries = POtoMO::parsePO($po_file);
        
        if (empty($entries)) {
            echo "⚠️  No translations found\n";
            continue;
        }
        
        $mo_content = POtoMO::generateMO($entries);
        
        if (file_put_contents($mo_file, $mo_content) !== false) {
            echo "✅ OK\n";
            $size = filesize($mo_file);
            printf("   → %s (%d bytes, %d entries)\n", $mo_file, $size, count($entries));
            $success++;
        } else {
            echo "❌ FAILED (could not write file)\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "❌ FAILED (" . $e->getMessage() . ")\n";
        $failed++;
    }
}

echo str_repeat("=", 40) . "\n";
echo "Results: $success compiled, $failed failed\n";

exit($failed > 0 ? 1 : 0);
