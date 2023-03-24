(function ($) {
    'use strict';

    $(window).load(function () {

        $('#download-calendar-form').submit(function (event) {
                        
            // This return prevents the submit event to refresh the page.
            event.preventDefault();

            // Disable button
            $('#download-calendar-form :input').prop('disabled', true);

            let formData = {
                action: 'download_meetup_calendar',
                name: $("input[name='name']").val(),
                starts_at: $("input[name='starts_at']").val(),
                ends_at: $("input[name='ends_at']").val(),
                address: $("input[name='address']").val(),
                image: $("input[name='image']").val(),
                //timezone: $("input[name='timezone']").val(),
                community_id: $("input[name='community_id']").val(),
                description: $("input[name='description']").val(),
                security: data.nonce,
            };

            $.ajax({
                url: data.ajaxUrl,
                type: 'post',
                //dataType: 'text/calendar; charset=utf-8',
                data: formData,
                success: function (res) {
                    // Enable button
                    $('#download-calendar-form :input').prop('disabled', false);

                    /*
                     * Make ICS downloadable
                     */
                    var downloadLink = document.createElement("a");
                    var fileData = [res];

                    var blobObject = new Blob(fileData, {
                        type: "text/calendar;charset=utf-8;"
                    });

                    var url = URL.createObjectURL(blobObject);
                    downloadLink.href = url;
                    downloadLink.download = "meetup.ics";

                    /*
                     * Actually download CSV
                     */
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);

                },
                error: function (res) {
                    // Enable button
                    $('#download-calendar-form :input').prop('disabled', false);
                }
            });
        });
    });

})(jQuery);