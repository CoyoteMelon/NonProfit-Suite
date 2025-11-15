<?php
/**
 * CRM Field Mappings View
 *
 * Configure field mappings between NonprofitSuite and CRM.
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define NonprofitSuite contact fields
$ns_contact_fields = array(
	array( 'name' => 'first_name', 'label' => 'First Name', 'type' => 'string' ),
	array( 'name' => 'last_name', 'label' => 'Last Name', 'type' => 'string' ),
	array( 'name' => 'email', 'label' => 'Email', 'type' => 'email' ),
	array( 'name' => 'phone', 'label' => 'Phone', 'type' => 'phone' ),
	array( 'name' => 'address', 'label' => 'Address', 'type' => 'text' ),
	array( 'name' => 'city', 'label' => 'City', 'type' => 'string' ),
	array( 'name' => 'state', 'label' => 'State', 'type' => 'string' ),
	array( 'name' => 'zip', 'label' => 'Zip Code', 'type' => 'string' ),
	array( 'name' => 'country', 'label' => 'Country', 'type' => 'string' ),
);

$ns_donation_fields = array(
	array( 'name' => 'amount', 'label' => 'Amount', 'type' => 'number' ),
	array( 'name' => 'created_at', 'label' => 'Date', 'type' => 'date' ),
	array( 'name' => 'processor', 'label' => 'Payment Method', 'type' => 'string' ),
	array( 'name' => 'transaction_id', 'label' => 'Transaction ID', 'type' => 'string' ),
	array( 'name' => 'status', 'label' => 'Status', 'type' => 'string' ),
);

// Group existing mappings by entity type
$mappings_by_entity = array();
foreach ( $mappings as $mapping ) {
	$mappings_by_entity[ $mapping['entity_type'] ][] = $mapping;
}

?>

<div class="wrap">
	<h1>CRM Field Mappings - <?php echo esc_html( ucfirst( $provider ) ); ?></h1>
	<p>Map NonprofitSuite fields to <?php echo esc_html( ucfirst( $provider ) ); ?> fields.</p>

	<h2 class="nav-tab-wrapper">
		<a href="#contact-mappings" class="nav-tab nav-tab-active">Contact Mappings</a>
		<a href="#donation-mappings" class="nav-tab">Donation Mappings</a>
	</h2>

	<!-- Contact Mappings -->
	<div id="contact-mappings" class="ns-mapping-tab-content">
		<form class="ns-field-mappings-form" data-entity-type="contact">
			<table class="widefat">
				<thead>
					<tr>
						<th>NonprofitSuite Field</th>
						<th>CRM Field</th>
						<th>Sync Direction</th>
						<th>Conflict Resolution</th>
						<th>Required</th>
						<th>Active</th>
					</tr>
				</thead>
				<tbody id="contact-mapping-rows">
					<?php
					$contact_mappings = isset( $mappings_by_entity['contact'] ) ? $mappings_by_entity['contact'] : array();
					if ( ! empty( $contact_mappings ) ) :
						foreach ( $contact_mappings as $mapping ) :
							?>
							<tr>
								<td>
									<select name="mappings[contact][]ns_field_name" class="ns-field-select">
										<?php foreach ( $ns_contact_fields as $field ) : ?>
											<option value="<?php echo esc_attr( $field['name'] ); ?>" data-type="<?php echo esc_attr( $field['type'] ); ?>" <?php selected( $mapping['ns_field_name'], $field['name'] ); ?>>
												<?php echo esc_html( $field['label'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<input type="hidden" name="mappings[contact][ns_field_type]" value="<?php echo esc_attr( $mapping['ns_field_type'] ); ?>">
								</td>
								<td>
									<input type="text" name="mappings[contact][crm_field_name]" value="<?php echo esc_attr( $mapping['crm_field_name'] ); ?>" class="regular-text">
								</td>
								<td>
									<select name="mappings[contact][sync_direction]">
										<option value="push" <?php selected( $mapping['sync_direction'], 'push' ); ?>>Push</option>
										<option value="pull" <?php selected( $mapping['sync_direction'], 'pull' ); ?>>Pull</option>
										<option value="bidirectional" <?php selected( $mapping['sync_direction'], 'bidirectional' ); ?>>Both</option>
									</select>
								</td>
								<td>
									<select name="mappings[contact][conflict_resolution]">
										<option value="ns_wins" <?php selected( $mapping['conflict_resolution'], 'ns_wins' ); ?>>NS Wins</option>
										<option value="crm_wins" <?php selected( $mapping['conflict_resolution'], 'crm_wins' ); ?>>CRM Wins</option>
										<option value="newest_wins" <?php selected( $mapping['conflict_resolution'], 'newest_wins' ); ?>>Newest Wins</option>
										<option value="manual" <?php selected( $mapping['conflict_resolution'], 'manual' ); ?>>Manual</option>
									</select>
								</td>
								<td>
									<input type="checkbox" name="mappings[contact][is_required]" value="1" <?php checked( $mapping['is_required'], 1 ); ?>>
								</td>
								<td>
									<input type="checkbox" name="mappings[contact][is_active]" value="1" <?php checked( $mapping['is_active'], 1 ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6">
								<p>No contact field mappings configured. Click "Fetch CRM Fields" to auto-generate mappings.</p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="submit">
				<button type="button" class="button ns-fetch-crm-fields" data-entity-type="contact">Fetch CRM Fields</button>
				<button type="submit" class="button button-primary">Save Mappings</button>
			</p>
		</form>
	</div>

	<!-- Donation Mappings -->
	<div id="donation-mappings" class="ns-mapping-tab-content" style="display:none;">
		<form class="ns-field-mappings-form" data-entity-type="donation">
			<table class="widefat">
				<thead>
					<tr>
						<th>NonprofitSuite Field</th>
						<th>CRM Field</th>
						<th>Sync Direction</th>
						<th>Conflict Resolution</th>
						<th>Required</th>
						<th>Active</th>
					</tr>
				</thead>
				<tbody id="donation-mapping-rows">
					<?php
					$donation_mappings = isset( $mappings_by_entity['donation'] ) ? $mappings_by_entity['donation'] : array();
					if ( ! empty( $donation_mappings ) ) :
						foreach ( $donation_mappings as $mapping ) :
							?>
							<tr>
								<td>
									<select name="mappings[donation][ns_field_name]">
										<?php foreach ( $ns_donation_fields as $field ) : ?>
											<option value="<?php echo esc_attr( $field['name'] ); ?>" <?php selected( $mapping['ns_field_name'], $field['name'] ); ?>>
												<?php echo esc_html( $field['label'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" name="mappings[donation][crm_field_name]" value="<?php echo esc_attr( $mapping['crm_field_name'] ); ?>" class="regular-text">
								</td>
								<td>
									<select name="mappings[donation][sync_direction]">
										<option value="push" <?php selected( $mapping['sync_direction'], 'push' ); ?>>Push</option>
										<option value="pull" <?php selected( $mapping['sync_direction'], 'pull' ); ?>>Pull</option>
										<option value="bidirectional" <?php selected( $mapping['sync_direction'], 'bidirectional' ); ?>>Both</option>
									</select>
								</td>
								<td>
									<select name="mappings[donation][conflict_resolution]">
										<option value="ns_wins" <?php selected( $mapping['conflict_resolution'], 'ns_wins' ); ?>>NS Wins</option>
										<option value="crm_wins" <?php selected( $mapping['conflict_resolution'], 'crm_wins' ); ?>>CRM Wins</option>
										<option value="newest_wins" <?php selected( $mapping['conflict_resolution'], 'newest_wins' ); ?>>Newest Wins</option>
									</select>
								</td>
								<td>
									<input type="checkbox" name="mappings[donation][is_required]" value="1" <?php checked( $mapping['is_required'], 1 ); ?>>
								</td>
								<td>
									<input type="checkbox" name="mappings[donation][is_active]" value="1" <?php checked( $mapping['is_active'], 1 ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6">
								<p>No donation field mappings configured. Click "Fetch CRM Fields" to auto-generate mappings.</p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="submit">
				<button type="button" class="button ns-fetch-crm-fields" data-entity-type="donation">Fetch CRM Fields</button>
				<button type="submit" class="button button-primary">Save Mappings</button>
			</p>
		</form>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ns-crm-settings&organization_id=' . $org_id ) ); ?>" class="button">
			Back to CRM Settings
		</a>
	</p>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.ns-mapping-tab-content').hide();
		$($(this).attr('href')).show();
	});

	// Fetch CRM fields
	$('.ns-fetch-crm-fields').on('click', function() {
		var button = $(this);
		var entityType = button.data('entity-type');

		button.prop('disabled', true).text('Fetching...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_get_crm_fields',
				provider: '<?php echo esc_js( $provider ); ?>',
				organization_id: <?php echo intval( $org_id ); ?>,
				entity_type: entityType,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert('CRM fields fetched successfully! Please configure the mappings and save.');
					// Optionally populate the table with the fetched fields
				} else {
					alert('Failed to fetch CRM fields: ' + response.data.message);
				}
				button.prop('disabled', false).text('Fetch CRM Fields');
			}
		});
	});

	// Save mappings
	$('.ns-field-mappings-form').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var entityType = form.data('entity-type');

		// Build mappings array from form data
		var mappings = [];
		form.find('tbody tr').each(function() {
			var row = $(this);
			mappings.push({
				entity_type: entityType,
				ns_field_name: row.find('[name*="ns_field_name"]').val(),
				ns_field_type: row.find('[name*="ns_field_type"]').val() || 'string',
				crm_field_name: row.find('[name*="crm_field_name"]').val(),
				crm_field_type: 'string',
				sync_direction: row.find('[name*="sync_direction"]').val(),
				conflict_resolution: row.find('[name*="conflict_resolution"]').val(),
				is_required: row.find('[name*="is_required"]').is(':checked') ? 1 : 0,
				is_active: row.find('[name*="is_active"]').is(':checked') ? 1 : 0
			});
		});

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ns_save_field_mappings',
				provider: '<?php echo esc_js( $provider ); ?>',
				organization_id: <?php echo intval( $org_id ); ?>,
				mappings: mappings,
				nonce: '<?php echo wp_create_nonce( 'ns_crm_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('Save failed: ' + response.data.message);
				}
			}
		});
	});
});
</script>

<style>
.ns-mapping-tab-content {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	border-top: none;
	margin-bottom: 20px;
}
</style>
