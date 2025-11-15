<?php
/**
 * Bulk Operations Helper
 *
 * Handles bulk delete, edit, and other mass operations on entities
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Operations class for mass operations on entities
 */
class NonprofitSuite_Bulk_Operations {

	/**
	 * Perform bulk delete operation
	 *
	 * @param string $entity_class Entity class name (e.g., 'NonprofitSuite_Meetings')
	 * @param array  $ids Array of entity IDs to delete
	 * @param string $capability Required capability to perform operation
	 * @return array Results with success/failure counts
	 */
	public static function bulk_delete( $entity_class, $ids, $capability = 'manage_options' ) {
		// Check permissions
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to perform this operation.', 'nonprofitsuite' )
			);
		}

		// Validate inputs
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_Error(
				'invalid_input',
				__( 'No items selected for deletion.', 'nonprofitsuite' )
			);
		}

		// Validate entity class exists
		if ( ! class_exists( $entity_class ) || ! method_exists( $entity_class, 'delete' ) ) {
			return new WP_Error(
				'invalid_entity',
				__( 'Invalid entity type.', 'nonprofitsuite' )
			);
		}

		// Perform deletions
		$results = array(
			'success' => 0,
			'failed' => 0,
			'errors' => array(),
		);

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				$results['failed']++;
				$results['errors'][] = sprintf(
					/* translators: %d: Invalid ID */
					__( 'Invalid ID: %d', 'nonprofitsuite' ),
					$id
				);
				continue;
			}

			$result = call_user_func( array( $entity_class, 'delete' ), $id );

			if ( is_wp_error( $result ) || $result === false ) {
				$results['failed']++;
				$results['errors'][] = is_wp_error( $result )
					? $result->get_error_message()
					: sprintf(
						/* translators: %d: Entity ID */
						__( 'Failed to delete item #%d', 'nonprofitsuite' ),
						$id
					);
			} else {
				$results['success']++;
			}
		}

		return $results;
	}

	/**
	 * Perform bulk update operation
	 *
	 * @param string $entity_class Entity class name
	 * @param array  $ids Array of entity IDs to update
	 * @param array  $data Data to update
	 * @param string $capability Required capability
	 * @return array Results with success/failure counts
	 */
	public static function bulk_update( $entity_class, $ids, $data, $capability = 'manage_options' ) {
		// Check permissions
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'permission_denied',
				__( 'You do not have permission to perform this operation.', 'nonprofitsuite' )
			);
		}

		// Validate inputs
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_Error(
				'invalid_input',
				__( 'No items selected for update.', 'nonprofitsuite' )
			);
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'No data provided for update.', 'nonprofitsuite' )
			);
		}

		// Validate entity class exists
		if ( ! class_exists( $entity_class ) || ! method_exists( $entity_class, 'update' ) ) {
			return new WP_Error(
				'invalid_entity',
				__( 'Invalid entity type.', 'nonprofitsuite' )
			);
		}

		// Perform updates
		$results = array(
			'success' => 0,
			'failed' => 0,
			'errors' => array(),
		);

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				$results['failed']++;
				continue;
			}

			$result = call_user_func( array( $entity_class, 'update' ), $id, $data );

			if ( is_wp_error( $result ) || $result === false ) {
				$results['failed']++;
				$results['errors'][] = is_wp_error( $result )
					? $result->get_error_message()
					: sprintf(
						/* translators: %d: Entity ID */
						__( 'Failed to update item #%d', 'nonprofitsuite' ),
						$id
					);
			} else {
				$results['success']++;
			}
		}

		return $results;
	}

	/**
	 * Render bulk actions dropdown
	 *
	 * @param string $name Select element name
	 * @param array  $actions Available actions (value => label)
	 * @param string $button_text Button text (default: "Apply")
	 */
	public static function render_bulk_actions_dropdown( $name = 'bulk_action', $actions = array(), $button_text = null ) {
		if ( empty( $actions ) ) {
			$actions = array(
				'delete' => __( 'Delete', 'nonprofitsuite' ),
			);
		}

		if ( null === $button_text ) {
			$button_text = __( 'Apply', 'nonprofitsuite' );
		}
		?>
		<div class="ns-bulk-actions" style="margin: 10px 0;">
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" style="padding: 6px;">
				<option value=""><?php esc_html_e( 'Bulk Actions', 'nonprofitsuite' ); ?></option>
				<?php foreach ( $actions as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="ns-button ns-button-secondary" style="margin-left: 5px;">
				<?php echo esc_html( $button_text ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render select all checkbox for table header
	 *
	 * @param string $name Checkbox name attribute
	 */
	public static function render_select_all_checkbox( $name = 'bulk_select' ) {
		?>
		<th class="ns-check-column" style="width: 40px;">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $name . '_all' ); ?>"
				class="ns-select-all"
				data-target="<?php echo esc_attr( $name ); ?>"
			/>
		</th>
		<?php
	}

	/**
	 * Render checkbox for table row
	 *
	 * @param string $name Checkbox name attribute
	 * @param int    $id Entity ID
	 */
	public static function render_row_checkbox( $name = 'bulk_select', $id = 0 ) {
		?>
		<td class="ns-check-column">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $name ); ?>[]"
				value="<?php echo absint( $id ); ?>"
				class="ns-bulk-select-item"
			/>
		</td>
		<?php
	}

	/**
	 * Generate JavaScript for select all functionality
	 *
	 * @param string $container_selector jQuery selector for container
	 */
	public static function render_bulk_actions_script( $container_selector = '.ns-container' ) {
		?>
		<script>
		jQuery(document).ready(function($) {
			// Select all checkbox
			$(document).on('change', '.ns-select-all', function() {
				var targetClass = $(this).data('target');
				var isChecked = $(this).prop('checked');
				$('input[name="' + targetClass + '[]"]').prop('checked', isChecked);
			});

			// Update select all state when individual checkboxes change
			$(document).on('change', '.ns-bulk-select-item', function() {
				var allChecked = $('.ns-bulk-select-item').length === $('.ns-bulk-select-item:checked').length;
				$('.ns-select-all').prop('checked', allChecked);
			});

			// Bulk action form submission
			$('<?php echo esc_js( $container_selector ); ?> form').on('submit', function(e) {
				var bulkAction = $('[name="bulk_action"]').val();
				var selectedItems = $('.ns-bulk-select-item:checked').length;

				if (bulkAction && selectedItems === 0) {
					e.preventDefault();
					alert('<?php echo esc_js( __( 'Please select at least one item.', 'nonprofitsuite' ) ); ?>');
					return false;
				}

				if (bulkAction === 'delete' && selectedItems > 0) {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the selected items? This action cannot be undone.', 'nonprofitsuite' ) ); ?>')) {
						e.preventDefault();
						return false;
					}
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Display bulk operation results
	 *
	 * @param array $results Results array from bulk operation
	 */
	public static function display_bulk_results( $results ) {
		if ( is_wp_error( $results ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $results->get_error_message() ); ?></p>
			</div>
			<?php
			return;
		}

		if ( $results['success'] > 0 ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html( sprintf(
						/* translators: %d: Number of successfully processed items */
						_n(
							'Successfully processed %d item.',
							'Successfully processed %d items.',
							$results['success'],
							'nonprofitsuite'
						),
						$results['success']
					) );
					?>
				</p>
			</div>
			<?php
		}

		if ( $results['failed'] > 0 ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					echo esc_html( sprintf(
						/* translators: %d: Number of failed items */
						_n(
							'Failed to process %d item.',
							'Failed to process %d items.',
							$results['failed'],
							'nonprofitsuite'
						),
						$results['failed']
					) );
					?>
				</p>
				<?php if ( ! empty( $results['errors'] ) ) : ?>
					<ul style="margin-top: 10px;">
						<?php foreach ( array_slice( $results['errors'], 0, 5 ) as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
						<?php if ( count( $results['errors'] ) > 5 ) : ?>
							<li><?php esc_html_e( '...and more', 'nonprofitsuite' ); ?></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
