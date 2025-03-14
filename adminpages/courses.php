<?php
/**
 * Register the Sync Enrollments admin page.
 */
function pmpro_course_enrollment_sync_register_page() {
    add_submenu_page(
        null, // No parent - this makes it a hidden page.
        __( 'Sync Course Enrollments', 'paid-memberships-pro' ),
        __( 'Sync Course Enrollments', 'paid-memberships-pro' ),
        'manage_options',
        'pmpro-course-enrollment-sync',
        'pmpro_course_enrollment_sync_page_content'
    );
}
add_action( 'admin_menu', 'pmpro_course_enrollment_sync_register_page' );

/**
 * Display the Sync Enrollments admin page content.
 */
function pmpro_course_enrollment_sync_page_content() {
    // Check if a course ID is provided.
    if ( empty( $_GET['course_id'] ) ) {
        wp_die( __( 'No course selected.', 'paid-memberships-pro' ) );
    }

    $course_id = intval( $_GET['course_id'] );
    $course_title = get_the_title( $course_id );
    
    // Get the total number of users.
    $total_users = count_users();
    $total_users_count = $total_users['total_users'];
    
    // Enqueue scripts and styles.
    wp_enqueue_script( 'jquery-ui-progressbar' );
    wp_enqueue_style( 'jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Sync Course Enrollments', 'paid-memberships-pro' ); ?></h1>
        
        <div class="notice notice-info">
            <p>
                <?php 
                printf(
                    esc_html__( 'This tool will look through all existing users and ensure they are properly enrolled or unenrolled from the course: %s', 'paid-memberships-pro' ),
                    '<strong>' . esc_html( $course_title ) . '</strong>'
                ); 
                ?>
            </p>
            <p><?php esc_html_e( 'Users will be enrolled if they have a membership level that grants access to this course, and unenrolled if they do not.', 'paid-memberships-pro' ); ?></p>
            <p><?php esc_html_e( 'Be aware that this process can take a while to complete, especially for large sites. All actions related to enrolling a user, such as sending emails notifications or being added to CRMs, will be performed during this process.', 'paid-memberships-pro' ); ?></p>
        </div>
        
        <div id="pmpro-course-enrollment-sync-progress-container" style="margin: 20px 0; display: none;">
            <div id="pmpro-course-enrollment-sync-progressbar"></div>
            <p id="pmpro-course-enrollment-sync-progress-text">
                <?php esc_html_e( 'Processing users...', 'paid-memberships-pro' ); ?> 
                <span id="pmpro-course-enrollment-sync-processed-count">0</span> / <span id="pmpro-course-enrollment-sync-total-count"><?php echo esc_html( $total_users_count ); ?></span>
            </p>
        </div>
        
        <div id="pmpro-course-enrollment-sync-summary" style="margin-top: 20px; display: none;">
            <h3><?php esc_html_e( 'Summary', 'paid-memberships-pro' ); ?></h3>
            <p><?php esc_html_e( 'Total users processed:', 'paid-memberships-pro' ); ?> <span id="pmpro-course-enrollment-sync-summary-total">0</span></p>
            <p><?php esc_html_e( 'Users enrolled:', 'paid-memberships-pro' ); ?> <span id="pmpro-course-enrollment-sync-summary-enrolled">0</span></p>
            <p><?php esc_html_e( 'Users unenrolled:', 'paid-memberships-pro' ); ?> <span id="pmpro-course-enrollment-sync-summary-unenrolled">0</span></p>
            <p><?php esc_html_e( 'No change needed:', 'paid-memberships-pro' ); ?> <span id="pmpro-course-enrollment-sync-summary-unchanged">0</span></p>
        </div>

		<div id="pmpro-course-enrollment-sync-results-container" style="margin-top: 20px; display: none;">
            <h3><?php esc_html_e( 'Results', 'paid-memberships-pro' ); ?></h3>
            <div id="pmpro-sync-results">
                <ul id="pmpro-course-enrollment-sync-results-list"></ul>
            </div>
        </div>
                
        <p id="pmpro-course-enrollment-sync-button-container">
			<select id="pmpro-course-enrollment-sync-batch-size">
				<option value="1"><?php esc_html_e( '1 user per batch', 'paid-memberships-pro' ); ?></option>
				<option value="5"><?php esc_html_e( '5 users per batch', 'paid-memberships-pro' ); ?></option>
				<option value="25"><?php esc_html_e( '25 users per batch', 'paid-memberships-pro' ); ?></option>
				<option value="100"><?php esc_html_e( '100 users per batch', 'paid-memberships-pro' ); ?></option>
			</select>
            <button id="pmpro-course-enrollment-sync-button" class="button button-primary">
                <?php esc_html_e( 'Sync Enrollments', 'paid-memberships-pro' ); ?>
            </button>
            <span id="pmpro-course-enrollment-sync-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
        </p>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Variables
                var courseId = <?php echo esc_js( $course_id ); ?>;
                var totalUsers = <?php echo esc_js( $total_users_count ); ?>;
                var processedUsers = 0;
                var enrolledUsers = 0;
                var unenrolledUsers = 0;
                var unchangedUsers = 0;
                var offset = 0;
				var batchSize = 1;
                var isProcessing = false;
				console.log( batchSize );
                
                // Initialize progressbar
                $("#pmpro-course-enrollment-sync-progressbar").progressbar({
                    value: 0
                });
                
                // Update the total count
                $("#pmpro-course-enrollment-sync-total-count").text(totalUsers);
                
                // Handle the sync button click
                $("#pmpro-course-enrollment-sync-button").on("click", function() {
                    if (isProcessing) {
                        return;
                    }
                    
                    isProcessing = true;
                    $(this).prop("disabled", true);
                    $("#pmpro-course-enrollment-sync-spinner").addClass("is-active");
                    $("#pmpro-course-enrollment-sync-progress-container").show();
                    $("#pmpro-course-enrollment-sync-results-container").show();
                    $("#pmpro-course-enrollment-sync-results-list").empty();
                    
                    // Start the sync process
                    processUserBatch();
                });
                
                // Process a batch of users
                function processUserBatch() {
                	batchSize = parseInt($("#pmpro-course-enrollment-sync-batch-size").val()) || 1; // Number of users to process in each batch
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'pmpro_course_enrollment_sync',
                            course_id: courseId,
                            offset: offset,
                            batch_size: batchSize,
                            nonce: '<?php echo wp_create_nonce( 'pmpro_course_enrollment_sync' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update counts
                                processedUsers += response.data.processed;
                                enrolledUsers += response.data.enrolled;
                                unenrolledUsers += response.data.unenrolled;
                                unchangedUsers += response.data.unchanged;
                                
                                // Update progress
                                var progress = Math.min(100, Math.round((processedUsers / totalUsers) * 100));
                                $("#pmpro-course-enrollment-sync-progressbar").progressbar("value", progress);
                                $("#pmpro-course-enrollment-sync-processed-count").text(processedUsers);
                                
                                // Add results to the list
                                if (response.data.results && response.data.results.length > 0) {
                                    $.each(response.data.results, function(index, result) {
                                        var actionClass = '';
                                        if (result.action === 'enrolled') {
                                            actionClass = 'pmpro-enrolled';
                                        } else if (result.action === 'unenrolled') {
                                            actionClass = 'pmpro-unenrolled';
                                        } else {
                                            actionClass = 'pmpro-unchanged';
                                        }
                                        
                                        $("#pmpro-course-enrollment-sync-results-list").append(
                                            '<li class="' + actionClass + '">' +
                                            '<a href="' + result.edit_url + '" target="_blank">' + result.user_login + '</a> - ' +
                                            result.action_text +
                                            '</li>'
                                        );
                                    });
                                }
                                
                                // Check if we need to process more users
                                if (processedUsers < totalUsers && response.data.processed > 0) {
                                    offset += batchSize;
                                    processUserBatch();
                                } else {
                                    // We're done
                                    completeProcess();
                                }
                            } else {
                                // Error occurred
                                alert(response.data.message || 'An error occurred during the sync process.');
                                completeProcess();
                            }
                        },
                        error: function() {
                            alert('An error occurred during the sync process.');
                            completeProcess();
                        }
                    });
                }
                
                // Complete the process
                function completeProcess() {
                    isProcessing = false;
                    $("#pmpro-course-enrollment-sync-button").prop("disabled", false);
                    $("#pmpro-course-enrollment-sync-spinner").removeClass("is-active");
                    
                    // Update summary
                    $("#pmpro-course-enrollment-sync-summary").show();
                    $("#pmpro-course-enrollment-sync-summary-total").text(processedUsers);
                    $("#pmpro-course-enrollment-sync-summary-enrolled").text(enrolledUsers);
                    $("#pmpro-course-enrollment-sync-summary-unenrolled").text(unenrolledUsers);
                    $("#pmpro-pmpro-course-enrollment-sync-summary-unchanged").text(unchangedUsers);
                    
                    // Add a completion message
                    $("#pmpro-course-enrollment-sync-progress-text").html('<strong><?php esc_html_e( 'Process completed!', 'paid-memberships-pro' ); ?></strong>');

					// Hide button container
					$("#pmpro-course-enrollment-sync-button-container").hide();
				}
            });
        </script>
        
        <style type="text/css">
            #pmpro-course-enrollment-sync-results-list {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 10px;
                background: #f9f9f9;
            }
            #pmpro-course-enrollment-sync-results-list li {
                margin-bottom: 5px;
                padding: 5px;
                border-bottom: 1px solid #eee;
            }
            #pmpro-course-enrollment-sync-results-list li:last-child {
                border-bottom: none;
            }
            .pmpro-enrolled {
                background-color:rgb(182, 228, 164);
            }
            .pmpro-unenrolled {
                background-color:rgb(240, 179, 179);
            }
            .pmpro-unchanged {
                background-color: #fcf8e3;
            }
        </style>
    </div>
    <?php
}

/**
 * AJAX handler for syncing member course enrollments.
 */
function pmpro_course_enrollment_sync_ajax() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pmpro_course_enrollment_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'paid-memberships-pro' ) ) );
    }
    
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'paid-memberships-pro' ) ) );
    }
    
    // Get parameters
    $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
    $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 1;
    
    if ( empty( $course_id ) ) {
        wp_send_json_error( array( 'message' => __( 'No course selected.', 'paid-memberships-pro' ) ) );
    }
    
    // Get a batch of users
    $args = array(
        'number' => $batch_size,
        'offset' => $offset,
        'fields' => array( 'ID', 'user_login' ),
    );
    $user_query = new WP_User_Query( $args );
    $users = $user_query->get_results();
    
    $results = array();
    $enrolled_count = 0;
    $unenrolled_count = 0;
    $unchanged_count = 0;
    
    foreach ( $users as $user ) {
        // Check if the user should have access to the course
        $hasaccess = pmpro_has_membership_access( $course_id, $user->ID, true );
        
        if ( is_array( $hasaccess ) ) {
            $post_membership_levels_ids = $hasaccess[1];
            $hasaccess = $hasaccess[0];
            
            $is_enrolled = llms_is_user_enrolled( $user->ID, $course_id );
            $action = '';
            $action_text = '';
            
            // If the user should have access but isn't enrolled, enroll them
            if ( $hasaccess && ! $is_enrolled ) {
                llms_enroll_student( $user->ID, $course_id );
                $action = 'enrolled';
                $action_text = __( 'Enrolled', 'paid-memberships-pro' );
                $enrolled_count++;
            } 
            // If the user shouldn't have access but is enrolled, unenroll them
            else if ( ! $hasaccess && $is_enrolled ) {
                llms_unenroll_student( $user->ID, $course_id );
                $action = 'unenrolled';
                $action_text = __( 'Unenrolled', 'paid-memberships-pro' );
                $unenrolled_count++;
            } 
            // No change needed
            else {
                $action = 'unchanged';
                $action_text = $is_enrolled ? 
                    __( 'Already enrolled (no change)', 'paid-memberships-pro' ) : 
                    __( 'Already not enrolled (no change)', 'paid-memberships-pro' );
                $unchanged_count++;
            }
            
            // Add to results
            $results[] = array(
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'action' => $action,
                'action_text' => $action_text,
                'edit_url' => admin_url( 'user-edit.php?user_id=' . $user->ID ),
            );
        } else {
            // User doesn't have membership levels or there was an error
            $unchanged_count++;
            $results[] = array(
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'action' => 'unchanged',
                'action_text' => __( 'No membership levels (no change)', 'paid-memberships-pro' ),
                'edit_url' => admin_url( 'user-edit.php?user_id=' . $user->ID ),
            );
        }
    }
    
    // Send the response
	$result = array(
        'processed' => count( $users ),
        'enrolled' => $enrolled_count,
        'unenrolled' => $unenrolled_count,
        'unchanged' => $unchanged_count,
        'results' => $results,
    );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_pmpro_course_enrollment_sync', 'pmpro_course_enrollment_sync_ajax' );

/**
 * Add a button to the Edit User screen to sync course enrollments for a single user.
 */
function pmpro_member_course_enrollment_sync_panel( $user = '' ) {
    // Only show if streamline is enabled OR PMPro Courses is active.
    if ( ! ( pmpro_getOption( 'lifter_streamline' ) || defined( 'PMPRO_COURSES_VERSION' ) ) ) {
        return;
    }

    // Only show for users with manage_options capability.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Get the user ID.
	if ( ! empty( $user ) ) {
		if ( is_object( $user ) ) {
			$user_id = $user->ID;
		} else {
			$user_id = $user;
		}
	} else {
		$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
	}

    if ( empty( $user_id ) ) {
        return;
    }

    // Get all courses.
    $args = array(
        'post_type' => 'course',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $courses = get_posts( $args );

    if ( empty( $courses ) ) {
        return;
    }
    ?>
    <h2><?php esc_html_e( 'Sync with Courses/Enrollment', 'paid-memberships-pro' ); ?></h2>
	<div id="pmpro-course-enrollment-sync-container">
		<p><?php esc_html_e( 'Sync this user\'s enrollment status for all courses based on their PMPro membership level access.', 'paid-memberships-pro' ); ?></p>
		<button id="pmpro-course-enrollment-sync-button" class="button button-secondary" data-user-id="<?php echo esc_attr( $user_id ); ?>">
			<?php esc_html_e( 'Begin Sync', 'paid-memberships-pro' ); ?>
		</button>
		<span id="pmpro-course-enrollment-sync-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
		
		<div id="pmpro-course-enrollment-sync-results" style="margin-top: 10px; display: none;">
			<h4><?php esc_html_e( 'Results', 'paid-memberships-pro' ); ?></h4>
			<ul id="pmpro-course-enrollment-sync-results-list"></ul>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$("#pmpro-course-enrollment-sync-button").on("click", function(e) {
				e.preventDefault();
				
				var userId = $(this).data("user-id");
				var $button = $(this);
				var $spinner = $("#pmpro-course-enrollment-sync-spinner");
				var $results = $("#pmpro-course-enrollment-sync-results");
				var $resultsList = $("#pmpro-course-enrollment-sync-results-list");
				
				// Clear previous results
				$resultsList.empty();
				
				// Disable button and show spinner
				$button.prop("disabled", true);
				$spinner.addClass("is-active");
				
				// Make AJAX request
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pmpro_member_course_enrollment_sync',
						user_id: userId,
						nonce: '<?php echo wp_create_nonce( 'pmpro_member_course_enrollment_sync' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Show results
							$results.show();
							
							// Add results to the list
							if (response.data.results && response.data.results.length > 0) {
								$.each(response.data.results, function(index, result) {
									var actionClass = '';
									if (result.action === 'enrolled') {
										actionClass = 'pmpro-enrolled';
									} else if (result.action === 'unenrolled') {
										actionClass = 'pmpro-unenrolled';
									} else {
										actionClass = 'pmpro-unchanged';
									}
									
									$resultsList.append(
										'<li class="' + actionClass + '">' +
										'<a href="' + result.course_edit_url + '" target="_blank">' + result.course_title + '</a> - ' +
										result.action_text +
										'</li>'
									);
								});
							} else {
								$resultsList.append('<li><?php esc_html_e( 'No courses found.', 'paid-memberships-pro' ); ?></li>');
							}
						} else {
							// Show error
							$results.show();
							$resultsList.append('<li class="pmpro-error">' + (response.data.message || '<?php esc_html_e( 'An error occurred.', 'paid-memberships-pro' ); ?>') + '</li>');
						}
					},
					error: function() {
						// Show error
						$results.show();
						$resultsList.append('<li class="pmpro-error"><?php esc_html_e( 'An error occurred during the sync process.', 'paid-memberships-pro' ); ?></li>');
					},
					complete: function() {
						// Re-enable button and hide spinner
						$button.prop("disabled", false);
						$spinner.removeClass("is-active");
					}
				});
			});
		});
	</script>
	
	<style type="text/css">
		#pmpro-course-enrollment-sync-results-list {
			margin-top: 10px;
			border: 1px solid #ddd;
			padding: 10px;
			background: #f9f9f9;
			max-height: 200px;
			overflow-y: auto;
		}
		#pmpro-course-enrollment-sync-results-list li {
			margin-bottom: 5px;
			padding: 5px;
			border-bottom: 1px solid #eee;
		}
		#pmpro-course-enrollment-sync-results-list li:last-child {
			border-bottom: none;
		}
		.pmpro-enrolled {
			background-color: rgb(182, 228, 164);
		}
		.pmpro-unenrolled {
			background-color: rgb(240, 179, 179);
		}
		.pmpro-unchanged {
			background-color: #fcf8e3;
		}
		.pmpro-error {
			background-color: #f2dede;
			color: #a94442;
		}
	</style>
    <?php
}
add_action( 'edit_user_profile', 'pmpro_member_course_enrollment_sync_panel' );
add_action( 'show_user_profile', 'pmpro_member_course_enrollment_sync_panel' );

/**
 * AJAX handler for syncing course enrollments for a single user.
 */
function pmpro_member_course_enrollment_sync_ajax() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pmpro_member_course_enrollment_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'paid-memberships-pro' ) ) );
    }
    
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'paid-memberships-pro' ) ) );
    }
    
    // Get user ID
    $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
    if ( empty( $user_id ) ) {
        wp_send_json_error( array( 'message' => __( 'No user selected.', 'paid-memberships-pro' ) ) );
    }
    
    // Get all courses
    $args = array(
        'post_type' => 'course',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $courses = get_posts( $args );
    
    $results = array();
    $enrolled_count = 0;
    $unenrolled_count = 0;
    $unchanged_count = 0;
    
    foreach ( $courses as $course ) {
        // Check if the user should have access to the course.
        $hasaccess = pmpro_has_membership_access( $course->ID, $user_id, true );
        
        if ( is_array( $hasaccess ) ) {
            $post_membership_levels_ids = $hasaccess[1];
            $hasaccess = $hasaccess[0];
            
            $is_enrolled = llms_is_user_enrolled( $user_id, $course->ID );
            $action = '';
            $action_text = '';
            
            // If the user should have access but isn't enrolled, enroll them.
            if ( $hasaccess && ! $is_enrolled ) {
                llms_enroll_student( $user_id, $course->ID );
                $action = 'enrolled';
                $action_text = __( 'Enrolled', 'paid-memberships-pro' );
                $enrolled_count++;
            } 
            // If the user shouldn't have access but is enrolled, unenroll them.
            else if ( ! $hasaccess && $is_enrolled ) {
                llms_unenroll_student( $user_id, $course->ID );
                $action = 'unenrolled';
                $action_text = __( 'Unenrolled', 'paid-memberships-pro' );
                $unenrolled_count++;
            } 
            // No change needed.
            else {
                $action = 'unchanged';
                $action_text = $is_enrolled ? 
                    __( 'Already enrolled (no change)', 'paid-memberships-pro' ) : 
                    __( 'Already not enrolled (no change)', 'paid-memberships-pro' );
                $unchanged_count++;
            }
            
            // Add to results.
            $results[] = array(
                'course_id' => $course->ID,
                'course_title' => $course->post_title,
                'action' => $action,
                'action_text' => $action_text,
                'course_edit_url' => admin_url( 'post.php?post=' . $course->ID . '&action=edit' ),
            );
        } else {
            // Error with membership access check.
            $unchanged_count++;
            $results[] = array(
                'course_id' => $course->ID,
                'course_title' => $course->post_title,
                'action' => 'unchanged',
                'action_text' => __( 'Error checking access (no change)', 'paid-memberships-pro' ),
                'course_edit_url' => admin_url( 'post.php?post=' . $course->ID . '&action=edit' ),
            );
        }
    }
    
    // Send the response.
    wp_send_json_success( array(
        'processed' => count( $courses ),
        'enrolled' => $enrolled_count,
        'unenrolled' => $unenrolled_count,
        'unchanged' => $unchanged_count,
        'results' => $results,
    ) );
}
add_action( 'wp_ajax_pmpro_member_course_enrollment_sync', 'pmpro_member_course_enrollment_sync_ajax' );


/**
 * Register the panel for the member edit page.
 *
 * @param array $panels The panels to display on the member edit page.
 */
function pmpro_member_courses_edit_panels( $panels ) {

	// If the class exists, add a panel.
	if ( class_exists( 'PMPro_Member_Courses_Edit_Panel' ) ) {
		$panels[] = new PMPro_Member_Courses_Edit_Panel();
	}

	return $panels;
}

/**
 * Hook the correct function for admins editing a member's profile.
 */
function pmpro_add_on_template_hook_edit_member_profile() {
	
    // Added backwards compatibility if the Edit Member Panel isn't available.
	if ( function_exists( 'pmpro_member_edit_get_panels' ) ) {
		add_filter( 'pmpro_member_edit_panels', 'pmpro_member_courses_edit_panels' );
	} else {
		add_action( 'pmpro_after_membership_level_profile_fields', 'pmpro_member_course_enrollment_sync_panel', 10, 1 );
	}

}
add_action( 'admin_init', 'pmpro_add_on_template_hook_edit_member_profile', 0 );
