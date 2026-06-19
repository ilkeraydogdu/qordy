<?php
namespace App\Core;

class IPBlocker {
    private $storagePath;
    private $blockedIPs = [];
    private $whitelist = [];
    private $blacklist = [];
    
    public function __construct() {
        $this->storagePath = __DIR__ . '/../storage/ip_blocks/';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        
        $this->loadLists();
    }
    
    public function isBlocked(string $ip): bool {
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        if (in_array($ip, $this->blacklist)) {
            return true;
        }
        
        $blockFile = $this->storagePath . md5($ip) . '.json';
        if (!file_exists($blockFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($blockFile), true);
        if (!$data) {
            return false;
        }
        
        if (isset($data['permanent']) && $data['permanent']) {
            return true;
        }
        
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            unlink($blockFile);
            return false;
        }
        
        return true;
    }
    
    public function block(string $ip, int $duration = 3600, bool $permanent = false): bool {
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        $blockFile = $this->storagePath . md5($ip) . '.json';
        $data = [
            'ip' => $ip,
            'blocked_at' => time(),
            'expires_at' => $permanent ? null : (time() + $duration),
            'permanent' => $permanent
        ];
        
        file_put_contents($blockFile, json_encode($data), LOCK_EX);
        $this->blockedIPs[$ip] = $data;
        
        return true;
    }
    
    public function unblock(string $ip): bool {
        $blockFile = $this->storagePath . md5($ip) . '.json';
        
        if (file_exists($blockFile)) {
            unlink($blockFile);
        }
        
        unset($this->blockedIPs[$ip]);
        
        return true;
    }
    
    public function addToWhitelist(string $ip): void {
        if (!in_array($ip, $this->whitelist)) {
            $this->whitelist[] = $ip;
            $this->saveWhitelist();
        }
    }
    
    public function removeFromWhitelist(string $ip): void {
        $this->whitelist = array_diff($this->whitelist, [$ip]);
        $this->saveWhitelist();
    }
    
    public function addToBlacklist(string $ip): void {
        if (!in_array($ip, $this->blacklist)) {
            $this->blacklist[] = $ip;
            $this->saveBlacklist();
        }
    }
    
    public function removeFromBlacklist(string $ip): void {
        $this->blacklist = array_diff($this->blacklist, [$ip]);
        $this->saveBlacklist();
    }
    
    public function isWhitelisted(string $ip): bool {
        return in_array($ip, $this->whitelist);
    }
    
    public function getBlockInfo(string $ip): ?array {
        if (!$this->isBlocked($ip)) {
            return null;
        }
        
        $blockFile = $this->storagePath . md5($ip) . '.json';
        if (!file_exists($blockFile)) {
            return null;
        }
        
        return json_decode(file_get_contents($blockFile), true);
    }
    
    public function getAllBlockedIPs(): array {
        $blocked = [];
        $files = glob($this->storagePath . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $this->isBlocked($data['ip'])) {
                $blocked[] = $data;
            }
        }
        
        return $blocked;
    }
    
    private function loadLists(): void {
        $whitelistFile = $this->storagePath . 'whitelist.json';
        $blacklistFile = $this->storagePath . 'blacklist.json';
        
        if (file_exists($whitelistFile)) {
            $this->whitelist = json_decode(file_get_contents($whitelistFile), true) ?: [];
        }
        
        if (file_exists($blacklistFile)) {
            $this->blacklist = json_decode(file_get_contents($blacklistFile), true) ?: [];
        }
    }
    
    private function saveWhitelist(): void {
        $whitelistFile = $this->storagePath . 'whitelist.json';
        file_put_contents($whitelistFile, json_encode($this->whitelist), LOCK_EX);
    }
    
    private function saveBlacklist(): void {
        $blacklistFile = $this->storagePath . 'blacklist.json';
        file_put_contents($blacklistFile, json_encode($this->blacklist), LOCK_EX);
    }
    
    public function cleanup(): void {
        $files = glob($this->storagePath . '*.json');
        $now = time();
        
        foreach ($files as $file) {
            if (basename($file) === 'whitelist.json' || basename($file) === 'blacklist.json') {
                continue;
            }
            
            $data = json_decode(file_get_contents($file), true);
            if ($data && !($data['permanent'] ?? false) && isset($data['expires_at']) && $now > $data['expires_at']) {
                unlink($file);
            }
        }
    }
}

