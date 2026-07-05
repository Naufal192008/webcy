// ==================== PAYMENT SYSTEM ====================
const DANA_NUMBER = '085710785244';
const DANA_NAME = 'WebPro UMKM';

async function processPayment(orderData) {
    const { method, amount } = orderData;
    
    try {
        // Create order in Firestore
        const orderRef = await db.collection('orders').add({
            userId: currentUser.uid,
            userName: currentUser.displayName || 'User',
            productName: orderData.productName,
            totalPrice: amount,
            paymentMethod: method,
            status: 'pending',
            paymentNumber: DANA_NUMBER,
            createdAt: firebase.firestore.FieldValue.serverTimestamp()
        });
        
        // Generate payment instructions based on method
        let paymentInstructions = '';
        switch(method) {
            case 'dana':
                paymentInstructions = `
                    <div class="payment-instructions">
                        <h5>Pembayaran via DANA</h5>
                        <p>Silakan transfer ke nomor DANA:</p>
                        <div class="alert alert-info">
                            <h3>${DANA_NUMBER}</h3>
                            <p>a.n. ${DANA_NAME}</p>
                        </div>
                        <p>Jumlah: <strong>Rp ${formatRupiah(amount)}</strong></p>
                        <ol>
                            <li>Buka aplikasi DANA</li>
                            <li>Pilih "Kirim"</li>
                            <li>Masukkan nomor ${DANA_NUMBER}</li>
                            <li>Masukkan nominal Rp ${formatRupiah(amount)}</li>
                            <li>Konfirmasi pembayaran</li>
                        </ol>
                    </div>
                `;
                break;
            case 'ovo':
                paymentInstructions = `
                    <div class="payment-instructions">
                        <h5>Pembayaran via OVO</h5>
                        <p>Silakan transfer ke nomor OVO:</p>
                        <div class="alert alert-info">
                            <h3>${DANA_NUMBER}</h3>
                            <p>a.n. ${DANA_NAME}</p>
                        </div>
                        <p>Jumlah: <strong>Rp ${formatRupiah(amount)}</strong></p>
                    </div>
                `;
                break;
            case 'gopay':
                paymentInstructions = `
                    <div class="payment-instructions">
                        <h5>Pembayaran via GoPay</h5>
                        <p>Silakan transfer ke nomor GoPay:</p>
                        <div class="alert alert-info">
                            <h3>${DANA_NUMBER}</h3>
                            <p>a.n. ${DANA_NAME}</p>
                        </div>
                        <p>Jumlah: <strong>Rp ${formatRupiah(amount)}</strong></p>
                    </div>
                `;
                break;
            case 'qris':
                paymentInstructions = `
                    <div class="payment-instructions">
                        <h5>Pembayaran via QRIS</h5>
                        <p>Scan QR Code berikut:</p>
                        <div class="text-center my-4">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=DANA${DANA_NUMBER}" 
                                 alt="QRIS Code" class="img-fluid">
                        </div>
                        <p>Jumlah: <strong>Rp ${formatRupiah(amount)}</strong></p>
                    </div>
                `;
                break;
        }
        
        // Show payment modal
        Swal.fire({
            title: 'Instruksi Pembayaran',
            html: paymentInstructions,
            showCancelButton: true,
            confirmButtonText: 'Saya Sudah Bayar',
            cancelButtonText: 'Nanti Saja',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                // Update order status to paid
                await db.collection('orders').doc(orderRef.id).update({
                    status: 'paid',
                    paidAt: firebase.firestore.FieldValue.serverTimestamp()
                });
                
                // Clear cart
                await db.collection('carts').doc(currentUser.uid).delete();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Pembayaran Dikonfirmasi!',
                    text: 'Admin akan memproses pesanan Anda',
                    confirmButtonText: 'Lihat Pesanan'
                }).then(() => {
                    window.location.href = 'history.html';
                });
            }
        });
        
    } catch (error) {
        console.error("Payment error:", error);
        Swal.fire('Error', 'Gagal memproses pembayaran', 'error');
    }
}

// Confirm payment manually (for admin)
async function confirmPayment(orderId) {
    await db.collection('orders').doc(orderId).update({
        status: 'paid',
        confirmedAt: firebase.firestore.FieldValue.serverTimestamp()
    });
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}