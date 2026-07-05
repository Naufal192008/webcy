// ==================== CART MANAGER ====================

class CartManager {
    constructor() {
        this.userId = auth.currentUser?.uid;
        this.cartRef = this.userId ? db.collection('carts').doc(this.userId) : null;
    }
    
    async getCart() {
        if (!this.cartRef) return [];
        const doc = await this.cartRef.get();
        return doc.exists ? doc.data().items || [] : [];
    }
    
    async addItem(product) {
        if (!this.cartRef) throw new Error('User not logged in');
        
        const items = await this.getCart();
        const existingIndex = items.findIndex(item => item.productId === product.id);
        
        if (existingIndex > -1) {
            items[existingIndex].quantity += product.quantity || 1;
        } else {
            items.push({
                productId: product.id,
                name: product.name,
                price: product.price,
                discount: product.discount || 0,
                image: product.image || '',
                quantity: product.quantity || 1,
                addedAt: new Date().toISOString()
            });
        }
        
        await this.cartRef.set({ items, updatedAt: firebase.firestore.FieldValue.serverTimestamp() });
        return items;
    }
    
    async removeItem(productId) {
        if (!this.cartRef) return;
        
        const items = await this.getCart();
        const filteredItems = items.filter(item => item.productId !== productId);
        
        if (filteredItems.length === 0) {
            await this.cartRef.delete();
        } else {
            await this.cartRef.set({ items: filteredItems });
        }
        
        return filteredItems;
    }
    
    async updateQuantity(productId, quantity) {
        if (!this.cartRef || quantity < 1) return;
        
        const items = await this.getCart();
        const item = items.find(i => i.productId === productId);
        
        if (item) {
            item.quantity = quantity;
            await this.cartRef.set({ items });
        }
        
        return items;
    }
    
    async clearCart() {
        if (this.cartRef) {
            await this.cartRef.delete();
        }
    }
    
    async getTotal() {
        const items = await this.getCart();
        return items.reduce((total, item) => {
            const price = item.price - (item.price * item.discount / 100);
            return total + (price * item.quantity);
        }, 0);
    }
    
    async getItemCount() {
        const items = await this.getCart();
        return items.reduce((sum, item) => sum + item.quantity, 0);
    }
    
    async applyPromoCode(code) {
        // Dummy promo codes
        const promos = {
            'WEBPRO10': 10, // 10% off
            'UMKM2024': 15, // 15% off
            'NEWUSER': 20,  // 20% off
        };
        
        if (promos[code]) {
            const total = await this.getTotal();
            const discount = total * promos[code] / 100;
            return {
                valid: true,
                discount: discount,
                finalTotal: total - discount,
                message: `Kode promo berhasil! Diskon ${promos[code]}%`
            };
        }
        
        return {
            valid: false,
            message: 'Kode promo tidak valid'
        };
    }
}

// Initialize cart manager
const cartManager = new CartManager();

// Auth state listener
auth.onAuthStateChanged((user) => {
    if (user) {
        cartManager.userId = user.uid;
        cartManager.cartRef = db.collection('carts').doc(user.uid);
        updateCartUI();
    }
});

// Update cart UI
async function updateCartUI() {
    const itemCount = await cartManager.getItemCount();
    const badge = document.getElementById('cartBadge');
    
    if (badge) {
        badge.textContent = itemCount;
        badge.style.display = itemCount > 0 ? 'inline' : 'none';
    }
}