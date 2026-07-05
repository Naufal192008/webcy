// ==================== LOAD SERVICES & PRODUCTS ====================
let allProducts = [];
let currentUser = null;
let userCart = [];

// Check auth state
auth.onAuthStateChanged((user) => {
    currentUser = user;
    loadProducts();
    loadAds();
    loadTestimonials();
    
    if (user) {
        loadUserCart();
        updateCartBadge();
    }
});

// Load Products from Firestore
async function loadProducts() {
    try {
        const snapshot = await db.collection('products')
            .orderBy('createdAt', 'desc')
            .get();
        
        allProducts = [];
        snapshot.forEach((doc) => {
            allProducts.push({ id: doc.id, ...doc.data() });
        });
        
        displayProducts(allProducts);
    } catch (error) {
        console.error("Error loading products:", error);
    }
}

// Display Products as Cards
function displayProducts(products) {
    const container = document.getElementById('portfolioList');
    if (!container) return;
    
    container.innerHTML = '';
    
    products.forEach(product => {
        const discountPrice = product.discount ? 
            product.price - (product.price * product.discount / 100) : product.price;
        
        const card = `
            <div class="col-md-4 col-lg-3">
                <div class="card product-card h-100 shadow-sm">
                    <div class="product-image-container">
                        <div id="carousel-${product.id}" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                ${product.images ? product.images.map((img, index) => `
                                    <div class="carousel-item ${index === 0 ? 'active' : ''}">
                                        <img src="${img}" class="d-block w-100" alt="${product.name}">
                                    </div>
                                `).join('') : '<div class="carousel-item active"><img src="placeholder.jpg" class="d-block w-100"></div>'}
                            </div>
                            ${product.images && product.images.length > 1 ? `
                                <button class="carousel-control-prev" type="button" data-bs-target="#carousel-${product.id}" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#carousel-${product.id}" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            ` : ''}
                        </div>
                        ${product.discount ? `<span class="badge bg-danger discount-badge">-${product.discount}%</span>` : ''}
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">${product.name}</h5>
                        <div class="rating mb-2">
                            ${generateStars(product.rating || 0)}
                            <small class="text-muted">(${product.totalRatings || 0})</small>
                        </div>
                        <p class="card-text text-muted small">${product.description ? product.description.substring(0, 80) + '...' : ''}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                ${product.discount ? `
                                    <span class="text-decoration-line-through text-muted">Rp ${formatRupiah(product.price)}</span><br>
                                ` : ''}
                                <span class="fw-bold text-primary">Rp ${formatRupiah(discountPrice)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-grid gap-2">
                            <a href="product-detail.html?id=${product.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-info-circle"></i> Detail
                            </a>
                            <button class="btn btn-primary btn-sm" onclick="addToCart('${product.id}')">
                                <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += card;
    });
}

// Add to Cart
async function addToCart(productId) {
    if (!currentUser) {
        Swal.fire({
            icon: 'warning',
            title: 'Login Diperlukan',
            text: 'Silakan login terlebih dahulu untuk menambahkan ke keranjang',
            showCancelButton: true,
            confirmButtonText: 'Login',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.html';
            }
        });
        return;
    }
    
    const product = allProducts.find(p => p.id === productId);
    if (!product) return;
    
    try {
        const cartRef = db.collection('carts').doc(currentUser.uid);
        const cartDoc = await cartRef.get();
        
        let cartItems = cartDoc.exists ? cartDoc.data().items || [] : [];
        const existingItem = cartItems.find(item => item.productId === productId);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cartItems.push({
                productId: productId,
                name: product.name,
                price: product.price,
                discount: product.discount || 0,
                image: product.images ? product.images[0] : '',
                quantity: 1
            });
        }
        
        await cartRef.set({ items: cartItems });
        updateCartBadge();
        
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Produk ditambahkan ke keranjang',
            showConfirmButton: false,
            timer: 1500
        });
    } catch (error) {
        console.error("Error adding to cart:", error);
        Swal.fire('Error', 'Gagal menambahkan ke keranjang', 'error');
    }
}

// Update Cart Badge
async function updateCartBadge() {
    const badge = document.getElementById('cartBadge');
    if (!badge || !currentUser) return;
    
    try {
        const cartDoc = await db.collection('carts').doc(currentUser.uid).get();
        const itemCount = cartDoc.exists ? 
            cartDoc.data().items.reduce((sum, item) => sum + item.quantity, 0) : 0;
        
        badge.textContent = itemCount;
        badge.style.display = itemCount > 0 ? 'block' : 'none';
    } catch (error) {
        console.error("Error updating badge:", error);
    }
}

// Load Ads
async function loadAds() {
    try {
        const snapshot = await db.collection('ads')
            .where('active', '==', true)
            .limit(1)
            .get();
        
        const adBanner = document.getElementById('adBanner');
        const adText = document.getElementById('adText');
        
        if (!adBanner || !adText || snapshot.empty) return;
        
        const ad = snapshot.docs[0].data();
        adBanner.classList.remove('d-none');
        adText.innerHTML = `<a href="${ad.link}" target="_blank" class="text-dark">${ad.text}</a>`;
    } catch (error) {
        console.error("Error loading ads:", error);
    }
}

// Chat Functionality
const chatToggle = document.getElementById('chatToggle');
const chatBox = document.getElementById('chatBox');
const chatInput = document.getElementById('chatInput');
const sendChat = document.getElementById('sendChat');
const chatMessages = document.getElementById('chatMessages');

if (chatToggle) {
    chatToggle.addEventListener('click', () => {
        chatBox.style.display = chatBox.style.display === 'none' ? 'block' : 'none';
    });
}

if (sendChat) {
    sendChat.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
}

async function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    
    // Add message to UI
    chatMessages.innerHTML += `
        <div class="message user mb-2 text-end">
            <small class="text-muted">Anda</small>
            <p class="bg-light p-2 rounded d-inline-block">${message}</p>
        </div>
    `;
    
    // Save to Firestore
    if (currentUser) {
        await db.collection('chats').add({
            userId: currentUser.uid,
            userName: currentUser.displayName || 'User',
            message: message,
            timestamp: firebase.firestore.FieldValue.serverTimestamp(),
            isAdmin: false
        });
    }
    
    chatInput.value = '';
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Load Testimonials
async function loadTestimonials() {
    const container = document.getElementById('testimonialList');
    if (!container) return;
    
    try {
        const snapshot = await db.collection('testimonials')
            .orderBy('createdAt', 'desc')
            .limit(6)
            .get();
        
        container.innerHTML = '';
        snapshot.forEach((doc) => {
            const data = doc.data();
            container.innerHTML += `
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-2">${generateStars(data.rating)}</div>
                            <p class="card-text">"${data.comment}"</p>
                            <div class="d-flex align-items-center">
                                <img src="${data.avatar || 'default-avatar.png'}" class="rounded-circle me-2" width="40">
                                <div>
                                    <strong>${data.name}</strong><br>
                                    <small class="text-muted">${data.role}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    } catch (error) {
        console.error("Error loading testimonials:", error);
    }
}

// Search & Filter
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const sortFilter = document.getElementById('sortFilter');

if (searchInput) {
    searchInput.addEventListener('input', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);
    sortFilter.addEventListener('change', filterProducts);
}

function filterProducts() {
    let filtered = [...allProducts];
    const searchTerm = searchInput.value.toLowerCase();
    const category = categoryFilter.value;
    const sort = sortFilter.value;
    
    // Search filter
    if (searchTerm) {
        filtered = filtered.filter(p => 
            p.name.toLowerCase().includes(searchTerm) ||
            p.description.toLowerCase().includes(searchTerm)
        );
    }
    
    // Category filter
    if (category !== 'all') {
        filtered = filtered.filter(p => p.category === category);
    }
    
    // Sort
    switch(sort) {
        case 'price-low':
            filtered.sort((a, b) => a.price - b.price);
            break;
        case 'price-high':
            filtered.sort((a, b) => b.price - a.price);
            break;
        case 'rating':
            filtered.sort((a, b) => (b.rating || 0) - (a.rating || 0));
            break;
        default: // newest
            filtered.sort((a, b) => b.createdAt - a.createdAt);
    }
    
    displayProducts(filtered);
}

// Helper Functions
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else if (i - 0.5 <= rating) {
            stars += '<i class="fas fa-star-half-alt text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-warning"></i>';
        }
    }
    return stars;
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadAds();
    loadTestimonials();
});