<?php
/**
 * WP_List_Table implementation for email logs.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only table navigation, sorting, pagination, and filter parameters in WP_List_Table implementation.

/**
 * Class Logs_Table
 */
class Logs_Table extends \WP_List_Table {

	/**
	 * Log repository instance.
	 *
	 * @var Log_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Log_Repository $repository Log repository.
	 */
	public function __construct( Log_Repository $repository ) {
		parent::__construct(
			array(
				'singular' => 'email_log',
				'plural'   => 'email_logs',
				'ajax'     => false,
			)
		);
		$this->repository = $repository;
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'created_at'  => __( 'Sent At', 'advanced-email-log' ),
			'status'      => __( 'Status', 'advanced-email-log' ),
			'to_email'    => __( 'To', 'advanced-email-log' ),
			'subject'     => __( 'Subject', 'advanced-email-log' ),
			'cc'          => __( 'CC', 'advanced-email-log' ),
			'bcc'         => __( 'BCC', 'advanced-email-log' ),
			'reply_to'    => __( 'Reply-To', 'advanced-email-log' ),
			'attachments' => __( 'Attachment', 'advanced-email-log' ),
			'ip_address'  => __( 'IP Address', 'advanced-email-log' ),
			'actions'     => __( 'Actions', 'advanced-email-log' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
			'to_email'   => array( 'to_email', false ),
			'subject'    => array( 'subject', false ),
		);
	}

	/**
	 * Get bulk actions depending on whether viewing Trash or Active logs.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		if ( ! Capabilities::can_perform_operations() ) {
			return array(
				'export' => __( 'Export selected logs', 'advanced-email-log' ),
			);
		}

		$current_status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';

		if ( 'trash' === $current_status ) {
			return array(
				'restore' => __( 'Restore', 'advanced-email-log' ),
				'delete'  => __( 'Delete Permanently', 'advanced-email-log' ),
			);
		}

		return array(
			'trash'  => __( 'Move to Trash', 'advanced-email-log' ),
			'export' => __( 'Export selected logs', 'advanced-email-log' ),
		);
	}

	/**
	 * Render table with responsive wrapper only around the table element,
	 * keeping top and bottom tablenav navigation bars fully visible and unconstrained.
	 */
	public function display(): void {
		$this->display_tablenav( 'top' );
		?>
		<div class="ael-table-responsive">
			<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
				<thead>
					<tr>
						<?php $this->print_column_headers(); ?>
					</tr>
				</thead>

				<tbody id="the-list">
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>

				<tfoot>
					<tr>
						<?php $this->print_column_headers( false ); ?>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Get views (status filter links including Trash).
	 *
	 * @return array
	 */
	protected function get_views(): array {
		$counts         = $this->repository->get_status_counts();
		$current_status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$base_url       = admin_url( 'admin.php?page=advanced-email-log' );

		// Preserve search and date filters in status view tabs.
		$preserve_params = array();
		if ( ! empty( $_REQUEST['s'] ) ) {
			$preserve_params['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		if ( ! empty( $_REQUEST['date_range'] ) ) {
			$preserve_params['date_range'] = sanitize_key( wp_unslash( $_REQUEST['date_range'] ) );
		}
		if ( ! empty( $_REQUEST['date_from'] ) ) {
			$preserve_params['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
		}
		if ( ! empty( $_REQUEST['date_to'] ) ) {
			$preserve_params['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
		}

		$make_url = function ( $status = '' ) use ( $base_url, $preserve_params ) {
			$params = $preserve_params;
			if ( ! empty( $status ) ) {
				$params['status'] = $status;
			}
			return add_query_arg( $params, $base_url );
		};

		$views = array(
			'all'      => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( '' ) ),
				empty( $current_status ) ? ' class="current"' : '',
				esc_html__( 'All', 'advanced-email-log' ),
				$counts['all']
			),
			'accepted' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( 'accepted' ) ),
				'accepted' === $current_status ? ' class="current"' : '',
				esc_html__( 'Accepted', 'advanced-email-log' ),
				$counts['accepted']
			),
			'failed'   => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( 'failed' ) ),
				'failed' === $current_status ? ' class="current"' : '',
				esc_html__( 'Failed', 'advanced-email-log' ),
				$counts['failed']
			),
			'pending'  => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( 'pending' ) ),
				'pending' === $current_status ? ' class="current"' : '',
				esc_html__( 'Pending', 'advanced-email-log' ),
				$counts['pending']
			),
			'unknown'  => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( 'unknown' ) ),
				'unknown' === $current_status ? ' class="current"' : '',
				esc_html__( 'Unknown', 'advanced-email-log' ),
				$counts['unknown']
			),
		);

		if ( Capabilities::can_perform_operations() && ( $counts['trash'] > 0 || 'trash' === $current_status ) ) {
			$views['trash'] = sprintf(
				'<a href="%s"%s style="color:#b32d2e;">%s <span class="count">(%d)</span></a>',
				esc_url( $make_url( 'trash' ) ),
				'trash' === $current_status ? ' class="current"' : '',
				esc_html__( 'Trash', 'advanced-email-log' ),
				$counts['trash']
			);
		}

		return $views;
	}

	/**
	 * Render date filters and quick export in table nav.
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$date_range     = isset( $_REQUEST['date_range'] ) ? sanitize_key( wp_unslash( $_REQUEST['date_range'] ) ) : '';
		$date_from      = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		$date_to        = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';

		// Label for the date picker trigger button.
		$date_label = __( 'Filter by date', 'advanced-email-log' );
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$date_label = $date_from . ' &rarr; ' . $date_to;
		} elseif ( ! empty( $date_from ) ) {
			$date_label = 'From ' . $date_from;
		} elseif ( ! empty( $date_to ) ) {
			$date_label = 'Until ' . $date_to;
		} elseif ( ! empty( $date_range ) ) {
			$presets = array(
				'today'        => __( 'Today', 'advanced-email-log' ),
				'yesterday'    => __( 'Yesterday', 'advanced-email-log' ),
				'last_7_days'  => __( 'Last 7 Days', 'advanced-email-log' ),
				'last_30_days' => __( 'Last 30 Days', 'advanced-email-log' ),
				'this_month'   => __( 'This Month', 'advanced-email-log' ),
				'last_month'   => __( 'Last Month', 'advanced-email-log' ),
			);
			if ( isset( $presets[ $date_range ] ) ) {
				$date_label = $presets[ $date_range ];
			}
		}

		?>
		<div class="alignleft actions ael-filter-actions">
			<!-- Easy-to-Use Date Range Picker Dropdown -->
			<div class="ael-date-picker-wrap" id="ael-date-picker-wrap">
				<button type="button" class="button ael-date-picker-btn" id="ael-date-picker-btn">
					<span class="dashicons dashicons-calendar-alt"></span>
					<span class="ael-date-btn-label"><?php echo esc_html( $date_label ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>

				<div class="ael-date-dropdown-card" id="ael-date-dropdown-card" style="display:none;">
					<div class="ael-date-presets-row">
						<button type="button" class="button button-small ael-preset-btn" data-preset=""><?php esc_html_e( 'All Dates', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-small ael-preset-btn" data-preset="today"><?php esc_html_e( 'Today', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-small ael-preset-btn" data-preset="yesterday"><?php esc_html_e( 'Yesterday', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-small ael-preset-btn" data-preset="last_7_days"><?php esc_html_e( 'Last 7 Days', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-small ael-preset-btn" data-preset="last_30_days"><?php esc_html_e( 'Last 30 Days', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-small ael-preset-btn" data-preset="this_month"><?php esc_html_e( 'This Month', 'advanced-email-log' ); ?></button>
					</div>

					<div class="ael-date-custom-row">
						<div class="ael-date-input-group">
							<label><?php esc_html_e( 'From Date:', 'advanced-email-log' ); ?></label>
							<input type="date" id="ael-dp-from" value="<?php echo esc_attr( $date_from ); ?>" />
						</div>
						<div class="ael-date-input-group">
							<label><?php esc_html_e( 'To Date:', 'advanced-email-log' ); ?></label>
							<input type="date" id="ael-dp-to" value="<?php echo esc_attr( $date_to ); ?>" />
						</div>
					</div>

					<div class="ael-date-actions-row">
						<button type="button" class="button button-primary button-small" id="ael-apply-date-btn"><?php esc_html_e( 'Apply Filter', 'advanced-email-log' ); ?></button>
						<button type="button" class="button button-secondary button-small" id="ael-reset-date-btn"><?php esc_html_e( 'Clear', 'advanced-email-log' ); ?></button>
					</div>
				</div>

				<!-- Hidden Form Inputs for submitting with table GET -->
				<input type="hidden" name="date_range" id="ael-hidden-date-range" value="<?php echo esc_attr( $date_range ); ?>" />
				<input type="hidden" name="date_from" id="ael-hidden-date-from" value="<?php echo esc_attr( $date_from ); ?>" />
				<input type="hidden" name="date_to" id="ael-hidden-date-to" value="<?php echo esc_attr( $date_to ); ?>" />
			</div>

			<!-- Columns Visibility Toggle Dropdown -->
			<div class="ael-columns-wrap" id="ael-columns-wrap">
				<button type="button" class="button ael-columns-btn" id="ael-columns-btn" title="<?php esc_attr_e( 'Show / hide table columns', 'advanced-email-log' ); ?>">
					<span class="dashicons dashicons-columns"></span>
					<?php esc_html_e( 'Columns', 'advanced-email-log' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<div class="ael-columns-dropdown" id="ael-columns-dropdown" style="display:none;">
					<div class="ael-columns-title"><?php esc_html_e( 'Toggle Columns', 'advanced-email-log' ); ?></div>
					<label><input type="checkbox" class="ael-col-toggle" data-col="created_at" checked /> <?php esc_html_e( 'Sent At', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="status" checked /> <?php esc_html_e( 'Status', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="to_email" checked /> <?php esc_html_e( 'To', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="subject" checked /> <?php esc_html_e( 'Subject', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="cc" /> <?php esc_html_e( 'CC', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="bcc" /> <?php esc_html_e( 'BCC', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="reply_to" checked /> <?php esc_html_e( 'Reply-To', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="attachments" checked /> <?php esc_html_e( 'Attachment', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="ip_address" checked /> <?php esc_html_e( 'IP Address', 'advanced-email-log' ); ?></label>
					<label><input type="checkbox" class="ael-col-toggle" data-col="actions" checked /> <?php esc_html_e( 'Actions', 'advanced-email-log' ); ?></label>
				</div>
			</div>

			<!-- Import CSV Button -->
			<?php if ( Capabilities::can_perform_operations() ) : ?>
				<button type="button" class="button ael-import-btn" id="ael-import-btn" title="<?php esc_attr_e( 'Import email logs from CSV file', 'advanced-email-log' ); ?>">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import CSV', 'advanced-email-log' ); ?>
				</button>
			<?php endif; ?>

			<!-- Export CSV Button -->
			<button type="button" class="button ael-export-filtered-btn" id="ael-export-filtered-btn" title="<?php esc_attr_e( 'Export all logs matching current filters to CSV', 'advanced-email-log' ); ?>">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'advanced-email-log' ); ?>
			</button>

			<?php if ( Capabilities::can_perform_operations() ) : ?>
				<?php if ( 'trash' === $current_status ) : ?>
					<button type="button" class="button ael-empty-trash-btn" id="ael-empty-trash-btn" style="color:#b32d2e; border-color:#d63638;">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Empty Trash', 'advanced-email-log' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button ael-clear-all-btn" id="ael-clear-all-btn" title="<?php esc_attr_e( 'Clear all email logs permanently', 'advanced-email-log' ); ?>">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Clear All Logs', 'advanced-email-log' ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $date_range ) || ! empty( $date_from ) || ! empty( $date_to ) || ! empty( $_REQUEST['status'] ) || ! empty( $_REQUEST['s'] ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log' ) ); ?>" class="button ael-reset-filters-btn">
					<?php esc_html_e( 'Reset All', 'advanced-email-log' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for display with server-side pagination and filters.
	 */
	public function prepare_items(): void {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$per_page = (int) get_option( 'ael_rows_per_page', 20 );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$current_page = $this->get_pagenum();

		$args = array(
			'per_page' => $per_page,
			'page'     => $current_page,
		);

		if ( ! empty( $_REQUEST['status'] ) ) {
			$args['status'] = sanitize_key( wp_unslash( $_REQUEST['status'] ) );
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		if ( ! empty( $_REQUEST['date_range'] ) ) {
			$args['date_range'] = sanitize_key( wp_unslash( $_REQUEST['date_range'] ) );
		}
		if ( ! empty( $_REQUEST['date_from'] ) ) {
			$args['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
		}
		if ( ! empty( $_REQUEST['date_to'] ) ) {
			$args['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
		}
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) );
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			$args['order'] = sanitize_key( wp_unslash( $_REQUEST['order'] ) );
		}

		$total_items = $this->repository->get_total_count( $args );
		$this->items = $this->repository->get_logs( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="log_ids[]" value="%d" class="ael-log-cb" />',
			(int) $item['id']
		);
	}

	/**
	 * Sent At column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		$utc_timestamp = strtotime( $item['created_at'] . ' UTC' );
		$local_format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$local_time    = wp_date( $local_format, $utc_timestamp );

		/* translators: %s: UTC datetime string */
		$utc_title = sprintf( __( 'UTC: %s', 'advanced-email-log' ), $item['created_at'] );

		return sprintf(
			'<span class="ael-timestamp" title="%s">%s</span>',
			esc_attr( $utc_title ),
			esc_html( $local_time )
		);
	}

	/**
	 * Status column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$status = strtolower( (string) $item['status'] );
		$class  = 'ael-status-' . sanitize_html_class( $status );
		$label  = ucfirst( $status );

		$tooltip = '';
		if ( 'accepted' === $status ) {
			$tooltip = __( 'Accepted by mail transport (does not prove recipient inbox delivery).', 'advanced-email-log' );
		} elseif ( 'failed' === $status && ! empty( $item['error_message'] ) ) {
			$tooltip = $item['error_message'];
		} elseif ( 'pending' === $status ) {
			$tooltip = __( 'Email attempt registered, awaiting transport response.', 'advanced-email-log' );
		} elseif ( 'unknown' === $status ) {
			$tooltip = __( 'Send ended without transport confirmation.', 'advanced-email-log' );
		} elseif ( 'trash' === $status ) {
			$tooltip = __( 'Moved to trash.', 'advanced-email-log' );
		}

		return sprintf(
			'<span class="ael-badge %s" title="%s">%s</span>',
			esc_attr( $class ),
			esc_attr( $tooltip ),
			esc_html( $label )
		);
	}

	/**
	 * To Recipient column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_to_email( $item ): string {
		$to = (string) $item['to_email'];
		return sprintf(
			'<span class="ael-to-cell" title="%s">%s</span>',
			esc_attr( $to ),
			esc_html( wp_trim_words( $to, 4, '...' ) )
		);
	}

	/**
	 * Subject column with row actions.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_subject( $item ): string {
		$subject     = ! empty( $item['subject'] ) ? $item['subject'] : __( '(No Subject)', 'advanced-email-log' );
		$is_trash    = ( 'trash' === $item['status'] );
		$can_operate = Capabilities::can_perform_operations();

		$row_actions = array();
		if ( ! $is_trash ) {
			$row_actions['view'] = sprintf(
				'<a href="#" class="ael-view-log-trigger" data-id="%d">%s</a>',
				(int) $item['id'],
				esc_html__( 'View Details', 'advanced-email-log' )
			);
			if ( $can_operate ) {
				$row_actions['trash'] = sprintf(
					'<a href="#" class="ael-action-trash" data-id="%d" style="color:#b32d2e;">%s</a>',
					(int) $item['id'],
					esc_html__( 'Trash', 'advanced-email-log' )
				);
			}
		} else {
			if ( $can_operate ) {
				$row_actions['restore'] = sprintf(
					'<a href="#" class="ael-action-restore" data-id="%d">%s</a>',
					(int) $item['id'],
					esc_html__( 'Restore', 'advanced-email-log' )
				);
				$row_actions['delete'] = sprintf(
					'<a href="#" class="ael-action-delete" data-id="%d" style="color:#b32d2e;">%s</a>',
					(int) $item['id'],
					esc_html__( 'Delete Permanently', 'advanced-email-log' )
				);
			} else {
				$row_actions['view'] = sprintf(
					'<a href="#" class="ael-view-log-trigger" data-id="%d">%s</a>',
					(int) $item['id'],
					esc_html__( 'View Details', 'advanced-email-log' )
				);
			}
		}

		$title_html = sprintf(
			'<strong><a href="#" class="ael-view-log-trigger" data-id="%d" title="%s">%s</a></strong>',
			(int) $item['id'],
			esc_attr( __( 'Click to view full email details', 'advanced-email-log' ) ),
			esc_html( $subject )
		);

		return $title_html . $this->row_actions( $row_actions );
	}

	/**
	 * CC column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_cc( $item ): string {
		if ( empty( $item['cc'] ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}
		return sprintf( '<span title="%s">%s</span>', esc_attr( $item['cc'] ), esc_html( wp_trim_words( $item['cc'], 3, '...' ) ) );
	}

	/**
	 * BCC column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_bcc( $item ): string {
		if ( empty( $item['bcc'] ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}
		return sprintf( '<span title="%s">%s</span>', esc_attr( $item['bcc'] ), esc_html( wp_trim_words( $item['bcc'], 3, '...' ) ) );
	}

	/**
	 * Reply-To column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_reply_to( $item ): string {
		if ( empty( $item['reply_to'] ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}
		return sprintf( '<span title="%s">%s</span>', esc_attr( $item['reply_to'] ), esc_html( wp_trim_words( $item['reply_to'], 3, '...' ) ) );
	}

	/**
	 * Attachment column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_attachments( $item ): string {
		if ( empty( $item['attachments'] ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}

		$att_list = json_decode( $item['attachments'], true );
		if ( empty( $att_list ) || ! is_array( $att_list ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}

		$count = count( $att_list );
		$names = array();
		foreach ( $att_list as $att ) {
			if ( ! empty( $att['filename'] ) ) {
				$names[] = $att['filename'];
			}
		}

		return sprintf(
			'<span class="ael-attachment-badge ael-view-log-trigger" data-id="%d" title="%s" style="cursor:pointer;"><span class="dashicons dashicons-paperclip"></span> %d</span>',
			(int) $item['id'],
			esc_attr( implode( ', ', $names ) ),
			$count
		);
	}

	/**
	 * IP Address column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_ip_address( $item ): string {
		if ( empty( $item['ip_address'] ) ) {
			return '<span class="ael-dim">&mdash;</span>';
		}
		return sprintf( '<code>%s</code>', esc_html( $item['ip_address'] ) );
	}

	/**
	 * Actions column.
	 *
	 * @param array $item Row item.
	 * @return string
	 */
	protected function column_actions( $item ): string {
		$is_trash = ( 'trash' === $item['status'] );

		if ( $is_trash && Capabilities::can_perform_operations() ) {
			return sprintf(
				'<button type="button" class="button button-small ael-action-restore" data-id="%d" title="%s">
					<span class="dashicons dashicons-undo"></span> %s
				</button>',
				(int) $item['id'],
				esc_attr( __( 'Restore this log', 'advanced-email-log' ) ),
				esc_html__( 'Restore', 'advanced-email-log' )
			);
		}

		return sprintf(
			'<button type="button" class="button button-small ael-view-log-btn" data-id="%d" title="%s">
				<span class="dashicons dashicons-visibility"></span> %s
			</button>',
			(int) $item['id'],
			esc_attr( __( 'View full email details', 'advanced-email-log' ) ),
			esc_html__( 'View', 'advanced-email-log' )
		);
	}

	/**
	 * Default fallback for any unhandled column.
	 *
	 * @param array  $item Row item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * Render empty state.
	 */
	public function no_items(): void {
		$is_trash = isset( $_REQUEST['status'] ) && 'trash' === $_REQUEST['status'];
		?>
		<div class="ael-empty-state">
			<div class="ael-empty-icon"><span class="dashicons <?php echo $is_trash ? 'dashicons-trash' : 'dashicons-email'; ?>"></span></div>
			<h3><?php echo $is_trash ? esc_html__( 'Trash is empty', 'advanced-email-log' ) : esc_html__( 'No email logs found', 'advanced-email-log' ); ?></h3>
			<p>
				<?php
				if ( $is_trash ) {
					esc_html_e( 'There are currently no items in the trash.', 'advanced-email-log' );
				} else {
					esc_html_e( 'There are no emails matching your search or filter criteria. New email events through wp_mail() will appear here automatically.', 'advanced-email-log' );
				}
				?>
			</p>
			<?php if ( ! empty( $_REQUEST['status'] ) || ! empty( $_REQUEST['s'] ) || ! empty( $_REQUEST['date_range'] ) || ! empty( $_REQUEST['date_from'] ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Clear All Filters', 'advanced-email-log' ); ?>
				</a>
			<?php elseif ( Capabilities::can_perform_operations() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log&tab=test-email' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Send a Test Email', 'advanced-email-log' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
