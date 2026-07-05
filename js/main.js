// ==================== MAIN.JS - FULL FUNCTIONS ====================

let allProducts = [];
let currentUser = null;
let userCart = [];

// Check auth state
auth.onAuthStateChanged(async (user) => {
    currentUser = user;
    
    if (user) {
        document.getElementById('loginBtn')?.style.setProperty('display', 'none');
        document.getElementById('dashboardBtn')?.style.setProperty('display', 'block');
        document.getElementById('cartNav')?.style.setProperty('display', 'block');
        loadUserCart();
        updateCartBadge();
    } else {
        document.getElementById('loginBtn')?.style.setProperty('display', 'block');
        document.getElementById('dashboardBtn')?.style.setProperty('display', 'none');
        document.getElementById('cartNav')?.style.setProperty('display', 'none');
    }
    
    loadProducts();
    loadAds();
});

// ==================== LOAD PRODUCTS ====================
async function loadProducts() {
    try {
        const snapshot = await db.collection('products').orderBy('createdAt', 'desc').get();
        allProducts = [];
        snapshot.forEach((doc) => {
            allProducts.push({ id: doc.id, ...doc.data() });
        });
        displayProducts(allProducts);
    } catch (error) {
        console.error("Error loading products:", error);
        document.getElementById('productsContainer').innerHTML = 
            '<div class="col-12 text-center py-5"><p class="text-danger">Gagal memuat produk. Silakan refresh halaman.</p></div>';
    }
}

// ==================== DISPLAY PRODUCTS ====================
function displayProducts(products) {
    const container = document.getElementById('productsContainer');
    if (!container) return;

    if (products.length === 0) {
        container.innerHTML = '<div class="col-12 text-center py-5"><p class="text-muted">Belum ada produk tersedia.</p></div>';
        return;
    }

    container.innerHTML = products.map(product => {
        const discountPrice = product.discount ? 
            product.price - (product.price * product.discount / 100) : product.price;
        
        const imagesHtml = product.images && product.images.length > 0 ?
            product.images.map((img, index) => `
                <div class="carousel-item ${index === 0 ? 'active' : ''}">
                    <img src="${img}" class="d-block w-100" alt="${product.name}" style="height:220px;object-fit:cover;">
                </div>
            `).join('') :
            `<div class="carousel-item active">
                <img src="https://via.placeholder.com/400x220?text=No+Image" class="d-block w-100" alt="No Image" style="height:220px;object-fit:cover;">
            </div>`;

        const carouselId = `carousel-${product.id.replace(/[^a-zA-Z0-9]/g, '')}`;
        
        const carouselControls = product.images && product.images.length > 1 ? `
            <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        ` : '';

        return `
            <div class="col-md-4 col-lg-3">
                <div class="card product-card h-100">
                    <div class="product-img-container">
                        <div id="${carouselId}" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">${imagesHtml}</div>
                            ${carouselControls}
                        </div>
                        ${product.discount ? `<span class="discount-badge">-${product.discount}%</span>` : ''}
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">${product.name}</h5>
                        <div class="stars mb-2">${generateStars(product.rating || 0)} <small class="text-muted">(${product.totalRatings || 0})</small></div>
                        <p class="card-text text-muted small flex-grow-1">${truncateText(product.description || '', 60)}</p>
                        <div class="mt-auto">
                            ${product.discount ? `<span class="text-decoration-line-through text-muted small">Rp ${formatRupiah(product.price)}</span><br>` : ''}
                            <span class="fw-bold text-primary fs-5">Rp ${formatRupiah(discountPrice)}</span>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-grid gap-2">
                            <a href="product-detail.html?id=${product.id}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-info-circle"></i> Detail
                            </a>
                            <button class="btn btn-primary btn-sm" onclick="addToCart('${product.id}')">
                                <i class="fas fa-cart-plus"></i> Keranjang
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ==================== ADD TO CART ====================
async function addToCart(productId) {
    if (!currentUser) {
        Swal.fire({
            icon: 'warning',
            title: 'Login Diperlukan',
            text: 'Silakan login terlebih dahulu',
            showCancelButton: true,
            confirmButtonText: 'Login',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = 'login.html';
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

        await cartRef.set({ items: cartItems, updatedAt: firebase.firestore.FieldValue.serverTimestamp() });
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

// ==================== UPDATE CART BADGE ====================
async function updateCartBadge() {
    const badge = document.getElementById('cartBadge');
    if (!badge || !currentUser) return;

    try {
        const cartDoc = await db.collection('carts').doc(currentUser.uid).get();
        const itemCount = cartDoc.exists ? 
            cartDoc.data().items.reduce((sum, item) => sum + item.quantity, 0) : 0;
        badge.textContent = itemCount;
        badge.style.display = itemCount > 0 ? 'inline' : 'none';
    } catch (error) {
        console.error("Error updating badge:", error);
    }
}

// ==================== LOAD USER CART ====================
async function loadUserCart() {
    if (!currentUser) return;
    try {
        const cartDoc = await db.collection('carts').doc(currentUser.uid).get();
        userCart = cartDoc.exists ? cartDoc.data().items || [] : [];
    } catch (error) {
        console.error("Error loading cart:", error);
    }
}

// ==================== LOAD ADS ====================
async function loadAds() {
    try {
        const snapshot = await db.collection('ads').where('active', '==', true).limit(1).get();
        const adBanner = document.getElementById('adBanner');
        const adText = document.getElementById('adText');
        if (!adBanner || !adText || snapshot.empty) return;
        
        const ad = snapshot.docs[0].data();
        adBanner.classList.remove('d-none');
        adText.innerHTML = ad.link ? 
            `<a href="${ad.link}" target="_blank" class="text-dark fw-bold">📢 ${ad.text}</a>` : 
            `📢 ${ad.text}`;
    } catch (error) {
        console.error("Error loading ads:", error);
    }
}

// ==================== CHAT SYSTEM ====================
document.getElementById('chatToggle')?.addEventListener('click', () => {
    const chatBox = document.getElementById('chatBox');
    chatBox.style.display = chatBox.style.display === 'none' ? 'block' : 'none';
});

document.getElementById('sendChat')?.addEventListener('click', sendChatMessage);
document.getElementById('chatInput')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendChatMessage();
});

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;

    const messagesContainer = document.getElementById('chatMessages');
    
    // Display user message
    messagesContainer.innerHTML += `
        <div class="text-end mb-2">
            <small class="text-muted">Anda</small>
            <div class="bg-primary text-white d-inline-block p-2 rounded-3">${message}</div>
        </div>
    `;

    // Save to Firestore
    if (currentUser) {
        await db.collection('chats').add({
            userId: currentUser.uid,
            userName: currentUser.displayName || 'User',
            message: message,
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
    }

    input.value = '';
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// ==================== SEARCH & FILTER ====================
document.getElementById('searchInput')?.addEventListener('input', filterProducts);
document.getElementById('categoryFilter')?.addEventListener('change', filterProducts);
document.getElementById('sortFilter')?.addEventListener('change', filterProducts);

function filterProducts() {
    let filtered = [...allProducts];
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const category = document.getElementById('categoryFilter')?.value || 'all';
    const sort = document.getElementById('sortFilter')?.value || 'newest';

    if (searchTerm) {
        filtered = filtered.filter(p => 
            (p.name && p.name.toLowerCase().includes(searchTerm)) ||
            (p.description && p.description.toLowerCase().includes(searchTerm))
        );
    }

    if (category !== 'all') {
        filtered = filtered.filter(p => p.category === category);
    }

    switch(sort) {
        case 'price-low': filtered.sort((a, b) => a.price - b.price); break;
        case 'price-high': filtered.sort((a, b) => b.price - a.price); break;
        case 'rating': filtered.sort((a, b) => (b.rating || 0) - (a.rating || 0)); break;
        default: filtered.sort((a, b) => (b.createdAt?.seconds || 0) - (a.createdAt?.seconds || 0)); break;
    }

    displayProducts(filtered);
}

// ==================== HELPER FUNCTIONS ====================
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= Math.floor(rating)) stars += '<i class="fas fa-star"></i>';
        else if (i - 0.5 <= rating) stars += '<i class="fas fa-star-half-alt"></i>';
        else stars += '<i class="far fa-star"></i>';
    }
    return stars;
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

function truncateText(text, length) {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadAds();
});