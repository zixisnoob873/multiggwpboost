@php
    use App\Support\Privacy\CookieConsent;

    $tawkCookieConsent = CookieConsent::fromRequest(request());
@endphp

@if(CookieConsent::allows($tawkCookieConsent, CookieConsent::CATEGORY_SUPPORT))
    <!--Start of Tawk.to Script-->
    <script nonce="{{ $cspNonce ?? '' }}" type="text/javascript" data-ggwp-consent-script="support">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/69f292b86de35f1c378f957f/1jndoq8fv';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s1.setAttribute('data-ggwp-consent-script','support');
    s0.parentNode.insertBefore(s1,s0);
    window.ggwpSupportLoaded=true;
    })();
    </script>
    <!--End of Tawk.to Script-->
@endif
