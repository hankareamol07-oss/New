// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
var firebaseConfig = {
    apiKey: "AIzaSyDsAFFFSkaYfRzEfITA5-bpoSe2ha5bSw4",
    authDomain: "lpk-quiz.firebaseapp.com",
    databaseURL: "https://lpk-quiz.firebaseio.com",
    projectId: "lpk-quiz",
    storageBucket: "lpk-quiz.appspot.com",
    messagingSenderId: "121325179572",
    appId: "1:121325179572:web:5f5bda961036d108787de1"

};
// Initialize Firebase
firebase.initializeApp(firebaseConfig);
var URL = $('meta[name="baseURL"]').attr('content');

console.log("Firebase started.");

// Facebook
var facebookProvider = new firebase.auth.FacebookAuthProvider();

var googleProvider = new firebase.auth.GoogleAuthProvider();