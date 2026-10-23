
$(document).ready(function () {
    var url = window.location;
    $('ul .nav-item a[href="' + url + '"]').parent().addClass('active');
    $('ul .nav-item a').filter(function () {
        return this.href == url;
    }).parent().addClass('active');
});
