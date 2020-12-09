$(document).ready(function () {

    // weitere elemente mit in die navigation ziehen:
    //$('footer .mod_navigation').clone().appendTo('.js-mobile-nav-inner');
    $('<button aria-expanded="false" class="js-nav-main-button"><span class="menu-toggle">Menü <span class="middle-bar"></span></span> <span class="invisible">öffnen</span></button>').insertBefore('.js-mobile-nav-inner');
    // button um neben das menü klicken zu können zum schließen
    $('<button href="#" class="js-click-close invisible">schließen</button>').prependTo('#wrapper');



    // now we can play with it:
    $('.js-nav-main-button').click(function () {
        // Wenn im button menu steht, dann wollen wir runter sliden und auch noch den Text anpassen
        // ansonsten (wenn da was anderes steht), dann sliden wir hoch und schreiben wieder menu da rein
        if ($(this).attr('aria-expanded') === 'false') {
            //$('.js-mobile-nav-inner').slideDown(); // runter sliden mit default Zeit
            $(this).find('.invisible').text('schließen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $(this).attr('aria-expanded', 'true');
            $('.js-mobile-nav-inner').attr('data-expanded', 'true');
            $('.js-click-close').attr('class', 'js-click-close visible');
            $('body').attr('data-navi-expanded', 'true');
        } else {
            //$('.js-mobile-nav-inner').slideUp(1000); // hochsliden in einer sekunde
            $(this).find('.invisible').text('öffnen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $(this).attr('aria-expanded', 'false');
            $('.js-mobile-nav-inner').attr('data-expanded', 'false');
            $('.js-click-close').attr('class', 'js-click-close invisible');
            $('body').attr('data-navi-expanded', 'false');
        }
        return false;
    });

    $('.js-click-close').click(function () {

        // Wenn im button menu steht, dann wollen wir runter sliden und auch noch den Text anpassen
        // ansonsten (wenn da was anderes steht), dann sliden wir hoch und schreiben wieder menu da rein
        if ($('.js-nav-main-button').attr('aria-expanded') === 'false') {
            //$('.js-mobile-nav-inner').slideDown(); // runter sliden mit default Zeit
            $('.js-nav-main-button').find('.invisible').text('schließen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $('body').attr('data-navi-expanded', 'false');
            $('.js-nav-main-button').attr('aria-expanded', 'true');
            $('.js-mobile-nav-inner').attr('data-expanded', 'true');
            $('.js-click-close').attr('class', 'js-click-close visible');
            $('body').attr('data-navi-expanded', 'true');
        } else {
            //$('.js-mobile-nav-inner').slideUp(1000); // hochsliden in einer sekunde
            $('.js-nav-main-button').find('.invisible').text('öffnen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $('.js-nav-main-button').attr('aria-expanded', 'false');
            $('.js-mobile-nav-inner').attr('data-expanded', 'false');
            $('.js-click-close').attr('class', 'js-click-close invisible');
            $('body').attr('data-navi-expanded', 'false');
        }
        return false;
    });

    // submenü button

    $('<button aria-expanded="false" class="js-nav-main-sub-button"><span class="js-nav-main-button-text">Untermenü</span><span class="invisible"> öffnen</span></button>').insertAfter('.mainnavi li > .submenu');
    //$('.js-nav-main-sub-button + ul').slideUp();

    $('.nav-main li.submenu.trail > .js-nav-main-sub-button').attr('aria-expanded', 'true'); //open when subitems are active
    $('.nav-main li.submenu.active > .js-nav-main-sub-button').attr('aria-expanded', 'true'); //open when item is active and has subitems

    $('.js-nav-main-sub-button').click(function () {
        // Wenn im button menu steht, dann wollen wir runter sliden und auch noch den Text anpassen
        // ansonsten (wenn da was anderes steht), dann sliden wir hoch und schreiben wieder menu da rein
        if ($(this).attr('aria-expanded') === 'false') {
            //$('.js-mobile-nav-inner').slideDown(); // runter sliden mit default Zeit
            $(this).find('.invisible').text('schließen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $(this).attr('aria-expanded', 'true');
            //$('.js-nav-main-sub-button + ul').slideDown(1000);
        } else {
            //$('.js-mobile-nav-inner').slideUp(1000); // hochsliden in einer sekunde
            $(this).find('.invisible').text('öffnen'); // jetzt ändern wir noch den Text von diesem Bereich in close
            $(this).attr('aria-expanded', 'false');
            //$('.js-nav-main-sub-button + ul').slideUp(1000);
        }
        return false;
    });
});

var onResize = function() {
    //console.log(document.documentElement.clientWidth);
    //console.log(window.innerWidth);
    if(window.innerWidth < 1200) {
        $('.js-mobile-nav-inner').prependTo('#wrapper');
        $('body').addClass('mobile-view');
        if ($('.js-mobile-nav-inner').attr('data-expanded') === 'true') {
            $('.js-mobile-nav-inner').attr('data-expanded', 'false');
            $('body').attr('data-navi-expanded', 'false');
            $('.js-click-close').attr('class', 'js-click-close invisible');
            $('.js-nav-main-button').attr('aria-expanded', 'false');
        }
        return;
    }
    $('.js-mobile-nav-inner').insertAfter('.logo');
    if ($('.js-mobile-nav-inner').attr('data-expanded') === 'false') {
        $('.js-mobile-nav-inner').attr('data-expanded', 'true');
        $('body').attr('data-navi-expanded', 'true');
        $('.js-click-close').attr('class', 'js-click-close invisible');
        $('.js-nav-main-button').attr('aria-expanded', 'false');
    }
    $('body').removeClass('mobile-view');
};

$(document).ready(function () {
    // attach resize listener
    $(window).resize(onResize);
    // perform initial resize
    onResize()
});
