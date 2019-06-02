// (function () {
//     var query = window.location.search.substring(1),
//         vars = query.split("&"),
//         date = new Date();
//     date.setDate(date.getYear() + 1);
//     for (var i=0;i<vars.length;i++) {
//         var pair = vars[i].split("=");
//         switch(pair[0]) {
//             case 'click_id':
//                 pair[2] = 'CLICKIDINT';
//                 break;
//             case 'utm_affiliatecode':
//                 pair[2] = 'AFFILIATEINT';
//                 break;
//             case 'eaid':
//                 pair[2] = 'EAIDINT';
//                 break;
//             case 'campaignid':
//                 pair[2] = 'CAMPAINIDINT';
//                 break;
//             case 'a_id':
//                 pair[2] = 'A_ID';
//                 break;
//         }
//         document.cookie = pair[2]+"="+pair[1]+"; expires="+date+"; path=/;domain=.hycm.com";
//     }
// }());
function getUrlVar(variable)
{
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i=0;i<vars.length;i++) {
        var pair = vars[i].split("=");
        if(pair[0] == variable){return pair[1];}
    }
    return(false);
}
if(getUrlVar('subid')){
    Cookies.set('subid', getUrlVar('subid'), {expires: 365});
}
if(getUrlVar('subid2')){
    Cookies.set('subid2', getUrlVar('subid2'), {expires: 365});
}
if(getUrlVar('click_id') || getUrlVar('clickid')){
    var clickID = getUrlVar('click_id') ? getUrlVar('click_id') : getUrlVar('clickid');
    Cookies.set('CLICKIDINT', clickID, {expires: 365});
}
if(getUrlVar('utm_affiliatecode')){
    Cookies.set('AFFILIATEINT', getUrlVar('utm_affiliatecode'), {expires: 365});
}
if(getUrlVar('eaid')){
    Cookies.set('EAIDINT', getUrlVar('eaid'), {expires: 365});
}
if(getUrlVar('campaignid')){
    Cookies.set('CAMPAINIDINT', getUrlVar('campaignid'), {expires: 365});
}
if(getUrlVar('utm_source') === 'qrcode' ){  /// For users from QR code
    Cookies.set('CAMPAINIDINT', '701D0000001dsi8', {expires: 365});
}
if(getUrlVar('a_id')){
    Cookies.set('A_ID', getUrlVar('a_id'), {expires: 365});
}
if(getUrlVar('subid')){
    Cookies.set('subid', getUrlVar('subid'), {expires: 365});
}
if(getUrlVar('subid2')){
    Cookies.set('subid2', getUrlVar('subid2'), {expires: 365});
}

jQuery(function ($) {

    var lang = window.location.pathname.replace(/^\/([^\/]*).*$/, '$1'),
        urlArr = window.location.href.split("/"),
        protocol = urlArr[0],
        newUrl = protocol + '//'+document.location.hostname+'/'+lang+'/register',
        apiUrl = 'https://api.hycm.com/clients',
        parsleyEmailError,
        parsleyEmailEmpty,
        parsleyCountryPrefixError,
        emailUsed,
        countries,
        countryCodeFromGoogle,
        countryGlobalCode,
        countriesUrl = "/themes/custom/hycm/assets/registration/countries.json";
    switch(lang) {
        case 'ar':
            parsleyEmailError = 'هذه القيمة غير صالحة. الرجاء إدخال تفاصيل صحيحة.';
            parsleyEmailEmpty = 'هذه القيمة مطلوبة.';
            emailUsed = 'قم بتسجيل دخول أو سجل بإيميل جديد .هذا الايميل مسجل مسبقاً.';
            parsleyCountryPrefixError = 'يبدو أن هذه القيمة غير صالحة.';
            break;
        case 'en':
            parsleyEmailError = 'This value is invalid. Please enter correct details.';
            parsleyEmailEmpty = 'This value is required.';
            emailUsed = 'This email is already registered. Please sign in or enter another email.';
            parsleyCountryPrefixError = 'This value seems to be invalid.';
            break;
        case 'it':
            parsleyEmailError = 'Questo dato non è valido. Ti preghiamo di inserire dati corretti.';
            parsleyEmailEmpty = 'Campo obbligatorio.';
            emailUsed = 'Questa email e\' gia\' in uso. Inserire una nuova email.';
            parsleyCountryPrefixError = 'Il dato inserito risulta non valido.';
            break;
        case 'ru':
            parsleyEmailError = 'Данные неверны. Пожалуйста, введите правильные данные.';
            parsleyEmailEmpty = 'Это поле необходимо заполнить.';
            emailUsed = 'Данный email адрес уже зарегистрирован. Пожалуйста авторизуйтесь на сайте или введите другой email.';
            parsleyCountryPrefixError = 'Похоже, что это поле заполнено неверно.';
            break;
        case 'es':
            parsleyEmailError = 'Este valor es inválido. Por favor ingrese detalles correctos.';
            parsleyEmailEmpty = 'Este valor es requerido.';
            emailUsed = 'Este correo electrónico ya está registrada. Por favor, ingrese o entrar en otro correo electrónico.';
            parsleyCountryPrefixError = 'Este valor parece ser inválido.';
            break;
        case 'pl':
            parsleyEmailError = 'Este valor no es válido, ingrese los detalles correctos.';
            parsleyEmailEmpty = 'Ta wartość jest wymagana.';
            emailUsed = 'Ten adres email jest już zarejestrowany, prosimy zaloguj się lub wprowadź inny adres email.';
            parsleyCountryPrefixError = 'Wartość ta wydaje się być niepoprawna.';
            break;
        case 'fr':
            parsleyEmailError = 'Cette valeur est invalide, veuillez entrer des informations correctes.';
            parsleyEmailEmpty = 'Cette valeur est nécessaire.';
            emailUsed = 'Cette adresse e-mail est déjà enregistrée, veuillez vous inscrire ou utiliser une autre adresse e-mail.';
            parsleyCountryPrefixError = 'Cette valeur semble être invalide.';
            break;
        case 'cz':
            parsleyEmailError = 'This value is invalid, please enter correct details.';
            parsleyEmailEmpty = 'This value is required.';
            emailUsed = 'This email is already registered, please sign in or enter another email.';
            parsleyCountryPrefixError = 'This value seems to be invalid.';
            break;
        case 'sv':
            parsleyEmailError = 'Detta värde är ogiltigt, vänligen ange ett korrekt värde.';
            parsleyEmailEmpty = 'Detta värde är obligatoriskt';
            emailUsed = 'Denna mejladress finns redan registrerad. Vänligen logga in eller ange en annan mejladress';
            parsleyCountryPrefixError = 'Detta värde är ogiltigt';
            break;
        case 'fa':
            parsleyEmailError = 'این مقدار نامعتبر است، لطفا جزئیات صحیح را وارد کنید';
            parsleyEmailEmpty = 'این مقدار مورد نیاز است';
            emailUsed = 'این ایمیل قبلا ثبت شده است، لطفا وارد شوید یا ایمیل دیگری را ارائه کنید.';
            parsleyCountryPrefixError = 'به نظر می‌رسد این مقدار نامعتبر است';
            break;
        case 'de':
            parsleyEmailError = 'Geben Sie einen Wert ein zwischen 1920';
            parsleyEmailEmpty = 'Dieser Wert ist erforderlich';
            emailUsed = 'Diese E-Mail-Adresse ist bereits registriert, bitte melden Sie sich an oder geben Sie eine andere E-Mail-Adresse ein';
            parsleyCountryPrefixError = 'Dieser Wert scheint ungültig zu sein';
            break;

        default:
            parsleyEmailError = 'This value is invalid. Please enter correct details.';
            parsleyEmailEmpty = 'This value is required.';
            emailUsed = 'This email is already registered. Please sign in or enter another email.';
    }
    function addLabelToInput(el){
        if(el.val().length){
            el.parent().addClass('has-label');
            el.parsley().validate();
        }
    }
    $(document).ready(function () {
        $.ajax({
            dataType: "json",
            url: countriesUrl,
            async: false,
            success: function(data){
                countries = data;
            }
        });
        $('body').prepend('<div id="redirect_animation"></div>');
    });
    $('input:not(input[type="hidden"])').each(function () {
        addLabelToInput($(this));
    }).on('change',function () {
        addLabelToInput($(this));
    });


    //geolocation
    var requestJson = {
        "radioType": "gsm"
    };
    $.ajax({
        dataType: "json",
        url: "https://www.googleapis.com/geolocation/v1/geolocate?key=AIzaSyDO5hn6OmbMkuwdx5q-_VZwGLUHkZ62xGw",
        method: 'POST',
        contentType: "application/json; charset=utf-8",
        data : JSON.stringify(requestJson)
    }).done(function(data) {
        $.ajax({
            dataType: "json",
            url: "https://maps.googleapis.com/maps/api/geocode/json?latlng="+data['location'].lat+","+data['location'].lng+"&key=AIzaSyAzgyfH67CbFgapjc3EGAl6hVuWyITs4fc",
            method: 'POST'
        }).done(function(data) {
            function getCountry(addrComponents) {
                for (var i = 0; i < addrComponents.length; i++) {
                    if (addrComponents[i].types[0] == "country") {
                        return addrComponents[i].short_name;
                    }
                    if (addrComponents[i].types.length == 2) {
                        if (addrComponents[i].types[0] == "political") {
                            return addrComponents[i].short_name;
                        }
                    }
                }
                return false;
            }
            countryCodeFromGoogle = getCountry(data['results'][0].address_components);
            if(countryCodeFromGoogle.length!=0) {
                $.each(countries, function (value, item) {
                    var countryCode = item.code,
                        countryName = item.name,
                        countryPhoneCode = item.phoneCode;
                    if (countryCode == countryCodeFromGoogle) {
                        countryGlobalCode = countryCode;
                        $('input#country').val(countryName);
                        $('input#phone_prefix').val(countryPhoneCode).attr('data-val',countryPhoneCode.replace('+',''));
                        $('#phone_prefix_real, #phone_prefix_fake').val(countryPhoneCode).attr('data-val',countryPhoneCode);
                    }
                });
            }
        });
    });

    $(window).on('resize',function () {
        var pwW = $('.phone_wrap').width();
        $('.phone_wrap ul.options_list').width(pwW);
    });

    if($(window).width() < 992 ){


        $(window).load(function () {
            $('#phone_prefix').each(function () {
                var el = $(this).attr('id'),
                    real = el+'_real',
                    fake = el+'_fake';
                $('#' + el).attr('id',fake);
                $('input#' + fake).after('<select name="phone_prefix" class="transparent_select" id="' + real + '" data-val="'+$('input#' + fake).val()+'"></select>');
                $.each( countries, function( value, item ){
                    var currentList = $('select#' + real),
                        countryPhoneCode = item.phoneCode,
                        countryName = item.name,
                        selected = countryGlobalCode==item.code? ' selected="selected"':'';
                    currentList.append('<option value="'+countryPhoneCode+'"'+selected+'>'+countryName+' '+countryPhoneCode+'</option>');

                });
            });
        });
        $(document).on('change', 'select[id*="_real"]', function (e) {
            var el = $(this).attr('id').replace('_real',''),
                val = $(this).val(),
                fakeEl = $('#' + el+'_fake');
            fakeEl.val(val);
        }).on('focus click','input[id*="_fake"]', function (e) {
            var el = $(this).attr('id').replace('_fake',''),
                realEl = $('#' + el+'_real');
            realEl.click();
        });
        $('select').each(function () {
            $(this).removeClass('hidden');
        });
    }
    $(window).load(function () {
        if($(window).width() >= 992){
            $('header').after('<div class="overlay"></div>');

            $(".prefix").each(function (index) {
                var i = index + 'd',
                    currentInputField = $(this);
                currentInputField.addClass('full_list').attr('data-selectId',i).after('<ul class="options_list prefixes" data-selectId="'+i+'" style="width: '+$(this).closest('.phone_wrap').width()+'px!important;"></ul>').closest('.select_wrap_item').attr('data-selectId',i);
            }).on('keyup focusin', function (e) {// autocomplete
                var currentInputField = $(this),
                    currentInputVal = $(this).val(),
                    currentList = $(this).parent().find('ul.options_list.prefixes');

                currentList.mCustomScrollbar("destroy");
                if(currentInputVal.trim().length!=0 && !currentInputField.hasClass('full_list')){
                    currentList.find('li, span').remove();
                    $.each( countries, function( value, item ){
                        var countryName = item.name,
                            countryPhoneCode = item.phoneCode,
                            countryCode = item.code.toLowerCase();
                        if(countryPhoneCode.indexOf(currentInputVal) !=-1){
                            currentList.append('<li class="flag-'+countryCode+'" data-val="'+countryPhoneCode+'"><span></span>'+countryName+' '+countryPhoneCode+'</li>');
                        }
                    });

                    if(currentList.find('li').length == 0){
                        currentList.html('<span class="cant_find">We so sorry. We can\'t find this prefix</span>');
                    }
                }else{
                    currentInputField.removeClass('full_list');
                    currentList.find('li, span').remove();
                    $.each( countries, function( value, item ) {
                        var countryName = item.name,
                            countryPhoneCode = item.phoneCode,
                            countryCode = item.code.toLowerCase();
                        currentList.append('<li class="flag-'+countryCode+'" data-val="'+countryPhoneCode+'"><span></span>'+countryName+' '+countryPhoneCode+'</li>');
                    });
                }
                setTimeout(function () {
                    currentList.mCustomScrollbar({
                        live: "once"
                    });
                },0)

            });
        }
    });


    $('#phone_prefix').on('keypress',function (e) {
        if ($(this).val().length >= 5 ) {
            e.preventDefault();
        }
    });

    $('.checkbox').on('change','input[type="checkbox"]',function () {
        $(this).parent().toggleClass('active');
    });


    $(document).on('focusin focusout', '.field-input', function (event) {
        if (event.type == 'focusin') {
            $(event.target).parent().addClass('is-focused has-label');
        } else {
            $parent = $(event.target).parent();
            if (!$(event.target).val().length) {
                $parent.removeClass('has-label');
            }
            $parent.removeClass('is-focused');
        }
    });

    $('#shortForm input,#shortForm select').attr({
        'data-parsley-trigger':'focusout',
        'required':true
    });
    $('#shortForm .btn').append('<span class="process"></span>');

    $(document).on('click', function (e) {
        var target = $(e.target),
            overlay = $('.overlay'),
            curSelectId = target.closest('.select_wrap_item').attr('data-selectId');
        if(!$(this).parent().hasClass('open')) { // if select close

            if(target.closest('.mCSB_draggerContainer').length==0) {//if this not scrollbar (this prevent close after click on open input(not list))


                $('.select_wrap_item, ul.options_list').removeClass('open');
                if (target.is('.btn.select_btn')) { // click on btn
                    $('.options_list[data-selectId="' + curSelectId + '"]').fadeIn().closest('.select_wrap_item').addClass('open');
                } else {
                    if (target.is(".options_list li")) { // click on options item

                        var selectedVal = target.attr('data-val'),
                            selectedHtml = target.html().replace(/(<([^>]+)>)/ig,"");
                        if(target.closest('.options_list.countries').length != 0) {
                            $('input[data-selectId="' + curSelectId + '"]').attr('data-val', selectedVal).val(selectedHtml).parsley().validate();
                        }else if(target.closest('.options_list.prefixes').length != 0){
                            $('input[data-selectId="' + curSelectId + '"]').val(selectedVal).parsley().validate();
                        }else{
                            $('.select_btn[data-selectId="' + curSelectId + '"]').html(selectedHtml);
                            $('select[data-selectId="' + curSelectId + '"]').val(selectedVal).parsley().validate();
                            if($('.select_btn[data-selectId="' + curSelectId + '"]').hasClass('temp_after_error')){
                                $('.select_btn[data-selectId="' + curSelectId + '"]').removeClass('temp_after_error');
                                $('.select_wrap_item, ul.options_list').removeClass('open');
                                $('ul.options_list').fadeOut();
                            }
                        }

                    }
                }
            }
        }else{ // if select alredy open, just close it
            if(!$(this).is('.country') && !$(this).is('.prefix') && !$(this).hasClass('temp_after_error')){
                $('.select_wrap_item, ul.options_list').removeClass('open');
                $('ul.options_list').fadeOut();
            }else if($(this).hasClass('temp_after_error')){
                $(this).removeClass('temp_after_error')
            }
        }
    }).on('focusin focusout','.btn.select_btn',function (e) {
        if(e.type=='focusin' && !$(e.target).parent().hasClass('open')){
            var target = $(e.target),
                curSelectId = target.closest('.select_wrap_item').attr('data-selectId');
            $('.options_list[data-selectId="' + curSelectId + '"]').fadeIn().closest('.select_wrap_item').addClass('open');
        }else if(e.type=='focusout'){
            $('.select_wrap_item, ul.options_list').removeClass('open');
            $('ul.options_list').fadeOut();
        }
    }).on('click','#shortForm button[type="submit"]',function (e) {
        e.preventDefault();
        if($('#shortForm').hasClass('waiting_validation')){
            $(e.target).css('color','transparent').parent().find('span.process').fadeIn().css('display','block');
            var sbmtbtn = $(e.target),
                waiValidat = setInterval(function () {
                    if(!$('#shortForm').hasClass('waiting_validation')){
                        sbmtbtn.removeAttr('style').parent().find('span.process').fadeOut();
                        $(sbmtbtn).click();
                        clearInterval( waiValidat );
                    }
                },100)
        }else if($('#email').hasClass('parsley-error')){
            $('#shortForm').parsley().validate();
            $('html,body').animate({
                scrollTop: $("#shortForm .error:eq(0)").offset().top - 50
            }, 'fast', function () {
                if ($("#shortForm .error:eq(0) input").length != 0) {
                    $("#shortForm .error:eq(0) input").click().focus();
                } else if ($("#shortForm .error:eq(0) select").length != 0) {
                    $("#shortForm .error:eq(0) .select_btn").click().focus().addClass('temp_after_error');
                }
            });
            return false;
        }else{
            if ($('#shortForm').parsley().validate()) {
                // $('#phone_prefix_rrreal').val($('#phone_prefix_fake').attr('data-val'));
                var extra = [],
                    extraStr = '';
                extra.push("language="+$('html').attr('lang'));
                if(Cookies.get('CLICKIDINT'))
                    extra.push('click_id='+Cookies.get('CLICKIDINT'));

                if(Cookies.get('AFFILIATEINT'))
                    extra.push('affiliate_code='+Cookies.get('AFFILIATEINT'));

                if(Cookies.get('EAIDINT'))
                    extra.push('eaid='+Cookies.get('EAIDINT'));

                if(Cookies.get('CAMPAINIDINT'))
                    extra.push('campaign_id='+Cookies.get('CAMPAINIDINT'));

                if(Cookies.get('subid'))
                    extra.push('subId='+Cookies.get('subid'));

                if(Cookies.get('subid2'))
                    extra.push('subId2='+Cookies.get('subid2'));


                if(extra.length > 0)
                    extraStr = '&' + extra.join("&");
                document.getElementById("btn_sbmt").setAttribute("disabled", "disabled");
                $.ajax({
                    method: "POST",
                    url: apiUrl+"/add",
                    data: $('#shortForm').serialize()+extraStr+'&promotion=registration25'
                }).done(function( response ) {
                    if(response['response'].messageCode == '5'){
                        ga('send', 'event', 'registration', 'send', 'short_form');
                        $('#redirect_animation').fadeIn();
                        var formSerialized = $('#shortForm').find('input,select').serializeArray();
                        localStorage.setItem("userlanding",JSON.stringify(formSerialized));
                        Cookies.set('step_1', JSON.stringify(formSerialized), {expires: 365});
                        console.log(formSerialized);
                        Cookies.set('lastStep', 1, {expires: 365});
                        // Cookies.set('tokenLP', response['response'].data, {expires: 1});
                        Cookies.set('fromLP', 'true', {expires: 1});
                        setTimeout(function () {
                            window.location.replace(newUrl);
                        },400)
                    }else{
                        document.getElementById("btn_sbmt").removeAttribute("disabled");
                        console.log(response);
                    }
                })
                    .fail(function () {
                        document.getElementById("btn_sbmt").removeAttribute("disabled");
                    });
            } else {
                $('html,body').animate({
                    scrollTop: $("#shortForm .error:eq(0)").offset().top - 50
                }, 'fast', function () {
                    if ($("shortForm .error:eq(0) input").length != 0 && $("shortForm .error:eq(0) input").type == 'text') {
                        $("shortForm .error:eq(0) input").click().focus();
                    } else if ($("shortForm .error:eq(0) select").length != 0) {
                        $("shortForm .error:eq(0) .select_btn").click().focus().addClass('temp_after_error');
                    }
                });

            }
        }
    });

    window.Parsley.on('field:error', function() {
        if(this.$element.is('#email')){
            var errorMessage;
            if(this.$element.val().length !==0){
                errorMessage = parsleyEmailError
            }else{
                errorMessage = parsleyEmailEmpty
            }
            this.$element.parsley().reset();
            this.$element.parsley().addError('emailUsed', {message: errorMessage, updateClass: true});
        }
        this.$element.parent().addClass('error').removeClass('success');
    });
    window.Parsley.on('field:success', function() {
        if(this.$element.is('#email')){
            var el = this.$element;

            $('#shortForm').addClass('waiting_validation');
            $.ajax({
                url: apiUrl+'/exist',
                type: "POST",
                data: 'username=' + encodeURIComponent(el.val()),
                success: function( response ){
                    if (response['response'].messageCode == '81') {
                        el.parent().addClass('success').removeClass('error');
                        el.parsley().removeError('emailUsed');
                        el.removeClass('email_used');
                    }else{
                        el.parsley().reset();
                        el.addClass('email_used').parent().addClass('error').removeClass('success');
                        el.parsley().addError('emailUsed', {message: emailUsed, updateClass: true});
                    }
                    $('#shortForm').removeClass('waiting_validation');
                }
            })
        }
        this.$element.parent().addClass('success').removeClass('error');
    });
    window.Parsley.addValidator('prefix', {
        requirementType: 'string',
        validateString: function(val) {

            function checkPrefix() {
                var check = false;
                if(countries===undefined){
                    $.ajax({
                        dataType: "json",
                        url: countriesUrl,
                        async: false,
                        success: function( data ){
                            $.each( data, function( value, item ) {
                                if(item.phoneCode === val || item.phoneCode === '+'+val){
                                    check = true;
                                }
                            });
                        }
                    });
                    return check;
                }else{
                    $.each( countries, function( value, item ) {
                        if(item.phoneCode === val || item.phoneCode === '+'+val){
                            check = true;
                        }
                    });
                    return check;
                }

            }
            return checkPrefix();

        },
        messages: {
            en: parsleyCountryPrefixError
        }
    });

    $('.prefix').on('focusout', function (e) {
        $(this).addClass('full_list');
    });


});
