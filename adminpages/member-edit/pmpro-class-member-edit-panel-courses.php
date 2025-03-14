<?php
class PMPro_Member_Courses_Edit_Panel extends PMPro_Member_Edit_Panel {
    /**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug        = 'courses';
		$this->title       = __( 'Courses', 'paid-memberships-pro' );
	}

    /**
     * Display the panel content.
     */
    protected function display_panel_contents() {
		pmpro_member_course_enrollment_sync_panel( self::get_user() );

		// Let others hook in here to display the contents of the panel.
        do_action( 'pmpro_member_courses_edit_panel_contents', self::get_user() );
    }

    /**
     * Custom save logic for our panel.
     * Only needed if you are saving the panel contents.
     */
    public function save() {
        // We are going to save the data to the options table, and make sure only the admin can save the info.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if the save is coming from the 'right' panel?
        if ( ! empty( $_REQUEST['pmpro_member_edit_panel'] ) && $_REQUEST['pmpro_member_edit_panel'] == 'pmpro-member-courses' ) {

            // Check that the save nonce is okay. May seem redundant, but it's good practice.
            if ( ! wp_verify_nonce( $_REQUEST['pmpro_member_courses_edit_panel_nonce'], 'pmpro_member_courses_edit_panel_save' ) ) {
                return;
            }

			// Check that the user has permission to edit the user.
			if ( ! current_user_can( pmpro_get_edit_member_capability() ) ) {
				pmpro_setMessage( __( "You do not have permission to update this user's membership levels.", 'paid-memberships-pro' ), 'pmpro_error' );
				return;
			}

			// Check the user that we are editing.
			$user = self::get_user();
			if ( empty( $user->ID ) ) {
				pmpro_setMessage( __( 'User not found.', 'paid-memberships-pro' ), 'pmpro_error' );
				return;
			}

			// Let others hook in here to save the data from this panel.
			do_action( 'pmpro_member_courses_edit_panel_save', $user );

        }
    }
}
