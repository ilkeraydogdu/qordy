/**
 * Tables Management Page JavaScript
 * Handles table listing, filtering, CRUD operations, and QR code generation
 * Version: 2.0 - Removed status functionality, QR code focused
 */

// Import logger (fallback to console if not available)
const logger = (typeof window !== 'undefined' && window.logger) || {
    debug: () => {},
    info: () => {},
    warn: console.warn ? console.warn.bind(console) : () => {},
    error: console.error ? console.error.bind(console) : () => {},
    log: () => {}
};


// Floor-plan / table-top iconography (restaurant bird's-eye view)
const BIZ_FLOOR_PLAN_ICON = '<svg class="biz-icon-floor-plan w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>';
const BIZ_TABLE_TOP_ICON = '<svg class="biz-icon-table-top w-9 h-9 mx-auto text-indigo-500/80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke-width="1.5"/><circle cx="12" cy="4.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg>';
const BIZ_TABLE_TOP_ICON_SM = '<svg class="biz-icon-table-top w-4 h-4 shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke-width="1.5"/><circle cx="12" cy="4.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg>';

// TablesPage module
const TablesPage = {
    config: {
        baseUrl: '',
        apiPrefix: '/api/business', // Default for business users, overridden by init
        translations: {}
    },
    
    state: {
        tables: [],
        zones: [],
        currentQRTable: null
    },
    
    /**
     * Natural sort function for table names (bahçe1, bahçe2, bahçe10)
     * Handles numeric values in strings correctly
     */
    naturalSort: function(a, b) {
        const nameA = String(a || '').toUpperCase().trim();
        const nameB = String(b || '').toUpperCase().trim();
        
        // Extract numeric and non-numeric parts
        const regex = /(\d+|\D+)/g;
        const partsA = nameA.match(regex) || [];
        const partsB = nameB.match(regex) || [];
        
        const minLength = Math.min(partsA.length, partsB.length);
        
        for (let i = 0; i < minLength; i++) {
            const partA = partsA[i];
            const partB = partsB[i];
            
            const numA = parseInt(partA, 10);
            const numB = parseInt(partB, 10);
            
            // If both are numbers, compare numerically
            if (!isNaN(numA) && !isNaN(numB)) {
                if (numA !== numB) {
                    return numA - numB;
                }
            } else {
                // Compare as strings
                if (partA !== partB) {
                    return partA.localeCompare(partB);
                }
            }
        }
        
        // If all parts are equal, compare by length
        return partsA.length - partsB.length;
    },
    
    /**
     * Initialize tables page
     * @param {Object} config - Configuration object with baseUrl and translations
     */
    init: function(config) {
        this.config = { ...this.config, ...config };
        window.BASE_URL = this.config.baseUrl;
        this.setupEventListeners();
        this.loadZones();
        this.loadTables();
        this.setupRealtimeUpdates();
    },
    
    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        const tableForm = document.getElementById('tableForm');
        if (tableForm) {
            tableForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleTableSubmit(e);
            });
        }
    },
    
    /**
     * Setup real-time updates
     */
    setupRealtimeUpdates: function() {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.realtimeService) {
                window.realtimeService.start('tables', (tablesData) => {
                    this.updateTablesRealtime(tablesData);
                }, 3000);
            }
        });
    },
    
    /**
     * Load tables from API
     */
    async loadTables() {
        try {
            const response = await fetch(`${this.config.baseUrl}/api/tables`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                logger.error('Error loading tables:', data.error);
                if (window.NotificationManager) {
                    window.NotificationManager.error('Masalar yüklenirken hata oluştu: ' + (data.error || 'Bilinmeyen hata'));
                }
                this.state.tables = [];
                this.renderTables();
                return;
            }
            
            // Map database format to view format
            const tablesData = Array.isArray(data) ? data : (data.tables || data.data || []);
            
            if (!Array.isArray(tablesData)) {
                logger.warn('Invalid tables data format:', data);
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Masalar yüklenirken veri formatı hatası oluştu.');
                }
                this.state.tables = [];
                this.renderTables();
                return;
            }
            
            this.state.tables = tablesData.map(table => ({
                id: table.table_id || table.id,
                name: table.name || 'Bilinmiyor',
                zone_id: table.zone_id || null,
                zone: table.zone || table.zone_name || 'Bilinmiyor',
                floor: table.floor || '',
                section: table.section || '',
                capacity: parseInt(table.capacity || 4),
                url: table.url || '',
                qr_code_url: table.qr_code_url || ''
            }));
            
            // Natural sort: zone first, then table name (bahçe1, bahçe2, bahçe10)
            this.state.tables.sort((a, b) => {
                // Sort by zone first
                const zoneCompare = this.naturalSort(a.zone, b.zone);
                if (zoneCompare !== 0) {
                    return zoneCompare;
                }
                // Then by table name with natural sort
                return this.naturalSort(a.name, b.name);
            });
            
            logger.debug('Loaded tables:', this.state.tables.length);
            this.updateFilterOptions();
            this.renderTables();
        } catch (error) {
            logger.error('Error loading tables:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Masalar yüklenirken hata oluştu. Sayfayı yenileyin.');
            }
            this.state.tables = [];
            this.renderTables();
        }
    },
    
    
    /**
     * Render tables to DOM (grouped by zone - default view)
     */
    renderTables: function() {
        const grid = document.getElementById('tablesGrid');
        if (!grid) {
            logger.warn('tablesGrid element not found');
            return;
        }
        
        const filters = this.getActiveFilters();
        
        const filteredTables = this.state.tables.filter(table => {
            // Filter by zone
            if (filters.zone && filters.zone !== '') {
                const tableZoneId = table.zone_id || table.zone;
                if (String(tableZoneId) !== String(filters.zone)) {
                    return false;
                }
            }
            
            // Filter by floor - check both table.floor and zone.floor
            if (filters.floor && filters.floor !== '') {
                let tableFloor = table.floor || '';
                // If table doesn't have floor, check zone floor
                if (!tableFloor) {
                    const zoneId = table.zone_id || table.zone;
                    const zone = this.state.zones.find(z => (z.zone_id || z.id) === zoneId);
                    if (zone && zone.floor) {
                        tableFloor = zone.floor;
                    }
                }
                if (tableFloor !== filters.floor) {
                    return false;
                }
            }
            
            return true;
        });
        
        const escapeHtml = window.Utils?.escapeHtml || function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Escape JavaScript string for use in onclick handlers
        const escapeJs = function(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/"/g, '\\"')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '\\r')
                .replace(/\t/g, '\\t');
        };
        
        if (filteredTables.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <div class="text-slate-400 font-bold text-sm sm:text-base mb-2">Henüz masa bulunmuyor</div>
                    <button onclick="openAddTableModal()" class="px-4 py-2 q-btn q-btn--primary q-btn--sm transition-all">
                        ${this.config.translations.addTable || 'Yeni Masa Ekle'}
                    </button>
                </div>
            `;
            return;
        }
        
        // Group tables by zone
        const groupedByZone = {};
        filteredTables.forEach(table => {
            const zoneName = table.zone || 'Diğer';
            if (!groupedByZone[zoneName]) {
                groupedByZone[zoneName] = [];
            }
            groupedByZone[zoneName].push(table);
        });
        
        // Sort zones naturally
        const sortedZones = Object.keys(groupedByZone).sort((a, b) => this.naturalSort(a, b));
        
        // Render zone-grouped view
        let html = '';
        sortedZones.forEach(zoneName => {
            const zoneTables = groupedByZone[zoneName];
            const tableCount = zoneTables.length;
            
            html += `
                <section class="q-tables-zone-block" aria-label="${escapeHtml(zoneName)}">
                    <div class="q-tables-zone-header">
                        <h2>${escapeHtml(zoneName)} <span class="text-sm font-bold text-slate-500">(${tableCount} masa)</span></h2>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600">${BIZ_FLOOR_PLAN_ICON}<span>Bölge</span></span>
                    </div>
                    <div class="q-tables-zone-grid">
                        ${zoneTables.map((table) => {
                            const displayName = escapeHtml(table.name || 'Bilinmiyor');
                            return `
                            <article class="q-table-mgmt-card">
                                <div class="text-center">
                                    ${BIZ_TABLE_TOP_ICON}
                                    <h3 class="font-black text-base sm:text-lg text-slate-900 mt-2 mb-1 truncate" title="${displayName}">${displayName}</h3>
                                    <div class="text-xs text-slate-500 space-y-0.5">
                                        ${table.floor ? `<div>Kat: ${escapeHtml(table.floor)}</div>` : ''}
                                        <div>${this.config.translations.capacity || 'Kapasite'}: ${table.capacity} ${this.config.translations.person || 'kişi'}</div>
                                    </div>
                                </div>
                                <div class="q-table-mgmt-card__actions">
                                    <button type="button" onclick="viewQRCode('${escapeJs(table.id)}')" class="q-btn q-btn--primary q-btn--xs w-full justify-center">
                                        QR Kod
                                    </button>
                                    <button type="button" onclick="openEditTableModal('${escapeJs(table.id)}')" class="q-btn q-btn--soft q-btn--xs flex-1 justify-center">
                                        ${this.config.translations.editTable || 'Düzenle'}
                                    </button>
                                    <button type="button" onclick="deleteTable('${escapeJs(table.id)}')" class="q-btn q-btn--ghost q-btn--xs text-red-600 flex-1 justify-center">
                                        ${this.config.translations.deleteTable || 'Sil'}
                                    </button>
                                </div>
                            </article>`;
                        }).join('')}
                    </div>
                </section>
            `;
        });
        
        grid.innerHTML = html;
    },
    
    /**
     * Get active filters
     */
    getActiveFilters: function() {
        const filterZone = document.getElementById('filterZone');
        const filterFloor = document.getElementById('filterFloor');
        
        return {
            zone: filterZone ? filterZone.value : '',
            floor: filterFloor ? filterFloor.value : ''
        };
    },
    
    /**
     * Update filters
     */
    updateFilters: function() {
        this.renderTables();
        this.updateFilterOptions();
    },
    
    /**
     * Update filter dropdown options based on available data
     */
    updateFilterOptions: function() {
        // Update zone filter
        const zoneSelect = document.getElementById('filterZone');
        if (zoneSelect && this.state.zones.length > 0) {
            const currentValue = zoneSelect.value;
            // Clear options except first
            while (zoneSelect.options.length > 1) {
                zoneSelect.remove(1);
            }
            // Add unique zones from tables
            const uniqueZones = new Map();
            this.state.tables.forEach(table => {
                const zoneId = table.zone_id || table.zone;
                const zoneName = table.zone || 'Bilinmiyor';
                if (zoneId && !uniqueZones.has(zoneId)) {
                    uniqueZones.set(zoneId, zoneName);
                }
            });
            uniqueZones.forEach((name, id) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = name;
                zoneSelect.appendChild(option);
            });
            // Restore selection
            zoneSelect.value = currentValue;
        }
        
        // Update floor filter - get floors from both tables and zones
        const floorSelect = document.getElementById('filterFloor');
        if (floorSelect) {
            const currentValue = floorSelect.value;
            // Clear options except first
            while (floorSelect.options.length > 1) {
                floorSelect.remove(1);
            }
            // Add unique floors from tables
            const uniqueFloors = new Set();
            this.state.tables.forEach(table => {
                if (table.floor && table.floor.trim() !== '') {
                    uniqueFloors.add(table.floor);
                }
            });
            // Add unique floors from zones
            this.state.zones.forEach(zone => {
                if (zone.floor && zone.floor.trim() !== '') {
                    uniqueFloors.add(zone.floor);
                }
            });
            // Sort floors naturally and add to select
            Array.from(uniqueFloors).sort((a, b) => this.naturalSort(a, b)).forEach(floor => {
                const option = document.createElement('option');
                option.value = floor;
                option.textContent = floor;
                floorSelect.appendChild(option);
            });
            // Restore selection
            floorSelect.value = currentValue;
        }
    },
    
    /**
     * Clear all filters
     */
    clearFilters: function() {
        const filterZone = document.getElementById('filterZone');
        const filterFloor = document.getElementById('filterFloor');
        if (filterZone) filterZone.value = '';
        if (filterFloor) filterFloor.value = '';
        this.renderTables();
    },
    
    /**
     * Open add table modal
     */
    openAddTableModal: function() {
        const modal = document.getElementById('tableModal');
        const title = document.getElementById('modalTableTitle');
        const form = document.getElementById('tableForm');
        const tableId = document.getElementById('tableId');
        
        if (!modal || !title || !form || !tableId) {
            logger.error('Modal elements not found');
            if (window.NotificationManager) {
                window.NotificationManager.error('Modal açılamadı. Sayfayı yenileyin.');
            }
            return;
        }
        
        title.textContent = this.config.translations.addTable || 'Yeni Masa Ekle';
        form.reset();
        tableId.value = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },
    
    /**
     * Open edit table modal
     */
    openEditTableModal: function(tableId) {
        const table = this.state.tables.find(t => (t.id === tableId || t.table_id === tableId));
        if (!table) {
            logger.error('Table not found:', tableId);
            if (window.NotificationManager) {
                window.NotificationManager.error('Masa bulunamadı.');
            }
            return;
        }
        
        const modal = document.getElementById('tableModal');
        const title = document.getElementById('modalTableTitle');
        const formId = document.getElementById('tableId');
        const formName = document.getElementById('tableName');
        const formZone = document.getElementById('tableZone');
        const formCapacity = document.getElementById('tableCapacity');
        
        if (!modal || !title || !formId || !formName || !formZone || !formCapacity) {
            logger.error('Modal elements not found');
            if (window.NotificationManager) {
                window.NotificationManager.error('Modal açılamadı. Sayfayı yenileyin.');
            }
            return;
        }
        
        title.textContent = this.config.translations.editTable || 'Masa Düzenle';
        formId.value = table.id || table.table_id;
        formName.value = table.name || '';
        formZone.value = table.zone_id || '';
        formCapacity.value = table.capacity || 4;
        
        const remoteAccess = document.getElementById('tableRemoteAccess');
        if (remoteAccess) {
            remoteAccess.checked = !!(table.allow_remote_access && table.allow_remote_access != '0');
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },
    
    /**
     * Close table modal
     */
    closeTableModal: function() {
        const modal = document.getElementById('tableModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    
    /**
     * Delete table
     */
    deleteTable: async function(tableId) {
        const confirmMsg = this.config.translations.deleteConfirm || 'Bu masayı silmek istediğinizden emin misiniz?';
        
        if (!window.NotificationManager) {
            logger.error('NotificationManager is not available');
            return;
        }
        
        const confirmed = await window.NotificationManager.confirm(confirmMsg, this.config.translations.tableDeleteTitle || 'Masa Silme Onayı');
        
        if (!confirmed) {
            return;
        }
        
        try {
            const response = await fetch(`${this.config.baseUrl}${this.config.apiPrefix}/delete-table?id=${tableId}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            
            if (data.success) {
                if (window.NotificationManager) {
                    window.NotificationManager.success('Masa başarıyla silindi');
                }
                this.loadTables();
            } else {
                const msg = 'Hata: ' + (data.error || this.config.translations.deleteFailed || 'Silme başarısız');
                if (window.NotificationManager) {
                    window.NotificationManager.error(msg);
                }
            }
        } catch (error) {
            logger.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(this.config.translations.error || 'Bir hata oluştu');
            }
        }
    },
    
    /**
     * Load zones from API
     */
    async loadZones() {
        try {
            const response = await fetch(`${this.config.baseUrl}${this.config.apiPrefix}/zones`);
            const data = await response.json();
            
            if (data.error) {
                logger.error('Error loading zones:', data.error);
                this.state.zones = [];
                this.populateZoneSelect();
                return;
            }
            
            const zonesData = Array.isArray(data) ? data : (data.zones || data.data || []);
            this.state.zones = Array.isArray(zonesData) ? zonesData : [];
            this.populateZoneSelect();
            this.updateFilterOptions();
        } catch (error) {
            logger.error('Error loading zones:', error);
            this.state.zones = [];
            this.populateZoneSelect();
        }
    },
    
    /**
     * Populate zone select dropdown
     */
    populateZoneSelect: function() {
        const zoneSelect = document.getElementById('tableZone');
        if (!zoneSelect) return;
        
        // Clear existing options except the first one
        while (zoneSelect.options.length > 1) {
            zoneSelect.remove(1);
        }
        
        // Add zones
        this.state.zones.forEach(zone => {
            const option = document.createElement('option');
            option.value = zone.zone_id || zone.id;
            option.textContent = zone.name || 'Bilinmiyor';
            zoneSelect.appendChild(option);
        });
    },
    
    /**
     * Handle table form submit
     */
    handleTableSubmit: function(e) {
        e.preventDefault();
        
        const id = document.getElementById('tableId')?.value;
        const name = document.getElementById('tableName')?.value;
        const zoneId = document.getElementById('tableZone')?.value;
        const capacity = parseInt(document.getElementById('tableCapacity')?.value || 4);
        
        if (!name || !zoneId) {
            const msg = this.config.translations.fillRequiredFields || 'Lütfen tüm gerekli alanları doldurun';
            if (window.NotificationManager) {
                window.NotificationManager.warning(msg);
            }
            return;
        }
        
        const remoteAccess = document.getElementById('tableRemoteAccess')?.checked ? 1 : 0;
        
        const tableData = {
            name: name,
            zone_id: zoneId,
            capacity: capacity,
            allow_remote_access: remoteAccess
        };
        
        const url = id ? `${this.config.baseUrl}${this.config.apiPrefix}/update-table?id=${id}` : `${this.config.baseUrl}${this.config.apiPrefix}/add-table`;
        const method = id ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tableData)
        })
        .then(response => response.json())
        .then(async data => {
            if (data.success) {
                this.loadTables();
                this.closeTableModal();
                if (window.NotificationManager) {
                    window.NotificationManager.success(data.message || this.config.translations.saveFailed || 'Masa kaydedildi');
                }
            } else {
                // Handle error response - error can be boolean true or string message
                let errorMsg = '';
                if (data.error === true || data.error === false) {
                    // If error is boolean, use message or translation_key
                    errorMsg = data.message || this.config.translations.saveFailed || 'Kaydetme başarısız';
                    if (data.translation_key) {
                        if (typeof getTranslation === 'function') {
                            try {
                                const translated = await getTranslation(data.translation_key);
                                errorMsg = translated || errorMsg;
                            } catch (e) {
                                logger.warn('Translation failed:', e);
                                errorMsg = data.translation_key;
                            }
                        } else {
                            errorMsg = data.translation_key;
                        }
                    }
                } else {
                    // If error is string, use it
                    errorMsg = data.error || data.message || this.config.translations.saveFailed || 'Kaydetme başarısız';
                    if (data.translation_key && !errorMsg) {
                        if (typeof getTranslation === 'function') {
                            try {
                                const translated = await getTranslation(data.translation_key);
                                errorMsg = translated || data.translation_key;
                            } catch (e) {
                                logger.warn('Translation failed:', e);
                                errorMsg = data.translation_key;
                            }
                        } else {
                            errorMsg = data.translation_key;
                        }
                    }
                }
                
                if (window.NotificationManager) {
                    window.NotificationManager.error(errorMsg);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(this.config.translations.error || 'Bir hata oluştu');
            }
        });
    },
    
    /**
     * Update tables real-time
     */
    updateTablesRealtime: function(tablesData) {
        if (!tablesData || !tablesData.tables) return;
        this.loadTables();
    },
    
    /**
     * Update tables data (for super admin)
     */
    updateData: function(tables) {
        if (!Array.isArray(tables)) {
            logger.warn('Invalid tables data format:', tables);
            return;
        }
        
        // Map database format to view format
        this.state.tables = tables.map(table => ({
            id: table.table_id || table.id,
            name: table.name || 'Bilinmiyor',
            zone_id: table.zone_id || null,
            zone: table.zone || table.zone_name || 'Bilinmiyor',
            floor: table.floor || '',
            section: table.section || '',
            capacity: parseInt(table.capacity || 4),
            url: table.url || '',
            qr_code_url: table.qr_code_url || ''
        }));
        
        // Natural sort: zone first, then table name
        this.state.tables.sort((a, b) => {
            const zoneCompare = this.naturalSort(a.zone, b.zone);
            if (zoneCompare !== 0) {
                return zoneCompare;
            }
            return this.naturalSort(a.name, b.name);
        });
        
        logger.debug('Updated tables:', this.state.tables.length);
        this.updateFilterOptions();
        this.renderTables();
    },
    
    /**
     * View QR code for table
     */
    viewQRCode: function(tableId) {
        const table = this.state.tables.find(t => t.id === tableId || t.table_id === tableId);
        if (!table) {
            logger.error('Table not found:', tableId);
            if (window.NotificationManager) {
                window.NotificationManager.error('Masa bulunamadı.');
            }
            return;
        }
        
        this.state.currentQRTable = table;
        const tableIdValue = table.id || table.table_id || tableId;
        
        const qrModalTitle = document.getElementById('qrModalTitle');
        const qrTableInfo = document.getElementById('qrTableInfo');
        const qrCodeUrl = document.getElementById('qrCodeUrl');
        const qrContainer = document.getElementById('qrCodeContainer');
        const qrModal = document.getElementById('qrModal');
        
        if (!qrModalTitle || !qrTableInfo || !qrCodeUrl || !qrContainer || !qrModal) return;
        
        qrModalTitle.textContent = `${table.name} - QR Kod`;
        const zoneInfo = table.floor ? `${table.zone} - ${table.floor}` : table.zone;
        qrTableInfo.textContent = `${zoneInfo} - ${table.name}`;
        qrContainer.innerHTML = '';
        
        // Use URL from table data (already SEO-friendly from backend UrlService)
        // Will be updated from API response if available
        qrCodeUrl.value = table.url || '';
        
        // Generate QR code from API (uses UrlService for SEO-friendly URL)
        fetch(`${this.config.baseUrl}${this.config.apiPrefix}/tables/generate-qr-code?id=${tableIdValue}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.qr_code_url) {
                    // Use QR code from API (always uses SEO-friendly URL via UrlService)
                    const img = document.createElement('img');
                    img.src = data.qr_code_url;
                    img.alt = 'QR Code';
                    img.className = 'w-full max-w-xs mx-auto';
                    qrContainer.appendChild(img);
                    
                    // Update URL input with SEO-friendly URL from API response
                    if (data.table_url) {
                        qrCodeUrl.value = data.table_url;
                    }
                } else {
                    // Fallback: Generate QR code client-side using table.url from backend
                    const tableUrl = table.url || '';
                    if (tableUrl) {
                        this.generateQRCodeClientSide(qrContainer, tableUrl);
                    } else {
                        logger.error('No table URL available for QR code generation');
                        if (window.NotificationManager) {
                            window.NotificationManager.error('QR kod oluşturulamadı. Masa URL\'si bulunamadı.');
                        }
                    }
                }
            })
            .catch(error => {
                logger.error('Error fetching QR code:', error);
                // Fallback: Generate QR code client-side using table.url from backend
                const tableUrl = table.url || '';
                if (tableUrl) {
                    this.generateQRCodeClientSide(qrContainer, tableUrl);
                } else {
                    logger.error('No table URL available for QR code generation');
                    if (window.NotificationManager) {
                        window.NotificationManager.error('QR kod oluşturulamadı. Masa URL\'si bulunamadı.');
                    }
                }
            });
        
        qrModal.classList.remove('hidden');
        qrModal.classList.add('flex');
    },
    
    /**
     * Generate QR code client-side (fallback)
     */
    generateQRCodeClientSide: function(container, url) {
        // Load QRCode library if not already loaded
        if (typeof QRCode === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            script.onload = () => this.generateQR(container, url);
            document.head.appendChild(script);
        } else {
            this.generateQR(container, url);
        }
    },
    
    /**
     * Generate QR code
     */
    generateQR: function(container, url) {
        if (typeof QRCode === 'undefined') return;
        new QRCode(container, {
            text: url,
            width: 256,
            height: 256,
            colorDark: '#1e293b',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    },
    
    /**
     * Close QR modal
     */
    closeQRModal: function() {
        const qrModal = document.getElementById('qrModal');
        if (qrModal) {
            qrModal.classList.add('hidden');
            qrModal.classList.remove('flex');
        }
        this.state.currentQRTable = null;
    },
    
    /**
     * Copy QR URL
     */
    copyQRUrl: function() {
        const urlInput = document.getElementById('qrCodeUrl');
        if (!urlInput) return;
        
        urlInput.select();
        urlInput.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            document.execCommand('copy');
            if (window.NotificationManager) {
                window.NotificationManager.success('Link kopyalandı!');
            }
        } catch (err) {
            logger.error('Copy failed:', err);
            if (window.NotificationManager) {
                window.NotificationManager.error('Kopyalama başarısız');
            }
        }
    },
    
    /**
     * Download QR code
     */
    downloadQRCode: function() {
        if (!this.state.currentQRTable) return;
        
        const tableId = this.state.currentQRTable.id || this.state.currentQRTable.table_id;
        
        // Try to get QR code from API first
        fetch(`${this.config.baseUrl}${this.config.apiPrefix}/download-qr?id=${tableId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.qr_code_url) {
                    // Download from URL
                    const link = document.createElement('a');
                    link.href = data.qr_code_url;
                    link.download = `qr-${this.state.currentQRTable.name.replace(/\s+/g, '-').toLowerCase()}.png`;
                    link.click();
                    
                    if (window.NotificationManager) {
                        window.NotificationManager.success('QR kod indirildi!');
                    }
                } else {
                    // Fallback to canvas download
                    this.downloadQRFromCanvas();
                }
            })
            .catch(error => {
                logger.error('Error downloading QR:', error);
                // Fallback to canvas download
                this.downloadQRFromCanvas();
            });
    },
    
    /**
     * Download QR code from canvas (fallback)
     */
    downloadQRFromCanvas: function() {
        if (!this.state.currentQRTable) return;
        
        const canvas = document.querySelector('#qrCodeContainer canvas');
        if (!canvas) {
            if (window.NotificationManager) {
                window.NotificationManager.error('QR kod bulunamadı');
            }
            return;
        }
        
        try {
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = `qr-${this.state.currentQRTable.name.replace(/\s+/g, '-').toLowerCase()}.png`;
            link.href = url;
            link.click();
            
            if (window.NotificationManager) {
                window.NotificationManager.success('QR kod indirildi!');
            }
        } catch (err) {
            logger.error('Download failed:', err);
            if (window.NotificationManager) {
                window.NotificationManager.error('İndirme başarısız');
            }
        }
    },
    
    /**
     * Open zone management section (inline, not modal)
     */
    openZoneManagement: function() {
        const section = document.getElementById('zoneManagementSection');
        const tablesGrid = document.getElementById('tablesGrid');
        const filtersSection = document.getElementById('filtersSection');
        
        if (!section) {
            logger.error('Zone management section not found');
            return;
        }
        
        // Hide tables grid and filters
        if (tablesGrid) {
            tablesGrid.style.display = 'none';
        }
        if (filtersSection) {
            filtersSection.style.display = 'none';
        }
        
        // Show zone management section
        section.classList.remove('hidden');
        section.classList.add('block');
        
        // Load zones
        this.loadZonesForManagement();
    },
    
    /**
     * Close zone management section
     */
    closeZoneManagement: function() {
        const section = document.getElementById('zoneManagementSection');
        const tablesGrid = document.getElementById('tablesGrid');
        const filtersSection = document.getElementById('filtersSection');
        
        if (section) {
            section.classList.add('hidden');
            section.classList.remove('block');
        }
        
        // Show tables grid and filters
        if (tablesGrid) {
            tablesGrid.style.display = '';
        }
        if (filtersSection) {
            filtersSection.style.display = '';
        }
    },
    
    /**
     * Load zones for management modal
     */
    async loadZonesForManagement() {
        try {
            const response = await fetch(`${this.config.baseUrl}${this.config.apiPrefix}/zones`);
            const data = await response.json();
            
            if (data.error) {
                logger.error('Error loading zones:', data.error);
                this.state.zones = [];
                this.renderZonesForManagement();
                return;
            }
            
            const zonesData = Array.isArray(data) ? data : (data.zones || data.data || []);
            this.state.zones = Array.isArray(zonesData) ? zonesData : [];
            
            // Natural sort zones
            this.state.zones.sort((a, b) => {
                const nameA = String(a.name || '').toUpperCase();
                const nameB = String(b.name || '').toUpperCase();
                return this.naturalSort(nameA, nameB);
            });
            
            this.renderZonesForManagement();
        } catch (error) {
            logger.error('Error loading zones:', error);
            this.state.zones = [];
            this.renderZonesForManagement();
        }
    },
    
    /**
     * Render zones in management section
     */
    renderZonesForManagement: function() {
        const container = document.getElementById('zonesManagementList');
        if (!container) return;
        
        const escapeHtml = window.Utils?.escapeHtml || function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Escape JavaScript string for use in onclick handlers
        const escapeJs = function(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/"/g, '\\"')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '\\r')
                .replace(/\t/g, '\\t');
        };
        
        if (!this.state.zones || this.state.zones.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <div class="inline-block p-4 bg-slate-100 rounded-full mb-4">
                        <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                    </div>
                    <p class="text-slate-400 font-bold text-sm">Henüz bölge eklenmemiş</p>
                    <button onclick="openAddZoneModal()" class="mt-4 px-4 py-2 q-btn q-btn--primary q-btn--sm transition-all">
                        İlk Bölgeyi Ekle
                    </button>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.state.zones.map(zone => {
            const zoneId = zone.zone_id || zone.id;
            const name = escapeHtml(zone.name || '');
            const floor = escapeHtml(zone.floor || '');
            const description = escapeHtml(zone.description || '');
            const tableCount = zone.table_count || 0;
            
            return `
                <article class="q-zone-mgmt-card">
                    <div class="q-zone-mgmt-card__head">
                        <div class="flex items-start gap-2 min-w-0">
                            ${BIZ_FLOOR_PLAN_ICON.replace('w-4 h-4', 'w-5 h-5 text-indigo-600 mt-0.5')}
                            <div class="min-w-0 flex-1">
                                <h3 class="q-zone-mgmt-card__name" title="${name}">${name}</h3>
                                <div class="q-zone-mgmt-card__meta">
                                    ${floor ? `<span class="inline-flex items-center gap-1">${BIZ_TABLE_TOP_ICON_SM}<span>Kat: ${floor}</span></span>` : ''}
                                    ${description ? `<span class="line-clamp-2 w-full">${description}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="q-zone-mgmt-card__footer">
                        <span class="q-zone-mgmt-card__count">${BIZ_TABLE_TOP_ICON_SM}<span>${tableCount} masa</span></span>
                        <div class="q-zone-mgmt-card__actions">
                            <button type="button" onclick="openEditZoneModal('${escapeJs(zoneId)}')" class="q-btn q-btn--soft q-btn--xs">Düzenle</button>
                            <button type="button" onclick="deleteZone('${escapeJs(zoneId)}')" class="q-btn q-btn--ghost q-btn--xs text-red-600">Sil</button>
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    },
    
    
    /**
     * Open add zone modal
     */
    openAddZoneModal: function() {
        const modal = document.getElementById('zoneModal');
        const modalTitle = document.getElementById('modalZoneTitle');
        const zoneForm = document.getElementById('zoneForm');
        const zoneId = document.getElementById('zoneId');
        const zoneName = document.getElementById('zoneName');
        const zoneFloor = document.getElementById('zoneFloor');
        const zoneDescription = document.getElementById('zoneDescription');
        
        if (!modal || !modalTitle || !zoneForm) {
            logger.error('Zone modal elements not found');
            if (window.NotificationManager) {
                window.NotificationManager.error('Modal açılamadı. Sayfayı yenileyin.');
            }
            return;
        }
        
        // Reset form
        zoneId.value = '';
        zoneName.value = '';
        zoneFloor.value = '';
        zoneDescription.value = '';
        modalTitle.textContent = 'Bölge Ekle';
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },
    
    /**
     * Open edit zone modal
     */
    openEditZoneModal: function(zoneId) {
        const zone = this.state.zones.find(z => (z.zone_id || z.id) === zoneId);
        if (!zone) {
            logger.error('Zone not found:', zoneId);
            if (window.NotificationManager) {
                window.NotificationManager.error('Bölge bulunamadı.');
            }
            return;
        }
        
        const modal = document.getElementById('zoneModal');
        const modalTitle = document.getElementById('modalZoneTitle');
        const zoneIdInput = document.getElementById('zoneId');
        const zoneName = document.getElementById('zoneName');
        const zoneFloor = document.getElementById('zoneFloor');
        const zoneDescription = document.getElementById('zoneDescription');
        
        if (!modal || !modalTitle || !zoneIdInput) {
            logger.error('Zone modal elements not found');
            if (window.NotificationManager) {
                window.NotificationManager.error('Modal açılamadı. Sayfayı yenileyin.');
            }
            return;
        }
        
        // Fill form
        zoneIdInput.value = zone.zone_id || zone.id;
        zoneName.value = zone.name || '';
        zoneFloor.value = zone.floor || '';
        zoneDescription.value = zone.description || '';
        modalTitle.textContent = 'Bölge Düzenle';
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },
    
    /**
     * Close zone modal
     */
    closeZoneModal: function() {
        const modal = document.getElementById('zoneModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    /**
     * Save zone (create or update)
     */
    saveZone: async function() {
        const zoneId = document.getElementById('zoneId')?.value;
        const name = document.getElementById('zoneName')?.value?.trim();
        const floor = document.getElementById('zoneFloor')?.value?.trim() || '';
        const description = document.getElementById('zoneDescription')?.value?.trim() || '';
        
        if (!name) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Bölge adı gereklidir');
            }
            return;
        }
        
        const zoneData = {
            name: name,
            floor: floor,
            description: description
        };
        
        // Get business_id from URL or window variable (for super admin)
        const urlParams = new URLSearchParams(window.location.search);
        const businessId = urlParams.get('business_id') || window.currentBusinessId || null;
        
        // Build URL with business_id query parameter if available
        let baseUrl = zoneId 
            ? `${this.config.baseUrl}${this.config.apiPrefix}/zones/${zoneId}` 
            : `${this.config.baseUrl}${this.config.apiPrefix}/zones`;
        
        if (businessId) {
            baseUrl += (baseUrl.includes('?') ? '&' : '?') + `business_id=${encodeURIComponent(businessId)}`;
        }
        
        const method = zoneId ? 'PUT' : 'POST';
        
        try {
            const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                             document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             (window.CSRF_TOKEN || '');
            
            const response = await fetch(baseUrl, {
                method: method,
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(zoneData)
            });
            
            let data;
            const responseText = await response.text();
            
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                // If response is not JSON, show the raw text
                logger.error('Non-JSON response:', responseText);
                throw new Error(`Server error: ${response.status} - ${responseText.substring(0, 200)}`);
            }
            
            if (!response.ok) {
                // Log full response for debugging
                logger.error('Error response:', data);
                let errorMsg = data.error || data.message || `HTTP error! status: ${response.status}`;
                
                // If translation_key exists, translate it
                if (data.translation_key && typeof getTranslation === 'function') {
                    try {
                        const translated = await getTranslation(data.translation_key);
                        errorMsg = translated || errorMsg;
                    } catch (e) {
                        logger.warn('Translation failed:', e);
                    }
                } else if (data.translation_key) {
                    errorMsg = data.translation_key;
                }
                
                throw new Error(errorMsg);
            }
            
            if (data.success || !data.error) {
                if (window.NotificationManager) {
                    window.NotificationManager.success(zoneId ? 'Bölge güncellendi' : 'Bölge eklendi');
                }
                // Reload zones and tables
                await this.loadZones();
                await this.loadTables();
                this.closeZoneModal();
                // Reload zones in management section if open
                const zoneSection = document.getElementById('zoneManagementSection');
                if (zoneSection && !zoneSection.classList.contains('hidden')) {
                    await this.loadZonesForManagement();
                }
            } else {
                let msg = 'Hata: ' + (data.error || data.message || 'Kaydetme başarısız');
                
                // If translation_key exists, translate it
                if (data.translation_key && typeof getTranslation === 'function') {
                    try {
                        const translated = await getTranslation(data.translation_key);
                        msg = translated || msg;
                    } catch (e) {
                        logger.warn('Translation failed:', e);
                        if (data.translation_key) {
                            msg = data.translation_key;
                        }
                    }
                } else if (data.translation_key) {
                    msg = data.translation_key;
                }
                
                if (window.NotificationManager) {
                    window.NotificationManager.error(msg);
                }
            }
        } catch (error) {
            logger.error('Error saving zone:', error);
            let errorMsg = error.message || 'Bağlantı hatası';
            
            // Check if error message is a translation key
            if (errorMsg.includes('.') && typeof getTranslation === 'function') {
                try {
                    const translated = await getTranslation(errorMsg);
                    if (translated && translated !== errorMsg) {
                        errorMsg = translated;
                    }
                } catch (e) {
                    logger.warn('Translation failed:', e);
                }
            }
            
            if (window.NotificationManager) {
                window.NotificationManager.error(errorMsg);
            }
        }
    },
    
    /**
     * Delete zone
     */
    deleteZone: async function(zoneId) {
        const confirmMsg = this.config.translations.zoneDeleteConfirm || 'Bu bölgeyi silmek istediğinize emin misiniz? Bu bölgeye ait masalar varsa silinemez.';
        
        if (!window.NotificationManager) {
            console.error('NotificationManager is not available');
            return;
        }
        
        const confirmed = await window.NotificationManager.confirm(confirmMsg, this.config.translations.zoneDeleteTitle || 'Bölge Silme Onayı');
        
        if (!confirmed) {
            return;
        }
        
        try {
            const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                             document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             (window.CSRF_TOKEN || '');
            
            // Get business_id from URL or window variable (for super admin)
            const urlParams = new URLSearchParams(window.location.search);
            const businessId = urlParams.get('business_id') || window.currentBusinessId || null;
            
            // Build URL with business_id query parameter if available
            let deleteUrl = `${this.config.baseUrl}${this.config.apiPrefix}/zones/${zoneId}`;
            if (businessId) {
                deleteUrl += `?business_id=${encodeURIComponent(businessId)}`;
            }
            
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success || !data.error) {
                if (window.NotificationManager) {
                    window.NotificationManager.success('Bölge silindi');
                }
                // Reload zones and tables
                await this.loadZones();
                await this.loadTables();
                // Reload zones in management section if open
                const zoneSection = document.getElementById('zoneManagementSection');
                if (zoneSection && !zoneSection.classList.contains('hidden')) {
                    await this.loadZonesForManagement();
                }
            } else {
                const msg = 'Hata: ' + (data.error || 'Silme başarısız');
                if (window.NotificationManager) {
                    window.NotificationManager.error(msg);
                }
            }
        } catch (error) {
            logger.error('Error deleting zone:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Bağlantı hatası');
            }
        }
    },
};

// Global functions for backward compatibility (onclick handlers)
window.openAddTableModal = function() { TablesPage.openAddTableModal(); };
window.openEditTableModal = function(id) { TablesPage.openEditTableModal(id); };
window.closeTableModal = function() { TablesPage.closeTableModal(); };
window.deleteTable = function(id) { TablesPage.deleteTable(id); };
window.updateFilters = function() { TablesPage.updateFilters(); };
window.clearFilters = function() { TablesPage.clearFilters(); };
window.viewQRCode = function(id) { TablesPage.viewQRCode(id); };
window.closeQRModal = function() { TablesPage.closeQRModal(); };
window.copyQRUrl = function() { TablesPage.copyQRUrl(); };
window.downloadQRCode = function() { TablesPage.downloadQRCode(); };
window.openZoneManagement = function() { TablesPage.openZoneManagement(); };
window.closeZoneManagement = function() { TablesPage.closeZoneManagement(); };
window.openAddZoneModal = function() { TablesPage.openAddZoneModal(); };
window.openEditZoneModal = function(id) { TablesPage.openEditZoneModal(id); };
window.closeZoneModal = function() { TablesPage.closeZoneModal(); };
window.saveZone = function() { TablesPage.saveZone(); };
window.deleteZone = function(id) { TablesPage.deleteZone(id); };

// Make TablesPage globally available

// Make TablesPage globally available
window.TablesPage = TablesPage;
