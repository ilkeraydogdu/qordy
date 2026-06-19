/**
 * Business Selector Component for Super Admin
 * Displays business selection grid and handles business context switching
 * Version: 1.0
 */

const BusinessSelector = {
    businesses: [],
    selectedBusinessId: null,
    selectedBusinessName: null,
    onSelectCallback: null,
    
    config: {
        baseUrl: '',
        containerSelector: '#business-grid',
        contentSelector: '#business-content-view'
    },
    
    /**
     * Initialize the business selector
     * @param {Object} config - Configuration options
     */
    init: function(config = {}) {
        this.config = { ...this.config, ...config };
        return this;
    },
    
    /**
     * Load all businesses from API
     * @returns {Promise<Array>} Array of businesses
     */
    async loadBusinesses() {
        try {
            const response = await fetch(`${this.config.baseUrl}/api/qodmin/businesses`);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`HTTP ${response.status}: İşletmeler yüklenemedi`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
            
            // Debug log
            console.log('Businesses loaded:', this.businesses.length, 'businesses');
            
            return this.businesses;
        } catch (error) {
            console.error('Error loading businesses:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(`İşletmeler yüklenirken hata oluştu: ${error.message}`);
            } else {
                alert(`İşletmeler yüklenirken hata oluştu: ${error.message}`);
            }
            return [];
        }
    },
    
    /**
     * Convert text to URL-safe slug
     * @param {string} text - Text to convert
     * @returns {string} URL-safe slug
     */
    createSlug: function(text) {
        const trMap = {
            'ç': 'c', 'Ç': 'C',
            'ğ': 'g', 'Ğ': 'G',
            'ı': 'i', 'İ': 'I',
            'ö': 'o', 'Ö': 'O',
            'ş': 's', 'Ş': 'S',
            'ü': 'u', 'Ü': 'U'
        };
        
        return text
            .split('')
            .map(char => trMap[char] || char)
            .join('')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },
    
    /**
     * Render business selection grid
     * @param {string} containerId - Container element ID
     * @param {Function} onSelect - Callback when business is selected
     */
    renderBusinessGrid: function(containerId, onSelect) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Business grid container not found:', containerId);
            return;
        }
        
        this.onSelectCallback = onSelect;
        
        if (this.businesses.length === 0) {
            // CRITICAL: BusinessSelector is ONLY for Super Admin
            // If businesses array is empty, check if user is actually super admin
            // If not super admin, this component should not be loaded at all
            container.innerHTML = `
                <div class="col-span-full text-center py-12 px-4">
                    <div class="inline-block p-6 bg-blue-50 rounded-full mb-4">
                        <svg class="w-16 h-16 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-slate-800 font-black text-xl mb-3">Henüz İşletme Kaydı Yok</h3>
                    <p class="text-slate-600 mb-6">Sistemde henüz kayıtlı işletme bulunmuyor. Yeni işletme ekleyerek başlayabilirsiniz.</p>
                    <a href="${this.config.baseUrl}/qodmin/businesses/create" 
                       class="inline-block px-6 py-3 bg-orange-500 text-white rounded-lg font-bold hover:bg-orange-600 transition-all shadow-md hover:shadow-lg">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        İlk İşletmeyi Ekle
                    </a>
                </div>
            `;
            return;
        }
        
        const escapeHtml = window.Utils?.escapeHtml || function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        let html = '';
        
        this.businesses.forEach(business => {
            const businessId = business.business_id || business.id;
            
            // Determine business name with improved fallback logic (same as other pages)
            let businessName = (business.company_name || business.business_name || business.name || '').trim();
            
            // If no business name, try fallback options
            if (!businessName) {
                // Try owner name
                const ownerName = (business.owner_name || business.owner || '').trim();
                if (ownerName) {
                    businessName = ownerName;
                } else {
                    // Try email
                    const email = (business.email || business.business_email || '').trim();
                    if (email) {
                        businessName = email.split('@')[0]; // Use email username part
                    } else {
                        // Last resort: use generic name with ID
                        businessName = `İşletme (${businessId.substring(0, 8)})`;
                    }
                }
            }
            
            const businessSlug = this.createSlug(businessName);
            const ownerName = business.owner_name || business.owner || '';
            const location = business.location || business.city || '';
            const packageName = business.package_name || business.package || 'Standart';
            const status = business.status || 'active';
            const totalRevenue = business.total_revenue || 0;
            const totalOrders = business.total_orders || 0;
            const totalTables = business.total_tables || 0;
            const totalStaff = business.total_staff || 0;
            
            const statusClass = status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
            const statusText = status === 'active' ? 'Aktif' : 'Pasif';
            
            // Get logo path (from BusinessService - customers table)
            const logoPath = business.logo_path || business.logo_url || '';
            const baseUrl = this.config.baseUrl || '';
            const logoUrl = logoPath ? (logoPath.startsWith('http') ? logoPath : baseUrl + logoPath) : '';
            
            html += `
                <div class="group bg-white border-2 border-slate-200 rounded-2xl p-6 hover:border-orange-500 hover:shadow-2xl transition-all duration-300 cursor-pointer transform hover:-translate-y-1 flex flex-col items-center text-center"
                     onclick="BusinessSelector.selectBusiness('${escapeHtml(businessId)}', '${escapeHtml(businessName)}', '${escapeHtml(businessSlug)}')">
                    <!-- Logo or Icon -->
                    <div class="mb-4 flex items-center justify-center w-24 h-24 bg-slate-50 rounded-xl border-2 border-slate-200 group-hover:border-orange-500 transition-all">
                        ${logoUrl ? `
                            <img src="${escapeHtml(logoUrl)}" 
                                 alt="${escapeHtml(businessName)}" 
                                 class="w-full h-full object-contain rounded-lg p-2"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="hidden w-full h-full items-center justify-center text-slate-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                        ` : `
                            <div class="w-full h-full flex items-center justify-center text-slate-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                        `}
                    </div>
                    
                    <!-- Business Name -->
                    <h3 class="text-lg font-black text-slate-900 mb-2 group-hover:text-orange-600 transition-colors line-clamp-2">
                        ${escapeHtml(businessName)}
                    </h3>
                    
                    <!-- Status Badge -->
                    <span class="px-3 py-1 rounded-lg text-xs font-bold ${statusClass}">
                        ${statusText}
                    </span>
                </div>
            `;
        });
        
        container.innerHTML = html;
    },
    
    /**
     * Select a business
     * @param {string} businessId - Business ID
     * @param {string} businessName - Business name
     * @param {string} businessSlug - Business slug (optional)
     */
    selectBusiness: function(businessId, businessName, businessSlug) {
        this.selectedBusinessId = businessId;
        this.selectedBusinessName = businessName;
        
        // Generate slug if not provided
        if (!businessSlug) {
            businessSlug = this.createSlug(businessName);
        }
        
        // Store in sessionStorage for persistence
        sessionStorage.setItem('selected_business_id', businessId);
        sessionStorage.setItem('selected_business_name', businessName);
        sessionStorage.setItem('selected_business_slug', businessSlug);
        
        // ✅ CRITICAL FIX: Add business_id to URL for tenant context persistence
        // This ensures that when superadmin adds data, it's properly isolated to this business
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('business_id', businessId);
        
        // Update URL without page reload (if history API is supported)
        if (window.history && window.history.pushState) {
            window.history.pushState(
                { businessId, businessName, businessSlug }, 
                '', 
                currentUrl.toString()
            );
        }
        
        // Log for debugging
        console.log('Business selected - Tenant context set:', {
            businessId,
            businessName,
            url: currentUrl.toString()
        });
        
        if (this.onSelectCallback && typeof this.onSelectCallback === 'function') {
            this.onSelectCallback(businessId, businessName, businessSlug);
        }
    },
    
    /**
     * Clear business selection
     */
    clearSelection: function() {
        this.selectedBusinessId = null;
        this.selectedBusinessName = null;
        sessionStorage.removeItem('selected_business_id');
        sessionStorage.removeItem('selected_business_name');
    },
    
    /**
     * Get selected business from session
     * @returns {Object|null} Selected business data or null
     */
    getSelectedBusiness: function() {
        const businessId = sessionStorage.getItem('selected_business_id');
        const businessName = sessionStorage.getItem('selected_business_name');
        
        if (businessId && businessName) {
            return {
                business_id: businessId,
                business_name: businessName
            };
        }
        
        return null;
    },
    
    /**
     * Show business selection view
     * @param {string} selectionViewId - Selection view container ID
     * @param {string} contentViewId - Content view container ID
     */
    showSelectionView: function(selectionViewId, contentViewId) {
        const selectionView = document.getElementById(selectionViewId);
        const contentView = document.getElementById(contentViewId);
        
        if (selectionView) {
            selectionView.classList.remove('hidden');
        }
        if (contentView) {
            contentView.classList.add('hidden');
        }
        
        this.clearSelection();
    },
    
    /**
     * Show content view (after business selection)
     * @param {string} selectionViewId - Selection view container ID
     * @param {string} contentViewId - Content view container ID
     * @param {string} businessName - Business name to display
     */
    showContentView: function(selectionViewId, contentViewId, businessName) {
        const selectionView = document.getElementById(selectionViewId);
        const contentView = document.getElementById(contentViewId);
        
        if (selectionView) {
            selectionView.classList.add('hidden');
        }
        if (contentView) {
            contentView.classList.remove('hidden');
        }
        
        // Update business name display if element exists
        const businessNameElement = document.getElementById('selected-business-name');
        if (businessNameElement && businessName) {
            businessNameElement.textContent = businessName;
        }
    },
    
    /**
     * Format currency
     * @param {number} amount - Amount to format
     * @returns {string} Formatted currency
     */
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount || 0);
    },
    
    /**
     * Search businesses by name
     * @param {string} searchTerm - Search term
     */
    searchBusinesses: function(searchTerm) {
        const term = searchTerm.toLowerCase();
        const cards = document.querySelectorAll('[onclick*="selectBusiness"]');
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(term)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
};

// Export to global scope
window.BusinessSelector = BusinessSelector;
