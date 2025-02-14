<?php
/**
 * Plugin Name: Easy Digital Downloads - Discount Cloner
 * Plugin URI: https://github.com/GravityKit/edd-discount-cloner
 * Description: Adds the ability to clone discount codes in Easy Digital Downloads
 * Version: 1.0.0
 * Author: GravityKit
 * Author URI: https://www.gravitykit.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class EDD_Discount_Cloner {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'process_clone_discount' ) );
		add_filter( 'edd_discount_row_actions', array( $this, 'add_clone_link' ), 10, 2 );
	}

	/**
	 * Add clone link to discount code actions
	 *
	 * @param array $actions Existing actions
	 * @param array $discount Discount data
	 *
	 * @return array Modified actions
	 */
	public function add_clone_link( $actions, $discount ) {
		if ( ! current_user_can( 'manage_shop_discounts' ) ) {
			return $actions;
		}

		$clone_url = wp_nonce_url(
			add_query_arg(
				array(
					'edd-action'  => 'clone_discount',
					'discount-id' => $discount->id,
				),
				admin_url( 'edit.php?post_type=download&page=edd-discounts' )
			),
			'edd_discount_clone_nonce'
		);

		$actions['clone'] = '<a href="' . esc_url( $clone_url ) . '">' . esc_html__( 'Clone', 'edd-discount-cloner' ) . '</a>';

		return $actions;
	}

	/**
	 * Process the discount code cloning
	 */
	public function process_clone_discount() {
		// Check if we're cloning a discount
		if ( ! isset( $_GET['edd-action'] ) || 'clone_discount' !== $_GET['edd-action'] ) {
			return;
		}

		// Verify permissions
		if ( ! current_user_can( 'manage_shop_discounts' ) ) {
			wp_die( esc_html__( 'You do not have permission to clone discount codes.', 'edd-discount-cloner' ), esc_html__( 'Error', 'edd-discount-cloner' ), array( 'response' => 403 ) );
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_discount_clone_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'edd-discount-cloner' ), esc_html__( 'Error', 'edd-discount-cloner' ), array( 'response' => 403 ) );
		}

		// Get the discount ID
		$discount_id = absint( $_GET['discount-id'] );
		if ( ! $discount_id ) {
			wp_die( esc_html__( 'No discount ID provided.', 'edd-discount-cloner' ) );
		}

		// Get the original discount
		$discount = edd_get_discount( $discount_id );
		if ( ! $discount ) {
			wp_die( esc_html__( 'Invalid discount ID.', 'edd-discount-cloner' ) );
		}

		// Prepare the new discount data
		$discount_data = array(
			'name'              => sprintf( esc_html__( '%s (Copy)', 'edd-discount-cloner' ), $discount->name ),
			'code'              => $this->generate_unique_code( $discount->code ),
			'status'            => 'inactive',
			'type'              => $discount->type,
			'amount'            => $discount->amount,
			'amount_type'       => $discount->amount_type,
			'min_charge_amount' => $discount->min_charge_amount,
			'start_date'        => $discount->start_date,
			'end_date'          => $discount->end_date,
			'use_count'         => 0,
			'max_uses'          => $discount->max_uses,
			'once_per_customer' => $discount->once_per_customer,
			'scope'             => $discount->scope ? $discount->scope : 'global',
			'product_condition' => $discount->product_condition ? $discount->product_condition : 'any',
			'product_reqs'      => $discount->product_reqs,
			'excluded_products' => $discount->excluded_products,
			'categories'        => edd_get_adjustment_meta( $discount_id, 'categories', true ),
			'term_condition'    => edd_get_adjustment_meta( $discount_id, 'term_condition', true ),
		);

		// Insert the new discount.
		$new_discount_id = edd_add_discount( $discount_data );

		if ( ! $new_discount_id || is_wp_error( $new_discount_id ) ) {
			wp_die( esc_html__( 'Error creating discount code clone.', 'edd-discount-cloner' ) );
		}

		// Force inactive status after creation (this wasn't taking effect when using edd_add_discount()).
		edd_update_adjustment( $new_discount_id, array( 'status' => 'inactive' ) );

		// If there are product requirements, ensure scope is not_global.
		if ( ! empty( $discount_data['product_reqs'] ) && $discount_data['scope'] === 'global' ) {
			edd_update_adjustment( $new_discount_id, array( 'scope' => 'not_global' ) );
		}

		// Clone notes for a discount.
		$this->clone_notes( $discount_id, $new_discount_id );

		// Clone all additional meta to support other add-ons.
		$this->clone_adjustment_meta( $discount_id, $new_discount_id );

		// Redirect back to the discounts page.
		wp_safe_redirect( add_query_arg(
			array(
				'post_type'   => 'download',
				'page'        => 'edd-discounts',
				'edd-message' => 'discount_cloned',
				'discount-id' => absint( $new_discount_id ),
			),
			admin_url( 'edit.php' )
		) );
		exit;
	}

	/**
	 * Clone notes for a discount
	 *
	 * @param int $original_discount_id The ID of the original discount
	 * @param int $new_discount_id The ID of the new discount
	 */
	private function clone_notes( $discount_id, $new_discount_id ) {

		// Called directly instead of using edd_get_discount_notes() to avoid the limit of 30 notes.
		$notes = edd_get_notes( array(
			'object_id'   => $discount_id,
			'object_type' => 'discount',
			'order'       => 'asc',
			'number'      => - 1,
		) );

		if ( empty( $notes ) ) {
			return;
		}
		foreach ( $notes as $note ) {
			$note_data = array(
				'object_id'   => $new_discount_id,
				'object_type' => 'discount',
				'content'     => $note->content,
			);

			// Only include optional fields if they exist
			if ( ! empty( $note->user_id ) ) {
				$note_data['user_id'] = $note->user_id;
			}
			if ( ! empty( $note->date_created ) ) {
				$note_data['date_created'] = $note->date_created;
			}
			if ( ! empty( $note->date_modified ) ) {
				$note_data['date_modified'] = $note->date_modified;
			}

			edd_add_note( $note_data );
		}
	}

	/**
	 * Clone adjustment meta for a discount to support other add-ons, like
	 * AffiliateWP and WP Fusion.
	 *
	 * @param int $discount_id The ID of the original discount
	 * @param int $new_discount_id The ID of the new discount
	 */
	private function clone_adjustment_meta( $discount_id, $new_discount_id ) {
		global $wpdb;

		$meta = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->prefix}edd_adjustmentmeta 
			WHERE edd_adjustment_id = %d 
			AND meta_key NOT IN (
				'product_requirement',
				'excluded_product',
				'product_condition',
				'categories',
				'term_condition'
			)",
			$discount_id
		) );

		foreach ( $meta as $meta_item ) {
			$meta_value = maybe_unserialize( $meta_item->meta_value );
			edd_add_adjustment_meta( $new_discount_id, $meta_item->meta_key, $meta_value );
		}
	}

	/**
	 * Generate a unique discount code
	 *
	 * @param string $original_code The original discount code
	 *
	 * @return string A unique discount code
	 */
	private function generate_unique_code( $original_code ) {
		$counter  = 1;
		$new_code = $original_code . '-' . $counter;

		while ( edd_get_discount_by_code( $new_code ) ) {
			$counter ++;
			$new_code = $original_code . '-' . $counter;
		}

		return $new_code;
	}
}

// Initialize the plugin
new EDD_Discount_Cloner();

// Add a notice for successful cloning
function edd_discount_cloner_admin_notices() {
	if ( ! isset( $_GET['edd-message'] ) || 'discount_cloned' !== $_GET['edd-message'] ) {
		return;
	}

	$discount_id = isset( $_GET['discount-id'] ) ? absint( $_GET['discount-id'] ) : 0;
	$edit_url    = '';

		if ( $discount_id ) {
			$edit_url = add_query_arg(
				array(
					'edd-action' => 'edit_discount',
					'discount'   => $discount_id,
				),
				admin_url( 'edit.php?post_type=download&page=edd-discounts' )
			);
		}
		?>
        <div class="notice notice-success is-dismissible">
            <p>
				<?php
				if ( $edit_url ) {
					printf(
					/* translators: %s: URL to edit the cloned discount */
						esc_html__( 'Discount code cloned successfully. %s', 'edd-discount-cloner' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( $edit_url ),
							esc_html__( 'Edit the cloned discount', 'edd-discount-cloner' )
						)
					);
				} else {
					esc_html_e( 'Discount code cloned successfully.', 'edd-discount-cloner' );
				}
				?>
            </p>
        </div>
		<?php
	}

add_action( 'admin_notices', 'edd_discount_cloner_admin_notices' );
