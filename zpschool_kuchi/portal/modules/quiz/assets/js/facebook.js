
function facebook_login() {

    firebase
            .auth()
            .signInWithPopup(facebookProvider)
            .then((result) => {
                /** @type {firebase.auth.OAuthCredential} */
                var credential = result.credential;

                // The signed-in user info.
                var user = result.user;

                console.log(user);
                console.log(credential);
                $.ajax({
                    url: URL + 'Index/web_facebook_login',
                    data: user.providerData[0],
                    type: 'post',
                    dataType: 'json',

                    success: function (data) {

                        if (data.status == true) {

                            window.location.replace(URL);
                        } else {
                            alert("Something went wrong here");
                        }

                    },
                    error: function (error) {
                        alert("Error occured");
                    }
                })


                // This gives you a Facebook Access Token. You can use it to access the Facebook API.
                var accessToken = credential.accessToken;

                // ...
            })
            .catch((error) => {
                // Handle Errors here.
                var errorCode = error.code;
                var errorMessage = error.message;
               
                // The email of the user's account used.
                var email = error.email;
                // The firebase.auth.AuthCredential type that was used.
                var credential = error.credential;
                console.log(error);
                 $('#facebook_msg').html(credential.message);
                setTimeout(function () {
                    $('#facebook_msg').fadeOut('slow');
                }, 3000);

                // ...
            });

}