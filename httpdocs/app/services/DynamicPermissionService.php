<?php
namespace App\Services;

use App\Core\BaseService;
use App\Core\DependencyFactory;

/**
 * Dynamic Permission Service
 * Automatically discovers and integrates new permission structures into the system
 */
class DynamicPermissionService extends BaseService {
    protected $permissionModel;
    protected $roleService;
    protected $preparationScreenService;
    
    public function __construct() {
        parent::__construct(null);
        $this->permissionModel = DependencyFactory::getPermissionModel();
        $this->roleService = DependencyFactory::getRoleService();
        $this->preparationScreenService = DependencyFactory::getPreparationScreenService();
    }
    
    /**
     * Discover and sync all preparation screen permissions
     * This method scans for all preparation screens and ensures their permissions exist
     * @return array Results
     */
    public function syncPreparationScreenPermissions(): array {
        $results = [
            'screens_processed' => 0,
            'permissions_created' => 0,
            'permissions_assigned' => 0,
            'errors' => []
        ];
        
        try {
            // Get all preparation screens
            $screens = $this->preparationScreenService->getAllScreens();
            
            foreach ($screens as $screen) {
                $screenId = $screen['screen_id'] ?? '';
                $slug = $screen['slug'] ?? '';
                
                if (empty($slug)) {
                    continue;
                }
                
                $results['screens_processed']++;
                
                // Ensure permissions exist
                $permissionKeys = [
                    "preparation-screen.{$slug}.view",
                    "preparation-screen.{$slug}.update_status"
                ];
                
                $permissionIds = [];
                foreach ($permissionKeys as $permissionKey) {
                    try {
                        $existing = $this->permissionModel->getByKey($permissionKey);
                        if (!$existing) {
                            // Create permission (use permission_key as permission_id)
                            $permissionId = $permissionKey;
                            $permissionName = $this->generatePermissionName($permissionKey);
                            
                            $permData = [
                                'permission_id' => $permissionId,
                                'permission_key' => $permissionKey,
                                'permission_name' => $permissionName,
                                'description' => "Auto-generated permission for {$slug} preparation screen"
                            ];
                            
                            $this->permissionModel->create($permData);
                            $results['permissions_created']++;
                            $permissionIds[] = $permissionId;
                        } else {
                            $permissionIds[] = $existing['permission_id'];
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Failed to create permission {$permissionKey}: " . $e->getMessage();
                    }
                }
                
                // Assign permissions to relevant roles
                if (!empty($permissionIds)) {
                    $assigned = $this->assignToRelevantRoles($permissionIds, $slug);
                    $results['permissions_assigned'] += $assigned;
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = "Failed to sync preparation screen permissions: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Assign permissions to relevant roles
     * @param array $permissionIds Permission IDs
     * @param string $slug Screen slug
     * @return int Number of assignments made
     */
    private function assignToRelevantRoles(array $permissionIds, string $slug): int {
        $assigned = 0;
        
        try {
            // Roles that should have preparation screen permissions
            // BUSINESS_MANAGER added because they create and manage preparation screens
            $roleCodes = ['KITCHEN', 'MANAGER', 'BUSINESS_MANAGER'];
            
            foreach ($roleCodes as $roleCode) {
                try {
                    $role = $this->roleService->getByRoleCode($roleCode);
                    if (!$role) {
                        continue;
                    }
                    
                    $roleId = $role['role_id'] ?? '';
                    if (empty($roleId)) {
                        continue;
                    }
                    
                    // Get current permissions
                    $currentPermissions = $this->roleService->getRolePermissionKeys($roleId);
                    
                    // Assign new permissions
                    foreach ($permissionIds as $permissionId) {
                        if (!in_array($permissionId, $currentPermissions)) {
                            $this->roleService->assignPermission($roleId, $permissionId);
                            $assigned++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to assign permissions to role {$roleCode}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to assign permissions to roles: " . $e->getMessage());
        }
        
        return $assigned;
    }
    
    /**
     * Generate permission name from key
     * @param string $permissionKey Permission key
     * @return string Permission name
     */
    private function generatePermissionName(string $permissionKey): string {
        // Convert "preparation-screen.cayci.view" to "View Cayci Preparation Screen"
        $parts = explode('.', $permissionKey);
        
        if (count($parts) >= 3 && $parts[0] === 'preparation-screen') {
            $screenName = ucfirst($parts[1]);
            $action = ucfirst($parts[2]);
            
            if ($action === 'View') {
                return "View {$screenName} Preparation Screen";
            } elseif ($action === 'Update_status') {
                return "Update Status for {$screenName} Screen";
            }
        }
        
        // Fallback: capitalize and replace underscores
        return ucwords(str_replace(['.', '_'], ' ', $permissionKey));
    }
    
    /**
     * Discover all dynamic permission structures
     * This is a hook for future extensions
     * @return array Discovery results
     */
    public function discoverAllDynamicPermissions(): array {
        $results = [
            'preparation_screens' => $this->syncPreparationScreenPermissions()
        ];
        
        // Future: Add other dynamic permission structures here
        // e.g., $results['custom_modules'] = $this->syncCustomModulePermissions();
        
        return $results;
    }
}

