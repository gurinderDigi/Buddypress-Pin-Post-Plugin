jQuery(document).ready(function($) {
    console.log('loaded');
    $(document).on('click', '.pin-post-button', function(e) {
        e.preventDefault();
        console.log('clicked');
        var postId = $(this).data('post-id');
        var userId = $(this).data('user-id');
        var action = $(this).data('action');
        var button = $(this);

        // Show loader
        $('#ajax-loader').show();

        $.ajax({
            url: ajax_params.ajax_url,
            type: 'POST',
            data: {
                action: action,
                post_id: postId,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    if (action == 'pin_post') {
                        button.text('Unpin');
                        button.data('action', 'unpin_post');
                        location.reload();
                    } else {
                        button.text('Pin');
                        button.data('action', 'pin_post');
                        location.reload();
                    }
                } else {
                    alert(response.data);
                }
            },
            complete: function() {
                // Hide loader
                $('#ajax-loader').hide();
            }
        });
    });
});

// This function hides the BuddyPress success message after a delay
document.addEventListener('DOMContentLoaded', function() {

    function afterActivityLoaded() {
        $('.activity-list.custom li').each(function() {
            var id = $(this).attr('id');

            // Remove the item from the non-custom list if it has the same ID
            $('.activity-list:not(.custom) li#' + id).remove();
        });
        //console.log('Activity items processed.');

        setTimeout(function() {
            $('.activity-content').each(function() {
                $(this).find('.who-reacted').slice(2).hide();
                $(this).find('.who-reacted').not('.custom').slice(1).hide();
            });

            $('.activity-meta.action').each(function() {
                $(this).find('.generic-button.reactions').not(':first').remove();
            });
        }, 100);
    }

    // Function to handle AJAX complete event
    function handleAjaxComplete(event, xhr, settings) {
        if (settings.url.indexOf('admin-ajax.php') !== -1 && settings.data.indexOf('action=activity_filter') !== -1) {
            console.log('activity_filter ajax completed');
            setTimeout(afterActivityLoaded, 500); // Slight delay to ensure DOM updates

            setTimeout(function() {
                $('.activity-content').each(function() {
                    $(this).find('.who-reacted.custom').slice(2).hide();
                    $(this).find('.who-reacted').not('.custom').slice(1).hide();
                });

                $('.activity-meta.action').each(function() {
                    $(this).find('.generic-button.reactions').not(':first').remove();
                });
            }, 100);
        }

        if (settings.url.indexOf('admin-ajax.php') !== -1 && settings.data.indexOf('action=post_update') !== -1) {
            setTimeout(function() {
                $('.activity-meta.action').each(function() {
                    $(this).find('.generic-button.reactions').not(':first').remove();
                });
            }, 100);
        }
    }

    // Bind the function to the AJAX complete event
    $(document).ajaxComplete(handleAjaxComplete);

    // Trigger the function once after the initial page load
    setTimeout(afterActivityLoaded, 500); // Slight delay to ensure DOM updates
});
