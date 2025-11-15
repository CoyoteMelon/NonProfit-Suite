<?php
/**
 * Document Viewer
 *
 * Public document viewer with download option.
 *
 * @package NonprofitSuite
 * @subpackage Public/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get document details (would integrate with Phase 2 document storage)
global $wpdb;
$documents_table = $wpdb->prefix . 'ns_documents';
$document = $wpdb->get_row(
	$wpdb->prepare( "SELECT * FROM {$documents_table} WHERE id = %d", $share['document_id'] ),
	ARRAY_A
);

if ( ! $document ) {
	wp_die( __( 'Document not found', 'nonprofitsuite' ) );
}

$can_download = ! empty( $share['permissions']['download'] );
$can_print = ! empty( $share['permissions']['print'] );

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $document['document_name'] ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		}

		.viewer-header {
			background: #2271b1;
			color: #fff;
			padding: 15px 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.document-title {
			font-size: 18px;
			font-weight: 600;
			margin: 0;
		}

		.viewer-actions {
			display: flex;
			gap: 10px;
		}

		.viewer-button {
			padding: 8px 16px;
			background: rgba(255,255,255,0.2);
			color: #fff;
			border: 1px solid rgba(255,255,255,0.3);
			border-radius: 4px;
			text-decoration: none;
			font-size: 14px;
			cursor: pointer;
			transition: background 0.2s;
		}

		.viewer-button:hover {
			background: rgba(255,255,255,0.3);
		}

		.viewer-button.primary {
			background: #fff;
			color: #2271b1;
			border-color: #fff;
		}

		.viewer-button.primary:hover {
			background: #f0f0f1;
		}

		.viewer-container {
			height: calc(100vh - 60px);
			background: #f0f0f1;
		}

		.document-iframe {
			width: 100%;
			height: 100%;
			border: none;
		}

		.watermark-overlay {
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%) rotate(-45deg);
			font-size: 72px;
			color: rgba(0,0,0,0.05);
			pointer-events: none;
			z-index: 9999;
			white-space: nowrap;
		}

		.document-info {
			background: #fff;
			padding: 20px;
			border-bottom: 1px solid #ddd;
		}

		.document-info p {
			margin: 5px 0;
			color: #646970;
			font-size: 14px;
		}

		.download-disclaimer {
			background: #fcf9e8;
			border-left: 4px solid #dba617;
			padding: 15px;
			margin: 20px;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="viewer-header">
		<h1 class="document-title"><?php echo esc_html( $document['document_name'] ); ?></h1>
		<div class="viewer-actions">
			<?php if ( $can_print ) : ?>
				<button class="viewer-button" onclick="window.print();">
					<?php esc_html_e( 'Print', 'nonprofitsuite' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $can_download ) : ?>
				<a href="<?php echo esc_url( $document['file_url'] ?? '#' ); ?>" class="viewer-button primary" download>
					<?php esc_html_e( 'Download', 'nonprofitsuite' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="document-info">
		<p>
			<strong><?php esc_html_e( 'File Type:', 'nonprofitsuite' ); ?></strong>
			<?php echo esc_html( strtoupper( $document['file_type'] ?? 'pdf' ) ); ?>
			&nbsp;•&nbsp;
			<strong><?php esc_html_e( 'Size:', 'nonprofitsuite' ); ?></strong>
			<?php echo esc_html( size_format( $document['file_size'] ?? 0 ) ); ?>
			&nbsp;•&nbsp;
			<strong><?php esc_html_e( 'Uploaded:', 'nonprofitsuite' ); ?></strong>
			<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $document['created_at'] ) ) ); ?>
		</p>
		<?php if ( ! empty( $document['document_description'] ) ) : ?>
			<p><?php echo esc_html( $document['document_description'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! $can_download && ! empty( $share['max_downloads'] ) ) : ?>
		<div class="download-disclaimer">
			<strong><?php esc_html_e( 'Notice:', 'nonprofitsuite' ); ?></strong>
			<?php esc_html_e( 'This document is view-only. Download has been restricted by the document owner.', 'nonprofitsuite' ); ?>
		</div>
	<?php endif; ?>

	<div class="viewer-container">
		<?php if ( ! empty( $share['watermark_text'] ) ) : ?>
			<div class="watermark-overlay">
				<?php echo esc_html( $share['watermark_text'] ); ?>
			</div>
		<?php endif; ?>

		<iframe src="<?php echo esc_url( $document['file_url'] ?? '' ); ?>" class="document-iframe"></iframe>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
