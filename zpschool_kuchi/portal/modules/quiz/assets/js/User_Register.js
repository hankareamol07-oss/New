$(function () {

//Login Model
    $('#loginModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget) // Button that triggered the modal
        var recipient = button.data('whatever') // Extract info from data-* attributes
        // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
        // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
        var modal = $(this)
        modal.find('.modal-title').text('New message to ' + recipient)
        modal.find('.modal-body input').val(recipient)
    })


//Sign up Model
    $('#signUpModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget) // Button that triggered the modal
        var recipient = button.data('whatever') // Extract info from data-* attributes
        // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).

        // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
        var modal = $(this)
        modal.find('.modal-title').text('New message to ' + recipient)
        modal.find('.modal-body input').val(recipient)
    })

//Check Confirm Password
    $('#con_pwd').on('blur', function () {
        if ($('#pass').val() != $('#con_pwd').val()) {

            $('#compare').addClass("alert alert-danger");
            $('#compare').html('Both passwords mismatched!');
            bothMatched = 0;
        } else {

            $('#compare').addClass("alert alert-success");
            $('#compare').html('Passwords matched');
            bothMatched = 1;
        }
        if ($('#con_pwd').val() == '')
        {

            $('#compare').addClass("alert alert-warning");
            $('#compare').html('confirm password can not be blank!');
            bothMatched = 0;
        }
    });
//Login
    $("#myForm_login").on('submit', function (event) {
        event.preventDefault();
        var fd = new FormData(this);

        $.ajax({
            type: 'post',
            url: 'Register_user/Sign_In',
            data: fd,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function (data) {

                if (data.status == true) {
                    var username = data.username;
                    var password = data.password;
                    //--------------Firebase Authentication-------------------//
                    firebase.auth().createUserWithEmailAndPassword(username, password)

                            .then((userCredential) => {
                                // Signed in 
                                var user = userCredential.user;
                                // ...

                            })
                            .catch((error) => {
                                var errorCode = error.code;
                                var errorMessage = error.message;

                                // ..
                            });
                    //--------------end-----------------------//
                    //Index Page Profile model
                    $("#Profilemsg").css("display", "none");
                    $('#profile_button').prop("disabled", false);

                    document.getElementById("display_name").value = data.display_name;
                    document.getElementById("mobile").value = data.mobile_no;
                    document.getElementById("address").value = data.address;
                    //change pass
                    $("#check_change_pass_session").css("display", "none");
                    $('#changePass').prop("disabled", false);

                    //Index page menu
                    $("#user_login_button").css("display", "none");
                    document.getElementById("email_name").innerHTML = data.display_name;
                    if (data.image == '') {

                        document.getElementById("imagename").src = 'http://localhost/WebQuiz/Images/user/default/user.png';
                        document.getElementById("display_image").innerHTML = '<ul class="nav"><li class="nav-item"><a class="nav-link text-white cursor-pointer"> <img src="http://localhost/WebQuiz/Images/user/default/user.png"  id="imagename_menu" class="user-profile shadow img-fluid rounded-circle ml-3" alt="User"/></a><small class="nav-link text-white text-center"><p id="email_name_menu">' + data.display_name + '</p></small></li></ul>';
                    } else {
                        document.getElementById("imagename").src = 'http://localhost/WebQuiz/Images/' + data.image;
                        document.getElementById("profileImage").src = 'http://localhost/WebQuiz/Images/' + data.image;
                        document.getElementById("display_image").innerHTML = '<ul class="nav"><li class="nav-item"><a class="nav-link text-white cursor-pointer"> <img src="http://localhost/WebQuiz/Images/' + data.image + '" id="imagename_menu" class="user-profile shadow img-fluid rounded-circle ml-3" alt="User"/><small class="nav-link text-white text-center"><p id="email_name_menu">' + data.display_name + '</p></small></a></li></ul>';
                    }

                    $("#myForm_login")[0].reset();
                    $('#msg_login').fadeIn().html('<div class="alert alert-success"><center>Login SuccessFully...</center></div>');
                    setTimeout(function () {
                        $('#msg_login').fadeOut('slow');
                        $("#loginModal").modal('hide');
                    }, 2000);

                } else {
                    $("#myForm_login")[0].reset();
                    $('#msg_login').fadeIn().html('<div class="alert alert-danger"><center>Authentication Fail..!</center></div>');
                    setTimeout(function () {
                        $('#msg_login').fadeOut('slow');
                    }, 5000);
                }
            }
        });
    });
    //User Registration
    $("#myForm_Register").on('submit', function (event) {
        event.preventDefault();
        var fd = new FormData(this);
        var base_url = $('#base').val();
        $.ajax({
            type: 'post',
            url: 'Register_user/Sign_up',
            data: fd,
            contentType: false,
            processData: false,
            success: function (data) {
                if (data == 1) {
                    $("#myForm_Register")[0].reset();
                    $('#msg_reg').fadeIn().html('<div class="alert alert-success"><center>Register SuccessFully...</center></div>');
                    setTimeout(function () {
                        //Hide Sucess Msg
                        $('#msg_reg').fadeOut('slow');
                        //Close Sign Up Model
                        $('#signUpModal').modal('hide');
                        //Open Login Model
                        $("#loginModal").modal("show");

                    }, 1000);
                } else {
                    $("#myForm_Register")[0].reset();
                    $('#msg_reg').fadeIn().html('<div class="alert alert-danger"><center>Something Wrong..!</center></div>');
                    setTimeout(function () {
                        $('#msg_reg').fadeOut('slow');

                    }, 5000);
                }
            }
        });
    });
});