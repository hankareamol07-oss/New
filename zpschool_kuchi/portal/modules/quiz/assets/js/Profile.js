$("#mySidenav").click(function (e) {
    closeNav();
});

$("#profileImage").click(function (e) {

    $("#imageUpload").click();
});
function fasterPreview(uploader) {

    if (uploader.files && uploader.files[0]) {
        $('#profileImage').attr('src',
                window.webkitURL.createObjectURL(uploader.files[0]));
    }
    var image = $('#imageUpload').prop('files')[0];
}
$("#imageUpload").change(function () {

    fasterPreview(this);
});
//Update Image
$(document).on('change', '#imageUpload', function () {

    var name = document.getElementById("imageUpload").files[0].name;
    var form_data = new FormData();
    var ext = name.split('.').pop().toLowerCase();
    if (jQuery.inArray(ext, ['gif', 'png', 'jpg', 'jpeg', 'PNG', 'JPG', 'JPEG']) == -1)
    {
        alert("Invalid Image File");
    }
    var oFReader = new FileReader();
    oFReader.readAsDataURL(document.getElementById("imageUpload").files[0]);
    var f = document.getElementById("imageUpload").files[0];
    var fsize = f.size || f.fileSize;
    if (fsize > 2000000)
    {
        alert("Image File Size is very big");
    } else
    {
        form_data.append("file", document.getElementById('imageUpload').files[0]);
        $.ajax({
            url: "Profile/Update_image",
            method: "POST",
            data: form_data,
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function () {

                $('#uploaded_image').html("<label class='text-success'>Image Uploading...</label>");
            },
            success: function (data)
            {

                $('#uploaded_image').html(data);
                document.getElementById("imagename_menu").src = 'http://localhost/WebQuiz/Images/' + data;
                document.getElementById("imagename").src = 'http://localhost/WebQuiz/Images/' + data;

            }
        });
    }
});
//Update Profile
$("#myForm").on('submit', function (event) {

    event.preventDefault();
    var fd = new FormData(this);
    $.ajax({
        type: 'post',
        url: 'Profile/Upadte_Profile',
        data: fd,
        contentType: false,
        processData: false,
        success: function (data) {

            if (data == false) {
                $("#myForm")[0].reset();
                $('#Profilemsg').fadeIn().html('<div class="alert alert-danger"><center>Something Wrong</center></div>');
                setTimeout(function () {
                    $('#Profilemsg').fadeOut('slow');
                }, 3000);
            } else {

                $('#email_name').html(data);
                $('#email_name_menu').html(data);
//                $("#myForm")[0].reset();
                $('#Profilemsg').fadeIn().html('<div class="alert alert-success"><center>Profile Update SuccessFully...</center></div>');

                setTimeout(function () {
                    $('#Profilemsg').fadeOut('slow');
                }, 3000);

            }
        }
    });
});
//Change Password
var oldMatched = 0;
var bothMatched = 0;
$('#old_pwd').on('blur', function () {
    var dataString = 'id=' + $('#pwd_id').val() + '&old=' + $('#old_pwd').val();
    if (!$('#old_pwd').val() == '')
    {
        $.ajax({
            type: "POST",
            url: "Profile/check_pass",
            data: dataString,
            cache: false,
            success: function (data) {
                // alert(data);
                if (data == 1)
                {
                    $('#waitFor').html('Password matched');
                    oldMatched = 1;
                } else {
                    $('#waitFor').html('Password does not match!');
                    oldMatched = 0;
                    $('#old_pwd').focus();
                }
            }
        });
    }
});
$('#cnew_pwd').on('blur', function () {

    if ($('#cnew_pwd').val() == '')
    {
        $('#compare_c').html('new password can not be blank!');
        bothMatched = 0;
    } else
    {
        $('#compare_c').html('please confirm password');
    }

});
$('#ccon_pwd').on('blur', function () {
    if ($('#cnew_pwd').val() != $('#ccon_pwd').val())
    {
        $('#compare_c').html('Both passwords mismatched!');
        bothMatched = 0;
    } else
    {
        $('#compare_c').css('color', 'green');
        $('#compare_c').html('Passwords matched');
        bothMatched = 1;
    }
    if ($('#ccon_pwd').val() == '')
    {
        $('#compare_c').html('confirm password can not be blank!');
        bothMatched = 0;
    }
});
$('#changePass').click(function () {
// $('#msg1').html('you can not change password because is trial demo.');
    if (oldMatched == '0')
    {
        $('#waitFor').html('provide valid password!');
        $('#old_pwd').focus();
    }
    if (bothMatched == '0')
    {
        $('#compare').html('please check new and confirm password!');
        $('#cnew_pwd').focus();
    }
    if ((oldMatched == '1') && (bothMatched == '1'))
    {
        var dataString = 'change=' + $('#pwd_id').val() + '&new=' + $('#cnew_pwd').val();
        $.ajax({
            type: "POST",
            url: "Profile/update_password",
            data: dataString,
            cache: false,

            success: function (data) {

                if (data == 1)
                {
                    $('#compare_c').html('Password changed successfully');

                } else
                {
                    $('#compare_c').html('Couldn\'t Change Password! Try Again');

                }
            }});
    }
});