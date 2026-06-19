<?php
namespace App\Services;

require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * PleskService - Plesk API integration
 * Manages subdomain creation, SSL certificates, and domain configuration via Plesk
 */
class PleskService {
    
    private $pleskHost;
    private $pleskApiKey;
    private $pleskUser;
    private $pleskPassword;
    private $baseDomain;
    private $documentRoot;
    
    public function __construct() {
        $this->pleskHost = $_ENV['PLESK_HOST'] ?? 'localhost';
        $this->pleskApiKey = $_ENV['PLESK_API_KEY'] ?? null;
        $this->pleskUser = $_ENV['PLESK_USER'] ?? 'admin';
        $this->pleskPassword = $_ENV['PLESK_PASSWORD'] ?? null;
        $this->baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
        $this->documentRoot = $_ENV['APP_DOCUMENT_ROOT'] ?? '/var/www/vhosts/qordy.com/httpdocs';
        // Note: Passwordless sudo is configured in /etc/sudoers.d/qordy-plesk
        // No password needed for Plesk CLI commands
    }
    
    /**
     * Execute sudo command (passwordless sudo configured in sudoers)
     * @param string $command Command to execute
     * @return string|null Command output
     */
    private function executeSudoCommand(string $command): ?string {
 // SECURITY: Whitelist - only allowed binaries
 $allowedBinaries = ['/usr/sbin/plesk', '/usr/bin/mysql', '/usr/bin/mysqldump', '/bin/systemctl'];
 $isAllowed = false;
 foreach ($allowedBinaries as $binary) {
 if (strpos(trim($command), $binary) === 0) {
 $isAllowed = true;
 break;
 }
 }
 if (!$isAllowed) {
 throw new \InvalidArgumentException('Command not in whitelist');
 }

        // Use sudo -n (non-interactive) since passwordless sudo is configured
        // Sudoers file: /etc/sudoers.d/qordy-plesk allows qordy.com_jckqwoy6r4j to run /usr/sbin/plesk without password
        $fullCommand = "sudo -n " . $command;
        return shell_exec($fullCommand);
    }
    
    /**
     * Execute sudo command (static version for isAvailable)
     * @param string $command Command to execute
     * @return string|null Command output
     */
    private static function executeSudoCommandStatic(string $command): ?string {
        // Use sudo -n (non-interactive) since passwordless sudo is configured
        // Sudoers file: /etc/sudoers.d/qordy-plesk allows qordy.com_jckqwoy6r4j to run /usr/sbin/plesk without password
 // SECURITY: Whitelist - same as instance version
 $allowedBinaries = ['/usr/sbin/plesk', '/usr/bin/mysql', '/usr/bin/mysqldump', '/bin/systemctl'];
 $isAllowed = false;
 foreach ($allowedBinaries as $binary) {
 if (strpos(trim($command), $binary) === 0) {
 $isAllowed = true;
 break;
 }
 }
 if (!$isAllowed) {
 throw new \InvalidArgumentException('Command not in whitelist');
 }

        $fullCommand = "sudo -n " . $command;
        return shell_exec($fullCommand);
    }
    
    /**
     * Create subdomain using Plesk CLI
     * @param string $subdomain Subdomain name (e.g., "cafe")
     * @param string $customerId Customer ID
     * @return array ['success' => bool, 'message' => string, 'plesk_domain_id' => int|null]
     */
    public function createSubdomain(string $subdomain, string $customerId): array {
        try {
            $fullDomain = $subdomain . '.' . $this->baseDomain;
            
            // Log the operation
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Creating subdomain via Plesk', [
                    'subdomain' => $subdomain,
                    'full_domain' => $fullDomain,
                    'customer_id' => $customerId,
                    'base_domain' => $this->baseDomain
                ]);
            }
            
            // Check if subdomain already exists in Plesk
            // FIXED: Correct Plesk command syntax - --list doesn't accept -domain parameter
            // CRITICAL: Plesk CLI requires root privileges, use sudo with password
            $checkCmd = "/usr/sbin/plesk bin subdomain --list 2>&1";
            $checkOutput = $this->executeSudoCommand($checkCmd);
            
            // Check if subdomain exists in the output (format: subdomain.qordy.com)
            $fullDomainCheck = $subdomain . '.' . $this->baseDomain;
            if ($checkOutput && (strpos($checkOutput, $fullDomainCheck) !== false || strpos($checkOutput, $subdomain) !== false)) {
                // Subdomain already exists
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Subdomain already exists in Plesk', [
                        'subdomain' => $subdomain,
                        'full_domain' => $fullDomain
                    ]);
                }
                
                // Get subdomain ID
                $domainId = $this->getSubdomainId($subdomain);
                
                // Save to database
                $this->saveSubdomainToDatabase($subdomain, $customerId, $fullDomain, $domainId);
                
                return [
                    'success' => true,
                    'message' => 'Subdomain zaten mevcut',
                    'plesk_domain_id' => $domainId
                ];
            }
            
            // Create subdomain using Plesk CLI
            // Base directory where subdomain folders are created
            $baseDir = dirname($this->documentRoot); // /var/www/vhosts/qordy.com
            $expectedFolder = $baseDir . '/' . $fullDomain; // e.g., /var/www/vhosts/qordy.com/caddecafe.qordy.com
            
            // Create subdomain - Plesk will create folder, we'll rename if needed
            // CRITICAL: Plesk CLI requires root privileges, use sudo with password
            $createCmd = "/usr/sbin/plesk bin subdomain --create " . escapeshellarg($subdomain) . 
                        " -domain " . escapeshellarg($this->baseDomain) . " 2>&1";
            
            $output = $this->executeSudoCommand($createCmd);
            
            // Log subdomain creation
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('Plesk subdomain creation command', [
                    'subdomain' => $subdomain,
                    'full_domain' => $fullDomain,
                    'expected_folder' => $expectedFolder,
                    'command' => $createCmd
                ]);
            }
            
            // Log command output for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('Plesk subdomain creation command output', [
                    'subdomain' => $subdomain,
                    'command' => $createCmd,
                    'output' => $output,
                    'output_length' => strlen($output ?? ''),
                    'output_empty' => empty($output)
                ]);
            }
            
            // Check if creation was successful
            // IMPORTANT: Plesk returns "SUCCESS: Creation of domain 'subdomain.domain.com' completed."
            $isSuccess = false;
            if ($output) {
                $outputLower = strtolower(trim($output));
                // Check for explicit success messages
                $isSuccess = (
                    strpos($outputLower, 'success') !== false || 
                    strpos($outputLower, 'created') !== false ||
                    strpos($outputLower, 'completed') !== false
                );
                
                // Also check for error indicators
                if (strpos($outputLower, 'error') !== false || strpos($outputLower, 'failed') !== false || strpos($outputLower, 'cannot') !== false || strpos($outputLower, 'already exists') !== false) {
                    $isSuccess = false;
                }
            }
            
            // CRITICAL: Always verify subdomain was actually created in Plesk
            // Even if output suggests success, verify it exists
            if ($isSuccess || empty($output)) {
                $verifyCmd = "/usr/sbin/plesk bin subdomain --list 2>&1";
                $verifyOutput = $this->executeSudoCommand($verifyCmd);
                if ($verifyOutput && (strpos($verifyOutput, $fullDomain) !== false || strpos($verifyOutput, $subdomain . '.' . $this->baseDomain) !== false)) {
                    $isSuccess = true;
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug('Subdomain creation verified in Plesk', [
                            'subdomain' => $subdomain,
                            'full_domain' => $fullDomain,
                            'verify_output' => substr($verifyOutput, 0, 200)
                        ]);
                    }
                } else {
                    // Output said success but subdomain doesn't exist - treat as failure
                    $isSuccess = false;
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Subdomain creation output suggested success but subdomain not found in Plesk', [
                            'subdomain' => $subdomain,
                            'full_domain' => $fullDomain,
                            'create_output' => $output,
                            'verify_output' => substr($verifyOutput ?? '', 0, 200)
                        ]);
                    }
                }
            }
            
            if ($isSuccess) {
                // Get the subdomain ID
                $domainId = $this->getSubdomainId($subdomain);

                // Step 1: Point Plesk WWW-Root to the shared multi-tenant
                // application (httpdocs/public). This must happen BEFORE we
                // rename/delete the auto-generated site1 folder, otherwise
                // Plesk may regenerate an empty placeholder while serving
                // the default page.
                $this->configureDocumentRoot($subdomain, $this->documentRoot);

                // Step 2: Rename the auto-generated site1/site2 folder to
                // match the subdomain (purely cosmetic, since www-root is
                // shared). If rename is impossible, remove it so it doesn't
                // clutter the vhost root.
                $this->cleanupOrphanSiteFolder($subdomain, $fullDomain);

                // Step 3: Enable SSL/HTTPS (Let's Encrypt)
                $this->enableSSL($subdomain);

                // Step 4: Save subdomain to database
                $this->saveSubdomainToDatabase($subdomain, $customerId, $fullDomain, $domainId);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain created successfully in Plesk', [
                        'subdomain' => $subdomain,
                        'full_domain' => $fullDomain,
                        'plesk_domain_id' => $domainId,
                        'output' => $output
                    ]);
                }
                
                return [
                    'success' => true,
                    'message' => 'Subdomain başarıyla oluşturuldu',
                    'plesk_domain_id' => $domainId
                ];
            } else if ($output && strpos($output, 'already exists') !== false) {
                // Subdomain already exists - treat as success, configure and save
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain already exists in Plesk, configuring...', [
                        'subdomain' => $subdomain,
                        'full_domain' => $fullDomain
                    ]);
                }

                $domainId = $this->getSubdomainId($subdomain);
                $this->configureDocumentRoot($subdomain, $this->documentRoot);
                $this->cleanupOrphanSiteFolder($subdomain, $fullDomain);
                $this->enableSSL($subdomain);
                $this->saveSubdomainToDatabase($subdomain, $customerId, $fullDomain, $domainId);
                
                return [
                    'success' => true,
                    'message' => 'Subdomain zaten mevcut ve yapılandırıldı',
                    'plesk_domain_id' => $domainId
                ];
            } else {
                // Creation failed - log detailed error and throw exception
                $errorMessage = 'Subdomain oluşturulamadı';
                if ($output) {
                    $errorMessage .= ': ' . trim($output);
                } else {
                    $errorMessage .= ': Komut çıktısı alınamadı (Plesk CLI erişilemiyor olabilir)';
                }
                
                // Additional verification: Check if Plesk CLI is accessible
                $pleskCheckCmd = "/usr/sbin/plesk bin --version 2>&1";
                $pleskVersionOutput = $this->executeSudoCommand($pleskCheckCmd);
                $pleskAccessible = !empty($pleskVersionOutput) && strpos(strtolower($pleskVersionOutput), 'error') === false;
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Plesk subdomain creation failed', [
                        'subdomain' => $subdomain,
                        'full_domain' => $fullDomain,
                        'customer_id' => $customerId,
                        'command' => $createCmd,
                        'output' => $output,
                        'output_length' => strlen($output ?? ''),
                        'error_message' => $errorMessage,
                        'plesk_accessible' => $pleskAccessible,
                        'plesk_version_output' => substr($pleskVersionOutput ?? '', 0, 200)
                    ]);
                }
                
                // Throw exception instead of returning failure
                // This ensures SubdomainService handles it properly
                throw new \Exception($errorMessage);
            }
            
        } catch (\Exception $e) {
            // Log detailed error information
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Plesk subdomain creation exception', [
                    'subdomain' => $subdomain,
                    'full_domain' => $subdomain . '.' . $this->baseDomain,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Re-throw exception instead of returning failure
            // This ensures SubdomainService handles it properly
            throw new \Exception('Plesk subdomain oluşturma hatası: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get subdomain ID from Plesk
     * @param string $subdomain
     * @return int|null
     */
    private function getSubdomainId(string $subdomain): ?int {
        try {
            $cmd = "/usr/sbin/plesk bin subdomain --info " . escapeshellarg($subdomain) . 
                   " -domain " . escapeshellarg($this->baseDomain) . " 2>&1";
            $output = $this->executeSudoCommand($cmd);
            
            // Parse output for ID
            if ($output && preg_match('/ID:\s*(\d+)/i', $output, $matches)) {
                return (int)$matches[1];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Rename subdomain folder from site1/site2 to subdomain name
     * CRITICAL: This ensures folder name matches subdomain (e.g., caddecafe.qordy.com)
     * instead of generic names like site1, site2
     * 
     * @param string $subdomain Subdomain name
     * @param string $fullDomain Full domain name (subdomain.qordy.com)
     * @return bool Success
     */
    private function renameSubdomainFolder(string $subdomain, string $fullDomain): bool {
        try {
            // Base directory where Plesk creates subdomain folders
            $baseDir = dirname($this->documentRoot); // /var/www/vhosts/qordy.com
            $targetFolder = $baseDir . '/' . $fullDomain; // e.g., /var/www/vhosts/qordy.com/caddecafe.qordy.com
            
            // Check if target folder already exists (already has correct name)
            if (is_dir($targetFolder)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain folder already exists with correct name', [
                        'subdomain' => $subdomain,
                        'folder' => $targetFolder
                    ]);
                }
                return true;
            }
            
            // Get subdomain info from Plesk to find current folder
            $subdomainInfoCmd = "/usr/sbin/plesk bin subdomain --info " . escapeshellarg($subdomain) . 
                              " -domain " . escapeshellarg($this->baseDomain) . " 2>&1";
            $infoOutput = $this->executeSudoCommand($subdomainInfoCmd);
            
            $foundFolder = null;
            
            // Try to extract hosting directory from Plesk info
            // Look for hosting directory or document root parent directory
            if ($infoOutput) {
                // Look for hosting directory or home directory
                if (preg_match('/Hosting\s+directory:\s*(.+)/i', $infoOutput, $matches) ||
                    preg_match('/Home\s+directory:\s*(.+)/i', $infoOutput, $matches)) {
                    $hostingDir = trim($matches[1]);
                    if (is_dir($hostingDir)) {
                        $foundFolder = $hostingDir;
                    }
                }
                
                // If not found, try to extract from document root
                if (!$foundFolder) {
                    if (preg_match('/--WWW-Root--:\s*(.+)/i', $infoOutput, $matches) || 
                        preg_match('/Document\s+root:\s*(.+)/i', $infoOutput, $matches)) {
                        $docRoot = trim($matches[1]);
                        
                        // CRITICAL: Check if WWW-Root itself is a site folder (site1, site2, etc.)
                        // This handles the case where WWW-Root is /var/www/vhosts/qordy.com/site1
                        $docRootBasename = basename($docRoot);
                        if (preg_match('/^site\d+$/', $docRootBasename) && is_dir($docRoot)) {
                            // WWW-Root is directly a site folder - use it
                            $foundFolder = $docRoot;
                        } else {
                            // Get parent directory (hosting directory is usually parent of document root)
                            $parentDir = dirname($docRoot);
                            if (is_dir($parentDir) && $parentDir !== $baseDir && strpos($parentDir, $baseDir) === 0) {
                                $foundFolder = $parentDir;
                            }
                        }
                    }
                }
            }
            
            // If still not found, search for site folders (site1, site2, etc.)
            if (!$foundFolder || !is_dir($foundFolder)) {
                $dirHandle = @opendir($baseDir);
                
                if ($dirHandle) {
                    $siteFolders = [];
                    while (($entry = readdir($dirHandle)) !== false) {
                        // Skip . and ..
                        if ($entry === '.' || $entry === '..') {
                            continue;
                        }
                        
                        $entryPath = $baseDir . '/' . $entry;
                        
                        // Check if it's a directory and matches Plesk's pattern (site1, site2, etc.)
                        if (is_dir($entryPath) && preg_match('/^site\d+$/', $entry)) {
                            // Check modification time to find most recent
                            $mtime = @filemtime($entryPath) ?: 0;
                            $siteFolders[] = [
                                'path' => $entryPath,
                                'name' => $entry,
                                'mtime' => $mtime
                            ];
                        }
                    }
                    closedir($dirHandle);
                    
                    // Sort by modification time (newest first) and use most recent
                    if (!empty($siteFolders)) {
                        usort($siteFolders, function($a, $b) {
                            return $b['mtime'] - $a['mtime'];
                        });
                        $foundFolder = $siteFolders[0]['path'];
                    }
                }
            }
            
            // If folder found, rename it to subdomain name
            if ($foundFolder && is_dir($foundFolder) && $foundFolder !== $targetFolder) {
                // Check permissions before renaming. Sudo is only granted
                // for /usr/sbin/plesk (see /etc/sudoers.d/qordy-plesk), so
                // we rely on PHP running as the vhost owner user.
                $parentDir = dirname($foundFolder);
                if (!is_writable($parentDir)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Cannot rename subdomain folder - parent directory not writable', [
                            'subdomain' => $subdomain,
                            'parent_dir' => $parentDir,
                            'found_folder' => $foundFolder,
                            'target_folder' => $targetFolder,
                        ]);
                    }
                    return false;
                }
                
                // Rename the folder to subdomain name
                if (@rename($foundFolder, $targetFolder)) {
                    // CRITICAL: Update Plesk WWW-Root to point to new folder path
                    // After renaming, we need to update Plesk configuration
                    // The document root should still point to main app, but folder name is now correct
                    // We'll update it in configureDocumentRoot() which is called after this
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('Subdomain folder renamed successfully', [
                            'subdomain' => $subdomain,
                            'old_folder' => $foundFolder,
                            'new_folder' => $targetFolder
                        ]);
                    }
                    return true;
                } else {
                    $error = error_get_last();
                    $errorMessage = $error ? $error['message'] : 'Unknown error';
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Failed to rename subdomain folder', [
                            'subdomain' => $subdomain,
                            'old_folder' => $foundFolder,
                            'target_folder' => $targetFolder,
                            'error' => $errorMessage
                        ]);
                    }
                    return false;
                }
            } else if ($foundFolder === $targetFolder) {
                // Folder already has correct name
                return true;
            } else {
                // Folder not found - might already be correct or doesn't exist yet
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain folder not found for renaming (may already be correct)', [
                        'subdomain' => $subdomain,
                        'base_dir' => $baseDir,
                        'target_folder' => $targetFolder,
                        'found_folder' => $foundFolder,
                        'info_output_preview' => substr($infoOutput ?? '', 0, 200)
                    ]);
                }
                // Return true if target folder exists, false otherwise
                return is_dir($targetFolder);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error renaming subdomain folder', [
                    'subdomain' => $subdomain,
                    'full_domain' => $fullDomain,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Remove the orphan site1/site2 folder that Plesk auto-generates.
     * In our multi-tenant setup every subdomain's WWW-Root is already
     * pointed to httpdocs/public, so the per-subdomain hosting directory
     * is never used. We either rename it to match the subdomain (for
     * readability in SSH/Plesk file manager) or delete it if empty.
     *
     * @param string $subdomain Subdomain name (e.g. "pofudukcafe")
     * @param string $fullDomain Full domain name (e.g. "pofudukcafe.qordy.com")
     */
    private function cleanupOrphanSiteFolder(string $subdomain, string $fullDomain): void {
        try {
            $baseDir = dirname($this->documentRoot); // /var/www/vhosts/qordy.com
            $targetFolder = $baseDir . '/' . $fullDomain;

            // First try the PHP-native rename path (which also handles
            // discovery of the Plesk-assigned siteN folder).
            if (!is_dir($targetFolder)) {
                $this->renameSubdomainFolder($subdomain, $fullDomain);
            }

            // Detect any remaining siteN folders that are empty or only
            // contain Plesk's default index.html and remove them via sudo.
            $handle = @opendir($baseDir);
            if (!$handle) {
                return;
            }
            while (($entry = readdir($handle)) !== false) {
                if (!preg_match('/^site\d+$/', $entry)) {
                    continue;
                }
                $path = $baseDir . '/' . $entry;
                if (!is_dir($path)) {
                    continue;
                }

                // Only delete when the folder is effectively empty (just
                // Plesk's default index.html). Never delete something a
                // user might have uploaded.
                $files = @scandir($path) ?: [];
                $files = array_values(array_diff($files, ['.', '..']));
                $isDefaultOnly = (count($files) === 0) ||
                    (count($files) === 1 && $files[0] === 'index.html');

                if ($isDefaultOnly && is_writable(dirname($path))) {
                    // Remove default index.html then the empty directory.
                    $indexFile = $path . '/index.html';
                    if (is_file($indexFile)) {
                        @unlink($indexFile);
                    }
                    $removed = @rmdir($path);
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('Orphan Plesk site folder cleanup', [
                            'subdomain' => $subdomain,
                            'path' => $path,
                            'removed' => $removed,
                        ]);
                    }
                }
            }
            closedir($handle);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('cleanupOrphanSiteFolder failed', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Configure document root for subdomain
     * @param string $subdomain
     * @param string $documentRoot Base document root path (should be /var/www/vhosts/qordy.com/httpdocs)
     * @return bool
     */
    private function configureDocumentRoot(string $subdomain, string $documentRoot): bool {
        try {
            // Multi-tenant model: EVERY subdomain shares the same codebase
            // under /var/www/vhosts/qordy.com/httpdocs/public.
            //
            // IMPORTANT: Plesk CLI `-www-root` is resolved RELATIVE to the
            // subscription's home directory (/var/www/vhosts/qordy.com).
            // Passing an absolute path causes Plesk to prepend the home
            // directory anyway, resulting in the bogus path:
            //   /var/www/vhosts/qordy.com/var/www/vhosts/qordy.com/httpdocs/public
            // and Plesk silently leaves WWW-Root pointing at the
            // auto-generated `site1`/`site2` folder -> default Plesk page.
            // So we always pass the relative path and only fall back to the
            // absolute form if the relative one fails for some reason.
            $absolutePath = rtrim($this->documentRoot, '/') . '/public';
            $relativePath = 'httpdocs/public';

            if (!is_dir($absolutePath)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Document root path does not exist', [
                        'subdomain'    => $subdomain,
                        'absolute_path'=> $absolutePath,
                    ]);
                }
                return false;
            }

            $cmd = "/usr/sbin/plesk bin subdomain --update " . escapeshellarg($subdomain) .
                   " -domain " . escapeshellarg($this->baseDomain) .
                   " -www-root " . escapeshellarg($relativePath) . " 2>&1";
            $output = $this->executeSudoCommand($cmd);
            $usedPath = $relativePath;

            // If the relative path call itself errored, try the absolute
            // form as a last-ditch fallback (some edge Plesk configs may
            // require it).
            $outputLower = strtolower(trim((string)$output));
            if (strpos($outputLower, 'error') !== false || strpos($outputLower, 'invalid') !== false) {
                $cmd2 = "/usr/sbin/plesk bin subdomain --update " . escapeshellarg($subdomain) .
                        " -domain " . escapeshellarg($this->baseDomain) .
                        " -www-root " . escapeshellarg($absolutePath) . " 2>&1";
                $output2 = $this->executeSudoCommand($cmd2);
                $output2Lower = strtolower(trim((string)$output2));
                if (strpos($output2Lower, 'error') === false && strpos($output2Lower, 'invalid') === false) {
                    $output = $output2;
                    $usedPath = $absolutePath;
                }
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Relative www-root failed, attempted absolute fallback', [
                        'subdomain' => $subdomain,
                        'relative_output' => substr((string)$output, 0, 200),
                        'absolute_output' => substr((string)$output2, 0, 200),
                    ]);
                }
            }

            $success = (empty($output) ||
                       strpos((string)$output, 'SUCCESS') !== false ||
                       strpos((string)$output, 'updated') !== false ||
                       strpos((string)$output, 'completed') !== false);

            if (!$success && strpos((string)$output, 'error') === false && strpos((string)$output, 'Error') === false) {
                $success = true;
            }

            if (class_exists('\App\Core\Logger')) {
                if ($success) {
                    \App\Core\Logger::info('Document root configured for subdomain', [
                        'subdomain' => $subdomain,
                        'www_root' => $usedPath,
                        'output' => substr($output ?? '', 0, 500)
                    ]);
                } else {
                    \App\Core\Logger::error('Failed to configure document root for subdomain', [
                        'subdomain' => $subdomain,
                        'www_root' => $usedPath,
                        'output' => $output
                    ]);
                }
            }

            return $success;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to configure document root', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Enable SSL (Let's Encrypt) for subdomain.
     *
     * Strategy (to make registration fully autonomous even when Let's
     * Encrypt takes 30-90s and would otherwise exceed PHP's
     * max_execution_time):
     *   1. Extend the PHP time limit for this request so a synchronous
     *      call has a realistic chance of completing.
     *   2. Run the LE CLI synchronously once. Parse the output to detect
     *      real success/failure.
     *   3. If the synchronous run fails or times out (e.g. DNS not yet
     *      propagated), kick off a detached background retry so the cert
     *      eventually installs without blocking the user's HTTP request.
     *   4. Always enforce HTTPS redirect on the subdomain regardless so
     *      that once the cert lands, visitors are auto-upgraded to HTTPS.
     *
     * @param string $subdomain
     * @return bool true if we are confident a certificate is (or will be) installed
     */
    private function enableSSL(string $subdomain): bool {
        $fullDomain = $subdomain . '.' . $this->baseDomain;
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? ('admin@' . $this->baseDomain);

        // Give the request enough time for a real LE handshake.
        @set_time_limit(180);
        @ignore_user_abort(true);

        $leCmd = "/usr/sbin/plesk bin extension --exec letsencrypt cli.php " .
                 "-d " . escapeshellarg($fullDomain) . " " .
                 "-m " . escapeshellarg($adminEmail) . " 2>&1";

        $syncSuccess = false;
        $output      = null;

        try {
            $output = $this->executeSudoCommand($leCmd);

            $outputLower = strtolower(trim((string)$output));
            $hasErrorToken = (
                $outputLower !== '' && (
                    strpos($outputLower, 'error') !== false ||
                    strpos($outputLower, 'failed') !== false ||
                    strpos($outputLower, 'cannot') !== false ||
                    strpos($outputLower, 'unable') !== false ||
                    strpos($outputLower, 'exception') !== false
                )
            );

            if (!$hasErrorToken) {
                // Verify the cert actually attached to the subdomain.
                $infoCmd = "/usr/sbin/plesk bin subdomain --info " .
                           escapeshellarg($subdomain) .
                           " -domain " . escapeshellarg($this->baseDomain) . " 2>&1";
                $infoOutput = (string)$this->executeSudoCommand($infoCmd);
                if (stripos($infoOutput, "Lets Encrypt $fullDomain") !== false ||
                    stripos($infoOutput, "Let's Encrypt $fullDomain") !== false) {
                    $syncSuccess = true;
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('SSL sync install threw', [
                    'subdomain' => $subdomain,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Always force HTTPS redirect + SSL flag on the subdomain so the
        // browser is upgraded as soon as a cert is present.
        try {
            $forceCmd = "/usr/sbin/plesk bin subdomain --update " .
                        escapeshellarg($subdomain) .
                        " -domain " . escapeshellarg($this->baseDomain) .
                        " -ssl true -seo-redirect true 2>&1";
            $this->executeSudoCommand($forceCmd);
        } catch (\Throwable $e) {
            // non-fatal
        }

        if ($syncSuccess) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('SSL installed synchronously', [
                    'subdomain'   => $subdomain,
                    'full_domain' => $fullDomain,
                    'output'      => substr((string)$output, 0, 500),
                ]);
            }
            return true;
        }

        // Fallback: fire the LE install in the background so registration
        // can finish immediately. The background process retries a couple
        // of times to handle DNS propagation lag for freshly-created
        // subdomains.
        $this->scheduleBackgroundSSL($subdomain, $fullDomain, $adminEmail);

        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('SSL queued for background install', [
                'subdomain'   => $subdomain,
                'full_domain' => $fullDomain,
                'sync_output' => substr((string)$output, 0, 500),
            ]);
        }

        // Returning true because we've queued the install; the front-door
        // HTTPS redirect will kick in automatically once the cert lands.
        return true;
    }

    /**
     * Fire a detached shell that retries the Let's Encrypt CLI a few times
     * so that freshly-created subdomains eventually get a certificate
     * without blocking the HTTP request that triggered registration.
     */
    private function scheduleBackgroundSSL(string $subdomain, string $fullDomain, string $adminEmail): void {
        try {
            $candidates = [
                '/var/www/vhosts/qordy.com/httpdocs/storage/logs',
                sys_get_temp_dir(),
                '/tmp',
            ];
            $logDir = null;
            foreach ($candidates as $cand) {
                if (!$cand) continue;
                if (!is_dir($cand)) {
                    @mkdir($cand, 0775, true);
                }
                if (is_dir($cand) && is_writable($cand)) {
                    $logDir = $cand;
                    break;
                }
            }
            if (!$logDir) {
                $logDir = '/tmp';
            }
            $logFile = $logDir . '/ssl-install-' . preg_replace('/[^a-z0-9_.-]/i', '_', $fullDomain) . '.log';

            // The loop retries the LE command up to 5 times with a
            // 20-second backoff to allow DNS propagation / challenge
            // preparation. Sudo-only access to /usr/sbin/plesk is fine
            // because every command inside is a plesk bin invocation.
            $script = sprintf(
                '( for i in 1 2 3 4 5; do ' .
                    'sudo -n /usr/sbin/plesk bin extension --exec letsencrypt cli.php ' .
                    '-d %s -m %s >> %s 2>&1; ' .
                    'sudo -n /usr/sbin/plesk bin subdomain --info %s -domain %s 2>&1 ' .
                    '| grep -qi "Lets Encrypt %s" && break; ' .
                    'sleep 20; ' .
                'done ) >> %s 2>&1 &',
                escapeshellarg($fullDomain),
                escapeshellarg($adminEmail),
                escapeshellarg($logFile),
                escapeshellarg($subdomain),
                escapeshellarg($this->baseDomain),
                escapeshellarg($fullDomain),
                escapeshellarg($logFile)
            );

            // Use shell_exec with & for real backgrounding. We rely on
            // PHP-FPM not waiting for the detached process.
            @shell_exec('nohup bash -c ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('scheduleBackgroundSSL failed to spawn', [
                    'subdomain' => $subdomain,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
    
    /**
     * Save subdomain to database
     * @param string $subdomain
     * @param string $customerId
     * @param string $fullDomain
     * @param int|null $pleskDomainId
     * @return bool
     */
    private function saveSubdomainToDatabase(string $subdomain, string $customerId, string $fullDomain, ?int $pleskDomainId): bool {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Check if subdomain already exists
            $checkStmt = $db->prepare("SELECT subdomain_id FROM subdomains WHERE subdomain_name = :subdomain");
            $checkStmt->execute(['subdomain' => $subdomain]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update existing record
                $updateStmt = $db->prepare("
                    UPDATE subdomains 
                    SET tenant_id = :tenant_id,
                        plesk_domain_id = :plesk_domain_id,
                        full_domain = :full_domain,
                        document_root = :document_root,
                        status = 'active',
                        activated_at = NOW(),
                        updated_at = NOW()
                    WHERE subdomain_name = :subdomain
                ");
                
                // Ensure business row exists (FK subdomains.tenant_id -> businesses.tenant_id)
                $this->ensureBusinessRow($db, $customerId, $subdomain);

                $updateStmt->execute([
                    'tenant_id' => $customerId,
                    'plesk_domain_id' => $pleskDomainId,
                    'full_domain' => $fullDomain,
                    'document_root' => $this->documentRoot . '/public',
                    'subdomain' => $subdomain
                ]);
            } else {
                // Ensure business row exists (FK subdomains.tenant_id -> businesses.tenant_id)
                $this->ensureBusinessRow($db, $customerId, $subdomain);

                $insertStmt = $db->prepare("
                    INSERT INTO subdomains (
                        subdomain_id, tenant_id, subdomain_name, full_domain,
                        document_root, plesk_domain_id, status, activated_at,
                        created_at, updated_at
                    ) VALUES (
                        :subdomain_id, :tenant_id, :subdomain_name, :full_domain,
                        :document_root, :plesk_domain_id, 'active', NOW(),
                        NOW(), NOW()
                    )
                ");

                $insertStmt->execute([
                    'subdomain_id' => 'SUB_' . uniqid(),
                    'tenant_id' => $customerId,
                    'subdomain_name' => $subdomain,
                    'full_domain' => $fullDomain,
                    'document_root' => $this->documentRoot . '/public',
                    'plesk_domain_id' => $pleskDomainId
                ]);
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Subdomain saved to database', [
                    'subdomain' => $subdomain,
                    'customer_id' => $customerId,
                    'plesk_domain_id' => $pleskDomainId
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to save subdomain to database', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Delete subdomain from Plesk
     * @param string $subdomain
     * @return array
     */
    public function deleteSubdomain(string $subdomain): array {
        try {
            $cmd = "/usr/sbin/plesk bin subdomain --remove " . escapeshellarg($subdomain) . 
                   " -domain " . escapeshellarg($this->baseDomain) . " 2>&1";
            
            $output = $this->executeSudoCommand($cmd);
            
            // Remove from database
            $db = \App\Core\DependencyFactory::getDatabase();
            $deleteStmt = $db->prepare("DELETE FROM subdomains WHERE subdomain_name = :subdomain");
            $deleteStmt->execute(['subdomain' => $subdomain]);
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Subdomain deleted from Plesk', [
                    'subdomain' => $subdomain,
                    'output' => $output
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Subdomain başarıyla silindi'
            ];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to delete subdomain', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Subdomain silinemedi: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if Plesk is available and configured
     * 
     * CRITICAL: Cannot use file_exists() or is_executable() due to PHP open_basedir restrictions.
     * Instead, we directly test if Plesk CLI command works via shell_exec().
     * shell_exec() uses system PATH and bypasses open_basedir restrictions.
     * 
     * @return bool
     */
    public static function isAvailable(): bool {
        // CRITICAL: Due to PHP open_basedir restrictions, we cannot check file existence
        // Instead, we directly test if Plesk CLI command works
        // shell_exec() uses system PATH and bypasses open_basedir restrictions
        
        try {
            // Try to run a simple Plesk command to verify it works
            // Use full path to avoid PATH issues, but shell_exec() will still work
            // CRITICAL: This is a static method, so we must use static version of executeSudoCommand
            $testCmd = "/usr/sbin/plesk bin subdomain --list 2>&1";
            $testOutput = self::executeSudoCommandStatic($testCmd);
            
            // Check if command succeeded
            // Success indicators:
            // - Output is not false/null (command executed)
            // - No error messages in output
            // - Empty output is OK (means no subdomains exist yet or command succeeded silently)
            
            if ($testOutput !== false && $testOutput !== null) {
                $outputLower = strtolower(trim($testOutput));
                
                // Check for error indicators
                $hasError = (
                    strpos($outputLower, 'permission denied') !== false ||
                    strpos($outputLower, 'command not found') !== false ||
                    strpos($outputLower, 'cannot execute') !== false ||
                    strpos($outputLower, 'no such file') !== false ||
                    strpos($outputLower, 'open_basedir') !== false ||
                    strpos($outputLower, 'access denied') !== false ||
                    strpos($outputLower, 'error') !== false && strpos($outputLower, 'unknown plesk command') !== false
                );
                
                // If no errors, Plesk is available
                // Empty output is OK (means no subdomains exist yet or command succeeded silently)
                if (!$hasError) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug('Plesk CLI is available', [
                            'test_cmd' => $testCmd,
                            'test_output_length' => strlen($testOutput),
                            'test_output_preview' => substr($testOutput, 0, 100),
                            'has_output' => !empty($testOutput)
                        ]);
                    }
                    return true;
                }
            }
            
            // Log why Plesk is not available
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Plesk CLI test command failed', [
                    'test_cmd' => $testCmd,
                    'test_output' => substr($testOutput ?? 'null', 0, 200),
                    'test_output_length' => strlen($testOutput ?? ''),
                    'test_output_is_false' => ($testOutput === false),
                    'test_output_is_null' => ($testOutput === null)
                ]);
            }
            
            return false;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Plesk availability check exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return false;
        }
    }

    /**
     * Ensure a `businesses` row exists for the given tenant id.
     * Required because `subdomains.tenant_id` has a FK to `businesses.tenant_id`.
     * Safe to call repeatedly (uses INSERT IGNORE).
     */
    private function ensureBusinessRow(\PDO $db, string $tenantId, string $subdomain): void {
        try {
            $checkBusiness = $db->prepare(
                "SELECT tenant_id FROM businesses WHERE tenant_id = :tenant_id LIMIT 1"
            );
            $checkBusiness->execute(['tenant_id' => $tenantId]);
            if ($checkBusiness->fetch()) {
                return;
            }

            $customerStmt = $db->prepare(
                "SELECT email, company_name FROM customers WHERE customer_id = :tenant_id LIMIT 1"
            );
            $customerStmt->execute(['tenant_id' => $tenantId]);
            $customer = $customerStmt->fetch() ?: [];
            $customerEmail = $customer['email'] ?? 'noreply@qordy.com';
            $companyName   = $customer['company_name'] ?? ('Business ' . $tenantId);

            $insertBusiness = $db->prepare("
                INSERT IGNORE INTO businesses (
                    tenant_id, business_name, business_type, subdomain,
                    contact_email, created_at, updated_at
                ) VALUES (
                    :tenant_id, :business_name, :business_type, :subdomain,
                    :contact_email, NOW(), NOW()
                )
            ");
            $insertBusiness->execute([
                'tenant_id'     => $tenantId,
                'business_name' => $companyName,
                'business_type' => 'restaurant',
                'subdomain'     => $subdomain,
                'contact_email' => $customerEmail,
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('ensureBusinessRow failed', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
            throw new \Exception('Business record could not be ensured: ' . $e->getMessage());
        }
    }
}
