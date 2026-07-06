// ============================================
// WEBPRO UMKM - MAIN JAVASCRIPT
// ============================================

// Add to cart via AJAX
function addToCart(productId) {
    fetch('ajax/cart_add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Produk ditambahkan ke keranjang',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            Swal.fire('Error', data.message || 'Gagal menambahkan', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Gagal terhubung ke server', 'error');
    });
}

// Format rupiah
function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});