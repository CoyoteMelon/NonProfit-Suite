<?php
/**
 * PDF Generator Helper
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class NonprofitSuite_PDF_Generator {

	/**
	 * Generate PDF for agenda.
	 *
	 * @param int $meeting_id The meeting ID.
	 * @return string|WP_Error PDF file path or error.
	 */
	public static function generate_agenda( $meeting_id ) {
		$meeting = NonprofitSuite_Meetings::get( $meeting_id );
		if ( ! $meeting ) {
			return new WP_Error( 'no_meeting', __( 'Meeting not found.', 'nonprofitsuite' ) );
		}

		$agenda_items = NonprofitSuite_Agenda::get_items( $meeting_id );

		$html = self::get_agenda_html( $meeting, $agenda_items );

		return self::html_to_pdf( $html, 'agenda-' . $meeting_id . '.pdf' );
	}

	/**
	 * Generate PDF for minutes.
	 *
	 * @param int $meeting_id The meeting ID.
	 * @return string|WP_Error PDF file path or error.
	 */
	public static function generate_minutes( $meeting_id ) {
		$meeting = NonprofitSuite_Meetings::get( $meeting_id );
		$minutes = NonprofitSuite_Minutes::get_by_meeting( $meeting_id );

		if ( ! $meeting || ! $minutes ) {
			return new WP_Error( 'no_data', __( 'Meeting or minutes not found.', 'nonprofitsuite' ) );
		}

		$html = self::get_minutes_html( $meeting, $minutes );

		return self::html_to_pdf( $html, 'minutes-' . $meeting_id . '.pdf' );
	}

	/**
	 * Convert HTML to PDF.
	 *
	 * @param string $html HTML content.
	 * @param string $filename PDF filename.
	 * @return string|WP_Error PDF file path or error.
	 */
	private static function html_to_pdf( $html, $filename ) {
		// For Phase 1, we'll use a simple HTML-to-print approach
		// In production, integrate with a PDF library like TCPDF or Dompdf

		$upload_dir = wp_upload_dir();
		$pdf_dir = $upload_dir['basedir'] . '/nonprofitsuite-pdfs';

		if ( ! file_exists( $pdf_dir ) ) {
			wp_mkdir_p( $pdf_dir );
		}

		$filepath = $pdf_dir . '/' . $filename;

		// For now, save as HTML (can be printed to PDF by browser)
		file_put_contents( $filepath . '.html', $html );

		return $filepath . '.html';
	}

	/**
	 * Get agenda HTML.
	 *
	 * @param object $meeting Meeting object.
	 * @param array  $items Agenda items.
	 * @return string HTML content.
	 */
	private static function get_agenda_html( $meeting, $items ) {
		$org_name = get_option( 'nonprofitsuite_organization_name', get_bloginfo( 'name' ) );

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		$html .= '<style>body { font-family: Arial, sans-serif; margin: 40px; }';
		$html .= 'h1 { color: #2563EB; } table { width: 100%; border-collapse: collapse; }';
		$html .= 'th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }';
		$html .= 'th { background-color: #f3f4f6; }</style></head><body>';

		$html .= '<h1>' . esc_html( $org_name ) . '</h1>';
		$html .= '<h2>' . esc_html( $meeting->title ) . '</h2>';
		$html .= '<p><strong>Date:</strong> ' . esc_html( date( 'F j, Y g:i A', strtotime( $meeting->meeting_date ) ) ) . '</p>';
		$html .= '<p><strong>Location:</strong> ' . esc_html( $meeting->location ) . '</p>';

		if ( $meeting->virtual_url ) {
			$html .= '<p><strong>Virtual Link:</strong> ' . esc_html( $meeting->virtual_url ) . '</p>';
		}

		$html .= '<h3>Agenda</h3><table><thead><tr><th>Item</th><th>Type</th><th>Time</th></tr></thead><tbody>';

		foreach ( $items as $item ) {
			$html .= '<tr>';
			$html .= '<td><strong>' . esc_html( $item->title ) . '</strong><br>' . esc_html( $item->description ) . '</td>';
			$html .= '<td>' . esc_html( $item->item_type ) . '</td>';
			$html .= '<td>' . ( $item->time_allocated ? esc_html( $item->time_allocated ) . ' min' : '-' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></body></html>';

		return $html;
	}

	/**
	 * Get minutes HTML.
	 *
	 * @param object $meeting Meeting object.
	 * @param object $minutes Minutes object.
	 * @return string HTML content.
	 */
	private static function get_minutes_html( $meeting, $minutes ) {
		$org_name = get_option( 'nonprofitsuite_organization_name', get_bloginfo( 'name' ) );

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		$html .= '<style>body { font-family: Arial, sans-serif; margin: 40px; }';
		$html .= 'h1 { color: #2563EB; }</style></head><body>';

		$html .= '<h1>' . esc_html( $org_name ) . '</h1>';
		$html .= '<h2>Meeting Minutes</h2>';
		$html .= '<p><strong>Meeting:</strong> ' . esc_html( $meeting->title ) . '</p>';
		$html .= '<p><strong>Date:</strong> ' . esc_html( date( 'F j, Y g:i A', strtotime( $meeting->meeting_date ) ) ) . '</p>';
		$html .= '<p><strong>Location:</strong> ' . esc_html( $meeting->location ) . '</p>';

		$html .= '<div>' . wp_kses_post( $minutes->content ) . '</div>';

		if ( $minutes->status === 'approved' ) {
			$html .= '<p><em>Approved on ' . esc_html( date( 'F j, Y', strtotime( $minutes->approved_at ) ) ) . '</em></p>';
		}

		$html .= '</body></html>';

		return $html;
	}
}
