// ==================== AUTHENTICATION SYSTEM ====================

// Current step
let currentStep = 1;

// Next step
function nextStep(step) {
    // Validate current step
    if (currentStep === 1) {
        const name = document.getElementById('fullName').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        const password = document.getElementById('password').value;
        
        if (!name || !email || !phone || !password) {
            Swal.fire('Oops!', 'Mohon lengkapi semua field wajib', 'warning');
            return;
        }
        
        if (password.length < 8) {
            Swal.fire('Oops!', 'Password minimal 8 karakter', 'warning');
            return;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            Swal.fire('Oops!', 'Format email tidak valid', 'warning');
            return;
        }
        
        // Validate phone
        const phoneRegex = /^[0-9]{10,13}$/;
        if (!phoneRegex.test(phone)) {
            Swal.fire('Oops!', 'Nomor handphone tidak valid (10-13 digit)', 'warning');
            return;
        }
    }
    
    if (currentStep === 2 && step === 3) {
        // Optional validation for step 2
    }
    
    // Hide current step
    document.getElementById(`formStep${currentStep}`).style.display = 'none';
    document.getElementById(`step${currentStep}`).classList.remove('active');
    document.getElementById(`step${currentStep}`).classList.add('completed');
    
    // Show next step
    currentStep = step;
    document.getElementById(`formStep${currentStep}`).style.display = 'block';
    document.getElementById(`step${currentStep}`).classList.add('active');
    
    // If step 3, show review
    if (currentStep === 3) {
        showReviewData();
    }
}

// Previous step
function prevStep(step) {
    document.getElementById(`formStep${currentStep}`).style.display = 'none';
    document.getElementById(`step${currentStep}`).classList.remove('active');
    
    currentStep = step;
    document.getElementById(`formStep${currentStep}`).style.display = 'block';
    document.getElementById(`step${currentStep}`).classList.add('active');
}

// Show review data
function showReviewData() {
    const review = document.getElementById('reviewData');
    review.innerHTML = `
        <table class="table table-sm">
            <tr><td><strong>Nama:</strong></td><td>${document.getElementById('fullName').value}</td></tr>
            <tr><td><strong>Email:</strong></td><td>${document.getElementById('email').value}</td></tr>
            <tr><td><strong>No. HP:</strong></td><td>+62${document.getElementById('phone').value}</td></tr>
            <tr><td><strong>Bisnis:</strong></td><td>${document.getElementById('businessName').value || '-'}</td></tr>
            <tr><td><strong>Jenis Bisnis:</strong></td><td>${document.getElementById('businessType').value || '-'}</td></tr>
        </table>
    `;
}

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password') || document.getElementById('loginPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Password strength checker
if (document.getElementById('password')) {
    document.getElementById('password').addEventListener('input', function(e) {
        const password = e.target.value;
        const strengthDiv = document.getElementById('passwordStrength');
        let strength = 0;
        let message = '';
        let color = '';
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/\d/)) strength++;
        if (password.match(/[^a-zA-Z\d]/)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                message = 'Lemah';
                color = 'danger';
                break;
            case 2:
                message = 'Sedang';
                color = 'warning';
                break;
            case 3:
                message = 'Kuat';
                color = 'info';
                break;
            case 4:
                message = 'Sangat Kuat';
                color = 'success';
                break;
        }
        
        strengthDiv.innerHTML = `
            <div class="progress" style="height: 5px;">
                <div class="progress-bar bg-${color}" style="width: ${strength * 25}%"></div>
            </div>
            <small class="text-${color}">${message}</small>
        `;
    });
}

// Register form submit
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!document.getElementById('agreeTerms').checked) {
        Swal.fire('Oops!', 'Anda harus menyetujui Syarat & Ketentuan', 'warning');
        return;
    }
    
    const fullName = document.getElementById('fullName').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const password = document.getElementById('password').value;
    const businessName = document.getElementById('businessName').value;
    const businessType = document.getElementById('businessType').value;
    const businessAddress = document.getElementById('businessAddress').value;
    const city = document.getElementById('city').value;
    
    try {
        // Show loading
        Swal.fire({
            title: 'Mendaftarkan...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Create user in Firebase Auth
        const userCredential = await auth.createUserWithEmailAndPassword(email, password);
        const user = userCredential.user;
        
        // Update profile
        await user.updateProfile({
            displayName: fullName,
            phoneNumber: phone
        });
        
        // Save user data to Firestore
        await db.collection('users').doc(user.uid).set({
            fullName: fullName,
            email: email,
            phone: phone,
            businessName: businessName || '',
            businessType: businessType || '',
            businessAddress: businessAddress || '',
            city: city || '',
            role: 'user',
            createdAt: firebase.firestore.FieldValue.serverTimestamp(),
            lastLogin: firebase.firestore.FieldValue.serverTimestamp(),
            isActive: true,
            avatar: '',
            totalOrders: 0,
            totalSpent: 0
        });
        
        // Send verification email
        await user.sendEmailVerification();
        
        Swal.fire({
            icon: 'success',
            title: 'Pendaftaran Berhasil!',
            text: 'Silakan cek email Anda untuk verifikasi',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'dashboard.html';
        });
        
    } catch (error) {
        console.error('Registration error:', error);
        let errorMessage = 'Gagal mendaftar';
        
        switch(error.code) {
            case 'auth/email-already-in-use':
                errorMessage = 'Email sudah terdaftar';
                break;
            case 'auth/invalid-email':
                errorMessage = 'Format email tidak valid';
                break;
            case 'auth/weak-password':
                errorMessage = 'Password terlalu lemah';
                break;
        }
        
        Swal.fire('Error', errorMessage, 'error');
    }
});

// Login form submit
document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const rememberMe = document.getElementById('rememberMe')?.checked;
    
    try {
        Swal.fire({
            title: 'Memproses...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Set persistence
        if (rememberMe) {
            await auth.setPersistence(firebase.auth.Auth.Persistence.LOCAL);
        } else {
            await auth.setPersistence(firebase.auth.Auth.Persistence.SESSION);
        }
        
        // Sign in
        const userCredential = await auth.signInWithEmailAndPassword(email, password);
        const user = userCredential.user;
        
        // Check if email verified (optional)
        // if (!user.emailVerified) {
        //     Swal.fire('Warning', 'Email belum diverifikasi', 'warning');
        // }
        
        // Update last login
        await db.collection('users').doc(user.uid).update({
            lastLogin: firebase.firestore.FieldValue.serverTimestamp()
        });
        
        // Check role and redirect
        const userDoc = await db.collection('users').doc(user.uid).get();
        const userData = userDoc.data();
        
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil!',
            text: `Selamat datang ${userData.fullName}`,
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            if (userData.role === 'admin') {
                window.location.href = 'admin.html';
            } else {
                window.location.href = 'dashboard.html';
            }
        });
        
    } catch (error) {
        console.error('Login error:', error);
        let errorMessage = 'Gagal login';
        
        switch(error.code) {
            case 'auth/user-not-found':
                errorMessage = 'Email tidak terdaftar';
                break;
            case 'auth/wrong-password':
                errorMessage = 'Password salah';
                break;
            case 'auth/too-many-requests':
                errorMessage = 'Terlalu banyak percobaan. Coba lagi nanti';
                break;
        }
        
        Swal.fire('Error', errorMessage, 'error');
    }
});

// Google Login
async function loginWithGoogle() {
    const provider = new firebase.auth.GoogleAuthProvider();
    try {
        const result = await auth.signInWithPopup(provider);
        const user = result.user;
        
        // Check if user exists in Firestore
        const userDoc = await db.collection('users').doc(user.uid).get();
        if (!userDoc.exists) {
            await db.collection('users').doc(user.uid).set({
                fullName: user.displayName,
                email: user.email,
                phone: user.phoneNumber || '',
                role: 'user',
                createdAt: firebase.firestore.FieldValue.serverTimestamp(),
                lastLogin: firebase.firestore.FieldValue.serverTimestamp()
            });
        }
        
        window.location.href = 'dashboard.html';
    } catch (error) {
        console.error('Google login error:', error);
        Swal.fire('Error', 'Gagal login dengan Google', 'error');
    }
}

// Facebook Login
async function loginWithFacebook() {
    const provider = new firebase.auth.FacebookAuthProvider();
    try {
        const result = await auth.signInWithPopup(provider);
        // Similar to Google login
        window.location.href = 'dashboard.html';
    } catch (error) {
        console.error('Facebook login error:', error);
        Swal.fire('Error', 'Gagal login dengan Facebook', 'error');
    }
}

// Logout
function logout() {
    Swal.fire({
        title: 'Yakin ingin keluar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            auth.signOut().then(() => {
                window.location.href = 'index.html';
            });
        }
    });
}

// Forgot password
async function resetPassword(email) {
    try {
        await auth.sendPasswordResetEmail(email);
        Swal.fire('Sukses!', 'Link reset password telah dikirim ke email Anda', 'success');
    } catch (error) {
        Swal.fire('Error', 'Gagal mengirim reset password', 'error');
    }
}

// Auth state observer
auth.onAuthStateChanged((user) => {
    if (user) {
        // User is signed in
        console.log('User logged in:', user.uid);
        
        // Update UI elements that require auth
        const protectedPages = ['dashboard.html', 'cart.html', 'checkout.html', 
                               'profile.html', 'history.html'];
        const currentPage = window.location.pathname.split('/').pop();
        
        // If user is not verified and trying to access protected page
        // if (!user.emailVerified && protectedPages.includes(currentPage)) {
        //     Swal.fire('Warning', 'Email belum diverifikasi', 'warning');
        // }
    } else {
        // User is signed out
        console.log('No user logged in');
        
        // Redirect to login if on protected page
        const protectedPages = ['dashboard.html', 'cart.html', 'checkout.html', 
                               'profile.html', 'history.html', 'admin.html'];
        const currentPage = window.location.pathname.split('/').pop();
        
        if (protectedPages.includes(currentPage)) {
            window.location.href = 'login.html';
        }
    }
});