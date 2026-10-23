/*--------loader script-----------*/
$(function () {

    timer();
    function timer() {
        const FULL_DASH_ARRAY = 283;
        const WARNING_THRESHOLD = 10;
        const ALERT_THRESHOLD = 5;
        const COLOR_CODES = {
            info: {
                color: "green"
            },
            warning: {
                color: "orange",
                threshold: WARNING_THRESHOLD
            },
            alert: {
                color: "red",
                threshold: ALERT_THRESHOLD
            }
        };
        var time = $('#mission_time').val(); //mission quiz timer
        if (time) {
            //set timer for mission quiz
            var TIME_LIMIT = time;
        } else {

            var TIME_LIMIT = 20;
        }


        let timePassed = 0;
        let timeLeft = TIME_LIMIT;
        let timerInterval = null;
        let remainingPathColor = COLOR_CODES.info.color;
        document.getElementById("app").innerHTML = `
<div class="base-timer">
  <svg class="base-timer__svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
    <g class="base-timer__circle">
      <circle class="base-timer__path-elapsed" cx="50" cy="50" r="45"></circle>
      <path
        id="base-timer-path-remaining"
        stroke-dasharray="283"
        class="base-timer__path-remaining ${remainingPathColor}"
        d="
          M 50, 50
          m -45, 0
          a 45,45 0 1,0 90,0
          a 45,45 0 1,0 -90,0
        "
      ></path>
    </g>
  </svg>
  <span id="base-timer-label" class="base-timer__label">${formatTime(
                timeLeft
                )}</span>
</div>
`;
        startTimer();
        function onTimesUp() {

            clearInterval(timerInterval);
        }


        function startTimer() {

            timerInterval = setInterval(() => {
                timePassed = timePassed += 1;
                timeLeft = TIME_LIMIT - timePassed;
                document.getElementById("base-timer-label").innerHTML = formatTime(
                        timeLeft
                        );
                setCircleDasharray();
                setRemainingPathColor(timeLeft);
                //When Use Lifeline Reset Timer
                if (reset_time == 1) {
                    $('#reset_timer').addClass("overlay_lifeline");
                    document.getElementById("reset_timer").disabled = true;
                    setTimeout(function () {

                        onTimesUp();
                        timer();
                        document.getElementById("reset_timer").disabled = true;
                    }, 1000);
                    reset_time = 0;
                    return reset_time;
                }


                //When the question changes, the timer resets.


                if (check_click == 1) {

                    if (doubledip == 1) {

                        doubledip = 0;
                        return doubledip;
                        $('#doubledip').addClass("overlay_lifeline");
                        document.getElementById("doubledip").disabled = true;
                    } else {
                        setTimeout(function () {

                            onTimesUp();
                            timer();
                            $('#quiz').show();
                            $('#loadbar').fadeOut();
                        }, 2500);
                        check_click = 0;
                        return check_click;
                    }
                }
                if (lifeline_fifty == 1) {
                    $('#lifeline_fifty').addClass("overlay_lifeline");
                    document.getElementById("lifeline_fifty").disabled = true;
                    lifeline_fifty = 0;
                }
                if (timeLeft === 0) {

                    //When the timer is over, the next question comes and the timer reset.
                    onTimesUp();
                    questionNo++;

                    //Progress Bar
                    updateProgress();
                    document.getElementById("soption").style.display = "block";
                    document.getElementById("foption").style.display = "block";
                    document.getElementById("toption").style.display = "block";
                    document.getElementById("xoption").style.display = "block";
                    var foption = document.getElementById("foption");
                    var soption = document.getElementById("soption");
                    var toption = document.getElementById("toption");
                    var xoption = document.getElementById("xoption");
                    foption.style.removeProperty('margin');
                    soption.style.removeProperty('margin');
                    toption.style.removeProperty('margin');
                    xoption.style.removeProperty('margin');
                    foption.style.removeProperty('background-color');
                    soption.style.removeProperty('background-color');
                    toption.style.removeProperty('background-color');
                    xoption.style.removeProperty('background-color');
                    $(foption).removeClass("animated wow bounce infinite disabled");
                    $(soption).removeClass("animated wow bounce infinite disabled");
                    $(toption).removeClass("animated wow bounce infinite disabled");
                    $(xoption).removeClass("animated wow bounce infinite disabled");

                    //set data in session 

                    if ((questionNo + 1) > q.length) {

                        document.getElementById('quiz_web').innerHTML = "";


                        // alert("Quiz completed, Now click ok to get your answer");
                        $('label.element-animation').unbind('click');

                        setTimeout(function () {
                            var toAppend = '';
                            $.each(q, function (i, a) {
                                toAppend += '<tr>'
                                toAppend += '<td>' + (i + 1) + '</td>';
                                toAppend += '<td>' + a.A + '</td>';
                                toAppend += '<td>' + a.UC + '</td>';
                                toAppend += '<td>' + a.result + '</td>';
                                toAppend += '</tr>'
                            });
                            backsound.pause();
                            //Insert Result In Database
                            count_score = correctCount * 5;
//                            document.getElementById("count_score").innerHTML = count_score;

                            var wrong_count = wrongCount * 3;
                            var point = (correctCount * 5) - wrong_count;
                            var earnPerc = correctCount / count * 100;
                            if (earnPerc >= 30 && earnPerc < 50) {
                                coins = 1;
                            } else if (earnPerc >= 50 && earnPerc < 80) {
                                coins = 2;
                            } else if (earnPerc >= 80 && earnPerc < 99) {
                                coins = 3;
                            } else if (earnPerc >= 99) {
                                coins = 4;
                            }

                            //set url
                            var url = base_url + 'Quiz/StoreResult';
                            if (mission == 'mission') {
                                main_cat_id = main_id;
                                sub_cat_id = sub_id;
                            }

                            $.ajax({
                                type: "POST",
                                url: url,
                                data: {
                                    user_id: user_id,
                                    main_cat_id: main_cat_id,
                                    sub_cat_id: sub_cat_id,
                                    right_question: correctCount,
                                    coin: coins,
                                    wrong_question: wrongCount,
                                    point: point
                                },
                                cache: false,
                                success: function (data) {
                                    //get data for result model
                                    if (rquiz == 'rquiz') {
                                        var url = base_url + 'quiz/rquiz';
                                    } else if (mission == 'mission') {

                                        var url = base_url + 'quiz/mission/' + mission_index_id + '/' + time;
                                    } else {
                                        //Sub Category Id
                                        var url = base_url + 'quiz?id=' + sub_cat_id + '&&mid=' + main_cat_id;
                                    }
                                    var percent = (point / TotalQuizScore) * 100;
                                    $.redirect(base_url + 'Quiz_result_model/Result/' + data, {'category': data, 'score': point, 'TotalQuizScore': TotalQuizScore, 'trueanswer': correctCount, 'falseanswer': wrongCount, 'url': url, 'percent': percent, 'id': data});
                                }
                            });
                            backsound.pause();

                            $('#loadbar').fadeOut();
                            $('#result-of-question').show();
                            $('#graph-result').show();
                            chartMake();
                        }, 1000);
                    } else {
                        //reset timer
                        timer();
                        $('#qid').html(questionNo + 1 + '.');
                        $('input:radio').prop('checked', false);
                        setTimeout(function () {

                            $('#quiz').show();
                            $('#loadbar').fadeOut();
                        }, 1500);
                        if (q[questionNo]['image'] == 'undefined' || q[questionNo]['image'] == '' || q[questionNo]['image'] == null) {
                            $("#question_image").css("display", "none");
                        } else {
                            $("#question_image").css("display", "block");
                            document.getElementById("question_image").innerHTML = '<img src="' + base_url + 'Images/question/' + q[questionNo]['image'] + '" width="100" height="100" alt="question_image"/>'

                        }
                        $('#question').html(q[questionNo]['question']);
                        if (q[questionNo]['type'] == 1) {
                            $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
                            $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
                            $("#xoption").css("display", "none");
                            $("#toption").css("display", "none");
                        } else {
                            $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
                            $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
                            $($('#t-option').parent().find('label')).html(q[questionNo]['option_c']);
                            $($('#x-option').parent().find('label')).html(q[questionNo]['option_d']);
                        }
                    }
                }

            }, 1000);
        }

        function formatTime(time) {

            const minutes = Math.floor(time / 60);
            let seconds = time % 60;
            if (seconds < 10) {
                seconds = `0${seconds}`;
            }

            return `${seconds}`;
        }

        function setRemainingPathColor(timeLeft) {
            const {alert, warning, info} = COLOR_CODES;
            if (timeLeft <= alert.threshold) {
                document
                        .getElementById("base-timer-path-remaining")
                        .classList.remove(warning.color);
                document
                        .getElementById("base-timer-path-remaining")
                        .classList.add(alert.color);
            } else if (timeLeft <= warning.threshold) {
                document
                        .getElementById("base-timer-path-remaining")
                        .classList.remove(info.color);
                document
                        .getElementById("base-timer-path-remaining")
                        .classList.add(warning.color);
            }
        }

        function calculateTimeFraction() {
            const rawTimeFraction = timeLeft / TIME_LIMIT;
            return rawTimeFraction - (1 / TIME_LIMIT) * (1 - rawTimeFraction);
        }

        function setCircleDasharray() {
            const circleDasharray = `${(
                    calculateTimeFraction() * FULL_DASH_ARRAY
                    ).toFixed(0)} 283`;
            document
                    .getElementById("base-timer-path-remaining")
                    .setAttribute("stroke-dasharray", circleDasharray);
        }
    }
    ;
    var loading = $('#loadbar').hide();
    $(document)
            .ajaxStart(function () {
                loading.show();
            }).ajaxStop(function () {
        loading.hide();
    });
    // AJAX request

    const urlParams = new URLSearchParams(window.location.search);
    const pageurl = window.location.pathname;
    var rquiz = pageurl.substring(pageurl.lastIndexOf('/') + 1);
    var base_url = $('#base_url').val();
//-----play sound Setting-----------------//  

    //play sound for wrong and wright Question
    const TrueSound = new Audio(base_url + "/assets/sound/true.wav");
    const WrongSound = new Audio(base_url + "/assets/sound/false.wav");
    var backsound = new Audio(base_url + "/assets/sound/quiz.wav");
    backsound.addEventListener('ended', function () {
        this.currentTime = 0;
        this.autoplay = true;
        this.play();
    }, false);
    function setsound() {
        var sound = $('#set_sound').is(':checked') ? 'ON' : 'OFF';
        if (sound == 'ON') {
            return true;
        } else {
            return false;
        }
    }
    function back_sound() {
        var sound = $('#back_sound').is(':checked') ? 'ON' : 'OFF';
        if (sound == 'ON') {
            return true;
        } else {
            return false;
        }
    }
    setsound();
    back_sound();
    if (back_sound()) {
        backsound.play();
    }
    $("#myForm_sound").on('submit', function (event) {

        event.preventDefault();
        var sound = $('#set_sound').is(':checked') ? 'ON' : 'OFF';
        var back_sound = $('#back_sound').is(':checked') ? 'ON' : 'OFF';
        $.ajax({
            type: "POST",
            url: base_url + "Setting",
            data: {
                sound: sound,
                back_sound: back_sound
            },
            success: function (data) {

                if (data == 1) {
                    if (back_sound == 'ON') {
                        backsound.play();
                    } else {
                        backsound.pause();
                    }

                    setTimeout(function () {
                        $('#setting_msg').fadeIn().html('<div class="alert alert-success"><center>Set SuccessFully...</center></div>');
                        $("#settingModal").modal('hide');
                    }, 1000);
                }
            }
        });
    });
//-----------End------------// 


//set Url
    var mission = $('#mission').val();
    if (rquiz == 'rquiz') {
        var url = base_url + 'Quiz/get_random_question';
    } else if (mission == 'mission') {

        var url = base_url + 'Quiz/get_mission_question';
        var id = $('#mission_id').val();
    } else {
//Sub Category Id
        var id = urlParams.get('id');
        var url = 'Quiz/get_question';
    }

//Get Question using Ajax

    var qa = $.ajax({
        url: url,
        method: "post",
        cache: false,
        data: {id: id},
        async: false
    }
    ).responseText;

    var array = JSON.parse(qa);

    var main_id = '-1';
    var sub_id = '-1';

    var questionNo = 0;
    var correctCount = 0;
    var wrongCount = 0;
    var check_click = 0;
    var user_id = $('#user_id').val();
    var main_cat_id = $('#main_cat_id').val();
    var sub_cat_id = $('#sub_cat_id').val();
    var q = array.data;
    var count = array.data.length;

    //-----Display Fisrt Question------------//
    if (q[questionNo]['image'] == 'undefined' || q[questionNo]['image'] == '' || q[questionNo]['image'] == null) {

        $("#question_image").css("display", "none");
    } else {
        $("#question_image").css("display", "block");
        document.getElementById("question_image").innerHTML = '<img src="' + base_url + 'Images/question/' + q[questionNo]['image'] + '" width="100" height="100" alt="question_image"/>'
    }

    $('#question').html(q[questionNo]['question']);
    if (q[questionNo]['type'] == 1) {
        $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
        $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
        $("#xoption").css("display", "none");
        $("#toption").css("display", "none");
    } else {
        $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
        $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
        $($('#t-option').parent().find('label')).html(q[questionNo]['option_c']);
        $($('#x-option').parent().find('label')).html(q[questionNo]['option_d']);
    }
    //-----end-----//
    //LifeLine Reset Timer
    var reset_time = 0;

    $(document.body).on('click', "#reset_timer", function (e) {

        $('#reset_timer').addClass("overlay_lifeline");
        return reset_time = 1;

    });

    //LifeLine doubledip
    var doubledip_true = 0;
    var doubledip = 0;
    $(document.body).on('click', "#doubledip", function (e) {
        doubledip = 1;
        Swal.fire({
            icon: 'info',
            title: 'Info',
            text: 'You can select the option 2 times!'
        })
        $('#doubledip').addClass("overlay_lifeline");
        document.getElementById("doubledip").disabled = true;

    });

    //lifeline_fifty
    //Lifeline 50-50
    var lifeline_fifty = 0;
    $(document.body).on('click', "#lifeline_fifty", function (e) {

        if (lifeline_fifty == 0) {
            var ans = q[questionNo]['answer'];

            if (ans == 'A') {
                $("#soption").hide();
                $("#xoption").hide();
                $("#foption").css("margin", "10px");
                $("#toption").css("margin", "10px");
            } else if (ans == 'B') {
                $("#foption").hide();
                $("#toption").hide();
                $("#soption").css("margin", "10px");
                $("#xoption").css("margin", "10px");
            } else if (ans == 'C') {
                $("#foption").hide();
                $("#xoption").hide();
                $("#soption").css("margin", "10px");
                $("#toption").css("margin", "10px");
            } else if (ans == 'D') {
                $("#soption").hide();
                $("#foption").hide();
                $("#toption").css("margin", "10px");
                $("#xoption").css("margin", "10px");
            } else {
                $("#foption").show();
                $("#soption").show();
                $("#toption").show();
                $("#xoption").show();
            }

            $('#lifeline_fifty').addClass("overlay_lifeline");
            document.getElementById("lifeline_fifty").disabled = true;

            lifeline_fifty = 1;

        }
    });

    //Progress Bar
    var progress;
    var progressPercentage;// progress bar

    progress = document.getElementsByClassName('progress-bar')[0];
    updateProgress();
    function updateProgress() {
        // progress bar will be updated as count goes up
        progressPercentage = Math.round((questionNo / count) * 100);

        progress.style.width = progressPercentage + '%';

    }
    var TotalQuizScore = count * 5;
    var coins = 0;
    var time = $('#mission_time').val();
    var mission_index_id = $('#mission_id').val();
    $(document.body).on('click', "label.element-animation", function (e) {

        var ques_no = document.getElementById("qid").innerHTML;

        check_click = 1;
        //ripple start
        var parent, ink, d, x, y;
        parent = $(this);
        if (parent.find(".ink").length == 0)
            parent.prepend("<span class='ink'></span>");
        ink = parent.find(".ink");
        ink.removeClass("animate");
        if (!ink.height() && !ink.width())
        {
            d = Math.max(parent.outerWidth(), parent.outerHeight());
            ink.css({height: "100px", width: "45%"});
            // ink.css({border-radius:"8px"});
        }

        x = ink.width() / 2;
        y = (ink.height() / 2) - 50;
        ink.css({top: y + 'px', left: x + 'px'}).addClass("animate");
        //ripple end

        var choice = $(this).parent().find('input:radio').val();
        console.log(choice);
        var anscheck = $(this).checking(questionNo, choice); //$( "#answer" ).html(  );      

        q[questionNo].UC = choice;
        if (anscheck) {

            correctCount++;
            q[questionNo]['result'] = "Correct";
            if (doubledip == 1) {
                doubledip = 0;
            }
        } else {

            if (doubledip == 1) {

            } else {
                wrongCount++;
                q[questionNo]['result'] = "Incorrect";
            }
        }

        var count_score = correctCount * 5;

        var wrong_count = wrongCount * 3;
        var point = (correctCount * 5) - wrong_count;
        var earnPerc = correctCount / count * 100;
        if (earnPerc >= 30 && earnPerc < 50) {
            coins = 1;
        } else if (earnPerc >= 50 && earnPerc < 80) {
            coins = 2;
        } else if (earnPerc >= 80 && earnPerc < 99) {
            coins = 3;
        } else if (earnPerc >= 99) {
            coins = 4;
        }


        document.getElementById("count_score").innerHTML = count_score;
        $('#coins').html(coins);

        console.log("CorrectCount:" + correctCount);
        console.log("WrongCount:" + wrongCount);
        if (doubledip == 1) {


        } else if (doubledip == 0) {
            $("#foption").addClass("disabled");
            $("#soption").addClass("disabled");
            $("#toption").addClass("disabled");
            $("#xoption").addClass("disabled");
            setTimeout(function () {

                $('#loadbar').show();
                $('#quiz').fadeOut();
                questionNo++;


                //Progress Bar
                updateProgress();
                document.getElementById("soption").style.display = "block";
                document.getElementById("foption").style.display = "block";
                document.getElementById("toption").style.display = "block";
                document.getElementById("xoption").style.display = "block";
                var foption = document.getElementById("foption");
                var soption = document.getElementById("soption");
                var toption = document.getElementById("toption");
                var xoption = document.getElementById("xoption");
                foption.style.removeProperty('margin');
                soption.style.removeProperty('margin');
                toption.style.removeProperty('margin');
                xoption.style.removeProperty('margin');
                foption.style.removeProperty('background-color');
                soption.style.removeProperty('background-color');
                toption.style.removeProperty('background-color');
                xoption.style.removeProperty('background-color');
                $(foption).removeClass("animated wow pulse slow  infinite");
                $(soption).removeClass("animated wow pulse slow  infinite");
                $(toption).removeClass("animated wow pulse slow  infinite");
                $(xoption).removeClass("animated wow pulse slow  infinite");
                if ((questionNo + 1) > count) {
                    document.getElementById('quiz_web').innerHTML = "";
                    // alert("Quiz completed, Now click ok to get your answer");
                    $('label.element-animation').unbind('click');

                    setTimeout(function () {
                        var toAppend = '';
                        $.each(q, function (i, a) {
                            toAppend += '<tr>'
                            toAppend += '<td>' + (i + 1) + '</td>';
                            toAppend += '<td>' + a.A + '</td>';
                            toAppend += '<td>' + a.UC + '</td>';
                            toAppend += '<td>' + a.result + '</td>';
                            toAppend += '</tr>'
                        });
                        //Insert Result In Database

                        var url = base_url + 'Quiz/StoreResult';
                        if (mission == 'mission') {
                            main_cat_id = main_id;
                            sub_cat_id = sub_id;
                        }
                        $.ajax({
                            type: "POST",
                            url: url,
                            data: {
                                user_id: user_id,
                                main_cat_id: main_cat_id,
                                sub_cat_id: sub_cat_id,
                                right_question: correctCount,
                                wrong_question: wrongCount,
                                coin: coins,
                                point: point
                            },
                            success: function (data) {
//                                get all data for result module
                                if (rquiz == 'rquiz') {
                                    var url = base_url + 'quiz/rquiz';
                                } else if (mission == 'mission') {

                                    var url = base_url + 'quiz/mission/' + mission_index_id + '/' + time;
                                } else {
                                    //Sub Category Id

                                    var url = base_url + 'quiz?id=' + sub_cat_id + '&&mid=' + main_cat_id;
                                }
                                var percent = (point / TotalQuizScore) * 100;
                                $.redirect(base_url + 'Quiz_result_model/Result/' + data, {'category': data, 'score': point, 'TotalQuizScore': TotalQuizScore, 'trueanswer': correctCount, 'falseanswer': wrongCount, 'url': url, 'percent': percent, 'id': data});
                            }
                        });
                        backsound.pause();

                        $('#quizResult').show();
                        $('#loadbar').fadeOut();
                        $('#result-of-question').show();
                        $('#graph-result').show();
                        chartMake();
                    }, 1000);
                } else {

                    $('#qid').html(questionNo + 1 + '.');
                    $('#qid_s').html(questionNo + 1 + '.');
                    $('input:radio').prop('checked', false);
                    setTimeout(function () {

                        $('#quiz').show();
                        $('#loadbar').fadeOut();
                    }, 1500);

                    if (q[questionNo]['image'] == 'undefined' || q[questionNo]['image'] == '' || q[questionNo]['image'] == null) {
                        $("#question_image").css("display", "none");
                    } else {
                        $("#question_image").css("display", "block");
                        document.getElementById("question_image").innerHTML = '<img src="' + base_url + 'Images/question/' + q[questionNo]['image'] + '" width="100" height="100" alt="question_image"/>'

                    }
                    $('#question').html(q[questionNo]['question']);
                    if (q[questionNo]['type'] == 1) {
                        $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
                        $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
                        $("#xoption").css("display", "none");
                        $("#toption").css("display", "none");
                    } else {
                        $($('#f-option').parent().find('label')).html(q[questionNo]['option_a']);
                        $($('#s-option').parent().find('label')).html(q[questionNo]['option_b']);
                        $($('#t-option').parent().find('label')).html(q[questionNo]['option_c']);
                        $($('#x-option').parent().find('label')).html(q[questionNo]['option_d']);
                    }
                    $('#foption').removeClass("disabled");
                    $('#soption').removeClass("disabled");
                    $('#toption').removeClass("disabled");
                    $('#xoption').removeClass("disabled");
                }
            }, 2000);
        }
    });
    $.fn.checking = function (qstn, ck) {

        var ans = q[questionNo]['answer'];
        if (ck != ans) {
            if (doubledip == 1) {

            } else {
                $(".ink").css("background", "rgba(255, 0, 0, 0.78)");
                var ans_no = q[questionNo]['answer'];
                if (ans_no == 'A') {
                    $("#foption").css("background-color", "#28a745");
                    $("#foption").addClass("animated wow pulse slow infinite");
                    $('#foption').removeClass("fadeInUp delay-0-1s");
                } else if (ans_no == 'B') {
                    $("#soption").css("background-color", "#28a745");
                    $("#soption").addClass("animated wow pulse slow infinite");
                    $('#soption').removeClass("fadeInUp delay-0-1s");
                } else if (ans_no == 'C') {
                    $("#toption").css("background-color", "#28a745");
                    $("#toption").addClass("animated wow pulse slow infinite");
                    $('#toption').removeClass("fadeInUp delay-0-1s");
                } else if (ans_no == 'D') {
                    $("#xoption").css("background-color", "#28a745");
                    $("#xoption").addClass("animated wow pulse slow infinite");
                    $('#xoption').removeClass("fadeInUp delay-0-1s");
                }
            }
            if (setsound()) {
                WrongSound.play();
            }

            return false;
        } else {

            $(".ink").css("background", "rgba(23, 149, 19, 0.78)");
            if (setsound()) {
                TrueSound.play();
            }
            return true;
        }
    };
// chartMake();
    function chartMake() {

        var chart = AmCharts.makeChart("chartdiv",
                {
                    "type": "serial",
                    "theme": "dark",
                    "dataProvider": [{
                            "name": "Correct",
                            "points": correctCount,
                            "color": "#00FF00",
                            "bullet": "http://i2.wp.com/img2.wikia.nocookie.net/__cb20131006005440/strategy-empires/images/8/8e/Check_mark_green.png?w=250"
                        }, {
                            "name": "Incorrect",
                            "points": q.length - correctCount,
                            "color": "red",
                            "bullet": "http://4vector.com/i/free-vector-x-wrong-cross-no-clip-art_103115_X_Wrong_Cross_No_clip_art_medium.png"
                        }],
                    "valueAxes": [{
                            "maximum": q.length,
                            "minimum": 0,
                            "axisAlpha": 0,
                            "dashLength": 4,
                            "position": "left"
                        }],
                    "startDuration": 1,
                    "graphs": [{
                            "balloonText": "<span style='font-size:13px;'>[[category]]: <b>[[value]]</b></span>",
                            "bulletOffset": 10,
                            "bulletSize": 52,
                            "colorField": "color",
                            "cornerRadiusTop": 8,
                            "customBulletField": "bullet",
                            "fillAlphas": 0.8,
                            "lineAlpha": 0,
                            "type": "column",
                            "valueField": "points"
                        }],
                    "marginTop": 0,
                    "marginRight": 0,
                    "marginLeft": 0,
                    "marginBottom": 0,
                    "autoMargins": false,
                    "categoryField": "name",
                    "categoryAxis": {
                        "axisAlpha": 0,
                        "gridAlpha": 0,
                        "inside": true,
                        "tickLength": 0
                    }
                });
    }

}
);
