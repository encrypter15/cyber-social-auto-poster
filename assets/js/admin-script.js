jQuery(document).ready(function($) {
    console.log('Cyber Social Auto-Poster loaded');

    var calendarEl = document.getElementById('csap-calendar');
    if (calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: function(fetchInfo, successCallback, failureCallback) {
                var events = [];
                $.each(csap_data.analytics, function(post_id, data) {
                    events.push({
                        title: 'Post to ' + data.platforms.join(', '),
                        start: data.timestamp,
                        url: '<?php echo admin_url("post.php?post="); ?>' + post_id + '&action=edit'
                    });
                });
                successCallback(events);
            },
            eventColor: '#3498db'
        });
        calendar.render();
    }
});