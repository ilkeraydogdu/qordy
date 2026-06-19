/**
 * Menu Management Page JavaScript
 * Handles menu item listing, CRUD operations, filtering, and pagination
 */

// Import logger (fallback to console if not available)
const logger = (typeof window !== 'undefined' && window.logger) || {
    debug: () => {},
    info: () => {},
    warn: console.warn ? console.warn.bind(console) : () => {},
    error: console.error ? console.error.bind(console) : () => {},
    log: () => {}
};

// Ensure escapeHtml is available (fallback if utils.js hasn't loaded yet)
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = function(text) {
        if (typeof text === 'undefined' || text === null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };
}

// Ensure formatCurrency is available (fallback if utils.js hasn't loaded yet)
if (typeof window.formatCurrency === 'undefined') {
    window.formatCurrency = function(amount) {
        if (typeof amount === 'undefined' || amount === null || isNaN(amount)) return '₺0';
        const num = parseFloat(amount);
        if (isNaN(num)) return '₺0';
        return '₺' + num.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
}

// MenuPage module
const MenuPage = {
    config: {
        baseUrl: '',
        adminPrefix: '/business', // Default to /business, will be overridden by init()
        apiPrefix: '/api/business',
        menuItems: [],
        categories: [],
        supportedLanguages: ['tr', 'en'],
        defaultLanguage: 'tr',
        permissions: {
            canCreate: false,
            canEdit: false,
            canDelete: false,
            canManageCategories: false
        },
        translations: {
            newItem: 'Yeni Ürün',
            edit: 'Düzenle'
        }
    },
    
    state: {
        editingItem: null,
        ingredients: [],
        extras: [],
        variants: [],
        allMenuItems: [],
        filteredItems: [],
        currentPage: 1,
        itemsPerPage: 25,
        searchTerm: '',
        currentModuleTab: 'ITEMS',
        currentLanguageTab: 'tr',
        activeFilters: {
            category: '',
            status: 'all',
            stock: 'all'
        },
        currentImageInputMode: 'url', // 'url' or 'file'
        searchTimeout: null,
        formSubmitHandlerAttached: false,
        pendingImageFile: null, // Store image file for upload after product creation
        extractedItems: [], // Store extracted menu items
        extractionImageFiles: [] // Store multiple image files for extraction (max 5)
    },
    
    /**
     * Initialize menu page
     * @param {Object} config - Configuration object
     */
    init: function(config) {
        try {
            console.log('MenuPage.init called with config:', config);
            
            // Merge config
            this.config = { ...this.config, ...config };
            
            // Validate and set state
            this.state.allMenuItems = Array.isArray(this.config.menuItems) ? [...this.config.menuItems] : [];
            this.state.filteredItems = [...this.state.allMenuItems];
            this.state.currentLanguageTab = this.config.defaultLanguage || 'tr';
            
            // Validate config
            if (!this.config.baseUrl || typeof this.config.baseUrl !== 'string') {
                console.warn('baseUrl is invalid, using fallback');
                this.config.baseUrl = window.BASE_URL || '';
            }
            
            if (!Array.isArray(this.config.categories)) {
                console.warn('categories is not an array, using empty array');
                this.config.categories = [];
            }
            
            if (!Array.isArray(this.config.supportedLanguages) || this.config.supportedLanguages.length === 0) {
                console.warn('supportedLanguages is invalid, using default');
                this.config.supportedLanguages = ['tr', 'en'];
            }
            
            if (!this.config.defaultLanguage || typeof this.config.defaultLanguage !== 'string') {
                console.warn('defaultLanguage is invalid, using fallback');
                this.config.defaultLanguage = 'tr';
            }
            
            console.debug('MenuPage initialized:', {
                baseUrl: this.config.baseUrl ? 'OK' : 'EMPTY',
                menuItems: this.state.allMenuItems.length,
                categories: this.config.categories.length,
                supportedLanguages: this.config.supportedLanguages.length,
                defaultLanguage: this.config.defaultLanguage
            });
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Setup form submit handler
            this.setupFormSubmit();
            
            // Setup image URL sync
            this.setupImageUrlSync();
            
            // Initialize filters and render
            this.applyFilters();
            
            // Expose functions to window for backward compatibility
            this.exposeToWindow();
            
            console.log('MenuPage initialized successfully');
        } catch (error) {
            console.error('Error initializing MenuPage:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Menü sayfası başlatılırken bir hata oluştu.');
            }
        }
    },
    
    /**
     * Expose functions to window for backward compatibility
     */
    exposeToWindow: function() {
        window.openModal = (id) => this.openModal(id);
        window.closeModal = () => this.closeModal();
        window.switchLanguageTab = (lang) => this.switchLanguageTab(lang);
        window.updateProductionPointFromCategory = () => this.updateProductionPointFromCategory();
        window.toggleVariantsSection = () => this.toggleVariantsSection();
        window.addVariant = () => this.addVariant();
        window.removeVariant = (index) => this.removeVariant(index);
        window.addIngredient = () => this.addIngredient();
        window.removeIngredient = (ing) => this.removeIngredient(ing);
        window.renderIngredients = () => this.renderIngredients();
        window.addExtra = () => this.addExtra();
        window.removeExtra = (name) => this.removeExtra(name);
        window.renderExtras = () => this.renderExtras();
        window.deleteMenuItem = (id) => this.deleteMenuItem(id);
        window.handleSearch = () => this.handleSearch();
        window.applyFilters = () => this.applyFilters();
        window.clearFilters = () => this.clearFilters();
        window.changeItemsPerPage = () => this.changeItemsPerPage();
        window.goToPage = (page) => this.goToPage(page);
        window.switchImageInput = (mode) => this.switchImageInput(mode);
        window.handleImageFileSelect = (event) => this.handleImageFileSelect(event);
        window.showImagePreview = (imageUrl) => this.showImagePreview(imageUrl);
        window.clearImagePreview = () => this.clearImagePreview();
        window.autoTranslateInput = (lang, field) => this.autoTranslateInput(lang, field);
    },
    
    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Remove existing listeners to prevent duplicates
        if (this._clickHandler) {
            document.removeEventListener('click', this._clickHandler, true);
        }
        if (this._inputHandler) {
            document.removeEventListener('input', this._inputHandler, true);
        }
        if (this._changeHandler) {
            document.removeEventListener('change', this._changeHandler, true);
        }
        if (this._fileChangeHandler) {
            document.removeEventListener('change', this._fileChangeHandler, true);
        }
        
        // Click handler with event delegation
        this._clickHandler = async (e) => {
            // Extract menu button
            let btnExtractMenu = null;
            if (e.target.id === 'btn-extract-menu' || e.target.closest('#btn-extract-menu')) {
                btnExtractMenu = e.target.id === 'btn-extract-menu' ? e.target : e.target.closest('#btn-extract-menu');
            }
            
            // Check for header button
            if (btnExtractMenu && btnExtractMenu.id === 'btn-extract-menu' && btnExtractMenu.closest('header')) {
                e.preventDefault();
                e.stopPropagation();
                console.debug('Extract menu button clicked');
                this.openMenuExtractionModal();
                return;
            }
            
            // Check for modal extract button
            if (e.target.id === 'btn-extract-menu-modal' || e.target.closest('#btn-extract-menu-modal')) {
                e.preventDefault();
                e.stopPropagation();
                console.debug('Extract menu button in modal clicked');
                this.extractMenuFromImage();
                return;
            }
            
            // New item button
            let btnNewItem = null;
            if (e.target.id === 'btn-new-item') {
                btnNewItem = e.target;
            } else if (e.target.closest) {
                btnNewItem = e.target.closest('#btn-new-item');
            }
            
            if (btnNewItem) {
                e.preventDefault();
                e.stopPropagation();
                console.debug('New item button clicked');
                this.openModal();
                return;
            }
            
            // Edit buttons - check for button or any parent with class
            let editBtn = null;
            if (e.target.classList && e.target.classList.contains('btn-edit-item')) {
                editBtn = e.target;
            } else if (e.target.closest) {
                editBtn = e.target.closest('.btn-edit-item');
            }
            
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                const itemId = editBtn.getAttribute('data-item-id');
                console.debug('Edit button clicked', { itemId });
                if (itemId) {
                    this.openModal(itemId);
                }
                return;
            }
            
            // Delete buttons - check for button or any parent with class
            let deleteBtn = null;
            if (e.target.classList && e.target.classList.contains('btn-delete-item')) {
                deleteBtn = e.target;
            } else if (e.target.closest) {
                deleteBtn = e.target.closest('.btn-delete-item');
            }
            
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                const itemId = deleteBtn.getAttribute('data-item-id');
                console.debug('Delete button clicked', { itemId });
                if (itemId) {
                    this.deleteMenuItem(itemId);
                }
                return;
            }
            
            // Modal backdrop click
            if (e.target.closest('#modal-backdrop')) {
                e.preventDefault();
                this.closeModal();
            }
            
            // Modal close buttons - check for button or any child element (including SVG paths)
            const closeXBtn = e.target.closest('#btn-modal-close-x') || (e.target.closest('svg') && e.target.closest('#btn-modal-close-x'));
            const cancelBtn = e.target.closest('#btn-modal-cancel');
            
            // Also check if clicked element is inside the close button
            if (closeXBtn || cancelBtn || 
                e.target.id === 'btn-modal-close-x' || 
                e.target.id === 'btn-modal-cancel' ||
                (e.target.closest('button') && e.target.closest('button').id === 'btn-modal-close-x') ||
                (e.target.closest('button') && e.target.closest('button').id === 'btn-modal-cancel')) {
                e.preventDefault();
                e.stopPropagation();
                this.closeModal();
                return;
            }
            
            // Clear filters button
            if (e.target.closest('#btn-clear-filters')) {
                e.preventDefault();
                this.clearFilters();
            }
            
            // Pagination buttons
            if (e.target.closest('#pagination-controls')) {
                const btn = e.target.closest('button');
                if (btn && !btn.disabled && !btn.classList.contains('cursor-not-allowed')) {
                    e.preventDefault();
                    const page = parseInt(btn.getAttribute('data-page'));
                    if (page && !isNaN(page)) {
                        this.goToPage(page);
                    }
                }
            }
            
            // Language tab buttons
            if (e.target.closest('.lang-tab-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.lang-tab-btn');
                const lang = btn.getAttribute('data-lang');
                if (lang) {
                    this.switchLanguageTab(lang);
                }
            }
            
            // Image toggle buttons
            if (e.target.closest('#image-url-toggle')) {
                e.preventDefault();
                this.switchImageInput('url');
            }
            
            if (e.target.closest('#image-file-toggle')) {
                e.preventDefault();
                this.switchImageInput('file');
            }
            
            // Add ingredient button
            if (e.target.closest('#btn-add-ingredient')) {
                e.preventDefault();
                this.addIngredient();
            }
            
            // Add extra button - check multiple ways to catch the click
            const addExtraBtn = e.target.closest('#btn-add-extra') || 
                               (e.target.id === 'btn-add-extra' ? e.target : null) ||
                               (e.target.closest('button') && e.target.closest('button').id === 'btn-add-extra' ? e.target.closest('button') : null) ||
                               (e.target.closest('svg') && e.target.closest('svg').parentElement && e.target.closest('svg').parentElement.id === 'btn-add-extra' ? e.target.closest('svg').parentElement : null);
            if (addExtraBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.debug('Add extra button clicked', { target: e.target, button: addExtraBtn });
                this.addExtra();
                return;
            }
            
            // Add variant button (use closest to handle span inside button)
            // Check both button ID and any child elements
            const addVariantBtn = e.target.closest('#btn-add-variant') || 
                                 (e.target.id === 'btn-add-variant' ? e.target : null) ||
                                 (e.target.parentElement && e.target.parentElement.id === 'btn-add-variant' ? e.target.parentElement : null) ||
                                 (e.target.closest('button') && e.target.closest('button').id === 'btn-add-variant' ? e.target.closest('button') : null);
            if (addVariantBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.debug('Add variant button clicked', { target: e.target, button: addVariantBtn });
                this.addVariant();
                return;
            }
            
            // Remove variant button
            const removeVariantBtn = e.target.closest('.btn-remove-variant') ||
                                    (e.target.classList.contains('btn-remove-variant') ? e.target : null);
            if (removeVariantBtn) {
                e.preventDefault();
                const index = parseInt(removeVariantBtn.getAttribute('data-variant-index'));
                if (!isNaN(index)) {
                    console.debug('Remove variant clicked', { index });
                    this.removeVariant(index);
                }
            }
            
            // Accordion toggles
            if (e.target.id === 'ingredients-accordion-toggle' || e.target.closest('#ingredients-accordion-toggle')) {
                e.preventDefault();
                this.toggleAccordion('ingredients');
            }
            
            if (e.target.id === 'extras-accordion-toggle' || e.target.closest('#extras-accordion-toggle')) {
                e.preventDefault();
                this.toggleAccordion('extras');
            }
            
            // Remove ingredient button (event delegation)
            if (e.target.closest('[data-ingredient-index]')) {
                e.preventDefault();
                const btn = e.target.closest('[data-ingredient-index]');
                const index = parseInt(btn.getAttribute('data-ingredient-index'));
                if (!isNaN(index) && this.state.ingredients[index]) {
                    this.removeIngredient(this.state.ingredients[index]);
                }
            }
            
            // Remove extra button (event delegation)
            if (e.target.closest('[data-extra-index]')) {
                e.preventDefault();
                const btn = e.target.closest('[data-extra-index]');
                const index = parseInt(btn.getAttribute('data-extra-index'));
                if (!isNaN(index) && this.state.extras[index]) {
                    this.removeExtra(this.state.extras[index].name);
                }
            }
            
            // Clear image preview button
            if (e.target.closest('#btn-clear-image-preview') || e.target.id === 'btn-clear-image-preview') {
                e.preventDefault();
                this.clearImagePreview();
            }
            
            // Extraction modal close buttons
            if (e.target.closest('#btn-extraction-modal-close') || e.target.id === 'btn-extraction-modal-close') {
                e.preventDefault();
                this.closeMenuExtractionModal();
            }
            
            if (e.target.closest('#btn-extraction-cancel') || e.target.id === 'btn-extraction-cancel') {
                e.preventDefault();
                this.closeMenuExtractionModal();
            }
            
            if (e.target.closest('#extraction-modal-backdrop')) {
                e.preventDefault();
                this.closeMenuExtractionModal();
            }
            
            // Save extracted items button
            if (e.target.closest('#btn-save-extracted-items') || e.target.id === 'btn-save-extracted-items') {
                e.preventDefault();
                this.saveExtractedItems();
            }
            
            // Remove all items button
            if (e.target.closest('#btn-remove-all-items') || e.target.id === 'btn-remove-all-items') {
                e.preventDefault();
                let removeConfirmed = false;
                if (window.NotificationManager && window.NotificationManager.confirm) {
                    removeConfirmed = await window.NotificationManager.confirm('Tüm ürünleri kaldırmak istediğinize emin misiniz?', 'Onay');
                } else {
                    removeConfirmed = confirm('Tüm ürünleri kaldırmak istediğinize emin misiniz?');
                }
                if (removeConfirmed) {
                    this.state.extractedItems = [];
                    this.renderExtractedItems();
                }
            }
            
            // Clear all images button
            if (e.target.closest('#btn-clear-all-images') || e.target.id === 'btn-clear-all-images') {
                e.preventDefault();
                this.state.extractionImageFiles = [];
                this.renderImagePreviews();
                document.getElementById('btn-extract-menu').classList.add('hidden');
            }
        };
        
        document.addEventListener('click', this._clickHandler, true);
        
        // Input handler for search and auto-translate
        this._inputHandler = (e) => {
            if (e.target.id === 'search-input') {
                this.handleSearch();
            }
            
            // Auto-translate when any input with data-auto-translate changes
            if (e.target.hasAttribute('data-auto-translate') && e.target.value.trim()) {
                const lang = e.target.getAttribute('data-lang');
                const field = e.target.getAttribute('data-field');
                
                // Auto-translate for any language (TR or EN) - translate to other languages
                if (lang && field && this.config.supportedLanguages.includes(lang)) {
                    // Use debounce to avoid too many requests
                    if (!this._autoTranslateTimers) {
                        this._autoTranslateTimers = {};
                    }
                    
                    const timerKey = `${lang}-${field}`;
                    clearTimeout(this._autoTranslateTimers[timerKey]);
                    this._autoTranslateTimers[timerKey] = setTimeout(() => {
                        this.autoTranslateInput(lang, field);
                    }, 1000); // Wait 1 second after user stops typing
                }
            }
        };
        
        document.addEventListener('input', this._inputHandler, true);
        
        // Change handler for filters
        this._changeHandler = (e) => {
            if (e.target.id === 'filter-category' || 
                e.target.id === 'filter-status' || 
                e.target.id === 'filter-stock' ||
                e.target.id === 'items-per-page') {
                if (e.target.id === 'items-per-page') {
                    this.changeItemsPerPage();
                } else {
                    this.applyFilters();
                }
            }
            
            if (e.target.id === 'form-category') {
                this.updateProductionPointFromCategory();
            }
            
            // Handle variant checkbox - support both direct click and label click
            if (e.target.id === 'form-has-variants') {
                // Direct checkbox click
                setTimeout(() => {
                    this.toggleVariantsSection();
                }, 0);
            } else if (e.target.closest('label') && e.target.closest('label').querySelector('#form-has-variants')) {
                // Label click - wait for checkbox state to update
                setTimeout(() => {
                    this.toggleVariantsSection();
                }, 10);
            }
            
            // Auto-translate when any input with data-auto-translate changes
            if (e.target.hasAttribute('data-auto-translate') && e.target.value.trim()) {
                const lang = e.target.getAttribute('data-lang');
                const field = e.target.getAttribute('data-field');
                
                // Auto-translate for any language (TR or EN) - translate to other languages
                if (lang && field && this.config.supportedLanguages.includes(lang)) {
                    // Use debounce to avoid too many requests
                    if (!this._autoTranslateTimers) {
                        this._autoTranslateTimers = {};
                    }
                    
                    const timerKey = `${lang}-${field}`;
                    clearTimeout(this._autoTranslateTimers[timerKey]);
                    this._autoTranslateTimers[timerKey] = setTimeout(() => {
                        this.autoTranslateInput(lang, field);
                    }, 1000); // Wait 1 second after user stops typing
                }
            }
            
            // Image file input handler
            if (e.target.id === 'form-image-file') {
                this.handleImageFileSelect(e);
            }
        };
        
        // Separate handler for file inputs (change event, not input event)
        this._fileChangeHandler = (e) => {
            // Extraction image input handler
            if (e.target.id === 'extraction-image-input') {
                this.handleMenuImageUpload(e);
            }
        };
        
        document.addEventListener('change', this._changeHandler, true);
        document.addEventListener('change', this._fileChangeHandler, true);
        
        // Window resize handler
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
        }
        
        let resizeTimer;
        this._resizeHandler = () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.renderTable();
            }, 250);
        };
        
        window.addEventListener('resize', this._resizeHandler);
    },
    
    /**
     * Switch language tab
     */
    switchLanguageTab: function(lang) {
        try {
            this.state.currentLanguageTab = lang;

            // Update tab buttons
            const langTabButtons = document.querySelectorAll('.lang-tab-btn');
            if (langTabButtons && langTabButtons.length > 0) {
                langTabButtons.forEach(btn => {
                    if (btn && btn.dataset && btn.dataset.lang === lang) {
                        btn.classList.add('active');
                    } else if (btn) {
                        btn.classList.remove('active');
                    }
                });
            }

            // Update content visibility
            const langContents = document.querySelectorAll('.lang-content');
            if (langContents && langContents.length > 0) {
                langContents.forEach(content => {
                    if (content && content.dataset) {
                        if (content.dataset.lang === lang) {
                            content.classList.remove('hidden');
                        } else {
                            content.classList.add('hidden');
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Error in switchLanguageTab:', error);
        }
    },
    
    /**
     * Update production point from selected category
     */
    updateProductionPointFromCategory: function() {
        try {
            const categorySelect = document.getElementById('form-category');
            const preparationScreenSelect = document.getElementById('form-preparation-screen');

            if (!categorySelect || !preparationScreenSelect) {
                return;
            }

            // Note: preparation_screen_id is now dynamic, no default from category
            // Users must manually select the preparation screen
        } catch (error) {
            console.error('Error in updateProductionPointFromCategory:', error);
        }
    },
    
    /**
     * Toggle accordion (ingredients or extras)
     */
    toggleAccordion: function(type) {
        try {
            const content = document.getElementById(`${type}-accordion-content`);
            const chevron = document.getElementById(`${type}-chevron`);
            
            if (!content || !chevron) return;
            
            const isHidden = content.classList.contains('hidden');
            
            if (isHidden) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        } catch (error) {
            console.error('Error in toggleAccordion:', error);
        }
    },
    
    /**
     * Toggle variants section visibility
     */
    toggleVariantsSection: function() {
        try {
            const hasVariantsCheckbox = document.getElementById('form-has-variants');
            const variantsSection = document.getElementById('variants-section');
            
            if (!hasVariantsCheckbox || !variantsSection) {
                return;
            }
            
            if (hasVariantsCheckbox.checked) {
                variantsSection.classList.remove('hidden');
            } else {
                variantsSection.classList.add('hidden');
                // Clear variants when unchecked
                this.state.variants = [];
                this.renderVariants();
            }
        } catch (error) {
            console.error('Error in toggleVariantsSection:', error);
        }
    },
    
    /**
     * Add a new variant
     */
    addVariant: function() {
        try {
            // Ensure variants section is visible
            const variantsSection = document.getElementById('variants-section');
            const hasVariantsCheckbox = document.getElementById('form-has-variants');
            if (variantsSection && hasVariantsCheckbox) {
                variantsSection.classList.remove('hidden');
                hasVariantsCheckbox.checked = true;
            }
            
            const newVariant = {
                name: '',
                price_modifier: 0,
                is_default: this.state.variants.length === 0 ? 1 : 0
            };
            this.state.variants.push(newVariant);
            this.renderVariants();
            
            // Focus on the first variant name input
            setTimeout(() => {
                const variantInputs = document.querySelectorAll('.variant-name-input');
                if (variantInputs.length > 0) {
                    const lastInput = variantInputs[variantInputs.length - 1];
                    lastInput.focus();
                }
            }, 100);
        } catch (error) {
            console.error('Error in addVariant:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Varyant eklenirken hata oluştu: ' + error.message);
            }
        }
    },
    
    /**
     * Remove a variant
     */
    removeVariant: function(index) {
        try {
            if (index >= 0 && index < this.state.variants.length) {
                this.state.variants.splice(index, 1);
                this.renderVariants();
            }
        } catch (error) {
            console.error('Error in removeVariant:', error);
        }
    },
    
    /**
     * Render variants list
     */
    renderVariants: function() {
        try {
            const variantsList = document.getElementById('variants-list');
            if (!variantsList) return;
            
            if (this.state.variants.length === 0) {
                variantsList.innerHTML = '<p class="q-hint text-xs italic">Henüz varyant eklenmedi</p>';
                return;
            }
            
            variantsList.innerHTML = this.state.variants.map((variant, index) => {
                return `
                    <div class="q-card q-card--pad space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div class="sm:col-span-2">
                                <input type="text" 
                                       class="variant-name-input q-input text-xs"
                                       placeholder="Varyant adı (örn: Şekerli, Büyük Boy)"
                                       value="${escapeHtml(variant.name || '')}"
                                       data-variant-index="${index}"/>
                            </div>
                            <div class="flex gap-2">
                                <input type="number" 
                                       class="variant-price-input q-input text-xs"
                                       placeholder="Fiyat farkı"
                                       step="any"
                                       value="${variant.price_modifier || 0}"
                                       data-variant-index="${index}"/>
                                <button type="button" 
                                        class="btn-remove-variant q-icon-btn"
                                        style="color:var(--color-status-danger);"
                                        data-variant-index="${index}">
                                    ×
                                </button>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" 
                                   name="variant-default" 
                                   class="variant-default-input"
                                   ${variant.is_default ? 'checked' : ''}
                                   data-variant-index="${index}"/>
                            <span class="text-xs font-medium">Varsayılan varyant</span>
                        </label>
                    </div>
                `;
            }).join('');
            
            // Add event listeners for variant inputs
            variantsList.querySelectorAll('.variant-name-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.getAttribute('data-variant-index'));
                    if (this.state.variants[index]) {
                        this.state.variants[index].name = e.target.value.trim();
                    }
                });
            });
            
            variantsList.querySelectorAll('.variant-price-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.getAttribute('data-variant-index'));
                    if (this.state.variants[index]) {
                        this.state.variants[index].price_modifier = parseFloat(e.target.value) || 0;
                    }
                });
            });
            
            variantsList.querySelectorAll('.variant-default-input').forEach(input => {
                input.addEventListener('change', (e) => {
                    const index = parseInt(e.target.getAttribute('data-variant-index'));
                    // Set all to 0 first
                    this.state.variants.forEach(v => v.is_default = 0);
                    // Set selected one to 1
                    if (this.state.variants[index]) {
                        this.state.variants[index].is_default = e.target.checked ? 1 : 0;
                    }
                    this.renderVariants(); // Re-render to update radio buttons
                });
            });
        } catch (error) {
            console.error('Error in renderVariants:', error);
        }
    },
    
    /**
     * Open modal for adding/editing menu item
     */
    openModal: function(id = null) {
        try {
            this.state.editingItem = id;
            this.state.ingredients = [];
            this.state.extras = [];
            this.state.variants = [];

            const modal = document.getElementById('menu-modal');
            if (!modal) {
                console.error('Modal element not found!');
                if (window.NotificationManager) {
                    window.NotificationManager.error('Modal açılamadı. Sayfayı yenileyin.');
                }
                return;
            }

            const form = document.getElementById('menu-form');
            const itemsFields = document.getElementById('items-form-fields');

            if (!form || !itemsFields) {
                console.error('Form elements not found!');
                if (window.NotificationManager) {
                    window.NotificationManager.error('Form elemanları bulunamadı. Sayfayı yenileyin.');
                }
                return;
            }

            // Update form action (form won't submit normally due to AJAX handler)
            if (form) {
                // Keep action as # to prevent any navigation
                form.action = '#';
                // Don't set onsubmit - let the event listener handle it
                
                // Store the actual API endpoint in data attribute for AJAX
                const _adminPrefix = this.config.adminPrefix || '/business';
                if (id) {
                    form.setAttribute('data-api-url', `${this.config.baseUrl}${_adminPrefix}/menu/edit/${id}`);
                } else {
                    form.setAttribute('data-api-url', `${this.config.baseUrl}${_adminPrefix}/menu/add`);
                }
                
                // Re-attach submit handler to form if not already attached
                if (this._submitHandler && !form.hasAttribute('data-submit-handler-attached')) {
                    form.addEventListener('submit', this._submitHandler, true);
                    form.setAttribute('data-submit-handler-attached', 'true');
                    console.debug('Submit handler re-attached to form in openModal');
                }
            }

            // Show items fields
            if (itemsFields) {
                itemsFields.classList.remove('hidden');
                itemsFields.style.display = '';
            }

            // Show multilingual section
            const multilingualSection = document.getElementById('multilingual-section');
            if (multilingualSection) {
                multilingualSection.classList.remove('hidden');
                multilingualSection.style.display = '';
            }

            // Update modal title
            const modalTitle = document.getElementById('modal-title');
            if (modalTitle) {
                modalTitle.textContent = id ? this.config.translations.edit : this.config.translations.newItem;
            }

            // Set required attributes for product fields
            const priceField = document.getElementById('form-price');
            const categoryField = document.getElementById('form-category');
            const preparationScreenField = document.getElementById('form-preparation-screen');
            if (priceField) priceField.setAttribute('required', 'required');
            if (categoryField) categoryField.setAttribute('required', 'required');
            // Preparation screen is optional - don't set required
            if (preparationScreenField) preparationScreenField.removeAttribute('required');

            // Default language için required ekle
            if (this.config.defaultLanguage) {
                const defaultNameField = document.getElementById(`form-name-${this.config.defaultLanguage}`);
                if (defaultNameField) defaultNameField.setAttribute('required', 'required');
            }

            // Force reflow
            if (itemsFields) void itemsFields.offsetHeight;

            if (id) {
                // Load existing item data
                fetch(`${this.config.baseUrl}/api/menu/item?id=${id}&translations=true`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json().catch(err => {
                            console.error('JSON parse error in item fetch:', err);
                            throw err;
                        });
                    })
                    .then(response => {
                        if (response.error) {
                            if (window.NotificationManager) {
                                window.NotificationManager.error('Ürün bilgileri alınamadı.');
                            }
                            return;
                        }
                        
                        // CRITICAL FIX: Extract menu_item from response
                        // API returns { success: true, menu_item: { ... } }
                        const data = response.menu_item || response;
                        
                        const editIdFieldItem = document.getElementById('edit-id');
                        const priceField = document.getElementById('form-price');
                        const categoryField = document.getElementById('form-category');

                        if (editIdFieldItem) editIdFieldItem.value = data.menu_item_id || id;
                        if (priceField) priceField.value = data.price || 0;
                        if (categoryField) {
                            categoryField.value = data.category_id || '';
                            // Trigger category change event to show/hide preparation screen container
                            // This will call handleCategoryChange() which is attached as event listener
                            categoryField.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        // Set preparation screen (after category change is handled)
                        if (data.preparation_screen_id) {
                            const preparationScreenField = document.getElementById('form-preparation-screen');
                            if (preparationScreenField) {
                                preparationScreenField.value = data.preparation_screen_id;
                            }
                        }

                        // Set image URL
                        const imageUrl = data.image_url || '';
                        const imageUrlField = document.getElementById('form-image-url');
                        if (imageUrlField) {
                            imageUrlField.value = imageUrl;
                        }
                        if (imageUrl) {
                            this.showImagePreview(imageUrl);
                        }

                        // Set is_available checkbox
                        const isAvailableCheckbox = document.getElementById('form-is-available');
                        if (isAvailableCheckbox) {
                            isAvailableCheckbox.checked = data.is_available !== undefined ? !!parseInt(data.is_available) : true;
                        }

                        // Set is_direct_service checkbox
                        const isDirectServiceCheckbox = document.getElementById('form-is-direct-service');
                        if (isDirectServiceCheckbox) {
                            isDirectServiceCheckbox.checked = data.is_direct_service !== undefined ? !!parseInt(data.is_direct_service) : false;
                        }

                        // Set stock tracking fields
                        const trackStockCheckbox = document.getElementById('form-track-stock');
                        const stockQuantitySection = document.getElementById('stock-quantity-section');
                        const stockQuantityField = document.getElementById('form-stock-quantity');
                        const stockValue = parseInt(data.stock);
                        const hasStockTracking = (data.track_stock !== undefined) 
                            ? (parseInt(data.track_stock) === 1) 
                            : (!isNaN(stockValue) && stockValue < 999);
                        if (trackStockCheckbox) {
                            trackStockCheckbox.checked = hasStockTracking;
                        }
                        if (stockQuantitySection) {
                            if (hasStockTracking) {
                                stockQuantitySection.classList.remove('hidden');
                            } else {
                                stockQuantitySection.classList.add('hidden');
                            }
                        }
                        if (stockQuantityField) {
                            stockQuantityField.value = hasStockTracking ? stockValue : 0;
                            stockQuantityField.required = hasStockTracking;
                        }

                        const lowStockThresholdField = document.getElementById('form-low-stock-threshold');
                        if (lowStockThresholdField) {
                            const parsedThreshold = parseInt(data.low_stock_threshold);
                            lowStockThresholdField.value = (!isNaN(parsedThreshold) && parsedThreshold > 0)
                                ? parsedThreshold
                                : '';
                        }

                        try {
                            this.state.ingredients = data.ingredients ? (typeof data.ingredients === 'string' ? JSON.parse(data.ingredients) : data.ingredients) : [];
                        } catch (e) {
                            console.error('Error parsing ingredients:', e);
                            this.state.ingredients = [];
                        }
                        
                        try {
                            this.state.extras = data.available_extras ? (typeof data.available_extras === 'string' ? JSON.parse(data.available_extras) : data.available_extras) : [];
                        } catch (e) {
                            console.error('Error parsing extras:', e);
                            this.state.extras = [];
                        }
                        
                        this.renderIngredients();
                        this.renderExtras();
                        
                        // Load variants
                        const hasVariantsCheckbox = document.getElementById('form-has-variants');
                        const variantsSection = document.getElementById('variants-section');
                        if (data.has_variants && data.has_variants == 1) {
                            if (hasVariantsCheckbox) {
                                hasVariantsCheckbox.checked = true;
                            }
                            if (variantsSection) {
                                variantsSection.classList.remove('hidden');
                            }
                            
                            // Load variants if available
                            if (data.variants && Array.isArray(data.variants) && data.variants.length > 0) {
                                this.state.variants = data.variants.map(v => ({
                                    name: v.name || '',
                                    price_modifier: parseFloat(v.price_modifier) || 0,
                                    is_default: v.is_default || 0
                                }));
                            } else {
                                this.state.variants = [];
                            }
                            this.renderVariants();
                        } else {
                            if (hasVariantsCheckbox) {
                                hasVariantsCheckbox.checked = false;
                            }
                            if (variantsSection) {
                                variantsSection.classList.add('hidden');
                            }
                            this.state.variants = [];
                            this.renderVariants();
                        }

                        // Load translations
                        if (data.translations) {
                            this.loadTranslationsForEdit(data.translations, data);
                        } else {
                            // If translations not loaded, try to fetch them
                            fetch(`${this.config.baseUrl}/api/menu/item/${id}/translations`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error(`HTTP error! status: ${response.status}`);
                                    }
                                    return response.json().catch(err => {
                                        console.error('JSON parse error in translations fetch:', err);
                                        throw err;
                                    });
                                })
                                .then(transData => {
                                    if (transData.success && transData.translations) {
                                        this.loadTranslationsForEdit(transData.translations, data);
                                    } else {
                                        // Fallback: use main data if no translations
                                        this.loadTranslationsForEdit({}, data);
                                    }
                                })
                                .catch(err => {
                                    console.error('Error loading translations:', err);
                                    // Fallback: use main data if translations fail
                                    this.loadTranslationsForEdit({}, data);
                                });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (window.NotificationManager) {
                            window.NotificationManager.error('Ürün bilgileri alınırken bir hata oluştu.');
                        }
                    });
            } else {
                // New item - reset form
                form.reset();
                const editIdField = document.getElementById('edit-id');
                if (editIdField) editIdField.value = '';

                // Reset stock tracking fields
                const trackStockCheckbox = document.getElementById('form-track-stock');
                const stockQuantitySection = document.getElementById('stock-quantity-section');
                const stockQuantityField = document.getElementById('form-stock-quantity');
                if (trackStockCheckbox) trackStockCheckbox.checked = false;
                if (stockQuantitySection) stockQuantitySection.classList.add('hidden');
                if (stockQuantityField) {
                    stockQuantityField.value = '0';
                    stockQuantityField.required = false;
                }

                // Reset is_available to checked by default
                const isAvailableCheckbox = document.getElementById('form-is-available');
                if (isAvailableCheckbox) isAvailableCheckbox.checked = true;

                // Reset language fields
                if (Array.isArray(this.config.supportedLanguages)) {
                    this.config.supportedLanguages.forEach(lang => {
                        const nameField = document.getElementById(`form-name-${lang}`);
                        const descField = document.getElementById(`form-description-${lang}`);
                        const metaTitleField = document.getElementById(`form-meta-title-${lang}`);
                        const metaDescField = document.getElementById(`form-meta-description-${lang}`);
                        const metaKeywordsField = document.getElementById(`form-meta-keywords-${lang}`);

                        if (nameField) nameField.value = '';
                        if (descField) descField.value = '';
                        if (metaTitleField) metaTitleField.value = '';
                        if (metaDescField) metaDescField.value = '';
                        if (metaKeywordsField) metaKeywordsField.value = '';
                    });
                }

                // Reset to default language tab
                this.switchLanguageTab(this.config.defaultLanguage);

                // Reset ingredients/extras/variants state for brand-new item so that
                // data from a previously edited item does not leak into the new form.
                this.state.ingredients = [];
                this.state.extras = [];
                this.state.variants = [];

                // Reset variants section
                const hasVariantsCheckboxNew = document.getElementById('form-has-variants');
                const variantsSectionNew = document.getElementById('variants-section');
                if (hasVariantsCheckboxNew) hasVariantsCheckboxNew.checked = false;
                if (variantsSectionNew) variantsSectionNew.classList.add('hidden');

                // Reset low stock threshold input for new items
                const lowStockThresholdFieldNew = document.getElementById('form-low-stock-threshold');
                if (lowStockThresholdFieldNew) lowStockThresholdFieldNew.value = '';

                this.renderIngredients();
                this.renderExtras();
                this.renderVariants();
            }

            // Show modal
            modal.classList.remove('hidden');

            // Scroll to top of modal body
            const modalContent = modal.querySelector('.q-modal__body');
            if (modalContent && modalContent.scrollTop !== undefined) {
                modalContent.scrollTop = 0;
            }

            // Update preparation screen container visibility based on category selection
            // Trigger category change event to show/hide preparation screen container
            setTimeout(() => {
                const catField = document.getElementById('form-category');
                if (catField) {
                    catField.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, 100);

            // Form submit handler'ı modal açıldığında da kontrol et
            const formElement = document.getElementById('menu-form');
            if (formElement && !formElement.hasAttribute('data-submit-handler-attached')) {
                formElement.setAttribute('data-submit-handler-attached', 'true');
            }
        } catch (error) {
            console.error('Error in openModal:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Modal açılırken bir hata oluştu.');
            }
        }
    },
    
    /**
     * Close modal
     */
    closeModal: function() {
        try {
            const modal = document.getElementById('menu-modal');
            const form = document.getElementById('menu-form');

            if (modal) {
                modal.classList.add('hidden');
            }

            if (form) {
                form.reset();
            }

            this.state.editingItem = null;
            this.state.ingredients = [];
            this.state.extras = [];
            this.state.variants = [];

            // Reset variant checkbox and section
            const hasVariantsCheckbox = document.getElementById('form-has-variants');
            const variantsSection = document.getElementById('variants-section');
            if (hasVariantsCheckbox) {
                hasVariantsCheckbox.checked = false;
            }
            if (variantsSection) {
                variantsSection.classList.add('hidden');
            }

            // Reset language fields
            if (Array.isArray(this.config.supportedLanguages)) {
                this.config.supportedLanguages.forEach(lang => {
                    const nameField = document.getElementById(`form-name-${lang}`);
                    const descField = document.getElementById(`form-description-${lang}`);
                    const metaTitleField = document.getElementById(`form-meta-title-${lang}`);
                    const metaDescField = document.getElementById(`form-meta-description-${lang}`);
                    const metaKeywordsField = document.getElementById(`form-meta-keywords-${lang}`);

                    if (nameField) nameField.value = '';
                    if (descField) descField.value = '';
                    if (metaTitleField) metaTitleField.value = '';
                    if (metaDescField) metaDescField.value = '';
                    if (metaKeywordsField) metaKeywordsField.value = '';
                });
            }

            // Reset image preview
            this.clearImagePreview();

            // Variants already reset above (lines 956-964)
            this.state.variants = [];
            this.renderVariants();

            // Reset production point
            const preparationScreenSelect = document.getElementById('form-preparation-screen');
            if (preparationScreenSelect) {
                preparationScreenSelect.value = '';
            }

            // Reset to default language tab
            this.switchLanguageTab(this.config.defaultLanguage);

            // Reset image input mode to URL
            this.switchImageInput('url');
        } catch (error) {
            console.error('Error in closeModal:', error);
        }
    },
    
    /**
     * Add ingredient
     */
    addIngredient: function() {
        try {
            const input = document.getElementById('new-ingredient');
            if (!input) {
                console.warn('new-ingredient element not found');
                return;
            }
            const value = input.value.trim();
            if (value && !this.state.ingredients.includes(value)) {
                this.state.ingredients.push(value);
                input.value = '';
                this.renderIngredients();
            }
        } catch (error) {
            console.error('Error in addIngredient:', error);
        }
    },
    
    /**
     * Remove ingredient
     */
    removeIngredient: function(ing) {
        try {
            this.state.ingredients = this.state.ingredients.filter(i => i !== ing);
            this.renderIngredients();
        } catch (error) {
            console.error('Error in removeIngredient:', error);
        }
    },
    
    /**
     * Render ingredients list
     */
    renderIngredients: function() {
        try {
            const list = document.getElementById('ingredients-list');
            if (!list) {
                console.warn('ingredients-list element not found');
                return;
            }
            list.innerHTML = this.state.ingredients.map((ing, index) => {
                const escapedIng = window.escapeHtml(ing);
                return `
                    <span class="q-badge flex items-center gap-2">
                        ${escapedIng}
                        <span class="cursor-pointer" style="color:var(--color-status-danger);" data-ingredient-index="${index}">×</span>
                    </span>
                `;
            }).join('');
        } catch (error) {
            console.error('Error in renderIngredients:', error);
        }
    },
    
    /**
     * Add extra
     */
    addExtra: function() {
        try {
            // Ensure extras accordion is open
            const extrasAccordionContent = document.getElementById('extras-accordion-content');
            const extrasChevron = document.getElementById('extras-chevron');
            if (extrasAccordionContent && extrasAccordionContent.classList.contains('hidden')) {
                extrasAccordionContent.classList.remove('hidden');
                if (extrasChevron) extrasChevron.classList.add('rotate-180');
            }
            
            const nameInput = document.getElementById('new-extra-name');
            const priceInput = document.getElementById('new-extra-price');
            if (!nameInput || !priceInput) {
                console.error('Extra input elements not found', { nameInput: !!nameInput, priceInput: !!priceInput });
                if (window.NotificationManager) {
                    window.NotificationManager.error('Ekstra ekleme alanları bulunamadı. Lütfen sayfayı yenileyin.');
                }
                return;
            }
            
            const name = nameInput.value.trim();
            const price = parseFloat(priceInput.value) || 0;

            if (!name) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Lütfen ekstra adı girin.');
                }
                nameInput.focus();
                return;
            }

            if (isNaN(price) || price < 0) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Lütfen geçerli bir fiyat girin (0 veya daha büyük).');
                }
                priceInput.focus();
                return;
            }

            // Check for duplicate names
            const existingExtra = this.state.extras.find(e => e.name.toLowerCase() === name.toLowerCase());
            if (existingExtra) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Bu ekstra zaten eklenmiş: ' + name);
                }
                nameInput.focus();
                return;
            }

            // CRITICAL: Prevent product from adding itself as an extra
            // Check both for new products (add mode) and existing products (edit mode)
            const productNameInput = document.getElementById('form-name-tr') || document.getElementById('form-name');
            const productName = productNameInput?.value?.trim().toLowerCase();
            if (productName && name.toLowerCase() === productName) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Bir ürün kendisini çıkarılabilir malzeme olarak ekleyemez!');
                }
                nameInput.focus();
                return;
            }

                this.state.extras.push({ name, price });
                nameInput.value = '';
                priceInput.value = '';
                this.renderExtras();
            
            // Focus back to name input for quick entry
            setTimeout(() => nameInput.focus(), 50);
        } catch (error) {
            console.error('Error in addExtra:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Ekstra eklenirken hata oluştu: ' + error.message);
            }
        }
    },
    
    /**
     * Remove extra
     */
    removeExtra: function(name) {
        try {
            if (!name) {
                console.warn('Extra name is required for removal');
                return;
            }
            
            // Remove by name (case-insensitive)
            const beforeLength = this.state.extras.length;
            this.state.extras = this.state.extras.filter(e => e.name.toLowerCase() !== name.toLowerCase());
            
            if (this.state.extras.length < beforeLength) {
            this.renderExtras();
            } else {
                console.warn('Extra not found:', name);
            }
        } catch (error) {
            console.error('Error in removeExtra:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Ekstra silinirken hata oluştu: ' + error.message);
            }
        }
    },
    
    /**
     * Render extras list
     */
    renderExtras: function() {
        try {
            const list = document.getElementById('extras-list');
            if (!list) {
                console.warn('extras-list element not found');
                return;
            }
            
            if (this.state.extras.length === 0) {
                list.innerHTML = '<p class="q-hint text-xs italic">Henüz ekstra eklenmedi</p>';
                return;
            }
            
            list.innerHTML = this.state.extras.map((ext, index) => {
                const extName = (ext && ext.name) ? String(ext.name) : '';
                const escapedName = window.escapeHtml ? window.escapeHtml(extName) : extName.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const safeName = extName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                // Coerce price to number - legacy rows may store it as a string
                const priceNum = Number(ext && ext.price);
                const priceText = (isFinite(priceNum) ? priceNum : 0).toFixed(2);
                return `
                    <span class="q-badge flex items-center gap-2">
                        ${escapedName} <span class="font-semibold">(+${priceText}₺)</span>
                        <button type="button" class="cursor-pointer bg-transparent border-0 p-0 leading-none" style="color:var(--color-status-danger);" data-extra-index="${index}" data-extra-name="${safeName}" title="Sil">×</button>
                    </span>
                `;
            }).join('');
        } catch (error) {
            console.error('Error in renderExtras:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Ekstralar gösterilirken hata oluştu: ' + error.message);
            }
        }
    },
    
    /**
     * Resolve business/tenant id for super-admin API calls.
     */
    getBusinessIdForRequest: function() {
        if (window.currentBusinessId) {
            return window.currentBusinessId;
        }
        try {
            const fromUrl = new URLSearchParams(window.location.search).get('business_id');
            if (fromUrl) {
                return fromUrl;
            }
        } catch (e) {}
        try {
            return sessionStorage.getItem('selected_business_id') || null;
        } catch (e) {
            return null;
        }
    },

    /**
     * Get CSRF token from multiple sources
     */
    getCSRFToken: function() {
        if (typeof window !== 'undefined' && window.CSRF_TOKEN) {
            return window.CSRF_TOKEN;
        } else if (typeof window !== 'undefined' && window.Utils && typeof window.Utils.getCSRFToken === 'function') {
            return window.Utils.getCSRFToken();
        } else if (typeof window !== 'undefined' && window.CSRF && typeof window.CSRF.getToken === 'function') {
            return window.CSRF.getToken();
        } else {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content') || '';
            }
        }
        
        // Final fallback
        if (typeof window !== 'undefined' && window.csrf_token) {
            return window.csrf_token;
        }
        
        return '';
    },
    
    /**
     * Delete menu item
     */
    deleteMenuItem: async function(id) {
        if (!id) {
            console.error('Menu item ID is required');
            return;
        }

        if (!window.NotificationManager) {
            console.error('NotificationManager is not available');
            return;
        }

        const confirmed = await window.NotificationManager.confirm(
            'Bu ürünü silmek istediğinizden emin misiniz?',
            'Ürünü Sil'
        );
        
        if (!confirmed) return;

        try {
            const csrfToken = this.getCSRFToken();
            if (!csrfToken) {
                throw new Error('CSRF token bulunamadı');
            }

            const adminPrefix = this.config.adminPrefix || '/business';
            const response = await fetch(`${this.config.baseUrl}${adminPrefix}/menu/delete/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Silme işlemi başarısız oldu' }));
                throw new Error(errorData.error || errorData.message || 'Silme işlemi başarısız oldu');
            }

            const result = await response.json();
            
            if (result.success) {
                if (window.NotificationManager) {
                    window.NotificationManager.success(result.message || 'Ürün başarıyla silindi');
                }
                // Reload page to refresh the list
                setTimeout(() => {
                    window.location.reload(true);
                }, 500);
            } else {
                throw new Error(result.error || result.message || 'Silme işlemi başarısız oldu');
            }
        } catch (error) {
            console.error('Error deleting menu item:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(error.message || 'Silme işlemi başarısız oldu.');
            }
        }
    },
    
    /**
     * Setup form submit handler
     */
    setupFormSubmit: function() {
        // Prevent multiple event listeners
        if (this.state.formSubmitHandlerAttached) {
            return;
        }

        // Event delegation - document'e listener ekle, form submit event'i bubble eder
        const self = this; // Store reference for use in handler
        const submitHandler = function(e) {
            console.debug('Submit event detected', { 
                target: e.target, 
                targetTagName: e.target.tagName,
                targetId: e.target.id,
                form: e.target.form,
                currentTarget: e.currentTarget
            });
            
            // Submit event form'da tetiklenir veya button'da (form attribute ile)
            let form = null;
            if (e.target.tagName === 'FORM') {
                form = e.target;
            } else if (e.target.form) {
                // Button with form attribute
                form = e.target.form;
            } else {
                // Try to find form by closest
                form = e.target.closest('form');
            }

            console.debug('Form found:', { 
                form, 
                formId: form?.id, 
                formAction: form?.action,
                formMethod: form?.method,
                formOnsubmit: form?.onsubmit
            });

            // Menu form'u kontrol et
            if (!form || form.id !== 'menu-form') {
                console.debug('Not menu form, ignoring', { formId: form?.id });
                return; // Bu form değil
            }

            console.debug('Menu form submit detected, preventing default and handling');
            
            // CRITICAL: Prevent default IMMEDIATELY
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // CRITICAL: Also prevent form's default action
            if (form) {
                form.action = '#';
                form.method = 'POST';
                // Remove any onsubmit handler that might cause navigation
                if (form.onsubmit) {
                    console.warn('Form had onsubmit handler, removing it', { onsubmit: form.onsubmit });
                    form.onsubmit = null;
                }
            }

            self.handleFormSubmit(e, form);
        };
        
        // Attach to both document and form directly for better coverage
        document.addEventListener('submit', submitHandler, true); // Capture phase'de yakala
        
        // Also attach directly to form if it exists
        const menuForm = document.getElementById('menu-form');
        if (menuForm) {
            menuForm.addEventListener('submit', submitHandler, true);
            console.debug('Form submit handler attached to form element');
        }
        
        console.debug('Form submit handler attached to document');
        this._submitHandler = submitHandler; // Store reference for cleanup
        this.state.formSubmitHandlerAttached = true;
    },
    
    /**
     * Handle form submit
     */
    handleFormSubmit: function(e, menuForm) {
        try {
            console.warn('🔥 HANDLE FORM SUBMIT CALLED!');

            if (!menuForm) {
                menuForm = document.getElementById('menu-form');
                console.warn('🔥 Menu form found by ID:', !!menuForm);
            }

            if (!menuForm) {
                console.error('🔥 ERROR: Menu form not found!');
                return;
            }
            
            console.warn('🔥 Menu form found, proceeding...');
            
            // CRITICAL: Ensure form action is always # to prevent any navigation
            if (menuForm.action !== '#' && menuForm.action !== window.location.href.split('#')[0] + '#') {
                console.warn('Form action was changed, resetting to #', { oldAction: menuForm.action });
                menuForm.action = '#';
            }
            
            // CRITICAL: Ensure form method is POST (not GET which could cause navigation)
            if (menuForm.method.toLowerCase() !== 'post') {
                console.warn('Form method was changed, resetting to POST', { oldMethod: menuForm.method });
                menuForm.method = 'POST';
            }

            // HTML5 validasyonunu kontrol et
            const isValid = menuForm.checkValidity();
            console.debug('Form validation result:', { isValid });

            if (!isValid) {
                // İlk geçersiz alanı bul ve odaklan
                const firstInvalid = menuForm.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                menuForm.reportValidity();
                return;
            }

            const formData = new FormData(menuForm);
            const editIdField = document.getElementById('edit-id');
            const id = editIdField?.value?.trim() || '';

            console.debug('Form submit - edit-id value:', { id, editIdFieldExists: !!editIdField, rawValue: editIdField?.value });

            const data = {
                has_variants: document.getElementById('form-has-variants')?.checked ? 1 : 0,
            };

            const availEl = document.getElementById('form-is-available');
            data.is_available = availEl ? (availEl.checked ? 1 : 0) : 1;

            const directEl = document.getElementById('form-is-direct-service');
            data.is_direct_service = directEl ? (directEl.checked ? 1 : 0) : 0;

            // CRITICAL: Parse price as numeric, handle empty/invalid values
            const priceValue = formData.get('price');
            data.price = (priceValue && !isNaN(parseFloat(priceValue))) ? parseFloat(priceValue) : null;
            
            // CRITICAL: Handle category_id - empty string should be null for validation
            // Get from FormData first, then fallback to select element directly
            const categoryIdValue = formData.get('category_id');
            const categorySelect = document.getElementById('form-category');
            const categoryIdFromSelect = categorySelect?.value?.trim() || '';
            
            // FormData'dan al, yoksa select'ten al (fallback)
            const finalCategoryId = categoryIdValue || categoryIdFromSelect;
            data.category_id = (finalCategoryId && finalCategoryId.trim()) ? finalCategoryId.trim() : null;
            
            // Debug log for category_id processing
            console.warn('🔥 CATEGORY ID PROCESSING:', {
                fromFormData: categoryIdValue,
                fromSelect: categoryIdFromSelect,
                final: data.category_id,
                selectElementExists: !!categorySelect,
                selectValue: categorySelect?.value,
                selectName: categorySelect?.name
            });
            
            // Preparation screen ID - empty string to null
            const prepScreenValue = document.getElementById('form-preparation-screen')?.value;
            data.preparation_screen_id = (prepScreenValue && prepScreenValue.trim()) ? prepScreenValue.trim() : null;
            
            // Image URL - empty string to null
            const imageUrlValue = document.getElementById('form-image-url')?.value;
            data.image_url = (imageUrlValue && imageUrlValue.trim()) ? imageUrlValue.trim() : null;
            
            // Stock - handle stock_quantity or stock field
            const stockQuantityValue = formData.get('stock_quantity');
            const stockValue = formData.get('stock');
            const trackStockChecked = document.getElementById('form-track-stock')?.checked;
            
            // Set track_stock flag
            data.track_stock = trackStockChecked ? 1 : 0;
            
            // If track_stock is checked, use stock_quantity, otherwise use stock
            if (trackStockChecked) {
                data.stock_quantity = (stockQuantityValue !== null && !isNaN(parseInt(stockQuantityValue))) ? parseInt(stockQuantityValue) : 0;
                // Also set stock to 0 for tracked items (will be managed separately)
                data.stock = 0;

                // Low stock threshold (optional) - only persist when stock tracking is on
                const lowStockThresholdValue = formData.get('low_stock_threshold');
                const parsedThreshold = parseInt(lowStockThresholdValue);
                data.low_stock_threshold = (!isNaN(parsedThreshold) && parsedThreshold >= 0) ? parsedThreshold : 0;
            } else {
                data.stock = (stockValue !== null && !isNaN(parseInt(stockValue))) ? parseInt(stockValue) : 999;
                // Reset threshold when stock tracking is disabled
                data.low_stock_threshold = 0;
            }
            data.ingredients = JSON.stringify(this.state.ingredients);
            data.available_extras = JSON.stringify(this.state.extras);
            
            // Collect variants if has_variants is checked
            if (data.has_variants === 1) {
                // Filter out empty variants (name is required)
                const validVariants = this.state.variants.filter(v => v.name && v.name.trim());
                
                if (validVariants.length === 0) {
                    if (window.NotificationManager) {
                        window.NotificationManager.warning('Varyant seçeneği işaretli ancak varyant eklenmemiş. Lütfen en az bir varyant ekleyin veya varyant seçeneğini kaldırın.');
                    }
                    console.warn('has_variants is checked but no valid variants found');
                    return; // Stop form submission
                }
                
                data.variants = validVariants.map(v => ({
                    name: v.name.trim(),
                    price_modifier: parseFloat(v.price_modifier) || 0,
                    is_default: v.is_default || 0
                }));
                console.debug('Variants collected:', data.variants, 'from state:', this.state.variants);
            } else {
                data.variants = [];
                console.debug('has_variants is not checked, variants array set to empty');
            }
            
            console.debug('Final data before submit:', {
                has_variants: data.has_variants,
                variants_count: data.variants.length,
                variants: data.variants
            });

            // Collect translations
            const translations = {};
            if (Array.isArray(this.config.supportedLanguages)) {
                this.config.supportedLanguages.forEach(lang => {
                    const nameField = document.getElementById(`form-name-${lang}`);
                    const descField = document.getElementById(`form-description-${lang}`);
                    const name = nameField?.value?.trim() || '';
                    const description = descField?.value?.trim() || '';

                    if (name || description) {
                        translations[lang] = {
                            name: name,
                            description: description
                        };
                    }
                });
            }

            console.debug('Collected translations:', translations);

            if (Object.keys(translations).length > 0) {
                data.translations = translations;
            }

            // Set default name and description from default language
            const defaultNameField = document.getElementById(`form-name-${this.config.defaultLanguage}`);
            const defaultDescField = document.getElementById(`form-description-${this.config.defaultLanguage}`);

            // Name alanını kontrol et ve set et
            if (defaultNameField && defaultNameField.value.trim()) {
                data.name = defaultNameField.value.trim();
            } else if (translations[this.config.defaultLanguage]?.name) {
                data.name = translations[this.config.defaultLanguage].name;
            } else if (Object.keys(translations).length > 0) {
                // Eğer default language'de name yoksa, ilk available translation'dan al
                const firstLang = Object.keys(translations)[0];
                data.name = translations[firstLang]?.name || '';
            } else {
                // Hiç translation yoksa, form'dan direkt name alanını kontrol et
                const nameInput = document.querySelector('input[name="name"]') ||
                                 document.querySelector('#form-name-tr') ||
                                 document.querySelector('#form-name-en');
                if (nameInput && nameInput.value.trim()) {
                    data.name = nameInput.value.trim();
                }
            }

            // Description alanını kontrol et ve set et
            if (defaultDescField && defaultDescField.value.trim()) {
                data.description = defaultDescField.value.trim();
            } else if (translations[this.config.defaultLanguage]?.description) {
                data.description = translations[this.config.defaultLanguage].description;
            } else if (Object.keys(translations).length > 0) {
                // Eğer default language'de description yoksa, ilk available translation'dan al
                const firstLang = Object.keys(translations)[0];
                data.description = translations[firstLang]?.description || '';
            } else {
                // Hiç translation yoksa, form'dan direkt description alanını kontrol et
                const descInput = document.querySelector('textarea[name="description"]') ||
                                 document.querySelector('#form-description-tr') ||
                                 document.querySelector('#form-description-en');
                if (descInput && descInput.value.trim()) {
                    data.description = descInput.value.trim();
                } else {
                    data.description = ''; // Boş string olarak set et
                }
            }

            // CRITICAL: Frontend validation - check required fields before submission
            const validationErrors = [];
            
            // Name validation
            if (!data.name || typeof data.name !== 'string' || !data.name.trim()) {
                validationErrors.push('Ürün adı gereklidir.');
            }
            
            // Category ID validation - ONLY REQUIRED FOR NEW ITEMS (when id is empty)
            // For editing, category_id can be null (will use existing value)
            if (!id || id.trim() === '') {
                // This is a new item, category is required
            if (!data.category_id || (typeof data.category_id === 'string' && !data.category_id.trim())) {
                validationErrors.push('Kategori seçimi gereklidir.');
                }
            }
            
            // Price validation - must be numeric and >= 0 (allow 0 for free items)
            if (data.price === null || data.price === undefined || isNaN(data.price) || data.price < 0) {
                validationErrors.push('Geçerli bir fiyat gereklidir (0 veya daha büyük olmalıdır).');
            }
            
            // Show validation errors if any
            if (validationErrors.length > 0) {
                const errorMessage = validationErrors.join(' ');
                if (window.NotificationManager) {
                    window.NotificationManager.error(errorMessage);
                }
                console.warn('Frontend validation failed:', validationErrors, { data });
                return;
            }

            // Validate image_url length (max 5000 characters - increased for AI-generated URLs with long BASE_URL)
            if (data.image_url && data.image_url.length > 5000) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Resim URL\'si en fazla 5000 karakter olabilir. Lütfen daha kısa bir URL kullanın.');
                }
                console.error('Validation failed: image_url too long', { length: data.image_url.length });
                return;
            }

            // Super admin için business_id ekle
            const businessId = this.getBusinessIdForRequest();
            if (businessId) {
                data.business_id = businessId;
            }

            // Construct URL from id (always use edit-id field as source of truth)
            // Ensure baseUrl is set
            const baseUrl = this.config.baseUrl || window.BASE_URL || '';
            console.debug('URL construction - baseUrl check:', { 
                configBaseUrl: this.config.baseUrl, 
                windowBaseUrl: window.BASE_URL, 
                finalBaseUrl: baseUrl 
            });
            
            if (!baseUrl || baseUrl.trim() === '') {
                console.error('baseUrl is not set! Cannot make request.', {
                    config: this.config,
                    windowBASE_URL: window.BASE_URL
                });
                if (window.NotificationManager) {
                    window.NotificationManager.error('Sistem hatası: baseUrl bulunamadı. Sayfayı yenileyin.');
                }
                return;
            }
            
            // Clean baseUrl - remove trailing slash if exists
            const cleanBaseUrl = baseUrl.replace(/\/$/, '');
            
            // Determine endpoint based on id and adminPrefix
            const adminPrefix = this.config.adminPrefix || '/business';
            let endpoint;
            if (id && id.trim() !== '') {
                endpoint = `${adminPrefix}/menu/edit/${id}`;
            } else {
                endpoint = `${adminPrefix}/menu/add`;
            }
            
            const url = `${cleanBaseUrl}${endpoint}`;
            
            console.debug('Endpoint construction:', {
                adminPrefix,
                id,
                endpoint,
                url
            });
            
            console.debug('Final URL determined:', { 
                url, 
                id, 
                baseUrl: cleanBaseUrl, 
                adminPrefix: adminPrefix,
                endpoint,
                hasId: !!id,
                idLength: id ? id.length : 0
            });
            console.debug('Form data being sent:', JSON.stringify(data, null, 2));
            console.debug('Category ID in data:', {
                category_id: data.category_id,
                type: typeof data.category_id,
                value: data.category_id
            });

            // Get CSRF token
            const csrfToken = this.getCSRFToken();

            if (!csrfToken || csrfToken.trim() === '') {
                console.error('CSRF token not found from any source!');
                if (window.NotificationManager) {
                    window.NotificationManager.error('Güvenlik hatası: CSRF token bulunamadı. Sayfayı yenileyin.');
                }
                return;
            }

            console.warn('🔥 CSRF token found, making fetch request to:', url);
            console.warn('🔥 Request data:', data);

            // Show loading state
            const submitButton = document.querySelector('button[type="submit"][form="menu-form"]');
            const originalButtonText = submitButton?.textContent;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Kaydediliyor...';
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            }

            // Create AbortController for timeout handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(data),
                redirect: 'manual', // Prevent automatic redirects
                credentials: 'same-origin', // Include cookies for CSRF
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                // Restore button state
                if (submitButton) {
                    submitButton.disabled = false;
                    if (originalButtonText) submitButton.textContent = originalButtonText;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                
                console.warn('🔥 Fetch response received:', {
                    status: response.status,
                    statusText: response.statusText,
                    redirected: response.redirected,
                    ok: response.ok,
                    type: response.type
                });
                
                // Prevent any redirects - stay on current page
                if (response.type === 'opaqueredirect' || response.redirected) {
                    console.warn('Response attempted redirect, ignoring it to stay on current page');
                    // Don't redirect, continue processing the response as JSON
                }

                const contentType = response.headers.get('content-type');
                console.debug('Response content-type:', contentType);

                // CRITICAL: Always try to parse as JSON first, even if content-type is not set
                // This handles cases where server returns JSON but content-type header is missing
                return response.text().then(text => {
                    console.debug('Response text received:', text.substring(0, 200));
                    
                    // Try to parse as JSON
                    try {
                        const jsonData = JSON.parse(text);
                        console.debug('Response parsed as JSON:', jsonData);
                        return jsonData;
                    } catch (e) {
                        // Not JSON - check content-type
                        if (contentType && contentType.includes('application/json')) {
                            // Content-type says JSON but parse failed - return error
                            console.error('Content-type says JSON but parse failed:', e);
                            return {
                                success: false,
                                error: 'Sunucu yanıtı işlenemedi. Lütfen tekrar deneyin.',
                                message: 'Sunucu yanıtı işlenemedi. Lütfen tekrar deneyin.',
                                status: response.status
                            };
                        } else {
                            // Not JSON, return as text
                            console.debug('Response is not JSON, returning as text');
                            return { 
                                success: response.ok, 
                                message: text, 
                                status: response.status 
                            };
                        }
                    }
                });
            })
            .catch(error => {
                clearTimeout(timeoutId);
                // Restore button state
                if (submitButton) {
                    submitButton.disabled = false;
                    if (originalButtonText) submitButton.textContent = originalButtonText;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                
                if (error.name === 'AbortError') {
                    console.error('Request timeout:', error);
                    if (window.NotificationManager) {
                        window.NotificationManager.error('İstek zaman aşımına uğradı. Lütfen tekrar deneyin.');
                    }
                } else {
                    console.error('Fetch error:', error);
                    if (window.NotificationManager) {
                        window.NotificationManager.error('Bir hata oluştu: ' + (error.message || 'Bilinmeyen hata'));
                    }
                }
            })
            .then(result => {
                if (!result) return;
                // CRITICAL: Ensure URL hash is cleared after response processing
                if (window.location.hash) {
                    const urlWithoutHash = window.location.href.split('#')[0];
                    window.history.replaceState(null, '', urlWithoutHash);
                }

                if (!result) {
                    return;
                }

                if (result.error || result.success === false || (result.translation_key && (result.translation_key.includes('error') || result.translation_key.includes('warning')))) {

                    let errorMsg = 'Bilinmeyen hata';

                    // Priority 1: Check if we have error_messages array (from backend)
                    if (result.error_messages && Array.isArray(result.error_messages) && result.error_messages.length > 0) {
                        errorMsg = result.error_messages.join(', ');
                    }
                    // Priority 2: Check if we have detailed validation errors
                    else if (result.errors && typeof result.errors === 'object') {
                        const errorMessages = [];
                        if (Array.isArray(result.errors)) {
                            errorMessages.push(...result.errors);
                        } else {
                            Object.values(result.errors).forEach(err => {
                                if (Array.isArray(err)) {
                                    errorMessages.push(...err);
                                } else if (typeof err === 'string') {
                                    errorMessages.push(err);
                                }
                            });
                        }
                        if (errorMessages.length > 0) {
                            errorMsg = errorMessages.join(', ');
                        }
                    }

                    // Priority 2: Use message if available and no detailed errors
                    if ((!errorMsg || errorMsg === 'Bilinmeyen hata') && typeof result.message === 'string' && result.message && result.message.trim()) {
                        errorMsg = result.message;
                    }

                    // Priority 3: Use error field if available
                    if ((!errorMsg || errorMsg === 'Bilinmeyen hata') && typeof result.error === 'string' && result.error && result.error.trim()) {
                        errorMsg = result.error;
                    }

                    if (window.NotificationManager) {
                        window.NotificationManager.error(errorMsg);
                    }
                    return;
                }

                // Check success flag directly
                const isSuccess = result.success === true;

                if (isSuccess) {
                    if (window.NotificationManager) {
                        window.NotificationManager.success(result.message || 'İşlem başarılı!');
                    }
                    
                    // Check if this is a new item (no id) or editing existing item
                    const editId = document.getElementById('edit-id')?.value || '';
                    
                    // Get menu_item_id from response (for new products)
                    const menuItemId = result.menu_item_id || result.data?.menu_item_id;
                    
                    if (!editId) {
                        // New item added - upload pending image if exists (before form reset)
                        const hadPendingImage = this.state.pendingImageFile !== null;
                        // CRITICAL: await image upload FIRST so DB has image_url persisted,
                        // THEN fetch the item for the list (otherwise list row shows no image
                        // until the next page refresh - classic race condition).
                        const imageUploadPromise = (menuItemId && this.state.pendingImageFile)
                            ? this.uploadPendingImage(menuItemId).catch(err => {
                                console.error('Error uploading pending image:', err);
                            })
                            : Promise.resolve();

                        if (menuItemId) {
                            imageUploadPromise.then(() => this.addMenuItemToList(menuItemId))
                                .catch(err => {
                                    console.error('Error adding menu item to list:', err);
                                });
                        }
                        
                        // New item added - reset form and keep modal open
                        console.debug('New item added, resetting form and keeping modal open');
                        const form = document.getElementById('menu-form');
                        if (form) {
                            form.reset();
                            // Clear pending image file after upload attempt
                            if (hadPendingImage) {
                                this.state.pendingImageFile = null;
                            }
                            const editIdField = document.getElementById('edit-id');
                            if (editIdField) editIdField.value = '';
                            
                            // Reset state
                            this.state.ingredients = [];
                            this.state.extras = [];
                            this.state.variants = [];
                            
                            // Reset language fields
                            if (Array.isArray(this.config.supportedLanguages)) {
                                this.config.supportedLanguages.forEach(lang => {
                                    const nameField = document.getElementById(`form-name-${lang}`);
                                    const descField = document.getElementById(`form-description-${lang}`);
                                    const metaTitleField = document.getElementById(`form-meta-title-${lang}`);
                                    const metaDescField = document.getElementById(`form-meta-description-${lang}`);
                                    const metaKeywordsField = document.getElementById(`form-meta-keywords-${lang}`);
                                    
                                    if (nameField) nameField.value = '';
                                    if (descField) descField.value = '';
                                    if (metaTitleField) metaTitleField.value = '';
                                    if (metaDescField) metaDescField.value = '';
                                    if (metaKeywordsField) metaKeywordsField.value = '';
                                });
                            }
                            
                            // Reset to default language tab
                            if (this.config.defaultLanguage) {
                                this.switchLanguageTab(this.config.defaultLanguage);
                            }
                            
                            // Reset image preview
                            this.clearImagePreview();
                            
                            // Reset variant checkbox and section
                            const hasVariantsCheckbox = document.getElementById('form-has-variants');
                            const variantsSection = document.getElementById('variants-section');
                            if (hasVariantsCheckbox) {
                                hasVariantsCheckbox.checked = false;
                            }
                            if (variantsSection) {
                                variantsSection.classList.add('hidden');
                            }
                            
                            // Reset preparation screen
                            const preparationScreenSelect = document.getElementById('form-preparation-screen');
                            if (preparationScreenSelect) {
                                preparationScreenSelect.value = '';
                            }
                            
                            // Re-render ingredients, extras, and variants
                            this.renderIngredients();
                            this.renderExtras();
                            this.renderVariants();
                            
                            // Update form action to add (form won't submit normally due to AJAX handler)
                            form.action = '#';
                            form.setAttribute('data-api-url', `${this.config.baseUrl}${this.config.adminPrefix || '/business'}/menu/add`);
                            // Don't set onsubmit - let the event listener handle it
                            
                            // Re-attach submit handler to form if not already attached
                            if (this._submitHandler && !form.hasAttribute('data-submit-handler-attached')) {
                                form.addEventListener('submit', this._submitHandler, true);
                                form.setAttribute('data-submit-handler-attached', 'true');
                                console.debug('Submit handler re-attached to form after reset');
                            }
                            
                            // Update modal title
                            const modalTitle = document.getElementById('modal-title');
                            if (modalTitle && this.config.translations) {
                                modalTitle.textContent = this.config.translations.newItem || 'Yeni Ürün';
                            }
                        }
                    } else {
                        // Editing existing item - update the item in the list and keep modal open
                        console.debug('Item updated, updating item in list and keeping modal open');
                        
                        // Get the menu item ID from edit-id field
                        const editId = document.getElementById('edit-id')?.value || '';
                        if (editId) {
                            // Update the item in the list
                            this.updateMenuItemInList(editId).catch(err => {
                                console.error('Error updating menu item in list:', err);
                            });
                        }
                        
                        // Modal stays open, user can continue editing or close manually
                        // No redirect, no page refresh - user stays exactly where they are
                    }
                } else {
                    console.debug('Result indicates failure or unclear status:', result);
                    // Error handling is already done above, this is for unclear cases
                    if (!result.error && result.message) {
                        if (window.NotificationManager) {
                            window.NotificationManager.warning(result.message || 'Yanıt alındı ancak durum belirsiz. Sayfayı yenileyin.');
                        }
                    }
                }
            })
            .catch(error => {
                let errorMessage = 'Kaydetme işlemi başarısız oldu';
                if (error.name === 'TypeError' && error.message && error.message.includes('fetch')) {
                    errorMessage = 'Sunucuya bağlanılamadı. İnternet bağlantınızı kontrol edin.';
                } else if (error.name === 'SyntaxError') {
                    errorMessage = 'Sunucu yanıtı işlenemedi. Lütfen tekrar deneyin.';
                } else if (error.message) {
                    errorMessage += ': ' + error.message;
                }

                console.error('Fetch error:', error);

                if (window.NotificationManager) {
                    window.NotificationManager.error(errorMessage);
                }
            });
        } catch (error) {
            console.error('Error in handleFormSubmit:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Form gönderilirken bir hata oluştu. Lütfen sayfayı yenileyip tekrar deneyin.');
            }
        }
    },
    
    /**
     * Handle search with debounce
     */
    handleSearch: function() {
        try {
            clearTimeout(this.state.searchTimeout);
            this.state.searchTimeout = setTimeout(() => {
                try {
                    const searchInput = document.getElementById('search-input');
                    if (!searchInput) {
                        console.warn('search-input element not found');
                        return;
                    }
                    this.state.searchTerm = searchInput.value.toLowerCase().trim();
                    this.applyFilters();
                } catch (error) {
                    console.error('Error in handleSearch timeout:', error);
                }
            }, 300);
        } catch (error) {
            console.error('Error in handleSearch:', error);
        }
    },
    
    /**
     * Apply filters
     */
    applyFilters: function() {
        try {
            const categoryFilter = document.getElementById('filter-category');
            const statusFilter = document.getElementById('filter-status');
            const stockFilter = document.getElementById('filter-stock');

            this.state.activeFilters.category = categoryFilter?.value || '';
            this.state.activeFilters.status = statusFilter?.value || 'all';
            this.state.activeFilters.stock = stockFilter?.value || 'all';

            // Filter menu items
            this.state.filteredItems = this.state.allMenuItems.filter(item => {
                // Search filter
                if (this.state.searchTerm) {
                    const nameMatch = (item.name || '').toLowerCase().includes(this.state.searchTerm);
                    const descMatch = (item.description || '').toLowerCase().includes(this.state.searchTerm);
                    const catMatch = this.config.categories.find(c => c.category_id === item.category_id)?.name?.toLowerCase().includes(this.state.searchTerm);

                    if (!nameMatch && !descMatch && !catMatch) {
                        return false;
                    }
                }

                // Category filter - support parent-child hierarchy
                if (this.state.activeFilters.category) {
                    const selectedCategoryId = this.state.activeFilters.category;
                    const selectedCategory = this.config.categories.find(c => c.category_id === selectedCategoryId);
                    
                    if (!selectedCategory) {
                    return false;
                    }
                    
                    // If selected category is a parent, include items from all child categories
                    if (selectedCategory.parent_id === null || selectedCategory.parent_id === '') {
                        // Check if this is a parent category - find all its children
                        const childCategories = this.config.categories.filter(c => c.parent_id === selectedCategoryId);
                        const childCategoryIds = childCategories.map(c => c.category_id);
                        
                        // Include items from parent category OR any child category
                        if (item.category_id !== selectedCategoryId && !childCategoryIds.includes(item.category_id)) {
                            return false;
                        }
                    } else {
                        // Selected category is a child - only show items from this specific category
                        if (item.category_id !== selectedCategoryId) {
                            return false;
                        }
                    }
                }

                // Status filter
                if (this.state.activeFilters.status === 'available' && !item.is_available) {
                    return false;
                }
                if (this.state.activeFilters.status === 'unavailable' && item.is_available) {
                    return false;
                }

                // Stock filter
                if (this.state.activeFilters.stock === 'in_stock' && (item.stock === 0 || item.stock === null)) {
                    return false;
                }
                if (this.state.activeFilters.stock === 'out_of_stock' && item.stock > 0) {
                    return false;
                }

                return true;
            });
        } catch (error) {
            console.error('Error in applyFilters:', error);
        }

        this.state.currentPage = 1;
        this.updateActiveFiltersDisplay();
        this.updateResultsCount();
        this.renderTable();
        this.renderPagination();
    },
    
    /**
     * Clear all filters
     */
    clearFilters: function() {
        try {
            if (document.getElementById('search-input')) {
                document.getElementById('search-input').value = '';
            }
            if (document.getElementById('filter-category')) {
                document.getElementById('filter-category').value = '';
            }
            if (document.getElementById('filter-status')) {
                document.getElementById('filter-status').value = 'all';
            }
            if (document.getElementById('filter-stock')) {
                document.getElementById('filter-stock').value = 'all';
            }
        } catch (error) {
            console.error('Error in clearFilters:', error);
        }

        this.state.searchTerm = '';
        this.state.activeFilters = {
            category: '',
            status: 'all',
            stock: 'all'
        };

        this.state.filteredItems = [...this.state.allMenuItems];
        this.state.currentPage = 1;
        this.updateActiveFiltersDisplay();
        this.updateResultsCount();
        this.renderTable();
        this.renderPagination();
    },
    
    /**
     * Update active filters display
     */
    updateActiveFiltersDisplay: function() {
        try {
            const container = document.getElementById('active-filters');
            if (!container) {
                console.warn('active-filters element not found');
                return;
            }

            container.innerHTML = '';

            let hasFilters = false;

            if (this.state.searchTerm) {
                hasFilters = true;
                const badge = document.createElement('span');
                badge.className = 'q-badge inline-flex items-center gap-1.5';
                badge.innerHTML = `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg> "${this.state.searchTerm}"`;
                container.appendChild(badge);
            }

            if (this.state.activeFilters.category) {
                hasFilters = true;
                const cat = this.config.categories.find(c => c.category_id === this.state.activeFilters.category);
                const badge = document.createElement('span');
                badge.className = 'q-badge inline-flex items-center';
                badge.textContent = cat?.name || '';
                container.appendChild(badge);
            }

            if (this.state.activeFilters.status !== 'all') {
                hasFilters = true;
                const badge = document.createElement('span');
                badge.className = 'q-badge inline-flex items-center';
                badge.textContent = this.state.activeFilters.status === 'available' ? 'Mevcut' : 'Tükenen';
                container.appendChild(badge);
            }

            if (this.state.activeFilters.stock !== 'all') {
                hasFilters = true;
                const badge = document.createElement('span');
                badge.className = 'q-badge inline-flex items-center';
                badge.textContent = this.state.activeFilters.stock === 'in_stock' ? 'Stokta' : 'Tükendi';
                container.appendChild(badge);
            }

            container.classList.toggle('hidden', !hasFilters);
        } catch (error) {
            console.error('Error in updateActiveFiltersDisplay:', error);
        }
    },
    
    /**
     * Update results count
     */
    updateResultsCount: function() {
        try {
            const countEl = document.getElementById('results-count');
            if (!countEl) {
                console.warn('results-count element not found');
                return;
            }

            countEl.textContent = `${this.state.filteredItems.length} ürün bulundu (Toplam: ${this.state.allMenuItems.length})`;
        } catch (error) {
            console.error('Error in updateResultsCount:', error);
        }
    },
    
    /**
     * Change items per page
     */
    changeItemsPerPage: function() {
        try {
            const itemsPerPageEl = document.getElementById('items-per-page');
            if (!itemsPerPageEl) {
                console.warn('items-per-page element not found');
                return;
            }

            this.state.itemsPerPage = parseInt(itemsPerPageEl.value) || 25;
            this.state.currentPage = 1;

            this.renderTable();
            this.renderPagination();
        } catch (error) {
            console.error('Error in changeItemsPerPage:', error);
        }
    },
    
    /**
     * Go to specific page
     */
    goToPage: function(page) {
        try {
            const totalPages = Math.ceil(this.state.filteredItems.length / this.state.itemsPerPage);
            if (page < 1 || page > totalPages) return;

            this.state.currentPage = page;
            this.renderTable();
            this.renderPagination();

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            console.error('Error in goToPage:', error);
        }
    },
    
    /**
     * Render pagination
     */
    renderPagination: function() {
        try {
            const totalPages = Math.ceil(this.state.filteredItems.length / this.state.itemsPerPage);
            const container = document.getElementById('pagination-controls');
            const infoEl = document.getElementById('pagination-info');

            if (!container || !infoEl) {
                console.warn('Pagination elements not found');
                return;
            }

            if (totalPages <= 1) {
                container.innerHTML = '';
                infoEl.textContent = '';
                return;
            }

            // Page info
            const start = (this.state.currentPage - 1) * this.state.itemsPerPage + 1;
            const end = Math.min(this.state.currentPage * this.state.itemsPerPage, this.state.filteredItems.length);
            infoEl.textContent = `${start}-${end} / ${this.state.filteredItems.length}`;

            // Minimal pagination
            let html = '';

            // Previous
            html += `<button type="button" data-page="${this.state.currentPage > 1 ? this.state.currentPage - 1 : 1}"
                         class="btn-pagination-prev q-pagination__btn ${this.state.currentPage === 1 ? 'is-disabled' : ''}" ${this.state.currentPage === 1 ? 'disabled' : ''}>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                 </button>`;

            // Page numbers
            const maxPagesToShow = 5;
            let startPage = Math.max(1, this.state.currentPage - Math.floor(maxPagesToShow / 2));
            let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

            if (endPage - startPage < maxPagesToShow - 1) {
                startPage = Math.max(1, endPage - maxPagesToShow + 1);
            }

            if (startPage > 1) {
                html += `<button type="button" data-page="1" class="btn-pagination q-pagination__btn">1</button>`;
                if (startPage > 2) {
                    html += `<span class="q-pagination__ellipsis">···</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button type="button" data-page="${i}" class="btn-pagination q-pagination__btn ${i === this.state.currentPage ? 'active' : ''}">${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="q-pagination__ellipsis">···</span>`;
                }
                html += `<button type="button" data-page="${totalPages}" class="btn-pagination q-pagination__btn">${totalPages}</button>`;
            }

            // Next
            html += `<button type="button" data-page="${this.state.currentPage < totalPages ? this.state.currentPage + 1 : totalPages}"
                         class="btn-pagination-next q-pagination__btn ${this.state.currentPage === totalPages ? 'is-disabled' : ''}" ${this.state.currentPage === totalPages ? 'disabled' : ''}>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                 </button>`;

            container.innerHTML = html;
        } catch (error) {
            console.error('Error in renderPagination:', error);
        }
    },
    
    /**
     * Build HTML for a single menu item row card.
     */
    buildMenuItemCardHtml: function(item, category, ingredientsCount, extrasCount) {
        const imageUrl = item.image_url ? window.escapeHtml(item.image_url).replace(/'/g, "\\'") : '';
        const thumbHtml = imageUrl
            ? `<div class="q-menu-item-card__thumb" style="background-image:url('${imageUrl}');"></div>`
            : `<div class="q-menu-item-card__thumb q-menu-item-card__thumb--empty"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>`;

        const editBtn = this.config.permissions.canEdit
            ? `<button type="button" data-item-id="${item.menu_item_id}" class="btn-edit-item q-icon-btn" title="Düzenle"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>`
            : '';
        const deleteBtn = this.config.permissions.canDelete
            ? `<button type="button" data-item-id="${item.menu_item_id}" class="btn-delete-item q-icon-btn q-icon-btn--danger" title="Sil"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>`
            : '';
        const isAvailable = item.is_available === undefined || item.is_available === null
            ? true
            : !!parseInt(item.is_available, 10);
        const statusBadge = isAvailable
            ? ''
            : '<span class="q-menu-item-card__status q-menu-item-card__status--off">Tükendi</span>';

        return `
            <article class="q-menu-item-card${isAvailable ? '' : ' q-menu-item-card--unavailable'}">
                ${thumbHtml}
                <div class="q-menu-item-card__body">
                    <div class="q-menu-item-card__top">
                        <div class="q-menu-item-card__info">
                            <h3 class="q-menu-item-card__title">${window.escapeHtml(item.name)}${statusBadge}</h3>
                            <p class="q-menu-item-card__category">${window.escapeHtml(category?.name || '-')}</p>
                        </div>
                        <span class="q-menu-item-card__price">${window.formatCurrency(item.price || 0)}</span>
                    </div>
                    <div class="q-menu-item-card__bottom">
                        <span class="q-menu-item-card__meta">${ingredientsCount} malzeme · ${extrasCount} ekstra</span>
                        <div class="q-menu-item-card__actions">${editBtn}${deleteBtn}</div>
                    </div>
                </div>
            </article>
        `;
    },

    /**
     * Render menu items list (all breakpoints)
     */
    renderTable: function() {
        try {
            const listEl = document.getElementById('menu-items-list');
            if (!listEl) {
                return;
            }

            listEl.innerHTML = '';

            const start = (this.state.currentPage - 1) * this.state.itemsPerPage;
            const end = start + this.state.itemsPerPage;
            const paginatedItems = this.state.filteredItems.slice(start, end);

            if (paginatedItems.length === 0) {
                listEl.innerHTML = '<div class="q-menu-items-empty"><p class="q-hint text-sm">Filtrelere uygun ürün bulunamadı.</p></div>';
                this.renderPagination();
                return;
            }

            paginatedItems.forEach(item => {
                const category = this.config.categories.find(c => c.category_id === item.category_id);

                let ingredientsCount = 0;
                try {
                    const ingredients = item.ingredients ? (typeof item.ingredients === 'string' ? JSON.parse(item.ingredients) : item.ingredients) : [];
                    ingredientsCount = Array.isArray(ingredients) ? ingredients.length : 0;
                } catch (e) {
                    ingredientsCount = 0;
                }

                let extrasCount = 0;
                try {
                    const extras = item.available_extras ? (typeof item.available_extras === 'string' ? JSON.parse(item.available_extras) : item.available_extras) : [];
                    extrasCount = Array.isArray(extras) ? extras.length : 0;
                } catch (e) {
                    extrasCount = 0;
                }

                const wrapper = document.createElement('div');
                wrapper.innerHTML = this.buildMenuItemCardHtml(item, category, ingredientsCount, extrasCount);
                listEl.appendChild(wrapper.firstElementChild);
            });

            this.renderPagination();
        } catch (error) {
            console.error('Error in renderTable:', error);
        }
    },
    
    /**
     * Switch image input mode (url/file)
     */
    switchImageInput: function(mode) {
        try {
            this.state.currentImageInputMode = mode;
            const urlToggle = document.getElementById('image-url-toggle');
            const fileToggle = document.getElementById('image-file-toggle');
            const urlInput = document.getElementById('image-url-input');
            const fileInput = document.getElementById('image-file-input');

            if (mode === 'url') {
                urlToggle.classList.add('selected');
                urlToggle.setAttribute('aria-selected', 'true');
                fileToggle.classList.remove('selected');
                fileToggle.setAttribute('aria-selected', 'false');
                urlInput.classList.remove('hidden');
                fileInput.classList.add('hidden');
            } else {
                fileToggle.classList.add('selected');
                fileToggle.setAttribute('aria-selected', 'true');
                urlToggle.classList.remove('selected');
                urlToggle.setAttribute('aria-selected', 'false');
                fileInput.classList.remove('hidden');
                urlInput.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error in switchImageInput:', error);
        }
    },
    
    /**
     * Handle image file select
     */
    handleImageFileSelect: async function(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Lütfen bir resim dosyası seçin.');
            }
            return;
        }

        // Validate file size (5MB max for products)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Dosya boyutu çok büyük. Maksimum 5MB olabilir.');
            }
            return;
        }

        // Get entity ID if editing
        const editId = document.getElementById('edit-id')?.value;
        
        // For new products (no entity_id), store file and show message
        if (!editId) {
            // Store file for upload after product creation
            this.state.pendingImageFile = file;
            
            // Show file name in UI
            const fileLabel = document.getElementById('image-file-label');
            if (fileLabel) {
                fileLabel.textContent = file.name + ' (Ürün kaydedildikten sonra yüklenecek)';
            }
            
            // Show preview from file object
            const reader = new FileReader();
            reader.onload = (e) => {
                this.showImagePreview(e.target.result);
            };
            reader.readAsDataURL(file);
            
            if (window.NotificationManager) {
                window.NotificationManager.info('Resim seçildi. Önce ürünü kaydedin, resim otomatik yüklenecek.');
            }
            return;
        }

        // For existing products, upload immediately
        // Show progress
        const progressContainer = document.getElementById('image-upload-progress');
        const progressBar = document.getElementById('image-upload-progress-bar');
        const statusText = document.getElementById('image-upload-status');
        const fileLabel = document.getElementById('image-file-label');

        progressContainer.classList.remove('hidden');
        progressBar.style.width = '0%';
        statusText.textContent = 'Yükleniyor...';
        fileLabel.textContent = file.name;

        // Create FormData
        const formData = new FormData();
        formData.append('file', file);
        formData.append('entity_type', 'product');
        formData.append('entity_id', editId);

        try {
            const csrfToken = this.getCSRFToken();

            // Upload image
            const response = await fetch(`${this.config.baseUrl}/api/images/upload`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text().catch(() => 'Unknown error');
                console.error('Image upload failed:', response.status, errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json().catch(err => {
                console.error('JSON parse error in image upload:', err);
                throw err;
            });

            if (result.success && result.data && result.data.url) {
                // Update progress
                progressBar.style.width = '100%';
                statusText.textContent = 'Yükleme tamamlandı!';

                // Set image URL in URL input
                const imageUrlField = document.getElementById('form-image-url');
                if (imageUrlField) {
                    imageUrlField.value = result.data.url;
                }

                // Show preview
                this.showImagePreview(result.data.url);

                // Update menu item in the list with new image URL
                if (editId) {
                    this.updateMenuItemImage(editId, result.data.url);
                }

                if (window.NotificationManager) {
                    window.NotificationManager.success('Resim başarıyla yüklendi!');
                }
            } else {
                progressContainer.classList.add('hidden');
                const errorMsg = result.errors && result.errors.length > 0
                    ? result.errors[0]
                    : 'Resim yüklenirken bir hata oluştu.';
                if (window.NotificationManager) {
                    window.NotificationManager.error(errorMsg);
                }
            }
        } catch (error) {
            progressContainer.classList.add('hidden');
            console.error('Image upload error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Resim yüklenirken bir hata oluştu: ' + error.message);
            }
        }
    },
    
    /**
     * Upload pending image after product creation
     */
    uploadPendingImage: async function(entityId) {
        if (!this.state.pendingImageFile || !entityId) {
            return;
        }
        
        const file = this.state.pendingImageFile;
        this.state.pendingImageFile = null; // Clear after use
        
        // Show progress
        const progressContainer = document.getElementById('image-upload-progress');
        const progressBar = document.getElementById('image-upload-progress-bar');
        const statusText = document.getElementById('image-upload-status');
        const fileLabel = document.getElementById('image-file-label');

        if (progressContainer) progressContainer.classList.remove('hidden');
        if (progressBar) progressBar.style.width = '0%';
        if (statusText) statusText.textContent = 'Yükleniyor...';
        if (fileLabel) fileLabel.textContent = file.name;

        // Create FormData
        const formData = new FormData();
        formData.append('file', file);
        formData.append('entity_type', 'product');
        formData.append('entity_id', entityId);

        try {
            const csrfToken = this.getCSRFToken();

            // Upload image
            const response = await fetch(`${this.config.baseUrl}/api/images/upload`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text().catch(() => 'Unknown error');
                console.error('Pending image upload failed:', response.status, errorText);
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Resim yüklenirken hata oluştu. Daha sonra tekrar deneyebilirsiniz.');
                }
                if (progressContainer) progressContainer.classList.add('hidden');
                return;
            }

            const result = await response.json().catch(err => {
                console.error('JSON parse error in pending image upload:', err);
                return null;
            });

            if (result && result.success && result.data && result.data.url) {
                // Update progress
                if (progressBar) progressBar.style.width = '100%';
                if (statusText) statusText.textContent = 'Yükleme tamamlandı!';

                // Set image URL in URL input
                const imageUrlField = document.getElementById('form-image-url');
                if (imageUrlField) {
                    imageUrlField.value = result.data.url;
                }

                // Show preview
                this.showImagePreview(result.data.url);

                // Update menu item in the list with new image URL
                this.updateMenuItemImage(entityId, result.data.url);

                if (window.NotificationManager) {
                    window.NotificationManager.success('Resim başarıyla yüklendi!');
                }
            } else {
                if (progressContainer) progressContainer.classList.add('hidden');
                const errorMsg = (result && result.errors && result.errors.length > 0)
                    ? result.errors[0]
                    : 'Resim yüklenirken bir hata oluştu.';
                if (window.NotificationManager) {
                    window.NotificationManager.warning(errorMsg);
                }
            }
        } catch (error) {
            if (progressContainer) progressContainer.classList.add('hidden');
            console.error('Pending image upload error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.warning('Resim yüklenirken bir hata oluştu. Daha sonra tekrar deneyebilirsiniz.');
            }
        }
    },
    
    /**
     * Check if menu item matches current filters
     */
    matchesFilters: function(item) {
        const filters = this.state.activeFilters;
        
        // Search term filter
        if (this.state.searchTerm) {
            const searchLower = this.state.searchTerm.toLowerCase();
            const nameMatch = (item.name || '').toLowerCase().includes(searchLower);
            const descMatch = (item.description || '').toLowerCase().includes(searchLower);
            const catMatch = this.config.categories.find(c => c.category_id === item.category_id)?.name?.toLowerCase().includes(searchLower);
            
            if (!nameMatch && !descMatch && !catMatch) {
                return false;
            }
        }
        
        // Category filter
        if (filters.category && item.category_id !== filters.category) {
            return false;
        }
        
        // Status filter
        if (filters.status === 'available' && !item.is_available) {
            return false;
        }
        if (filters.status === 'unavailable' && item.is_available) {
            return false;
        }
        
        // Stock filter
        if (filters.stock === 'in_stock' && (item.stock === 0 || item.stock === null)) {
            return false;
        }
        if (filters.stock === 'out_of_stock' && item.stock > 0) {
            return false;
        }
        
        return true;
    },
    
    /**
     * Add new menu item to the list after creation
     */
    addMenuItemToList: async function(menuItemId) {
        try {
            // Idempotency + race-safety: if item already present or a fetch is
            // already in flight for this id, return the existing promise rather
            // than firing a second fetch (which would push a duplicate row).
            if (!this._pendingAdds) {
                this._pendingAdds = new Map();
            }
            const exists = this.state.allMenuItems.some(item => item.menu_item_id === menuItemId);
            if (exists) {
                console.debug('Menu item already in list:', menuItemId);
                return;
            }
            if (this._pendingAdds.has(menuItemId)) {
                return this._pendingAdds.get(menuItemId);
            }

            const promise = (async () => {
                try {
                    const response = await fetch(`${this.config.baseUrl}/api/menu/item?id=${menuItemId}`);
                    if (!response.ok) {
                        console.error('Failed to fetch new menu item:', response.status);
                        return;
                    }

                    const data = await response.json().catch(err => {
                        console.error('JSON parse error:', err);
                        return null;
                    });

                    if (data && data.menu_item_id) {
                        // Re-check to avoid duplicate after concurrent push
                        const alreadyAdded = this.state.allMenuItems.some(item => item.menu_item_id === data.menu_item_id);
                        if (!alreadyAdded) {
                            this.state.allMenuItems.push(data);
                            if (this.matchesFilters(data)) {
                                this.state.filteredItems.push(data);
                            }
                            this.renderTable();
                            console.debug('New menu item added to list:', menuItemId);
                        }
                    }
                } finally {
                    this._pendingAdds.delete(menuItemId);
                }
            })();

            this._pendingAdds.set(menuItemId, promise);
            return promise;
        } catch (error) {
            console.error('Error adding menu item to list:', error);
        }
    },
    
    /**
     * Update menu item image in the list after upload
     */
    updateMenuItemImage: function(menuItemId, imageUrl) {
        try {
            // Find and update the item in state
            let itemIndex = this.state.allMenuItems.findIndex(item => item.menu_item_id === menuItemId);
            
            if (itemIndex === -1) {
                // Item not in list - fetch and add it first
                console.debug('Menu item not in list, fetching from API:', menuItemId);
                this.addMenuItemToList(menuItemId).then(() => {
                    // Retry update after adding to list
                    this.updateMenuItemImage(menuItemId, imageUrl);
                }).catch(err => {
                    console.error('Error fetching menu item for image update:', err);
                });
                return;
            }
            
            // Update the item's image_url
            this.state.allMenuItems[itemIndex].image_url = imageUrl;
            
            // Also update in filtered items if it exists there
            const filteredIndex = this.state.filteredItems.findIndex(item => item.menu_item_id === menuItemId);
            if (filteredIndex !== -1) {
                this.state.filteredItems[filteredIndex].image_url = imageUrl;
            }
            
            // Re-render the table to show updated image
            this.renderTable();
            
            console.debug('Menu item image updated in list:', { menuItemId, imageUrl });
        } catch (error) {
            console.error('Error updating menu item image in list:', error);
        }
    },
    
    /**
     * Update menu item in the list after edit
     */
    updateMenuItemInList: async function(menuItemId) {
        try {
            // Store current scroll position and page
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const currentPage = this.state.currentPage;
            const currentFilters = { ...this.state.activeFilters };
            
            // Fetch the updated menu item from API
            const response = await fetch(`${this.config.baseUrl}/api/menu/item?id=${menuItemId}`);
            if (!response.ok) {
                console.error('Failed to fetch updated menu item:', response.status);
                return;
            }

            const data = await response.json().catch(err => {
                console.error('JSON parse error:', err);
                return null;
            });

            if (data && data.menu_item_id) {
                // Find and update in allMenuItems
                const itemIndex = this.state.allMenuItems.findIndex(item => item.menu_item_id === menuItemId);
                if (itemIndex !== -1) {
                    // Update the item
                    this.state.allMenuItems[itemIndex] = data;
                } else {
                    // Item not in list - add it
                    this.state.allMenuItems.push(data);
                }
                
                // Update in filteredItems if it matches current filters
                const filteredIndex = this.state.filteredItems.findIndex(item => item.menu_item_id === menuItemId);
                if (filteredIndex !== -1) {
                    if (this.matchesFilters(data)) {
                        // Update the item in filtered list
                        this.state.filteredItems[filteredIndex] = data;
                    } else {
                        // Item no longer matches filters - remove it
                        this.state.filteredItems.splice(filteredIndex, 1);
                    }
                } else if (this.matchesFilters(data)) {
                    // Item matches filters but not in filtered list - add it
                    this.state.filteredItems.push(data);
                }
                
                // Restore filters and page (in case they changed)
                this.state.activeFilters = currentFilters;
                this.state.currentPage = currentPage;
                
                // Re-render the table without scrolling
                this.renderTable();
                
                // Restore scroll position after render
                requestAnimationFrame(() => {
                    window.scrollTo({ top: scrollTop, behavior: 'instant' });
                });
                
                console.debug('Menu item updated in list:', menuItemId);
            }
        } catch (error) {
            console.error('Error updating menu item in list:', error);
        }
    },
    
    /**
     * Show image preview
     */
    showImagePreview: function(imageUrl) {
        try {
            const previewContainer = document.getElementById('image-preview-container');
            const previewImg = document.getElementById('image-preview');

            if (previewContainer && previewImg) {
                previewImg.src = imageUrl;
                previewContainer.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error in showImagePreview:', error);
        }
    },
    
    /**
     * Clear image preview
     */
    clearImagePreview: function() {
        try {
            const previewContainer = document.getElementById('image-preview-container');
            const fileInput = document.getElementById('form-image-file');
            const urlInput = document.getElementById('form-image-url');
            const fileLabel = document.getElementById('image-file-label');
            const progressContainer = document.getElementById('image-upload-progress');

            if (previewContainer) previewContainer.classList.add('hidden');
            if (fileInput) fileInput.value = '';
            if (urlInput) urlInput.value = '';
            if (fileLabel) fileLabel.textContent = 'Dosya Seç veya Sürükle';
            if (progressContainer) progressContainer.classList.add('hidden');
            
            // Clear pending image file
            this.state.pendingImageFile = null;
        } catch (error) {
            console.error('Error in clearImagePreview:', error);
        }
    },
    
    /**
     * Setup image URL sync
     */
    setupImageUrlSync: function() {
        try {
            const urlInput = document.getElementById('form-image-url');

            if (urlInput) {
                // Remove existing listener if any
                if (this._imageUrlInputHandler) {
                    urlInput.removeEventListener('input', this._imageUrlInputHandler);
                }
                
                // Create new handler
                this._imageUrlInputHandler = () => {
                    try {
                        if (urlInput.value) {
                            this.showImagePreview(urlInput.value);
                        } else {
                            this.clearImagePreview();
                        }
                    } catch (error) {
                        console.error('Error in image URL input handler:', error);
                    }
                };
                
                urlInput.addEventListener('input', this._imageUrlInputHandler);
            }
        } catch (error) {
            console.error('Error in setupImageUrlSync:', error);
        }
    },
    
    /**
     * Load translations for edit
     */
    loadTranslationsForEdit: function(translations, mainData = {}) {
        try {
            if (Array.isArray(this.config.supportedLanguages)) {
                this.config.supportedLanguages.forEach(lang => {
                    const nameField = document.getElementById(`form-name-${lang}`);
                    const descField = document.getElementById(`form-description-${lang}`);

                    if (translations[lang]) {
                        const trans = translations[lang];
                        if (nameField) nameField.value = trans.name || '';
                        if (descField) descField.value = trans.description || '';
                    } else if (lang === this.config.defaultLanguage && mainData) {
                        // Fallback to main data for default language if translations don't exist
                        if (nameField) nameField.value = mainData.name || '';
                        if (descField) descField.value = mainData.description || '';
                    }
                });
            }
        } catch (error) {
            console.error('Error in loadTranslationsForEdit:', error);
        }
    },
    
    /**
     * Auto translate input field to all other languages
     */
    autoTranslateInput: async function(sourceLang, field) {
        try {
            const sourceField = document.getElementById(`form-${field}-${sourceLang}`);
            if (!sourceField) return;

            const sourceValue = sourceField.value.trim();
            if (!sourceValue) return;

            // Get all supported languages except the source language
            const targetLanguages = this.config.supportedLanguages.filter(lang => lang !== sourceLang);

            // Translate to each target language
            for (const targetLang of targetLanguages) {
                const targetField = document.getElementById(`form-${field}-${targetLang}`);
                if (!targetField) continue;

                // If target field already has value, don't auto-translate
                if (targetField.value.trim()) {
                    continue;
                }

                try {
                    const csrfToken = this.getCSRFToken();

                    const response = await fetch(`${this.config.baseUrl}${this.config.adminPrefix}/menu/translate-product-name`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            name: sourceValue,
                            source_language: sourceLang,
                            target_language: targetLang
                        })
                    });

                    if (!response.ok) {
                        // Silently fail - don't show error for auto-translate
                        console.error(`Auto-translate failed for ${targetLang}:`, response.status);
                        continue;
                    }

                    const data = await response.json().catch(err => {
                        console.error('JSON parse error in auto-translate:', err);
                        return null;
                    });

                    if (data && data.success) {
                        // Handle both old format (english_name) and new format (translated_text)
                        const translatedText = data.translated_text || data.english_name || data.name;
                        if (translatedText) {
                            targetField.value = translatedText;
                        }
                    }
                } catch (error) {
                    // Log error for debugging (auto-translate errors are logged but not shown to user)
                    console.error(`Error translating ${field} to ${targetLang}:`, error);
                }
            }
        } catch (error) {
            console.error('Error in autoTranslateInput:', error);
        }
    },
    
    /**
     * Generate menu description using AI
     */
    
    /**
     * Open menu extraction modal
     */
    openMenuExtractionModal: function() {
        try {
            const modal = document.getElementById('menu-extraction-modal');
            if (!modal) {
                console.error('Menu extraction modal not found');
                return;
            }
            
            // Reset modal state
            this.state.extractedItems = [];
            this.state.extractionImageFiles = [];
            
            // Show upload step
            document.getElementById('extraction-step-upload').classList.remove('hidden');
            document.getElementById('extraction-step-loading').classList.add('hidden');
            document.getElementById('extraction-step-review').classList.add('hidden');
            
            // Show/hide buttons
            const extractBtnModal = document.getElementById('btn-extract-menu-modal');
            const saveBtn = document.getElementById('btn-save-extracted-items');
            if (extractBtnModal) extractBtnModal.classList.add('hidden');
            if (saveBtn) saveBtn.classList.add('hidden');
            
            // Clear preview
            const previewContainer = document.getElementById('extraction-images-preview-container');
            const previewGrid = document.getElementById('extraction-images-preview');
            const imageInput = document.getElementById('extraction-image-input');
            const countElement = document.getElementById('selected-images-count');
            
            if (previewContainer) previewContainer.classList.add('hidden');
            if (previewGrid) previewGrid.innerHTML = '';
            if (imageInput) {
                imageInput.value = '';
            }
            if (countElement) countElement.textContent = '0';
            
            // Show modal
            modal.classList.remove('hidden');
        } catch (error) {
            console.error('Error opening menu extraction modal:', error);
        }
    },
    
    /**
     * Close menu extraction modal
     */
    closeMenuExtractionModal: function() {
        try {
            const modal = document.getElementById('menu-extraction-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
            
            // Reset state
            this.state.extractedItems = [];
            this.state.extractionImageFiles = [];
        } catch (error) {
            console.error('Error closing menu extraction modal:', error);
        }
    },
    
    /**
     * Handle menu image file selection (multiple files support)
     */
    handleMenuImageUpload: function(event) {
        try {
            console.log('handleMenuImageUpload called', event.target.files);
            const files = Array.from(event.target.files || []);
            console.log('Files selected:', files.length);
            if (files.length === 0) return;
            
            // Check total count (max 5)
            const currentCount = this.state.extractionImageFiles.length;
            const remainingSlots = 5 - currentCount;
            
            if (files.length > remainingSlots) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning(`Maksimum 5 fotoğraf yükleyebilirsiniz. ${remainingSlots} adet daha ekleyebilirsiniz.`);
                }
                files.splice(remainingSlots);
            }
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            const validFiles = [];
            
            files.forEach((file, index) => {
                // Validate file type
                if (!allowedTypes.includes(file.type)) {
                    if (window.NotificationManager) {
                        window.NotificationManager.error(`${file.name}: Desteklenmeyen dosya formatı. JPEG, PNG veya WebP formatında olmalıdır.`);
                    }
                    return;
                }
                
                // Validate file size
                if (file.size > maxSize) {
                    if (window.NotificationManager) {
                        window.NotificationManager.error(`${file.name}: Dosya boyutu çok büyük. Maksimum 10MB olabilir.`);
                    }
                    return;
                }
                
                validFiles.push(file);
            });
            
            if (validFiles.length === 0) {
                console.log('No valid files after validation');
                return;
            }
            
            // Add files to state
            this.state.extractionImageFiles = [...this.state.extractionImageFiles, ...validFiles];
            
            // Render previews
            this.renderImagePreviews();
            
            // Show extract button if we have files (use modal-specific button ID)
            const extractBtn = document.getElementById('btn-extract-menu-modal');
            if (extractBtn && this.state.extractionImageFiles.length > 0) {
                extractBtn.classList.remove('hidden');
            }
            
        } catch (error) {
            console.error('Error handling menu image upload:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Resim yüklenirken bir hata oluştu.');
            }
        }
    },
    
    /**
     * Render image previews
     */
    renderImagePreviews: function() {
        const container = document.getElementById('extraction-images-preview');
        const previewContainer = document.getElementById('extraction-images-preview-container');
        const countElement = document.getElementById('selected-images-count');
        
        if (!container || !previewContainer) {
            return;
        }
        
        container.innerHTML = '';
        
        if (this.state.extractionImageFiles.length === 0) {
            previewContainer.classList.add('hidden');
            return;
        }
        
        previewContainer.classList.remove('hidden');
        if (countElement) {
            countElement.textContent = this.state.extractionImageFiles.length;
        }
        
        this.state.extractionImageFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageDiv = document.createElement('div');
                imageDiv.className = 'relative group';
                imageDiv.innerHTML = `
                    <div class="relative aspect-square rounded-xl overflow-hidden q-card group">
                        <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity" style="background:linear-gradient(to top, rgba(0,0,0,.7), transparent);"></div>
                        <button type="button" class="btn-remove-image q-icon-btn absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-all z-10" style="background:var(--color-status-danger);color:#fff;" data-index="${index}" title="Kaldır">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <div class="absolute bottom-0 left-0 right-0 p-3" style="background:linear-gradient(to top, rgba(0,0,0,.8), transparent);">
                            <p class="text-xs text-white font-medium truncate drop-shadow-lg" title="${file.name}">${window.escapeHtml(file.name)}</p>
                            <p class="text-xs text-white/90 mt-0.5">${(file.size / 1024).toFixed(1)} KB</p>
                        </div>
                    </div>
                `;
                container.appendChild(imageDiv);
                
                // Attach remove button listener using event delegation
                const self = this;
                const removeBtn = imageDiv.querySelector('.btn-remove-image');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const idx = parseInt(this.getAttribute('data-index'));
                        self.removeImageFile(idx);
                    });
                }
            };
            reader.readAsDataURL(file);
        });
    },
    
    /**
     * Remove image file from selection
     */
    removeImageFile: function(index) {
        if (this.state.extractionImageFiles[index]) {
            this.state.extractionImageFiles.splice(index, 1);
            this.renderImagePreviews();
            
            // Hide extract button if no files
            const extractBtnModal = document.getElementById('btn-extract-menu-modal');
            if (extractBtnModal && this.state.extractionImageFiles.length === 0) {
                extractBtnModal.classList.add('hidden');
            }
        }
    },
    
    /**
     * Extract menu items from images (process all selected images)
     */
    extractMenuFromImage: async function() {
        try {
            if (!this.state.extractionImageFiles || this.state.extractionImageFiles.length === 0) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Lütfen en az bir fotoğraf seçin.');
                }
                return;
            }
            
            // Show loading state
            document.getElementById('extraction-step-upload').classList.add('hidden');
            document.getElementById('extraction-step-loading').classList.remove('hidden');
            document.getElementById('extraction-step-review').classList.add('hidden');
            const extractBtnModal = document.getElementById('btn-extract-menu-modal');
            if (extractBtnModal) extractBtnModal.classList.add('hidden');
            
            // Process all images sequentially
            let allExtractedItems = [];
            const totalImages = this.state.extractionImageFiles.length;
            
            for (let i = 0; i < this.state.extractionImageFiles.length; i++) {
                const file = this.state.extractionImageFiles[i];
                
                // Update loading message
                const loadingText = document.querySelector('#extraction-step-loading p');
                if (loadingText) {
                    loadingText.textContent = `Menü analiz ediliyor... (${i + 1}/${totalImages})`;
                }
                
                // Prepare form data
                const formData = new FormData();
                formData.append('image', file);
                
                // CRITICAL: Add CSRF token for request validation
                const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                                 document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                 (window.CSRF_TOKEN || '');
                if (csrfToken) {
                    formData.append('_token', csrfToken);
                }

                const businessId = this.getBusinessIdForRequest();
                if (businessId) {
                    formData.append('business_id', businessId);
                }
                
                try {
                    // Call API
                    const response = await fetch(`${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/menu/extract-from-image`, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        }
                    });
                    
                    // Check HTTP status first
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error(`Image ${i + 1} HTTP error (${response.status}):`, errorText);
                        let errorMsg = `HTTP ${response.status}`;
                        try {
                            const errorJson = JSON.parse(errorText);
                            errorMsg = errorJson.message || errorJson.error || errorMsg;
                        } catch (e) {
                            // Not JSON, use text
                        }
                        if (window.NotificationManager && i === 0) {
                            window.NotificationManager.error(`Fotoğraf ${i + 1} işlenirken hata: ${errorMsg}`);
                        }
                        continue; // Skip to next image
                    }
                    
                    const result = await response.json();
                    console.log(`Image ${i + 1} API response:`, result);
                    
                    // Check response format - backend uses toastNotificationService->sendApiResponse
                    // Format: { success: true/false, message: "...", data: {...} }
                    let items = null;
                    
                    if (result.success === true || result.success === 'success') {
                        // Success response
                        if (result.data && result.data.items && Array.isArray(result.data.items)) {
                            items = result.data.items;
                            console.log(`Image ${i + 1}: Found ${items.length} items in result.data.items`);
                        } else if (result.data && Array.isArray(result.data)) {
                            // Fallback: if items are directly in data array
                            items = result.data;
                            console.log(`Image ${i + 1}: Found ${items.length} items in result.data (direct array)`);
                        } else if (Array.isArray(result)) {
                            // Fallback: if result itself is an array
                            items = result;
                            console.log(`Image ${i + 1}: Found ${items.length} items in result (direct array)`);
                        }
                        
                        if (items && items.length > 0) {
                            // Map items to ensure category data is preserved
                            const mappedItems = items.map(item => ({
                                name: item.name_tr || item.name || '',
                                name_tr: item.name_tr || item.name || '',
                                name_en: item.name_en || '',
                                price: parseFloat(item.price) || 0,
                                description: item.description_tr || item.description || '',
                                description_tr: item.description_tr || item.description || '',
                                description_en: item.description_en || '',
                                ingredients: item.ingredients_tr || item.ingredients || [],
                                ingredients_tr: item.ingredients_tr || item.ingredients || [],
                                ingredients_en: item.ingredients_en || [],
                                category: item.category || '',  // Gemini extracted category
                                parent_category: item.parent_category || ''  // Gemini extracted parent
                            }));
                            allExtractedItems = [...allExtractedItems, ...mappedItems];
                            console.log(`Image ${i + 1}: Successfully extracted ${items.length} items. Total: ${allExtractedItems.length}`);
                        } else {
                            console.warn(`Image ${i + 1}: Success response but no items found`, result);
                            if (window.NotificationManager && i === 0) {
                                window.NotificationManager.warning(`Fotoğraf ${i + 1}'den ürün bulunamadı.`);
                            }
                        }
                    } else {
                        // Error response
                        const errorMsg = result.message || result.error || 'Bilinmeyen hata';
                        console.warn(`Image ${i + 1} extraction failed:`, errorMsg, result);
                        if (window.NotificationManager && i === 0) {
                            window.NotificationManager.warning(`Fotoğraf ${i + 1} işlenirken sorun oluştu: ${errorMsg}`);
                        }
                    }
                } catch (error) {
                    console.error(`Error extracting from image ${i + 1}:`, error);
                    if (window.NotificationManager && i === 0) {
                        window.NotificationManager.error(`Fotoğraf ${i + 1} işlenirken bir hata oluştu: ${error.message}`);
                    }
                    // Continue with next image
                }
            }
            
            // Store all extracted items
            this.state.extractedItems = allExtractedItems;
            
            if (this.state.extractedItems.length === 0) {
                throw new Error('Menüden ürün bulunamadı. Lütfen daha net menü fotoğrafları deneyin.');
            }
            
            // Show review step
            this.renderExtractedItems();
            
        } catch (error) {
            console.error('Error extracting menu:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(error.message || 'Menü çıkarılırken bir hata oluştu.');
            }
            
            // Show upload step again
            document.getElementById('extraction-step-upload').classList.remove('hidden');
            document.getElementById('extraction-step-loading').classList.add('hidden');
            const extractBtnModal = document.getElementById('btn-extract-menu-modal');
            if (extractBtnModal && this.state.extractionImageFiles.length > 0) {
                extractBtnModal.classList.remove('hidden');
            }
        }
    },
    
    /**
     * Render extracted items for review/edit
     */
    renderExtractedItems: function() {
        try {
            const container = document.getElementById('extracted-items-list');
            const countElement = document.getElementById('extraction-items-count');
            
            if (!container) return;
            
            if (countElement) {
                countElement.textContent = this.state.extractedItems.length;
            }
            
            container.innerHTML = '';
            
            // Get categories for dropdown
            const categories = this.config.categories || [];
            const self = this; // Store reference for use in forEach
            
            this.state.extractedItems.forEach((item, index) => {
                // Find matching category
                const matchingCategoryId = item.category ? self.findMatchingCategory(item.category, categories) : null;
                const hasCategoryWarning = item.category && !matchingCategoryId;
                
                const itemDiv = document.createElement('div');
                itemDiv.className = 'q-card q-card--pad';
                itemDiv.innerHTML = `
                    <div class="flex items-start justify-between mb-4 pb-3" style="border-bottom:1px solid var(--color-border-subtle);">
                        <div class="flex items-center gap-3">
                            <div class="q-badge font-semibold text-sm" style="min-width:2.5rem;justify-content:center;">${index + 1}</div>
                            <h4 class="font-semibold text-base">${window.escapeHtml(item.name_tr || item.name || 'Ürün ' + (index + 1))}</h4>
                        </div>
                        <button type="button" class="btn-remove-extracted-item q-btn q-btn--ghost q-btn--sm" style="color:var(--color-status-danger);" data-index="${index}">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Kaldır
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="q-label block mb-2">Ürün Adı (TR) *</label>
                            <input type="text" class="extracted-item-name-tr q-input" 
                                   value="${window.escapeHtml(item.name_tr || item.name || '')}" 
                                   data-index="${index}"
                                   data-lang="tr"
                                   required>
                        </div>
                        <div>
                            <label class="q-label block mb-2">Ürün Adı (EN) *</label>
                            <input type="text" class="extracted-item-name-en q-input" 
                                   value="${window.escapeHtml(item.name_en || item.name || '')}" 
                                   data-index="${index}"
                                   data-lang="en"
                                   required>
                        </div>
                        <div>
                            <label class="q-label block mb-2">Fiyat (₺) *</label>
                            <input type="number" step="0.01" class="extracted-item-price q-input" 
                                   value="${item.price || 0}" 
                                   data-index="${index}"
                                   required>
                        </div>
                        <div>
                            <label class="q-label block mb-2">Kategori ${hasCategoryWarning ? '<span style="color:var(--color-status-danger);">*</span>' : ''}</label>
                            <select class="extracted-item-category q-input${hasCategoryWarning ? ' q-input--error' : ''}" 
                                    data-index="${index}">
                                <option value="">${hasCategoryWarning ? '⚠ Kategori bulunamadı: ' + window.escapeHtml(item.category) : 'Kategori Seçin (opsiyonel)'}</option>
                                ${categories.map(cat => {
                                    const isMatch = matchingCategoryId === cat.category_id;
                                    return `
                                    <option value="${cat.category_id}" ${isMatch ? 'selected' : ''}>
                                        ${window.escapeHtml(cat.name)}${isMatch ? ' ✓' : ''}
                                    </option>
                                `;
                                }).join('')}
                            </select>
                            ${hasCategoryWarning ? `
                                <p class="q-hint text-xs mt-2 q-card q-card--pad flex items-start gap-2" style="border-color:var(--color-status-warning);">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <span>"${window.escapeHtml(item.category)}" kategorisi bulunamadı. Lütfen mevcut bir kategori seçin veya boş bırakın.</span>
                                </p>
                            ` : ''}
                        </div>
                        <div class="sm:col-span-2">
                            <label class="q-label block mb-2">Açıklama (TR)</label>
                            <textarea class="extracted-item-description-tr q-input resize-none" 
                                      rows="2"
                                      data-index="${index}"
                                      data-lang="tr"
                                      placeholder="Ürün açıklaması...">${window.escapeHtml(item.description_tr || item.description || '')}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="q-label block mb-2">Açıklama (EN)</label>
                            <textarea class="extracted-item-description-en q-input resize-none" 
                                      rows="2"
                                      data-index="${index}"
                                      data-lang="en"
                                      placeholder="Product description...">${window.escapeHtml(item.description_en || item.description || '')}</textarea>
                        </div>
                        <div>
                            <label class="q-label block mb-2">Malzemeler (TR)</label>
                            <input type="text" class="extracted-item-ingredients-tr q-input" 
                                   value="${window.escapeHtml(Array.isArray(item.ingredients_tr || item.ingredients) ? (item.ingredients_tr || item.ingredients).join(', ') : '')}" 
                                   data-index="${index}"
                                   data-lang="tr"
                                   placeholder="Domates, Peynir, Zeytin">
                        </div>
                        <div>
                            <label class="q-label block mb-2">Malzemeler (EN)</label>
                            <input type="text" class="extracted-item-ingredients-en q-input" 
                                   value="${window.escapeHtml(Array.isArray(item.ingredients_en) ? item.ingredients_en.join(', ') : '')}" 
                                   data-index="${index}"
                                   data-lang="en"
                                   placeholder="Tomatoes, Cheese, Olives">
                    </div>
                `;
                container.appendChild(itemDiv);
            });
            
            // Show review step
            document.getElementById('extraction-step-loading').classList.add('hidden');
            document.getElementById('extraction-step-review').classList.remove('hidden');
            document.getElementById('btn-save-extracted-items').classList.remove('hidden');
            
            // Attach event listeners for input changes
            container.querySelectorAll('.extracted-item-name-tr, .extracted-item-name-en, .extracted-item-price, .extracted-item-description-tr, .extracted-item-description-en, .extracted-item-category, .extracted-item-ingredients-tr, .extracted-item-ingredients-en').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    const className = e.target.className.split(' ')[0];
                    const field = className.replace('extracted-item-', '');
                    this.updateExtractedItem(index, field, e.target.value);
                });
            });
            
            // Attach remove button listeners
            container.querySelectorAll('.btn-remove-extracted-item').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    this.removeExtractedItem(index);
                });
            });
            
        } catch (error) {
            console.error('Error rendering extracted items:', error);
        }
    },
    
    /**
     * Check if two category names match (fuzzy matching)
     */
    categoriesMatch: function(cat1, cat2) {
        if (!cat1 || !cat2) return false;
        
        const normalize = (str) => {
            return str.toLowerCase()
                .replace(/ç/g, 'c')
                .replace(/ğ/g, 'g')
                .replace(/ı/g, 'i')
                .replace(/ö/g, 'o')
                .replace(/ş/g, 's')
                .replace(/ü/g, 'u')
                .replace(/[^a-z0-9]/g, '');
        };
        
        const n1 = normalize(cat1);
        const n2 = normalize(cat2);
        
        // Exact match
        if (n1 === n2) return true;
        
        // Contains match
        if (n1.includes(n2) || n2.includes(n1)) return true;
        
        // Common variations
        const variations = {
            'tostlar': ['tost'],
            'pizzalar': ['pizza'],
            'burgerler': ['hamburgerler', 'hamburger'],
            'salatalar': ['salata'],
            'tatlilar': ['tatli', 'dessert'],
            'kahveler': ['kahve', 'coffee'],
            'icecekler': ['icecek', 'drinks'],
            'nargile': ['nargile cesitleri'],
            'atistirmaliklar': ['atistirmalik', 'snacks'],
            'izgaralar': ['izgara', 'grills']
        };
        
        for (const [key, vars] of Object.entries(variations)) {
            if ((n1 === key || vars.includes(n1)) && (n2 === key || vars.includes(n2))) {
                return true;
            }
        }
        
        return false;
    },
    
    /**
     * Find matching category from extracted category name
     */
    findMatchingCategory: function(extractedCategory, categories) {
        if (!extractedCategory || !categories || categories.length === 0) return null;
        
        for (const cat of categories) {
            if (this.categoriesMatch(extractedCategory, cat.name)) {
                return cat.category_id;
            }
        }
        
        return null;
    },
    
    /**
     * Update extracted item field
     */
    updateExtractedItem: function(index, field, value) {
        if (!this.state.extractedItems[index]) return;
        
        if (field === 'ingredients-tr') {
            // Parse comma-separated ingredients Turkish
            this.state.extractedItems[index].ingredients_tr = value.split(',').map(i => i.trim()).filter(i => i);
            // Also update legacy field for backward compatibility
            this.state.extractedItems[index].ingredients = this.state.extractedItems[index].ingredients_tr;
        } else if (field === 'ingredients-en') {
            // Parse comma-separated ingredients English
            this.state.extractedItems[index].ingredients_en = value.split(',').map(i => i.trim()).filter(i => i);
        } else if (field === 'price') {
            this.state.extractedItems[index].price = parseFloat(value) || 0;
        } else if (field === 'name-tr') {
            this.state.extractedItems[index].name_tr = value;
            // Also update legacy field for backward compatibility
            this.state.extractedItems[index].name = value;
        } else if (field === 'name-en') {
            this.state.extractedItems[index].name_en = value;
        } else if (field === 'description-tr') {
            this.state.extractedItems[index].description_tr = value;
            // Also update legacy field for backward compatibility
            this.state.extractedItems[index].description = value;
        } else if (field === 'description-en') {
            this.state.extractedItems[index].description_en = value;
        } else {
            this.state.extractedItems[index][field] = value;
        }
    },
    
    /**
     * Remove extracted item
     */
    removeExtractedItem: function(index) {
        if (this.state.extractedItems[index]) {
            this.state.extractedItems.splice(index, 1);
            this.renderExtractedItems();
        }
    },
    
    /**
     * Save extracted items to database
     */
    saveExtractedItems: async function() {
        try {
            if (!this.state.extractedItems || this.state.extractedItems.length === 0) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Kaydedilecek ürün bulunamadı.');
                }
                return;
            }
            
            // Collect all items with current values from form
            const itemsToSave = [];
            const container = document.getElementById('extracted-items-list');
            
            this.state.extractedItems.forEach((item, index) => {
                const nameTrInput = container.querySelector(`.extracted-item-name-tr[data-index="${index}"]`);
                const nameEnInput = container.querySelector(`.extracted-item-name-en[data-index="${index}"]`);
                const priceInput = container.querySelector(`.extracted-item-price[data-index="${index}"]`);
                const descTrInput = container.querySelector(`.extracted-item-description-tr[data-index="${index}"]`);
                const descEnInput = container.querySelector(`.extracted-item-description-en[data-index="${index}"]`);
                const catInput = container.querySelector(`.extracted-item-category[data-index="${index}"]`);
                const ingTrInput = container.querySelector(`.extracted-item-ingredients-tr[data-index="${index}"]`);
                const ingEnInput = container.querySelector(`.extracted-item-ingredients-en[data-index="${index}"]`);
                
                const nameTr = nameTrInput ? nameTrInput.value.trim() : (item.name_tr || item.name || '');
                const nameEn = nameEnInput ? nameEnInput.value.trim() : (item.name_en || item.name_tr || item.name || nameTr);
                const priceValue = priceInput ? priceInput.value : (item.price || 0);
                const price = priceValue === '' || priceValue === null || priceValue === undefined ? 0 : parseFloat(priceValue);
                
                // Skip items without Turkish name only (price can be 0)
                if (!nameTr || nameTr.length === 0) {
                    console.warn(`Skipping item at index ${index} - no Turkish name`);
                    return;
                }
                
                // Get selected category
                const selectedCategory = catInput ? catInput.value : '';
                // Allow items without category
                if (!selectedCategory) {
                    console.log(`Item "${nameTr}" has no category selected, will be added without category`);
                }
                
                // Parse ingredients
                const ingredientsTr = ingTrInput ? ingTrInput.value.split(',').map(i => i.trim()).filter(i => i) : 
                                    (item.ingredients_tr || item.ingredients || []);
                const ingredientsEn = ingEnInput ? ingEnInput.value.split(',').map(i => i.trim()).filter(i => i) : 
                                    (item.ingredients_en || []);
                
                itemsToSave.push({
                    name: nameTr, // Legacy field for backward compatibility
                    name_tr: nameTr,
                    name_en: nameEn,
                    price: price,
                    description: descTrInput ? descTrInput.value.trim() : (item.description_tr || item.description || ''),
                    description_tr: descTrInput ? descTrInput.value.trim() : (item.description_tr || item.description || ''),
                    description_en: descEnInput ? descEnInput.value.trim() : (item.description_en || ''),
                    category: item.category || '',  // Gemini extracted category name
                    parent_category: item.parent_category || '',  // Gemini extracted parent
                    category_id: selectedCategory || null,  // User selected category ID from dropdown
                    ingredients: ingredientsTr, // Legacy field
                    ingredients_tr: ingredientsTr,
                    ingredients_en: ingredientsEn
                });
            });
            
            if (itemsToSave.length === 0) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Geçerli ürün bulunamadı. Lütfen en az bir ürün için Türkçe isim girildiğinden emin olun.');
                }
                console.error('No valid items to save. Total extracted items:', this.state.extractedItems.length);
                return;
            }
            
            console.log('Items to save:', itemsToSave.length, itemsToSave);
            
            // Show loading
            const saveBtn = document.getElementById('btn-save-extracted-items');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Kaydediliyor...';
            }
            
            // Get CSRF token
            const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                             document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                             (window.CSRF_TOKEN || '');
            
            // Call API
            const apiPrefix = this.config.apiPrefix || '/api/business';
            const bulkPayload = {
                items: itemsToSave,
                _token: csrfToken
            };
            const businessId = this.getBusinessIdForRequest();
            if (businessId) {
                bulkPayload.business_id = businessId;
            }

            const response = await fetch(`${this.config.baseUrl}${apiPrefix}/menu/bulk-add-from-extraction`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(bulkPayload)
            });
            
            const result = await response.json();
            
            if (result.success || result.type === 'success' || result.type === 'warning') {
                const successCount = result.data?.success_count || 0;
                const errorCount = result.data?.error_count || 0;
                
                if (window.NotificationManager) {
                    if (errorCount === 0) {
                        window.NotificationManager.success(`${successCount} ürün başarıyla eklendi.`);
                    } else if (successCount > 0) {
                        window.NotificationManager.warning(`${successCount} ürün eklendi, ${errorCount} ürün eklenemedi.`);
                    } else {
                        window.NotificationManager.error(`Hiçbir ürün eklenemedi.`);
                    }
                }
                
                // Close modal and refresh list
                this.closeMenuExtractionModal();
                
                // Reload page to get updated categories and items
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(result.message || 'Ürünler kaydedilirken bir hata oluştu.');
            }
            
        } catch (error) {
            console.error('Error saving extracted items:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error(error.message || 'Ürünler kaydedilirken bir hata oluştu.');
            }
        } finally {
            const saveBtn = document.getElementById('btn-save-extracted-items');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Onayla ve Ekle';
            }
        }
    }
    
};

// Export to global scope
if (typeof window !== 'undefined') {
    window.MenuPage = MenuPage;
}

