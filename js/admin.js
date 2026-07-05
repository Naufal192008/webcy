// ==================== ADMIN FUNCTIONS ====================
let currentAdmin = null;

// Check admin auth
auth.onAuthStateChanged(async (user) => {
    if (!user) {
        window.location.href = 'login.html';
        return;
    }
    
    // Check if user is admin
    const userDoc = await db.collection('users').doc(user.uid).get();
    if (!userDoc.exists || userDoc.data().role !== 'admin') {
        alert('Akses ditolak! Anda bukan admin.');
        window.location.href = 'index.html';
        return;
    }
    
    currentAdmin = user;
    loadDashboardStats();
    loadProducts();
    loadOrders();
    loadUsers();
});

// Navigation
document.querySelectorAll('.nav-link[data-section]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const section = e.target.closest('.nav-link').dataset.section;
        showSection(section);
    });
});

function showSection(section) {
    document.querySelectorAll('.section-content').forEach(s => s.style.display = 'none');
    document.getElementById(`${section}-section`).style.display = 'block';
}

// Load Dashboard Stats
async function loadDashboardStats() {
    const productsSnap = await db.collection('products').get();
    const ordersSnap = await db.collection('orders').get();
    const usersSnap = await db.collection('users').get();
    
    let totalRevenue = 0;
    ordersSnap.forEach(doc => {
        if (doc.data().status === 'completed') {
            totalRevenue += doc.data().totalPrice;
        }
    });
    
    document.getElementById('totalProducts').textContent = productsSnap.size;
    document.getElementById('totalOrders').textContent = ordersSnap.size;
    document.getElementById('totalRevenue').textContent = 'Rp ' + formatRupiah(totalRevenue);
    document.getElementById('totalUsers').textContent = usersSnap.size;
}

// Load Products Table
async function loadProducts() {
    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;
    
    const snapshot = await db.collection('products').get();
    tbody.innerHTML = '';
    
    snapshot.forEach(doc => {
        const product = doc.data();
        tbody.innerHTML += `
            <tr>
                <td><img src="${product.images?.[0] || 'placeholder.jpg'}" width="50"></td>
                <td>${product.name}</td>
                <td>${product.category}</td>
                <td>Rp ${formatRupiah(product.price)}</td>
                <td>${product.stock || '∞'}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editProduct('${doc.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteProduct('${doc.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
}

// Add Product
document.getElementById('addProductForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const name = document.getElementById('productName').value;
    const category = document.getElementById('productCategory').value;
    const price = parseInt(document.getElementById('productPrice').value);
    const discount = parseInt(document.getElementById('productDiscount').value) || 0;
    const stock = parseInt(document.getElementById('productStock').value) || 999;
    const description = document.getElementById('productDescription').value;
    const imageFiles = document.getElementById('productImages').files;
    
    // Upload images
    const imageUrls = [];
    for (let i = 0; i < imageFiles.length; i++) {
        const file = imageFiles[i];
        const storageRef = storage.ref(`products/${Date.now()}_${file.name}`);
        await storageRef.put(file);
        const url = await storageRef.getDownloadURL();
        imageUrls.push(url);
    }
    
    // Save to Firestore
    await db.collection('products').add({
        name,
        category,
        price,
        discount,
        stock,
        description,
        images: imageUrls,
        rating: 0,
        totalRatings: 0,
        createdAt: firebase.firestore.FieldValue.serverTimestamp()
    });
    
    Swal.fire('Sukses!', 'Produk berhasil ditambahkan', 'success');
    hideProductForm();
    loadProducts();
});

// Delete Product
async function deleteProduct(productId) {
    const result = await Swal.fire({
        title: 'Yakin?',
        text: 'Produk akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus!'
    });
    
    if (result.isConfirmed) {
        await db.collection('products').doc(productId).delete();
        Swal.fire('Dihapus!', 'Produk berhasil dihapus', 'success');
        loadProducts();
    }
}

// Show/Hide Product Form
function showAddProductForm() {
    document.getElementById('productForm').style.display = 'block';
}

function hideProductForm() {
    document.getElementById('productForm').style.display = 'none';
    document.getElementById('addProductForm').reset();
}

// Load Orders
async function loadOrders() {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;
    
    const snapshot = await db.collection('orders').orderBy('createdAt', 'desc').get();
    tbody.innerHTML = '';
    
    snapshot.forEach(doc => {
        const order = doc.data();
        tbody.innerHTML += `
            <tr>
                <td>#${doc.id.substring(0, 8)}</td>
                <td>${order.userName}</td>
                <td>${order.productName}</td>
                <td>Rp ${formatRupiah(order.totalPrice)}</td>
                <td>
                    <span class="badge bg-${order.paymentMethod === 'dana' ? 'info' : 
                        order.paymentMethod === 'ovo' ? 'purple' : 
                        order.paymentMethod === 'gopay' ? 'success' : 'warning'}">
                        ${order.paymentMethod.toUpperCase()}
                    </span>
                </td>
                <td>
                    <select class="form-select form-select-sm" onchange="updateOrderStatus('${doc.id}', this.value)">
                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="paid" ${order.status === 'paid' ? 'selected' : ''}>Dibayar</option>
                        <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Diproses</option>
                        <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>Selesai</option>
                    </select>
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewOrderDetail('${doc.id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
}

// Update Order Status
async function updateOrderStatus(orderId, status) {
    await db.collection('orders').doc(orderId).update({ status });
    Swal.fire('Sukses!', 'Status pesanan diperbarui', 'success');
}

// Manage Ads
document.getElementById('adForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const adText = document.getElementById('adText').value;
    const adLink = document.getElementById('adLink').value;
    const active = document.getElementById('adActive').checked;
    
    // Deactivate all ads first
    const adsSnap = await db.collection('ads').get();
    adsSnap.forEach(async (doc) => {
        await db.collection('ads').doc(doc.id).update({ active: false });
    });
    
    // Add new ad
    await db.collection('ads').add({
        text: adText,
        link: adLink,
        active: active,
        createdAt: firebase.firestore.FieldValue.serverTimestamp()
    });
    
    Swal.fire('Sukses!', 'Iklan berhasil disimpan', 'success');
});

// Format currency
function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

// Logout
function logout() {
    auth.signOut().then(() => {
        window.location.href = 'index.html';
    });
}