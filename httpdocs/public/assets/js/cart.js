/**
 * Cart Management System
 * Handles cart operations using localStorage
 */

window.Cart = {
    STORAGE_KEY: 'qordy_cart',
    storage: null,

    /**
     * Initialize cart (check for localStorage availability)
     */
    init: function() {
        try {
            if (typeof Storage !== 'undefined') {
                this.storage = window.localStorage;
            }
        } catch (e) {
            console.warn('localStorage not available');
        }
    },

    /**
     * Get all cart items
     * @returns {Array}
     */
    getItems: function() {
        if (!this.storage) {
            this.init();
        }
        
        try {
            const items = this.storage.getItem(this.STORAGE_KEY);
            return items ? JSON.parse(items) : [];
        } catch (e) {
            console.error('Error reading cart:', e);
            return [];
        }
    },

    /**
     * Save cart items to storage
     */
    saveItems: function(items) {
        if (!this.storage) {
            this.init();
        }
        
        try {
            this.storage.setItem(this.STORAGE_KEY, JSON.stringify(items));
            this.dispatchUpdate();
        } catch (e) {
            console.error('Error saving cart:', e);
        }
    },

    /**
     * Add item to cart
     * @param {object} item - Menu item to add
     * @param {number} quantity - Quantity (default: 1)
     * @param {string} note - Special note
     * @param {Array} excludedIngredients - Excluded ingredients
     * @param {Array} selectedExtras - Selected extras
     */
    addItem: function(item, quantity = 1, note = '', excludedIngredients = [], selectedExtras = []) {
        const items = this.getItems();
        const cartItemId = this.generateId();
        
        const cartItem = {
            id: cartItemId,
            menuItemId: item.id,
            name: item.name,
            price: item.price,
            quantity: quantity,
            note: note,
            excludedIngredients: excludedIngredients,
            selectedExtras: selectedExtras,
            imageUrl: item.imageUrl || '',
            category: item.category || '',
        };

        items.push(cartItem);
        this.saveItems(items);
        return cartItemId;
    },

    /**
     * Remove item from cart
     * @param {string} cartItemId - Cart item ID to remove
     */
    removeItem: function(cartItemId) {
        const items = this.getItems().filter(item => item.id !== cartItemId);
        this.saveItems(items);
    },

    /**
     * Update item quantity
     * @param {string} cartItemId - Cart item ID
     * @param {number} delta - Quantity change (positive or negative)
     */
    updateQuantity: function(cartItemId, delta) {
        const items = this.getItems();
        const item = items.find(i => i.id === cartItemId);
        
        if (item) {
            item.quantity = Math.max(1, item.quantity + delta);
            if (item.quantity <= 0) {
                this.removeItem(cartItemId);
            } else {
                this.saveItems(items);
            }
        }
    },

    /**
     * Clear cart
     */
    clear: function() {
        this.saveItems([]);
    },

    /**
     * Get total price
     * @returns {number}
     */
    getTotal: function() {
        return this.getItems().reduce((total, item) => {
            const itemTotal = item.price * item.quantity;
            // Add extras price if any
            const extrasTotal = (item.selectedExtras || []).reduce((sum, extra) => sum + (extra.price || 0), 0);
            return total + itemTotal + (extrasTotal * item.quantity);
        }, 0);
    },

    /**
     * Get total quantity
     * @returns {number}
     */
    getTotalQuantity: function() {
        return this.getItems().reduce((total, item) => total + item.quantity, 0);
    },

    /**
     * Generate a unique ID
     * @returns {string}
     */
    generateId: function() {
        // Use Utils.generateId if available (more secure), otherwise fallback
        if (window.Utils && window.Utils.generateId) {
            return window.Utils.generateId(21);
        }
        // Fallback: timestamp + random (better than pure random)
        const timestamp = Date.now().toString(36);
        const random = Math.random().toString(36).substring(2);
        return (timestamp + random).substring(0, 21);
    },

    /**
     * Dispatch cart update event
     */
    dispatchUpdate: function() {
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('cart-update', {
                detail: {
                    items: this.getItems(),
                    total: this.getTotal(),
                    quantity: this.getTotalQuantity(),
                }
            }));
        }
    },

    /**
     * Get item count (total quantity)
     */
    getItemCount: function() {
        return this.getTotalQuantity();
    },
};

// Initialize cart on load
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', function() {
        Cart.init();
    });
}

