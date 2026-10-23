function login_with_google(){

    firebase.auth()
            .signInWithPopup(googleProvider)
            .then((result) => {
                /** @type {firebase.auth.OAuthCredential} */
                var credential = result.credential;

                // This gives you a Google Access Token. You can use it to access the Google API.
                var token = credential.accessToken;
                // The signed-in user info.
                var user = result.user;
                console.log(user);


                $.ajax({
                    url: URL + "Index/Login_with_google",
                    type: "post",
                    dataType: "json",
                    data: user.providerData[0],
                    success: function (data) {

                        if (data.status == true) {
//                            alert("Sucessfully logged");
                          
                            window.location.replace(URL);
                        } else {
                            alert("Something went wrong here");
                        }

                    },
                    error: function (error) {
                        alert("Error occured");
                    }
                })
                // ...
            }).catch((error) => {
        // Handle Errors here.
        var errorCode = error.code;
        var errorMessage = error.message;
        // The email of the user's account used.
        var email = error.email;
        // The firebase.auth.AuthCredential type that was used.
        var credential = error.credential;
        console.log(error)
        // ...
    });

}