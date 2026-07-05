// ==================== FIREBASE CONFIGURATION ====================
// Project: TOFAL (tofal-44543)
// Owner: Naufal

const firebaseConfig = {
    apiKey: "AIzaSyAgd-7aXwHzJDyflM5i9VQGBhMUjMj1mRE",
    authDomain: "tofal-44543.firebaseapp.com",
    projectId: "tofal-44543",
    storageBucket: "tofal-44543.firebasestorage.app",
    messagingSenderId: "84582852062",
    appId: "1:84582852062:web:46f2e6c81cb30bfa34694f",
    measurementId: "G-F6T75KDTZ8"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

// Initialize Services
const auth = firebase.auth();
const db = firebase.firestore();

// Storage hanya di-inisialisasi kalau dibutuhkan
let storage = null;
try {
    storage = firebase.storage();
} catch (e) {
    console.warn('⚠️ Storage not available:', e.message);
}

// Enable Firestore Persistence (Offline Support)
db.enablePersistence()
    .then(() => {
        console.log('✅ Firestore persistence enabled');
    })
    .catch((err) => {
        if (err.code === 'failed-precondition') {
            console.warn('⚠️ Multiple tabs open');
        } else if (err.code === 'unimplemented') {
            console.warn('⚠️ Browser does not support persistence');
        } else {
            console.error('❌ Persistence error:', err);
        }
    });

console.log('🚀 Firebase connected!');
console.log('📦 Project:', firebaseConfig.projectId);