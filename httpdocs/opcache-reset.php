<?php
// Reset opcache
if (function_exists('opcache_reset')) {
 opcache_reset();
 echo "Opcache reset\n";
}
// Clear all cached files
if (function_exists('opcache_invalidate')) {
 $files = get_included_files();
 foreach ($files as $f) {
 @opcache_invalidate($f, true);
 }
 echo count($files) . " files invalidated\n";
}
echo "Done: " . date('Y-m-d H:i:s');
