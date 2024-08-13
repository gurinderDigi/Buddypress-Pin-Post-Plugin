<?php
/*
Plugin Name: BuddyPress Pin Posts (Digimantra GS)
Description: Allows users to pin BuddyPress posts to the top.
Version: 1.0
Author: Digimantra GS gg
*/

if (!defined('ABSPATH')) {
    exit;
}


class BuddyPressPinPosts {
    public function __construct() {

        register_activation_hook(__FILE__, [$this, 'create_table']);
        
        add_action('wp_ajax_pin_post', [$this, 'pin_post']);
        add_action('wp_ajax_unpin_post', [$this, 'unpin_post']);
        add_action('bp_activity_entry_meta', [$this, 'add_pin_button']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_filter( 'bp_has_activities', [$this,'bp_custom_activity_feed'], 10, 2 );
      
    }
 
    

    public function create_table() {

        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_pinned_posts';
       

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name ) {

            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY unique_post_user (post_id, user_id)
            ) $charset_collate;";
    
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        
    }

   
    public function add_pin_button() {
        if (is_user_logged_in() && (bp_get_activity_type() == 'activity_update' || bp_get_activity_type() == 'rtmedia_update')) {
            $post_id = bp_get_activity_id();
           $activity_user_id = bp_get_activity_user_id(); // Get the user ID of the activity
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_pinned_posts';
        $is_pinned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d AND activity_user_id = %d",
            $post_id, $activity_user_id
        ));
        
        $button_text = $is_pinned ? 'Unpin' : 'Pin';
        $action = $is_pinned ? 'unpin_post' : 'pin_post';
    
            echo '<a href="javascript:void(0)" class="pin-post-button" data-user-id="'.$activity_user_id.'" data-post-id="' . $post_id . '" data-action="' . $action . '">' . $button_text . '</a>';
        }
    }
    

    public function pin_post() {
        if (!is_user_logged_in() || !isset($_POST['post_id'])) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = intval($_POST['post_id']);
        $user_id = intval($_POST['user_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_pinned_posts';

        $wpdb->replace(
            $table_name,
            ['post_id' => $post_id, 'user_id' => $user_id],
            ['%d', '%d']
        );

        wp_send_json_success('Post pinned successfully');
    }

    public function unpin_post() {
        if (!is_user_logged_in() || !isset($_POST['post_id'])) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = intval($_POST['post_id']);
        $user_id = intval($_POST['user_id']);
       
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_pinned_posts';

        $wpdb->delete(
            $table_name,
            ['post_id' => $post_id, 'user_id' => $user_id],
            ['%d', '%d']
        );

        wp_send_json_success('Post unpinned successfully');
    }

    


	function bp_custom_activity_feed($has_activities, $args) {
        
        global $wpdb;
		$user_id = get_current_user_id();
		$table_name = $wpdb->prefix . 'bp_pinned_posts';
	
		// Fetch pinned post IDs for the current user

		/*$pinned_posts = $wpdb->get_col($wpdb->prepare(
			"SELECT post_id FROM $table_name WHERE user_id = %d",
			$user_id
		));
	   */

       
        $pinned_posts = $wpdb->get_col("SELECT post_id FROM $table_name");

		// Check if there are pinned posts
		if (!empty($pinned_posts)) {
			// Sort pinned posts in descending order
			//rsort($pinned_posts);
	
			echo '<ul class="activity-list custom customClass" >';
	
			// Loop through each pinned post ID
			foreach ($pinned_posts as $post_id) {
				// Fetch the specific activity
				$custom_activities = bp_activity_get_specific(array('activity_ids' => array($post_id)));
              
				if (!empty($custom_activities['activities'])) {
					$activity = $custom_activities['activities'][0];

					?>
					<li class="activity activity_update activity-item animate-item slideInUp pinnedPosts" id="activity-<?php echo esc_attr($activity->id); ?>" data-bp-activity-id="<?php echo esc_attr($activity->id); ?>" data-bp-timestamp="<?php bp_nouveau_activity_timestamp($activity->id); ?>" style="visibility: visible; animation-name: slideInUp;border: 1px solid #90207952 !important;">
						<div class="activity-avatar item-avatar">
							<?php echo bp_core_fetch_avatar(array('item_id' => $activity->user_id, 'type' => 'full')); ?>
						</div>
        
						<div class="activity-content">
							<div class="activity-header">
								<div class="posted-meta">
									<p><?php echo bp_core_get_userlink($activity->user_id); ?> posted an update</p>
								</div>
								<div class="date mute">
									<?php
									$date_recorded = $activity->date_recorded;
									$recorded_time = new DateTime($date_recorded);
									$current_time = new DateTime();
									$interval = $current_time->diff($recorded_time);
	
									$output = '';
									if ($interval->h > 0) {
										$output .= $interval->h . ' hour';
										if ($interval->h > 1) {
											$output .= 's';
										}
										$output .= ', ';
									}
									$output .= $interval->i . ' minute';
									if ($interval->i > 1) {
										$output .= 's';
									}
									$output .= ' ago';
	
									echo $output;
									?>
								</div>
                                <?php  if (is_user_logged_in()) { ?>
								<div class="activity-options dropleft">
									<a class="dropdown-toggle" href="#" role="button" id="activity-action-dropdown-<?php echo esc_attr($activity->id); ?>" data-toggle="dropdown" aria-expanded="false"><i class="uil-ellipsis-v"></i></a>
									<div class="dropdown-menu" aria-labelledby="activity-action-dropdown-<?php echo esc_attr($activity->id); ?>">
										<div class="activity-meta action">
											<div class="generic-button"><a href="<?php echo home_url();?>/home-activity/favorite/<?php echo esc_attr($activity->id); ?>/?_wpnonce=<?php echo wp_create_nonce( 'bp_nouveau_activity' ); ?>" class="button fav bp-secondary-action bp-tooltip" data-bp-tooltip="Mark as Favorite" aria-pressed="false"><span class="bp-screen-reader-text">Mark as Favorite</span></a></div>
                                           
                                            <?php  if (is_user_logged_in() && current_user_can('administrator')) { ?>
                                            <div class="generic-button"><a href="<?php echo home_url();?>/home-activity/delete/<?php echo esc_attr($activity->id); ?>/?_wpnonce=<?php echo wp_create_nonce( 'bp_activity_delete_link' ); ?>" class="button item-button bp-secondary-action bp-tooltip delete-activity confirm" data-bp-tooltip="Delete"><span class="bp-screen-reader-text">Delete</span></a></div>
                                            <?php } ?>
                                        </div>
									</div>
								</div>
                                <?php } ?>
							</div>
							<div class="activity-inner">
								<?php echo wp_kses_post($activity->content); ?>
							</div>
                            <div class="who-reacted custom"><span class="top-reactions">
                                <?php
                                    echo $this->display_reactions_for_activity($activity->id );
                                ?>
                            </div>
                           
							<div class="activity-meta action" style="<?php  if (!is_user_logged_in()){ ?> display:none; <?php } ?>">
								<div class="generic-button">
                                    <a id="acomment-comment-<?php echo esc_attr($activity->id); ?>" class="button acomment-reply bp-primary-action bp-tooltip" data-bp-tooltip="Comment" aria-expanded="false" href="<?php echo home_url();?>/home-activity/?ac=<?php echo esc_attr($activity->id); ?>/#ac-form-<?php echo esc_attr($activity->id); ?>" role="button"><span class="bp-screen-reader-text">Comment</span> <span class="comment-count">
                                    <?php  
                                   $comments = BP_Activity_Activity::get_activity_comments( $activity->id, 0, 0, 'ham_only', 0 );

                                     // Check if comments exist
                                    if ( ! empty( $comments ) ) {
                                        $comments_count = count( $comments );
                                        echo $comments_count;
                                    } else {
                                        echo 0; // No comments found
                                    }
                                    
                                    ?>   
                                    </span></a></div>
                                    <?php  if (is_user_logged_in() && current_user_can('administrator')) { ?>

								<a href="javascript:void(0)" class="pin-post-button" data-user-id="<?php echo $activity->user_id; ?>" data-post-id="<?php echo esc_attr($activity->id); ?>" data-action="unpin_post">Unpin</a>
								
                                <?php } ?>
                                <div class="generic-button reactions">
                                 
                                <a href="#" data-reaction-type="" class="button react-to-activity"><span class="bp-screen-reader-text">Like</span></a>
                                
                                <div class="pick-reaction"><span class="reaction like"><span class="reaction-tooltip">Like</span></span><span class="reaction love"><span class="reaction-tooltip">Love</span></span><span class="reaction care"><span class="reaction-tooltip">Care</span></span><span class="reaction haha"><span class="reaction-tooltip">Haha</span></span><span class="reaction wow"><span class="reaction-tooltip">Wow</span></span><span class="reaction sad"><span class="reaction-tooltip">Sad</span></span><span class="reaction angry"><span class="reaction-tooltip">Angry</span></span></div></div>
								<div class="generic-button"><a href="#" id="activity-share-<?php echo esc_attr($activity->id); ?>" class="button share-activity"><span class="bp-screen-reader-text">Share</span></a><ul class="share-activity-options" aria-labelledby="activity-share-<?php echo esc_attr($activity->id); ?>"><li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo home_url();?>/home-activity/p/<?php echo esc_attr($activity->id); ?>/" class="share-item share-on-facebook" target="_blank">Share on Facebook</a></li><li><a href="https://twitter.com/intent/tweet?url=<?php echo home_url();?>/home-activity/p/<?php echo esc_attr($activity->id); ?>/" class="share-item share-on-twitter" target="_blank">Share on Twitter</a></li><li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo home_url();?>/home-activity/p/<?php echo esc_attr($activity->id); ?>/" class="share-item share-on-linkedin" target="_blank">Share on LinkedIn</a></li></ul></div>
							</div>
                           
							<div class="activity-comments" style="<?php  if (!is_user_logged_in()){ ?> margin-top:25px; <?php } ?>">

                            <form action="<?php echo home_url();?>/home-activity/reply/" method="post" id="ac-form-<?php echo esc_attr($activity->id); ?>" class="ac-form customCommentForm">
                                <div class="ac-reply-avatar"><img loading="lazy" src="//www.gravatar.com/avatar/ff2215301926b805a13cad58f16e667a?s=50&amp;r=g&amp;d=mm" class="avatar user-14-avatar avatar-50 photo" width="50" height="50" alt=""></div>
                                <div class="ac-reply-content">
                                    <div class="ac-textarea">
                                        <label for="ac-input-<?php echo esc_attr($activity->id); ?>" class="bp-screen-reader-text">Comment</label>
                                        <textarea id="ac-input-<?php echo esc_attr($activity->id); ?>" class="ac-input bp-suggestions" name="ac_input_<?php echo esc_attr($activity->id); ?>"></textarea>
                                    </div>
                                    <input type="hidden" class="isadmin" name="isadmin" value="<?php  if (is_user_logged_in() && current_user_can('administrator'))
                                                                                                 { 
                                                                                                    echo 'isadmin';
                                                                                                 } elseif(is_user_logged_in() ){
                                                                                                    if(current_user_can('administrator')){

                                                                                                    }else{
                                                                                                        echo 'loggedin';
                                                                                                    }
                                                                                                 }
                                                                                                 ?>">
                                    
                                    <input type="hidden" name="comment_form_id" value="<?php echo esc_attr($activity->id); ?>">
                                    <input type="submit" name="ac_form_submit" value="Post">
                                    <input type="hidden" id="_wpnonce_new_activity_comment_<?php echo esc_attr($activity->id); ?>" name="_wpnonce_new_activity_comment_<?php echo esc_attr($activity->id); ?>" value="<?php echo wp_create_nonce('new_activity_comment'); ?>">
                                    <input type="hidden" name="_wp_http_referer" value="/wp-admin/admin-ajax.php">&nbsp;
                                    <button type="button" class="ac-reply-cancel">Cancel</button>
                                </div>
                            </form>
                        <?php
                        // Check if there are any comments to display
                        if (!empty($comments)) {
                            // Display comments recursively
                            $this->display_comments($comments);
                        }
                        ?>
                    </div>
                    <div class="pinClass"><i class="fa-solid fa-thumbtack"></i></div>
                                            </div>
                                        </li>
                                        <?php
                                    }
                                }
                        
                                echo '</ul>';
                            }

                            return $has_activities;
	}
   
  




        function display_reactions_for_activity($activity_id) {
           
            if ( ! class_exists( 'Buddy_Bridge_Activity_Reactions' ) ) {
                echo 'Buddy Bridge Activity Reactions plugin is not active.';
                return;
            }

            $reactions_instance = Buddy_Bridge_Activity_Reactions::instance();
            $reactions = $reactions_instance->get_activity_reaction($activity_id);
            $reactions = $reactions_instance->get_filtered_reactions($reactions);
            $count = 0; // Initialize count to 0

            // If there are reactions, display them
            if ( ! empty( $reactions ) ) {
                foreach ( $reactions as $reaction => $details ) {
                  
                    echo sprintf('<span class="%s"></span>', esc_html( $reaction ), esc_html( $details['count'] ));
                    $count += $details['count']; // Correctly update the count
                }
                if(!is_user_logged_in()) {

                    echo ($count) . ' Peoples reacted to this!';

                }else{

                    if ($count > 1) {
                        echo 'You and ' . ($count - 1) . ' others reacted to this!';
                    } else{
                        echo 'You reacted to this!';
                    }

                }
                
              
            }
             else {
               // echo 'No reactions found for this activity.';
            }
        }

        // Recursive function to display comments and their children
function display_comments($comments) {
    echo '<ul class="appendComments">';
    foreach ($comments as $comment) {

        // echo '<pre>';
        // print_r($comment);
        // echo '</pre>';
        // break;
    
        ?>
        <li id="acomment-<?php echo $comment->id; ?>" class="comment-item" data-bp-activity-comment-id="<?php echo $comment->id; ?>">
            <div class="acomment-avatar item-avatar">
                <a href="<?php echo $comment->primary_link; ?>">
                <?php echo bp_core_fetch_avatar(array('item_id' => $comment->user_id, 'type' => 'full')); ?>
                
                </a>

            </div>

            <div class="acomment-meta">
                <a href="<?php echo $comment->primary_link; ?>"><?php echo esc_html($comment->display_name); ?></a> replied 
                <a href="<?php echo home_url();?>/home-activity/p/<?php echo $comment->item_id; ?>/#acomment-<?php echo $comment->id; ?>" class="activity-time-since">
                    <time class="time-since" datetime="<?php echo $comment->date_recorded; ?>" data-bp-timestamp="<?php echo strtotime($comment->date_recorded); ?>">
                        <?php echo bp_core_time_since($comment->date_recorded); ?>
                    </time>
                </a>
            </div>

            <div class="acomment-content">
                <div class="rtmedia-activity-container">
                    <div class="rtmedia-activity-text">
                        <span><?php echo wpautop($comment->content); ?></span>
                    </div>
                    <ul class="rtmedia-list rtm-activity-media-list rtmedia-activity-media-length-0 rtm-activity-mixed-list rtm-activity-list-rendered"></ul>
                </div>
            </div>
            <?php  if (is_user_logged_in()) { 
                
                ?>
            <div class="activity-meta action">
                <div class="generic-button">
                    <a class="acomment-reply bp-primary-action" id="acomment-reply-<?php echo $comment->item_id; ?>-from-<?php echo $comment->id; ?>" href="#acomment-<?php echo $comment->id; ?>">Reply</a>
                </div>
                <?php  
                
                $current_user_id = get_current_user_id();
                if( $comment->user_id == $current_user_id){
                ?>
                <div class="generic-button">

                    <a class="delete acomment-delete confirm bp-secondary-action" rel="nofollow" href="<?php echo home_url();?>/home-activity/delete/<?php echo $comment->id; ?>/?cid=<?php echo $comment->id; ?>&amp;_wpnonce=<?php echo wp_create_nonce('bp_activity_delete_link'); ?>">Delete</a>
                </div>
                <?php
                }else{

                    if(current_user_can('administrator')){
                        ?>

<div class="generic-button">

<a class="delete acomment-delete confirm bp-secondary-action" rel="nofollow" href="<?php echo home_url();?>/home-activity/delete/<?php echo $comment->id; ?>/?cid=<?php echo $comment->id; ?>&amp;_wpnonce=<?php echo wp_create_nonce('bp_activity_delete_link'); ?>">Delete</a>
</div>
                        <?php
                    }
                }
                    ?>
            </div>
            <?php
                }
                    ?>
            <?php

            // Display children comments recursively
            if (!empty($comment->children)) {
                $this->display_comments($comment->children);
            }
            ?>
        </li>
        <?php
    }
    echo '</ul>';
}


    public function enqueue_scripts() {
        wp_enqueue_script('buddypress-pin-posts', plugin_dir_url(__FILE__) . 'buddypress-pin-posts.js', ['jquery'], '1.0', true);
        wp_localize_script('buddypress-pin-posts', 'ajax_params', ['ajax_url' => admin_url('admin-ajax.php')]);

            
              // Enqueue the custom stylesheet
             wp_enqueue_style('pin-post-custom-css', plugin_dir_url(__FILE__) . 'pin-post-custom.css', [], '1.0');

    }
}

new BuddyPressPinPosts();
