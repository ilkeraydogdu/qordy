/**
 * Global Confirmation Modal Component
 * Provides a confirmation dialog for critical operations
 * Version: 1.0
 */

const ConfirmationModal = {
    currentResolve: null,
    currentReject: null,
    
    /**
     * Show confirmation modal
     * @param {string} message - Confirmation message
     * @param {string} title - Modal title (optional)
     * @param {Object} options - Additional options
     * @returns {Promise<boolean>} Promise that resolves with user's choice
     */
    show: function(message, title = 'Onay', options = {}) {
        return new Promise((resolve, reject) => {
            this.currentResolve = resolve;
            this.currentReject = reject;
            
            // Get or create modal
            let modal = document.getElementById('globalConfirmationModal');
            if (!modal) {
                modal = this.createModal();
            }
            
            // Update modal content
            const modalTitle = modal.querySelector('#confirmationModalTitle');
            const modalMessage = modal.querySelector('#confirmationModalMessage');
            const confirmBtn = modal.querySelector('#confirmationConfirmBtn');
            const cancelBtn = modal.querySelector('#confirmationCancelBtn');
            
            if (modalTitle) modalTitle.textContent = title;
            if (modalMessage) modalMessage.textContent = message;
            
            // Set button texts from options
            if (confirmBtn && options.confirmText) confirmBtn.textContent = options.confirmText;
            if (cancelBtn && options.cancelText) cancelBtn.textContent = options.cancelText;
            
            // Set button colors from options
            if (confirmBtn && options.confirmClass) {
                confirmBtn.className = options.confirmClass;
            }
            
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Focus on cancel button by default (safer)
            if (cancelBtn) {
                setTimeout(() => cancelBtn.focus(), 100);
            }
        });
    },
    
    /**
     * Create modal element
     * @returns {HTMLElement} Modal element
     */
    createModal: function() {
        const modal = document.createElement('div');
        modal.id = 'globalConfirmationModal';
        modal.className = 'fixed inset-0 z-[9999] hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="relative bg-white w-full max-w-md rounded-2xl p-6 animate-slide-up shadow-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-black text-slate-900" id="confirmationModalTitle">Onay</h3>
                    <button onclick="ConfirmationModal.cancel()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-slate-700 font-medium mb-6" id="confirmationModalMessage">Bu işlemi yapmak istediğinize emin misiniz?</p>
                <div class="flex gap-3 justify-end">
                    <button 
                        id="confirmationCancelBtn"
                        onclick="ConfirmationModal.cancel()" 
                        class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-all">
                        İptal
                    </button>
                    <button 
                        id="confirmationConfirmBtn"
                        onclick="ConfirmationModal.confirm()" 
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg font-bold hover:bg-orange-600 transition-all">
                        Onayla
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.cancel();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                this.cancel();
            }
        });
        
        return modal;
    },
    
    /**
     * Confirm action
     */
    confirm: function() {
        this.hide();
        if (this.currentResolve) {
            this.currentResolve(true);
            this.currentResolve = null;
            this.currentReject = null;
        }
    },
    
    /**
     * Cancel action
     */
    cancel: function() {
        this.hide();
        if (this.currentResolve) {
            this.currentResolve(false);
            this.currentResolve = null;
            this.currentReject = null;
        }
    },
    
    /**
     * Hide modal
     */
    hide: function() {
        const modal = document.getElementById('globalConfirmationModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    /**
     * Helper methods for common confirmation patterns
     */
    confirmDelete: function(itemName = 'öğeyi') {
        return this.show(
            `${itemName} silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`,
            'Silme Onayı',
            {
                confirmText: 'Sil',
                cancelText: 'İptal',
                confirmClass: 'px-4 py-2 bg-red-500 text-white rounded-lg font-bold hover:bg-red-600 transition-all'
            }
        );
    },
    
    confirmPayment: function(amount) {
        return this.show(
            `${amount} tutarında ödeme almak istediğinize emin misiniz?`,
            'Ödeme Onayı',
            {
                confirmText: 'Ödeme Al',
                cancelText: 'İptal'
            }
        );
    },
    
    confirmTableTransfer: function(fromTable, toTable) {
        return this.show(
            `${fromTable} masasındaki siparişleri ${toTable} masasına taşımak istediğinize emin misiniz?`,
            'Masa Taşıma Onayı',
            {
                confirmText: 'Taşı',
                cancelText: 'İptal'
            }
        );
    },
    
    confirmTableMerge: function(tables) {
        return this.show(
            `${tables.join(', ')} masalarını birleştirmek istediğinize emin misiniz?`,
            'Masa Birleştirme Onayı',
            {
                confirmText: 'Birleştir',
                cancelText: 'İptal'
            }
        );
    },
    
    confirmTableSplit: function(tableName) {
        return this.show(
            `${tableName} masasını bölmek istediğinize emin misiniz?`,
            'Masa Bölme Onayı',
            {
                confirmText: 'Böl',
                cancelText: 'İptal'
            }
        );
    },
    
    confirmOrderCancel: function(orderId) {
        return this.show(
            `#${orderId} nolu siparişi iptal etmek istediğinize emin misiniz?`,
            'Sipariş İptal Onayı',
            {
                confirmText: 'İptal Et',
                cancelText: 'Vazgeç',
                confirmClass: 'px-4 py-2 bg-red-500 text-white rounded-lg font-bold hover:bg-red-600 transition-all'
            }
        );
    }
};

// Export to global scope
window.ConfirmationModal = ConfirmationModal;
