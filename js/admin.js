// ==================== ADMIN.JS - FULL FUNCTIONS ====================

auth.onAuthStateChanged(async (user) => {
    if (!user) { window.location.href = 'login.html'; return; }
    const userDoc = await db.collection('users').doc(user.uid).get();
    if (!userDoc.exists || userDoc.data().role !== 'admin') {
        alert('Akses ditolak!');
        window.location.href = 'dashboard.html';
        return;
    }
    loadDashboardStats();
    loadProducts();
    loadOrders();
    loadUsers();
    loadAds();
});

// Navigation
document.querySelectorAll('[data-section]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        const section = this.dataset.section;
        document.querySelectorAll('main > div').forEach(d => d.style.display = 'none');
        document.getElementById(section + 'Section').style.display = 'block';
        if (section === 'products') loadProducts();
        if (section === 'orders') loadOrders();
        if (section === 'users') loadUsers();
    });
});

// Dashboard Stats
async function loadDashboardStats() {
    const p = await db.collection('products').get();
    const o = await db.collection('orders').get();
    const u = await db.collection('users').get();
    let revenue = 0;
    o.forEach(d => { if (d.data().status === 'paid' || d.data().status === 'completed') revenue += d.data().totalPrice || 0; });
    document.getElementById('statProducts').textContent = p.size;
    document.getElementById('statOrders').textContent = o.size;
    document.getElementById('statRevenue').textContent = 'Rp ' + revenue.toLocaleString('id-ID');
    document.getElementById('statUsers').textContent = u.size;
}

// Products CRUD
async function loadProducts() {
    const snap = await db.collection('products').orderBy('createdAt', 'desc').get();
    const tbody = document.getElementById('productsTable');
    tbody.innerHTML = snap.docs.map(doc => {
        const p = doc.data();
        return `<tr><td>${p.name}</td><td>Rp ${(p.price||0).toLocaleString('id-ID')}</td><td>${p.discount||0}%</td><td><button class="btn btn-sm btn-warning" onclick="editProduct('${doc.id}')"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" onclick="deleteProduct('${doc.id}')"><i class="fas fa-trash"></i></button></td></tr>`;
    }).join('');
}

function showProductForm() { document.getElementById('productForm').style.display = 'block'; }
function hideProductForm() { document.getElementById('productForm').style.display = 'none'; document.getElementById('addProductForm').reset(); }

document.getElementById('addProductForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('prodName').value,
        category: document.getElementById('prodCategory').value,
        price: parseInt(document.getElementById('prodPrice').value),
        discount: parseInt(document.getElementById('prodDiscount').value) || 0,
        stock: parseInt(document.getElementById('prodStock').value) || 999,
        description: document.getElementById('prodDescription').value,
        images: document.getElementById('prodImages').value.split(',').map(s => s.trim()).filter(s => s),
        rating: 0,
        totalRatings: 0,
        updatedAt: firebase.firestore.FieldValue.serverTimestamp()
    };

    const editId = document.getElementById('editProductId').value;
    if (editId) {
        await db.collection('products').doc(editId).update(data);
    } else {
        data.createdAt = firebase.firestore.FieldValue.serverTimestamp();
        await db.collection('products').add(data);
    }
    Swal.fire('Sukses!', 'Produk disimpan', 'success');
    hideProductForm();
    loadProducts();
});

async function editProduct(id) {
    const doc = await db.collection('products').doc(id).get();
    const p = doc.data();
    document.getElementById('editProductId').value = id;
    document.getElementById('prodName').value = p.name;
    document.getElementById('prodCategory').value = p.category;
    document.getElementById('prodPrice').value = p.price;
    document.getElementById('prodDiscount').value = p.discount || 0;
    document.getElementById('prodStock').value = p.stock || 999;
    document.getElementById('prodDescription').value = p.description || '';
    document.getElementById('prodImages').value = (p.images || []).join(',');
    showProductForm();
}

async function deleteProduct(id) {
    if (await Swal.fire({ title: 'Yakin?', text: 'Data akan dihapus!', icon: 'warning', showCancelButton: true }).then(r => r.isConfirmed)) {
        await db.collection('products').doc(id).delete();
        Swal.fire('Dihapus!', '', 'success');
        loadProducts();
    }
}

// Orders
async function loadOrders() {
    const snap = await db.collection('orders').orderBy('createdAt', 'desc').get();
    document.getElementById('ordersTable').innerHTML = snap.docs.map(doc => {
        const o = doc.data();
        return `<tr><td>#${doc.id.substring(0,8)}</td><td>${o.userName||'-'}</td><td>Rp ${(o.totalPrice||0).toLocaleString('id-ID')}</td><td>${o.paymentMethod||'-'}</td><td><select class="form-select form-select-sm" onchange="updateOrderStatus('${doc.id}', this.value)"><option value="pending" ${o.status==='pending'?'selected':''}>Pending</option><option value="paid" ${o.status==='paid'?'selected':''}>Paid</option><option value="completed" ${o.status==='completed'?'selected':''}>Completed</option></select></td><td><button class="btn btn-sm btn-danger" onclick="deleteOrder('${doc.id}')"><i class="fas fa-trash"></i></button></td></tr>`;
    }).join('');
}

async function updateOrderStatus(id, status) {
    await db.collection('orders').doc(id).update({ status });
    Swal.fire('Updated!', '', 'success');
}

async function deleteOrder(id) {
    if (await Swal.fire({ title: 'Yakin?', icon: 'warning', showCancelButton: true }).then(r => r.isConfirmed)) {
        await db.collection('orders').doc(id).delete();
        loadOrders();
    }
}

// Ads
async function loadAds() {
    const snap = await db.collection('ads').limit(1).get();
    if (!snap.empty) {
        const ad = snap.docs[0].data();
        document.getElementById('adTextInput').value = ad.text || '';
        document.getElementById('adLinkInput').value = ad.link || '';
        document.getElementById('adActive').checked = ad.active || false;
    }
}

document.getElementById('adForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const snap = await db.collection('ads').get();
    snap.forEach(d => db.collection('ads').doc(d.id).update({ active: false }));
    await db.collection('ads').add({
        text: document.getElementById('adTextInput').value,
        link: document.getElementById('adLinkInput').value,
        active: document.getElementById('adActive').checked,
        createdAt: firebase.firestore.FieldValue.serverTimestamp()
    });
    Swal.fire('Sukses!', 'Iklan disimpan', 'success');
});

// Users
async function loadUsers() {
    const snap = await db.collection('users').get();
    document.getElementById('usersTable').innerHTML = snap.docs.map(doc => {
        const u = doc.data();
        return `<tr><td>${u.fullName||'-'}</td><td>${u.email||'-'}</td><td><span class="badge bg-${u.role==='admin'?'danger':'info'}">${u.role||'user'}</span></td><td><button class="btn btn-sm btn-warning" onclick="toggleRole('${doc.id}', '${u.role}')"><i class="fas fa-exchange-alt"></i></button></td></tr>`;
    }).join('');
}

async function toggleRole(uid, currentRole) {
    const newRole = currentRole === 'admin' ? 'user' : 'admin';
    await db.collection('users').doc(uid).update({ role: newRole });
    Swal.fire('Updated!', `Role changed to ${newRole}`, 'success');
    loadUsers();
}

function logout() { auth.signOut().then(() => window.location.href = 'login.html'); }