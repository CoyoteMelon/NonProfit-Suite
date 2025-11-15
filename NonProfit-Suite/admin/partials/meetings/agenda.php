<?php
/**
 * Agenda Builder View
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$meeting_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$meeting = NonprofitSuite_Meetings::get( $meeting_id );
$agenda_items = NonprofitSuite_Agenda::get_items( $meeting_id );
?>

<div class="wrap ns-container">
	<h1><?php printf( esc_html__( 'Agenda: %s', 'nonprofitsuite' ), esc_html( $meeting->title ) ); ?></h1>

	<div class="ns-card">
		<div class="ns-card-header">
			<h2 class="ns-card-title"><?php esc_html_e( 'Agenda Items', 'nonprofitsuite' ); ?></h2>
			<div>
				<button class="ns-button ns-button-outline" onclick="nsExportAgendaPDF(<?php echo esc_js( $meeting_id ); ?>);" style="margin-right: 10px;"><?php esc_html_e( 'Export PDF', 'nonprofitsuite' ); ?></button>
				<button class="ns-button ns-button-primary" onclick="alert('Add item functionality coming soon');"><?php esc_html_e( 'Add Item', 'nonprofitsuite' ); ?></button>
			</div>
		</div>

		<?php if ( ! empty( $agenda_items ) ) : ?>
			<ul class="ns-agenda-items">
				<?php foreach ( $agenda_items as $item ) : ?>
					<li class="ns-agenda-item" data-item-id="<?php echo esc_attr( $item->id ); ?>">
						<span class="ns-drag-handle" style="cursor: move; margin-right: 10px;">☰</span>
						<div style="display: inline-block; vertical-align: top;">
							<strong><?php echo esc_html( $item->title ); ?></strong>
							<p><?php echo esc_html( $item->description ); ?></p>
							<small><?php echo esc_html( ucwords( str_replace( '_', ' ', $item->item_type ) ) ); ?> - <?php echo $item->time_allocated ? esc_html( $item->time_allocated ) . ' min' : 'No time limit'; ?></small>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No agenda items yet. Add your first item!', 'nonprofitsuite' ); ?></p>
		<?php endif; ?>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nonprofitsuite-meetings' ) ); ?>" class="ns-button ns-button-outline"><?php esc_html_e( 'Back to Meetings', 'nonprofitsuite' ); ?></a>
	</p>
</div>
