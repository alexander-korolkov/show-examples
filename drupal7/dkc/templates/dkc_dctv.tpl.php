<?php
/**
 * @file
 * Represents template for displaying DCTV section.
 */
?>

<?php $detect = mobile_detect_get_object(); ?>
<?php $is_mobile = $detect->isMobile(); ?>
<?php $is_tablet = $detect->isTablet(); ?>
<script type="text/javascript">
    window.NREUM || (NREUM = {}), __nr_require = function(t, e, n){function r(n){if (!e[n]){var o = e[n] = {exports:{}}; t[n][0].call(o.exports, function(e){var o = t[n][1][e]; return r(o || e)}, o, o.exports)}return e[n].exports}if ("function" == typeof __nr_require)return __nr_require; for (var o = 0; o < n.length; o++)r(n[o]); return r}({1:[function(t, e, n){function r(){}function o(t, e, n){return function(){return i(t, [(new Date).getTime()].concat(u(arguments)), e?null:this, n), e?void 0:this}}var i = t("handle"), a = t(2), u = t(3), c = t("ee").get("tracer"), f = NREUM; "undefined" == typeof window.newrelic && (newrelic = f); var s = ["setPageViewName", "setCustomAttribute", "finished", "addToTrace", "inlineHit"], p = "api-", l = p + "ixn-"; a(s, function(t, e){f[e] = o(p + e, !0, "api")}), f.addPageAction = o(p + "addPageAction", !0), e.exports = newrelic, f.interaction = function(){return(new r).get()}; var d = r.prototype = {createTracer:function(t, e){var n = {}, r = this, o = "function" == typeof e; return i(l + "tracer", [Date.now(), t, n], r), function(){if (c.emit((o?"":"no-") + "fn-start", [Date.now(), r, o], n), o)try{return e.apply(this, arguments)} finally{c.emit("fn-end", [Date.now()], n)}}}}; a("setName,setAttribute,save,ignore,onEnd,getContext,end,get".split(","), function(t, e){d[e] = o(l + e)}), newrelic.noticeError = function(t){"string" == typeof t && (t = new Error(t)), i("err", [t, (new Date).getTime()])}}, {}], 2:[function(t, e, n){function r(t, e){var n = [], r = "", i = 0; for (r in t)o.call(t, r) && (n[i] = e(r, t[r]), i += 1); return n}var o = Object.prototype.hasOwnProperty; e.exports = r}, {}], 3:[function(t, e, n){function r(t, e, n){e || (e = 0), "undefined" == typeof n && (n = t?t.length:0); for (var r = - 1, o = n - e || 0, i = Array(o < 0?0:o); ++r < o; )i[r] = t[e + r]; return i}e.exports = r}, {}], ee:[function(t, e, n){function r(){}function o(t){function e(t){return t && t instanceof r?t:t?u(t, a, i):i()}function n(n, r, o){t && t(n, r, o); for (var i = e(o), a = l(n), u = a.length, c = 0; c < u; c++)a[c].apply(i, r); var s = f[m[n]]; return s && s.push([w, n, r, i]), i}function p(t, e){g[t] = l(t).concat(e)}function l(t){return g[t] || []}function d(t){return s[t] = s[t] || o(n)}function v(t, e){c(t, function(t, n){e = e || "feature", m[n] = e, e in f || (f[e] = [])})}var g = {}, m = {}, w = {on:p, emit:n, get:d, listeners:l, context:e, buffer:v}; return w}function i(){return new r}var a = "nr@context", u = t("gos"), c = t(2), f = {}, s = {}, p = e.exports = o(); p.backlog = f}, {}], gos:[function(t, e, n){function r(t, e, n){if (o.call(t, e))return t[e]; var r = n(); if (Object.defineProperty && Object.keys)try{return Object.defineProperty(t, e, {value:r, writable:!0, enumerable:!1}), r} catch (i){}return t[e] = r, r}var o = Object.prototype.hasOwnProperty; e.exports = r}, {}], handle:[function(t, e, n){function r(t, e, n, r){o.buffer([t], r), o.emit(t, e, n)}var o = t("ee").get("handle"); e.exports = r, r.ee = o}, {}], id:[function(t, e, n){function r(t){var e = typeof t; return!t || "object" !== e && "function" !== e? - 1:t === window?0:a(t, i, function(){return o++})}var o = 1, i = "nr@id", a = t("gos"); e.exports = r}, {}], loader:[function(t, e, n){function r(){if (!h++){var t = y.info = NREUM.info, e = s.getElementsByTagName("script")[0]; if (t && t.licenseKey && t.applicationID && e){c(m, function(e, n){t[e] || (t[e] = n)}); var n = "https" === g.split(":")[0] || t.sslForHttp; y.proto = n?"https://":"http://", u("mark", ["onload", a()], null, "api"); var r = s.createElement("script"); r.src = y.proto + t.agent, e.parentNode.insertBefore(r, e)}}}function o(){"complete" === s.readyState && i()}function i(){u("mark", ["domContent", a()], null, "api")}function a(){return(new Date).getTime()}var u = t("handle"), c = t(2), f = window, s = f.document, p = "addEventListener", l = "attachEvent", d = f.XMLHttpRequest, v = d && d.prototype; NREUM.o = {ST:setTimeout, CT:clearTimeout, XHR:d, REQ:f.Request, EV:f.Event, PR:f.Promise, MO:f.MutationObserver}, t(1); var g = "" + location, m = {beacon:"bam.nr-data.net", errorBeacon:"bam.nr-data.net", agent:"js-agent.newrelic.com/nr-963.min.js"}, w = d && v && v[p] && !/CriOS/.test(navigator.userAgent), y = e.exports = {offset:a(), origin:g, features:{}, xhrdzable:w}; s[p]?(s[p]("DOMContentLoaded", i, !1), f[p]("load", r, !1)):(s[l]("onreadystatechange", o), f[l]("onload", r)), u("mark", ["firstbyte", a()], null, "api"); var h = 0}, {}]}, {}, ["loader"]);</script>
<link rel="stylesheet"
      href="//da7xgjtj801h2.cloudfront.net/2015.1.408/styles/kendo.common-bootstrap.min.css"/>
<link rel="stylesheet"
      href="//da7xgjtj801h2.cloudfront.net/2015.1.408/styles/kendo.bootstrap.min.css"/>
<link rel="stylesheet"
      href="//da7xgjtj801h2.cloudfront.net/2015.1.408/styles/kendo.dataviz.min.css"/>
<link rel="stylesheet"
      href="//da7xgjtj801h2.cloudfront.net/2015.1.408/styles/kendo.dataviz.bootstrap.min.css"/>
<script type="text/javascript"
src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<script
src="//da7xgjtj801h2.cloudfront.net/2015.1.408/js/kendo.all.min.js"></script>
<script type="text/javascript">
    var meta_id = '',
            currentClipIndex = 0,
            clipList = eval(),
            downloadLinks = eval();</script>
<script type="text/javascript"
src="/sites/all/themes/raleway_sub/js/MvpHeader.js"></script>
<script type="text/javascript"
src="/sites/all/themes/raleway_sub/js/gmp.js"></script>
<script type="text/javascript">

    $(function () {
    GMP.setClientBranding('ffffff', '');
    });</script>
<div class=" dctv-description">
    <div class="content">
        <div class="news-hd">
            <a href="/dctv/welcome-dctv"><img alt="dctv logo" src="<?php echo $logo; ?>" class="img-responsive rtecenter"></a>
            <h1 class="rtecenter"><?php //print $title; ?></h1>
            <div class="news-infobox">
                <p><?php print $description; ?></p>
            </div>
        </div>
    </div>
</div>
<div class=" dctv-video">
    <div id="all">
        <div id="player-wrap">
            <div class="player" id="player-section">
                <div id="video-wrap">
                   <div id="video"><iframe autoplay="no" title="Content hosted by Granicus.com" frameborder="0" longdesc="This frame provides a link to the The County of Onlow's Live and Archived Media." name="granicusFrame" scrolling="yes" src="https://dekalbcountyga.granicus.com/MediaPlayer.php?publish_id=8&embed=1&autostart=0" height="100%" width="100%">Your browser does not support inline frames. To access archived media, click <a href="//dekalbcountyga.granicus.com/MediaPlayer.php?publish_id=8">here</a>.</iframe> </div>
                </div>
                <script>
                    var mvp_introClipCount = 0;
                </script>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        window.NREUM || (NREUM = {});
        NREUM.info = {
        "beacon": "bam.nr-data.net",
                "licenseKey": "2fdd6b8d1a",
                "applicationID": "2651201",
                "transactionName": "YwdbYEZTVxYHABALW1pNbEZdHVQAAgoFElhVG1xGGkJRFQ==",
                "queueTime": 0,
                "applicationTime": 91,
                "atts": "T0BYFg5JRBg=",
                "errorBeacon": "bam.nr-data.net",
                "agent": ""
        }
    </script>
</div>

