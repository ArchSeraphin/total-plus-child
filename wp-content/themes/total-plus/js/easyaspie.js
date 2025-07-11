/** Easy As Pie Responsive Navigation Plugin - Version 1.1
 
 The MIT License (MIT)
 
 * Copyright (c) 2014 Chris Divyak
 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
(function ($) {

    $.fn.extend({

        //pass the options variable to the function
        easyPie: function (options) {


            //Set the default values, use comma to separate the settings, example:
            var defaults = {
                icon: "+", //icon for mobile push menu
                navID: "nav", // nav id for ul
                navClass: "applePie", //Navigation class
                collapseClass: "pieCollapse", //class for collapsing menu on mobile
                slideTop: true //change to false if you wish to not have a scrollTo function on your menu
            }

            var options = $.extend(defaults, options);

            return this.each(function () {
                var o = options;

                //IF NAV LI CONTAINS DROPDOWN, ADD PLUS SIGN
                $("li").find('ul').addClass(o.collapseClass);
                $("ul." + o.collapseClass).before('<span>' + o.icon + '</span>');


                //on resize make sure hidden nav even if wasn't hidden first time
                $("#" + o.navID).css("display", "none");
                //ON CLICK SLIDETOGGLE vertical menu
                $("." + o.navClass + " li span").unbind('click').click(function (e) {
                    e.preventDefault();
                    $(this).next().slideToggle(function () {
                        $(this).parent().toggleClass("menuOpen");
                    });
                    //If slideTop equals true then slide
                    if (o.slideTop == true) {
                        navigateTo($(this));
                        return false;
                    }
                    //else, return false
                    else {
                        return false;
                    }
                });

                //FIX menu hide issue when nav gets to bottom of device
                if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {

                    $("." + o.navClass + " ul ul:first").show();
                }

                //ON CLICK SLIDETOGGLE
                $("." + o.navClass + " li span, .menubtn").unbind("click").click(function (e) {
                    e.preventDefault();

                    //remove all classes and slidetoggle

                    $('li').each(function () {
                        $(this).removeClass('menuOpen');
                        $('.pieCollapse').css('display', 'none');
                    })
                    //Add class to open slidetoggle menu
                    $(this).next("ul").slideToggle(function (e) {
                        $(this).parent().toggleClass("menuOpen");
                    });

                    if ($("." + o.navClass + "ul:first").is(":visible")) {
                        $(".menubtn").addClass("menuOpen");
                    }
                    //If slideToggle is close, remove class
                    if ($("." + o.navClass + "ul:first").is(":hidden")) {
                        $(".menubtn").removeClass("menuOpen");
                    }
                    //If slideTop equals true then slide
                    if (o.slideTop == true) {
                        navigateTo($(this));
                        return false;
                    }
                    //else, return false
                    else {
                        return false;
                    }

                });

                //Slide to li on click
                function navigateTo(destination) {
                    $('html,body').delay(500).animate({scrollTop: $(destination).offset().top - 48}, 'fast');
                }
            });
        }
    });
})(jQuery);
