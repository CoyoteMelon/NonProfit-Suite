<?php
/**
 * Chapters & Affiliates Admin Interface
 *
 * @package NonprofitSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get chapters
$chapters = isset( $chapters ) ? $chapters : NonprofitSuite_Chapters::get_chapters();
$dashboard_data = isset( $dashboard_data ) ? $dashboard_data : NonprofitSuite_Chapters::get_dashboard_data();
?>

<div class="wrap ns-container">
	<h1><?php esc_html_e( 'Chapters & Affiliates', 'nonprofitsuite' ); ?></h1>

	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
		<div class="ns-card" style="padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Total Chapters', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: bold;"><?php echo esc_html( $dashboard_data['total_chapters'] ?? 0 ); ?></p>
		</div>

		<div class="ns-card" style="padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Active Chapters', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $dashboard_data['active_chapters'] ?? 0 ); ?></p>
		</div>

		<div class="ns-card" style="padding: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Total Members', 'nonprofitsuite' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html( number_format( $dashboard_data['total_members'] ?? 0 ) ); ?></p>
		</div>
	</div>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'All Chapters', 'nonprofitsuite' ); ?></h2>
			<button class="ns-button ns-button-primary"><?php esc_html_e( 'Add Chapter', 'nonprofitsuite' ); ?></button>
		</div>

		<?php if ( is_array( $chapters ) && ! empty( $chapters ) ) : ?>
			<div class="ns-table-container">
				<table class="ns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Chapter Name', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Location', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'President', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Members', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Established', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nonprofitsuite' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nonprofitsuite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $chapters as $chapter ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $chapter->chapter_name ); ?></strong></td>
								<td><?php echo esc_html( $chapter->city ? $chapter->city . ', ' . $chapter->state_code : '-' ); ?></td>
								<td><?php echo $chapter->president_id ? esc_html( NonprofitSuite_Utilities::get_user_display_name( $chapter->president_id ) ) : '-'; ?></td>
								<td><?php echo esc_html( number_format( $chapter->member_count ?? 0 ) ); ?></td>
								<td><?php echo $chapter->established_date ? esc_html( NonprofitSuite_Utilities::format_date( $chapter->established_date ) ) : '-'; ?></td>
								<td><?php echo NonprofitSuite_Utilities::get_status_badge( $chapter->status ); ?></td>
								<td>
									<button class="ns-button ns-button-sm"><?php esc_html_e( 'View', 'nonprofitsuite' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div style="text-align: center; padding: 60px;">
				<p><?php esc_html_e( 'No chapters yet. Add your first chapter to start building your network!', 'nonprofitsuite' ); ?></p>
				<button class="ns-button ns-button-primary"><?php esc_html_e( 'Add First Chapter', 'nonprofitsuite' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<div class="ns-card" style="margin-top: 20px;">
		<h3><?php esc_html_e( 'Chapter Resources', 'nonprofitsuite' ); ?></h3>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
			<div>
				<h4><?php esc_html_e( 'Chapter Reporting', 'nonprofitsuite' ); ?></h4>
				<p><?php esc_html_e( 'View financial reports and compliance status for all chapters.', 'nonprofitsuite' ); ?></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Communication', 'nonprofitsuite' ); ?></h4>
				<p><?php esc_html_e( 'Send announcements and updates to chapter leaders.', 'nonprofitsuite' ); ?></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Best Practices', 'nonprofitsuite' ); ?></h4>
				<p><?php esc_html_e( 'Share resources and best practices with your chapter network.', 'nonprofitsuite' ); ?></p>
			</div>
		</div>
	</div>
</div>
