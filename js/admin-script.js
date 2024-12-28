// JS functionality for CPT Dashboard Widget
(function ($) {
    $(document).ready(function () {
        
        $('.cptdw-recent-posts').on('click', 'li', function () {
            var link = $(this).find('a').attr('href');
            if (link) {
                window.location.href = link;
            }
        });

        $('.cptdw-recent-posts').on('mouseenter', 'li', function () {
            $(this).addClass('hovered');
        }).on('mouseleave', 'li', function () {
            $(this).removeClass('hovered');
        });
    });
})(jQuery);

