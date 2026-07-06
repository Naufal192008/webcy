// ==================== ADMIN.JS - FULL CRUD + SECURITY ====================

// ==================== AUTH CHECK ====================
auth.onAuthStateChanged(async (user) => {
    if (!user) {
        window.location.href = 'login.html';
        return;
    }
    
    try {
        const userDoc = await db.collection('users').doc(user.uid).get();
        
        if (!userDoc.exists || userDoc.data().role !== 'admin') {
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak!',
                text: 'Anda bukan admin. Mengalihkan ke dashboard...',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                auth.signOut();
                window.location.href = 'dashboard.html';
            });
            return;
        }
        
        console.log('✅ Admin authenticated:', user.email);
        initAdmin();
        
    } catch (error) {
        console.error('Auth error:', error);
        // Jika Firestore error, tetap izinkan akses (offline mode)
        console.warn('⚠️ Firestore not available, using offline mode');
        initAdmin();
    }
});

// ==================== INIT ====================
function initAdmin() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    loadDashboardStats();
    loadProducts();
    loadOrders();
    loadUsers();
    loadAds();
    setupNavigation();
}

// ==================== DATE TIME ====================
function updateDateTime() {
    const now = new Date();
    const formatted = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    const el = document.getElementById('currentDateTime');
    if (el) el.textContent = formatted;
}

// ==================== NAVIGATION ====================
function setupNavigation() {
    document.querySelectorAll('.sidebar .nav-link[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active link
            document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // Show section
            const section = this.dataset.section;
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            
            const sectionMap = {
                'dashboard': 'dashboardSection',
                'products': 'productsSection',
                'orders': 'ordersSection',
                'users': 'usersSection',
                'ads': 'adsSection',
                'security': 'securitySection'
            };
            
            const targetSection = document.getElementById(sectionMap[section]);
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Reload data
            if (section === 'products') loadProducts();
            if (section === 'orders') loadOrders();
            if (section === 'users') loadUsers();
            if (section === 'ads') loadAds();
        });
    });
}

// ==================== DASHBOARD STATS ====================
async function loadDashboardStats() {
    try {
        const [productsSnap, ordersSnap, usersSnap] = await Promise.all([
            db.collection('products').get(),
            db.collection('orders').get(),
            db.collection('users').get()
        ]);
        
        let totalRevenue = 0;
        ordersSnap.forEach(doc => {
            const order = doc.data();
            if (order.status === 'paid' || order.status === 'completed') {
                totalRevenue += order.totalPrice || 0;
            }
        });
        
        document.getElementById('statProducts').textContent = productsSnap.size;
        document.getElementById('statOrders').textContent = ordersSnap.size;
        document.getElementById('statRevenue').textContent = 'Rp ' + totalRevenue.toLocaleString('id-ID');
        document.getElementById('statUsers').textContent = usersSnap.size;
        
    } catch (error) {
        console.error('Error loading stats:', error);
        // Set to 0 if error
        document.getElementById('statProducts').textContent = '0';
        document.getElementById('statOrders').textContent = '0';
        document.getElementById('statRevenue').textContent = 'Rp 0';
        document.getElementById('statUsers').textContent = '0';
    }
}

// ==================== PRODUCTS CRUD ====================
async function loadProducts() {
    const tbody = document.getElementById('productsTable');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>';
    
    try {
        const snapshot = await db.collection('products').orderBy('createdAt', 'desc').get();
        
        if (snapshot.empty) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-box-open fa-2x"></i><p class="mt-2">Belum ada produk. Klik "Tambah Produk" untuk menambahkan.</p></td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        snapshot.forEach(doc => {
            const p = doc.data();
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(p.name || '-')}</strong></td>
                <td><span class="badge bg-info">${p.category || '-'}</span></td>
                <td>Rp ${(p.price || 0).toLocaleString('id-ID')}</td>
                <td>${p.discount ? '<span class="badge bg-danger">-' + p.discount + '%</span>' : '<span class="text-muted">0%</span>'}</td>
                <td>${'★'.repeat(Math.floor(p.rating || 0))}${'☆'.repeat(5 - Math.floor(p.rating || 0))} <small>(${p.totalRatings || 0})</small></td>
                <td>
                    <button class="btn btn-warning btn-action me-1" onclick="editProduct('${doc.id}')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-action" onclick="deleteProduct('${doc.id}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading products:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Gagal memuat produk. Pastikan database sudah dibuat.</td></tr>';
    }
}

function showProductForm() {
    document.getElementById('productFormCard').style.display = 'block';
    document.getElementById('productFormTitle').textContent = 'Tambah Produk Baru';
    document.getElementById('productForm').reset();
    document.getElementById('editProductId').value = '';
    document.getElementById('productFormCard').scrollIntoView({ behavior: 'smooth' });
}

function hideProductForm() {
    document.getElementById('productFormCard').style.display = 'none';
    document.getElementById('productForm').reset();
    document.getElementById('editProductId').value = '';
}

async function editProduct(productId) {
    try {
        const doc = await db.collection('products').doc(productId).get();
        if (!doc.exists) {
            Swal.fire('Error', 'Produk tidak ditemukan', 'error');
            return;
        }
        
        const p = doc.data();
        document.getElementById('editProductId').value = productId;
        document.getElementById('prodName').value = p.name || '';
        document.getElementById('prodCategory').value = p.category || 'website';
        document.getElementById('prodPrice').value = p.price || 0;
        document.getElementById('prodDiscount').value = p.discount || 0;
        document.getElementById('prodStock').value = p.stock || 999;
        document.getElementById('prodDescription').value = p.description || '';
        document.getElementById('prodImages').value = (p.images || []).join(', ');
        
        document.getElementById('productFormCard').style.display = 'block';
        document.getElementById('productFormTitle').textContent = 'Edit Produk';
        document.getElementById('productFormCard').scrollIntoView({ behavior: 'smooth' });
        
    } catch (error) {
        console.error('Error editing product:', error);
        Swal.fire('Error', 'Gagal memuat data produk', 'error');
    }
}

async function deleteProduct(productId) {
    const result = await Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: 'Data produk akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#e74a3b'
    });
    
    if (result.isConfirmed) {
        try {
            await db.collection('products').doc(productId).delete();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Produk telah dihapus',
                timer: 1500,
                showConfirmButton: false
            });
            loadProducts();
            loadDashboardStats();
        } catch (error) {
            console.error('Error deleting product:', error);
            Swal.fire('Error', 'Gagal menghapus produk', 'error');
        }
    }
}

// Product Form Submit
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const productData = {
        name: escapeHtml(document.getElementById('prodName').value.trim()),
        category: document.getElementById('prodCategory').value,
        price: parseInt(document.getElementById('prodPrice').value) || 0,
        discount: parseInt(document.getElementById('prodDiscount').value) || 0,
        stock: parseInt(document.getElementById('prodStock').value) || 999,
        description: document.getElementById('prodDescription').value.trim(),
        images: document.getElementById('prodImages').value.split(',').map(s => s.trim()).filter(s => s !== ''),
        updatedAt: firebase.firestore.FieldValue.serverTimestamp()
    };
    
    // Validation
    if (!productData.name) {
        Swal.fire('Error', 'Nama produk harus diisi', 'error');
        return;
    }
    if (productData.price < 0) {
        Swal.fire('Error', 'Harga tidak valid', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Menyimpan...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    try {
        const editId = document.getElementById('editProductId').value;
        
        if (editId) {
            // Update existing product
            await db.collection('products').doc(editId).update(productData);
        } else {
            // Add new product
            productData.createdAt = firebase.firestore.FieldValue.serverTimestamp();
            productData.rating = 0;
            productData.totalRatings = 0;
            productData.sold = 0;
            await db.collection('products').add(productData);
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: editId ? 'Produk telah diperbarui' : 'Produk baru telah ditambahkan',
            timer: 2000,
            showConfirmButton: false
        });
        
        hideProductForm();
        loadProducts();
        loadDashboardStats();
        
    } catch (error) {
        console.error('Error saving product:', error);
        Swal.fire('Error', 'Gagal menyimpan produk: ' + error.message, 'error');
    }
});

// ==================== ORDERS ====================
async function loadOrders() {
    const tbody = document.getElementById('ordersTable');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>';
    
    try {
        const snapshot = await db.collection('orders').orderBy('createdAt', 'desc').get();
        
        if (snapshot.empty) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada pesanan</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        snapshot.forEach(doc => {
            const order = doc.data();
            const row = document.createElement('tr');
            
            const statusColors = {
                'pending': 'warning',
                'paid': 'info',
                'processing': 'primary',
                'completed': 'success',
                'cancelled': 'danger'
            };
            
            const date = order.createdAt ? new Date(order.createdAt.seconds * 1000) : new Date();
            
            row.innerHTML = `
                <td><code>#${doc.id.substring(0, 8)}</code></td>
                <td>${escapeHtml(order.userName || '-')}<br><small class="text-muted">${order.userEmail || ''}</small></td>
                <td><strong>Rp ${(order.totalPrice || 0).toLocaleString('id-ID')}</strong></td>
                <td><span class="badge bg-secondary">${(order.paymentMethod || '-').toUpperCase()}</span></td>
                <td>
                    <select class="form-select form-select-sm" onchange="updateOrderStatus('${doc.id}', this.value)" style="width:130px;">
                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                        <option value="paid" ${order.status === 'paid' ? 'selected' : ''}>💰 Paid</option>
                        <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>🔄 Processing</option>
                        <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>✅ Completed</option>
                        <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>❌ Cancelled</option>
                    </select>
                </td>
                <td><small>${date.toLocaleDateString('id-ID')}<br>${date.toLocaleTimeString('id-ID')}</small></td>
                <td>
                    <button class="btn btn-sm btn-info me-1" onclick="viewOrderDetail('${doc.id}')" title="Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteOrder('${doc.id}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading orders:', error);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Gagal memuat pesanan</td></tr>';
    }
}

async function updateOrderStatus(orderId, newStatus) {
    try {
        await db.collection('orders').doc(orderId).update({
            status: newStatus,
            updatedAt: firebase.firestore.FieldValue.serverTimestamp()
        });
        
        Swal.fire({
            icon: 'success',
            title: 'Status Diperbarui',
            timer: 1500,
            showConfirmButton: false
        });
        
        loadDashboardStats();
    } catch (error) {
        console.error('Error updating order:', error);
        Swal.fire('Error', 'Gagal update status', 'error');
    }
}

async function viewOrderDetail(orderId) {
    try {
        const doc = await db.collection('orders').doc(orderId).get();
        if (!doc.exists) {
            Swal.fire('Error', 'Pesanan tidak ditemukan', 'error');
            return;
        }
        
        const order = doc.data();
        Swal.fire({
            title: 'Detail Pesanan #' + orderId.substring(0, 8),
            html: `
                <table class="table table-sm text-start">
                    <tr><td><strong>Customer</strong></td><td>${order.userName || '-'}</td></tr>
                    <tr><td><strong>Email</strong></td><td>${order.userEmail || '-'}</td></tr>
                    <tr><td><strong>Produk</strong></td><td>${order.productName || '-'}</td></tr>
                    <tr><td><strong>Total</strong></td><td>Rp ${(order.totalPrice || 0).toLocaleString('id-ID')}</td></tr>
                    <tr><td><strong>Pembayaran</strong></td><td>${order.paymentMethod?.toUpperCase() || '-'}</td></tr>
                    <tr><td><strong>No. Tujuan</strong></td><td>${order.paymentNumber || '085710785244'}</td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="badge bg-info">${order.status}</span></td></tr>
                    <tr><td><strong>Tanggal</strong></td><td>${order.createdAt ? new Date(order.createdAt.seconds * 1000).toLocaleString('id-ID') : '-'}</td></tr>
                </table>
            `,
            width: '600px',
            confirmButtonText: 'Tutup'
        });
    } catch (error) {
        console.error('Error viewing order:', error);
        Swal.fire('Error', 'Gagal memuat detail', 'error');
    }
}

async function deleteOrder(orderId) {
    const result = await Swal.fire({
        title: 'Hapus pesanan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        confirmButtonColor: '#e74a3b'
    });
    
    if (result.isConfirmed) {
        try {
            await db.collection('orders').doc(orderId).delete();
            Swal.fire('Berhasil!', 'Pesanan dihapus', 'success');
            loadOrders();
            loadDashboardStats();
        } catch (error) {
            Swal.fire('Error', 'Gagal menghapus', 'error');
        }
    }
}

// ==================== USERS ====================
async function loadUsers() {
    const tbody = document.getElementById('usersTable');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>';
    
    try {
        const snapshot = await db.collection('users').orderBy('createdAt', 'desc').get();
        
        if (snapshot.empty) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada users</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        snapshot.forEach(doc => {
            const u = doc.data();
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(u.fullName || '-')}</strong></td>
                <td>${escapeHtml(u.email || '-')}</td>
                <td>${u.phone || '-'}</td>
                <td>
                    <span class="badge ${u.role === 'admin' ? 'bg-danger' : 'bg-info'}">${u.role || 'user'}</span>
                </td>
                <td>
                    <span class="badge ${u.isActive !== false ? 'bg-success' : 'bg-secondary'}">${u.isActive !== false ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-warning me-1" onclick="toggleUserRole('${doc.id}', '${u.role || 'user'}')" title="Toggle Role">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser('${doc.id}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading users:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Gagal memuat users</td></tr>';
    }
}

async function toggleUserRole(uid, currentRole) {
    const newRole = currentRole === 'admin' ? 'user' : 'admin';
    
    const result = await Swal.fire({
        title: 'Ubah Role?',
        text: `Ubah role menjadi "${newRole}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, ubah'
    });
    
    if (result.isConfirmed) {
        try {
            await db.collection('users').doc(uid).update({ role: newRole });
            Swal.fire('Berhasil!', `Role diubah menjadi ${newRole}`, 'success');
            loadUsers();
        } catch (error) {
            Swal.fire('Error', 'Gagal mengubah role', 'error');
        }
    }
}

async function deleteUser(uid) {
    const result = await Swal.fire({
        title: 'Hapus user?',
        text: 'Data user akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        confirmButtonColor: '#e74a3b'
    });
    
    if (result.isConfirmed) {
        try {
            await db.collection('users').doc(uid).delete();
            Swal.fire('Berhasil!', 'User dihapus', 'success');
            loadUsers();
            loadDashboardStats();
        } catch (error) {
            Swal.fire('Error', 'Gagal menghapus user', 'error');
        }
    }
}

// ==================== ADS ====================
async function loadAds() {
    try {
        const snapshot = await db.collection('ads').limit(1).get();
        if (!snapshot.empty) {
            const ad = snapshot.docs[0].data();
            document.getElementById('adText').value = ad.text || '';
            document.getElementById('adLink').value = ad.link || '';
            document.getElementById('adActive').checked = ad.active || false;
        }
    } catch (error) {
        console.error('Error loading ads:', error);
    }
}

document.getElementById('adForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const adData = {
        text: escapeHtml(document.getElementById('adText').value.trim()),
        link: document.getElementById('adLink').value.trim(),
        active: document.getElementById('adActive').checked,
        updatedAt: firebase.firestore.FieldValue.serverTimestamp()
    };
    
    try {
        // Deactivate all existing ads
        const snapshot = await db.collection('ads').get();
        snapshot.forEach(async (doc) => {
            await db.collection('ads').doc(doc.id).update({ active: false });
        });
        
        // Add new ad
        await db.collection('ads').add({
            ...adData,
            createdAt: firebase.firestore.FieldValue.serverTimestamp()
        });
        
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Iklan telah disimpan',
            timer: 2000,
            showConfirmButton: false
        });
        
    } catch (error) {
        console.error('Error saving ad:', error);
        Swal.fire('Error', 'Gagal menyimpan iklan', 'error');
    }
});

// ==================== HELPER FUNCTIONS ====================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

function logout() {
    Swal.fire({
        title: 'Yakin ingin logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            auth.signOut().then(() => {
                window.location.href = 'login.html';
            });
        }
    });
}

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(e) {
    // Ctrl+D = Dashboard
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        document.querySelector('[data-section="dashboard"]').click();
    }
    // Ctrl+P = Products
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        document.querySelector('[data-section="products"]').click();
    }
    // Ctrl+O = Orders
    if (e.ctrlKey && e.key === 'o') {
        e.preventDefault();
        document.querySelector('[data-section="orders"]').click();
    }
});

console.log('🛡️ Admin Panel Ready');
console.log('📋 Shortcuts: Ctrl+D Dashboard | Ctrl+P Products | Ctrl+O Orders');