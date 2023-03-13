tailwind.config = {
    theme: {
        extend: {
            colors: {
                grayCust: {
                    50: '#6B7280',
                    100: '#E5E7EB',
                    150: '#333333',
                    200: '#111827',
                    250: '#F9FAFB',
                    300: '#1F2937',
                    350: '#D1D5DB',
                    400: '#F9FAFB',
                    500: '#D93F21',
                    900: '#4B5563'
                },
                primary: {
                    700: '#11BF85',
                    800: '#0B6C63',
                    900: '#005E54',
                },
            }
        }
    }
};

(function ($, window, document, plugin_object) {

    let instawp_migrate_api_call_interval = null,
        instawp_migrate_api_call = () => {

            let instawp_migrate_container = $('.instawp-wrap .nav-item-content.create'),
                bar_backup = instawp_migrate_container.find('.instawp-bar-backup'),
                bar_restore = instawp_migrate_container.find('.instawp-bar-restore'),
                bar_migrate = instawp_migrate_container.find('.instawp-bar-migrate');

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_connect_migrate',
                },
                success: function (response) {

                    if (response.success) {

                        console.log(response.data);

                        bar_backup.css('--progress', response.data.backup.progress + '%');
                        bar_restore.css('--progress', response.data.restore.progress + '%');
                        bar_migrate.css('--progress', response.data.migrate.progress + '%');

                        // if (response.data.progress === 100) {
                        //     clearInterval(instawp_migrate_api_call_interval);
                        //     create_container.removeClass('loading');
                        // }
                    }
                }
            });
        };

    $(document).on('click', '.instawp-wrap .instawp-button-create', function () {

        let button_create = $(this),
            create_container = $('.instawp-wrap .nav-item-content.create');

        create_container.addClass('loading');

        instawp_migrate_api_call_interval = setInterval(instawp_migrate_api_call, 3000);
    });


    $(document).on('ready', function () {
        // let all_nav_items = $('.instawp-wrap .nav-items .nav-item');
        // all_nav_items.first().addClass('active').find('a').toggleClass('text-primary-900 border-primary-900');

        let this_nav_item_id = localStorage.getItem('instawp_admin_current'),
            all_nav_items = $('.instawp-wrap .nav-items .nav-item');

        if (this_nav_item_id !== null && typeof this_nav_item_id !== 'undefined') {
            $('.instawp-wrap #' + this_nav_item_id).find('a').trigger('click');
        } else {
            all_nav_items.first().find('a').trigger('click');
        }

        let instawp_migrate_container = $('.instawp-wrap .nav-item-content.create');

        if (instawp_migrate_container.hasClass('loading')) {
            instawp_migrate_api_call_interval = setInterval(instawp_migrate_api_call, 3000);
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-button-connect', function () {

        let button_connect = $(this);

        button_connect.addClass('loading');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_connect_api_url',
            },
            success: function (response) {

                if (response.success) {
                    setTimeout(function () {
                        window.open(response.data.connect_url, '_blank');
                        button_connect.removeClass('loading');
                    }, 1000);
                }
            }
        });
    });


    $(document).on('submit', '.instawp-form', function (e) {

        e.preventDefault();

        let this_form = $(this),
            this_form_data = this_form.serialize(),
            this_form_response = this_form.find('.instawp-form-response');

        this_form_response.html('');
        this_form.addClass('loading');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_update_settings',
                'form_data': this_form_data,
            },
            success: function (response) {

                setTimeout(function () {
                    this_form.removeClass('loading');

                    if (response.success) {
                        this_form_response.addClass('success').html(response.data.message);
                    } else {
                        this_form_response.addClass('error').html(response.data.message);
                    }
                }, 1000);

                setTimeout(function () {
                    this_form_response.removeClass('success error').html('');
                }, 3000);
            }
        });

        return false;
    });


    $(document).on('click', '.instawp-wrap .nav-items .nav-item > a', function () {
        let this_nav_item_link = $(this),
            this_nav_item = this_nav_item_link.parent(),
            this_nav_item_id = this_nav_item.attr('id'),
            all_nav_items = this_nav_item.parent().find('.nav-item'),
            nav_item_content_all = $('.instawp-wrap .nav-content .nav-item-content'),
            nav_item_content_target = nav_item_content_all.parent().find('.' + this_nav_item_id);

        all_nav_items.removeClass('active').find('a').removeClass('text-primary-900 border-primary-900');
        this_nav_item.addClass('active').find('a').addClass('text-primary-900 border-primary-900');

        nav_item_content_all.removeClass('active');
        nav_item_content_target.addClass('active');

        localStorage.setItem('instawp_admin_current', this_nav_item_id);
    });


    // $(function () {
    //     $("#switch-id").change(function () {
    //         if ($(this).is(":checked")) {
    //             $(".sync-listining").show();
    //             $(".data-listening").hide();
    //         }
    //     });
    // });

})(jQuery, window, document, instawp_migrate);
