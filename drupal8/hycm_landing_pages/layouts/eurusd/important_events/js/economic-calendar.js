/**
 * Created by Egor Kuznetski on 03.10.2017.
 */

jQuery.ajax({
    type: "POST",
    url: "https://authorization.fxstreet.com/token",
    contentType: "application/x-www-form-urlencoded",
    dataType: "json",
    data: {
        grant_type: "client_credentials",
        client_id: 'A73A85B093BF4AADAEFB',
        client_secret: 'AB44A492F25F427DB34ED21E75C920'
    },
    success: function (data) {

    }
});

var path = window.location.pathname.split('/');
var alias = jQuery("body").attr("class").split(" ")[1];
var pageClass = jQuery("#main").find(".layout").attr("class");
var language = jQuery("html").attr("lang");

//language = "en";

(function(a,b){
    var flag=0;
    if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))
    {
        (function ($) {

            var script = "https://calendar.fxstreet.com/scripts/mini";
            if(alias != "market-outlook"){
                var fxcalendar_config = '{host: "https://calendar.fxstreet.com", eventurl: "https://api.fxstreet.com/calendar.aspx?id=", pageculture: "en-us"}';
            }else{
                var fxcalendar_config = '{host: "https://calendar.fxstreet.com", eventurl: "https://api.fxstreet.com/calendar.aspx?id=", pageculture: "en-us", volatility: "2"}';
                var fxcalendar_config2 = '{host: "https://calendar.fxstreet.com", eventurl: "https://api.fxstreet.com/calendar.aspx?id=", pageculture: "en-us", volatility: "3"}';
            }

            function waitForElement(){
                window.setTimeout(function(){
                     $("head").append('<script>var fxcalendar_config = ' + fxcalendar_config + '</script>');
                     $("head").append('<script type="text/javascript" src="' + script + '"></script>');
                     if(alias == "market-outlook") {
                        $("head").append('<script>var fxcalendar_config2 = ' + fxcalendar_config2 + '</script>');
                        $("head").append('<script type="text/javascript" src="' + script + '"></script>');
                     }
                },5000)
            }
            waitForElement();


        })(jQuery);

    } else{
        (function ($) {

            var script = "/themes/custom/hycm/assets/js/fxstreet.calendar.js?dfdsf";
            if(pageClass.indexOf("economic-calendar") == 0)
            {
                var fxcalendar_config = '{' +
                    '"host":' +'"https://calendar.fxstreet.com",' +
                    '"gridselector":' +'"#fxst_grid",' +
                    '"filterselector":' +'"#fxst_filter",' +
                    '"columns":' +'"None",' +
                    '"showeventlink":' +'"1",' +
                    '"showcountrylink":' +'"1",' +
                    '"culture":"' +language+'",' +
                    '"countryurl":' +'"/\'+path[1]+\'/country?c=\'+language+\'&id=",' +
                    '"eventurl":' +'"https://api.fxstreet.com/calendar.aspx?id="' +

                    '}';
            }
            if(pageClass.indexOf("eur_usd") == 0)
            {
                var fxcalendar_config = '{' +
                    '"host":' +'"https://calendar.fxstreet.com",' +
                    '"gridselector":' +'"#fxst_grid",' +
                    '"filterselector":' +'"#fxst_filter",' +
                    '"columns":' +'"None",' +
                    '"view":' +'"day",' +
                    '"showeventlink":' +'"1",' +
                    '"showcountrylink":' +'"1",' +
                    '"culture":"' +language+'",' +
                    '"countryurl":' +'"/\'+path[1]+\'/country?c=\'+language+\'&id=",' +
                    '"eventurl":' +'"https://api.fxstreet.com/calendar.aspx?id="' +

                    '}';
            }
            if (pageClass.indexOf("market-outlook"))
            {
                var fxcalendar_config = '{' +
                    '"host":' +'"https://calendar.fxstreet.com",' +
                    '"gridselector":' +'"#fxst_grid",' +
                    '"filterselector":' +'"#fxst_filter",' +
                    '"columns":' +'"None",' +
                    '"volatility":' +'2,' +
                    '"view":' +'"day",' +
                    '"showeventlink":' +'"1",' +
                    '"showcountrylink":' +'"1",' +
                    '"culture":"' +language+'",' +
                    '"countryurl":' +'"/\'+path[1]+\'/country?c=\'+language+\'&id=",' +
                    '"eventurl":' +'"https://api.fxstreet.com/calendar.aspx?id="' +

                    '}';
                var fxcalendar_config2 = '{' +
                    '"host":' +'"https://calendar.fxstreet.com",' +
                    '"gridselector":' +'"#fxst_grid2",' +
                    '"filterselector":' +'"#fxst_filter",' +
                    '"columns":' +'"None",' +
                    '"volatility":' +'3,' +
                    '"view":' +'"day",' +
                    '"showeventlink":' +'"1",' +
                    '"showcountrylink":' +'"1",' +
                    '"culture":"' +language+'",' +
                    '"countryurl":' +'"/\'+path[1]+\'/country?c=\'+language+\'&id=",' +
                    '"eventurl":' +'"https://api.fxstreet.com/calendar.aspx?id="' +

                    '}';
            }



            function waitForElement()
            {
                window.setTimeout(function()
                {
                     $("head").append('<script>var fxcalendar_config = ' + fxcalendar_config + '</script>');
                     $("head").append('<script type="text/javascript" src="' + script + '"></script>');

                     // work around in fxstreet.calendar.js to load specific volatility
                     if(alias == "market-outlook")
                     {
                        $("head").append('<script>var fxcalendar_config2 = ' + fxcalendar_config2 + '</script>');
                        $("head").append('<script type="text/javascript" src="' + script + '"></script>');
                     }
                },1000)
            }


            waitForElement();

        })(jQuery);

    }
})(navigator.userAgent||navigator.vendor||window.opera,'http://detectmobilebrowser.com/mobile');



