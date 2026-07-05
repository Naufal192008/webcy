// ==================== FIREBASE CONFIGURATION ====================
// Project: TOFAL (tofal-44543)
// Owner: Naufal

const firebaseConfig = {
    apiKey: "AIzaSyAgd-7aXwHzJDyflM5i9VQGBhMUjMj1mRE",
    authDomain: "tofal-44543.firebaseapp.com",
    projectId: "tofal-44543",
    storageBucket: "tofal-44543.firebasestorage.app",  // PERHATIKAN: .app bukan .com
    messagingSenderId: "84582852062",
    appId: "1:84582852062:web:46f2e6c81cb30bfa34694f",
    measurementId: "G-F6T75KDTZ8"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

// Initialize Services
const auth = firebase.auth();
const db = firebase.firestore();
const storage = firebase.storage();
const analytics = firebase.analytics ? firebase.analytics() : null;

// Enable Firestore Persistence (Offline Support)
db.enablePersistence()
    .then(() => {
        console.log('✅ Firestore persistence enabled');
    })
    .catch((err) => {
        if (err.code === 'failed-precondition') {
            console.warn('⚠️ Multiple tabs open, persistence can only be enabled in one tab at a time.');
        } else if (err.code === 'unimplemented') {
            console.warn('⚠️ The current browser does not support persistence.');
        } else {
            console.error('❌ Persistence error:', err);
        }
    });

// Test Connection
console.log('🚀 Firebase connected successfully!');
console.log('📦 Project:', firebaseConfig.projectId);
console.log('🔐 Auth Domain:', firebaseConfig.authDomain);

// Export for use in other files (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { auth, db, storage, analytics };
}