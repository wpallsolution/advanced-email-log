<?php
/**
 * View template for Analytics tab.
 *
 * @package WPAllSolution\AdvancedEmailLog
 * @var \WPAllSolution\AdvancedEmailLog\Log_Repository $repository Log repository instance.
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template view file loaded within controller method.

$ael_summary  = $repository->get_analytics_summary( array( 'date_range' => 'last_7_days' ) );
$ael_timeline = $repository->get_analytics_timeline( array( 'date_range' => 'last_7_days' ) );
$ael_top_ips  = $repository->get_top_ip_stats( array( 'date_range' => 'last_7_days' ), 8 );
$ael_errors   = $repository->get_top_error_reasons( array( 'date_range' => 'last_7_days' ), 5 );
?>
<div class="ael-analytics-container">
	<!-- Filter & Date Controls Card -->
	<div class="ael-card ael-analytics-filter-card">
		<div class="ael-analytics-filter-row">
			<div class="ael-filter-group">
				<label for="ael-analytics-range">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'Date Range:', 'advanced-email-log' ); ?>
				</label>
				<select id="ael-analytics-range" class="ael-select-control">
					<option value="today"><?php esc_html_e( 'Today', 'advanced-email-log' ); ?></option>
					<option value="yesterday"><?php esc_html_e( 'Yesterday', 'advanced-email-log' ); ?></option>
					<option value="last_7_days" selected="selected"><?php esc_html_e( 'Last 7 Days', 'advanced-email-log' ); ?></option>
					<option value="last_30_days"><?php esc_html_e( 'Last 30 Days', 'advanced-email-log' ); ?></option>
					<option value="this_month"><?php esc_html_e( 'This Month', 'advanced-email-log' ); ?></option>
					<option value="last_month"><?php esc_html_e( 'Last Month', 'advanced-email-log' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom Range...', 'advanced-email-log' ); ?></option>
				</select>
			</div>

			<div class="ael-filter-group ael-custom-dates-group" id="ael-analytics-custom-dates" style="display:none;">
				<label><?php esc_html_e( 'From:', 'advanced-email-log' ); ?></label>
				<input type="date" id="ael-analytics-date-from" class="ael-input-date" />
				<label><?php esc_html_e( 'To:', 'advanced-email-log' ); ?></label>
				<input type="date" id="ael-analytics-date-to" class="ael-input-date" />
			</div>

			<div class="ael-filter-group ael-filter-ip-group">
				<label for="ael-analytics-ip">
					<span class="dashicons dashicons-admin-site"></span>
					<?php esc_html_e( 'IP Filter:', 'advanced-email-log' ); ?>
				</label>
				<input type="text" id="ael-analytics-ip" class="regular-text ael-ip-input" placeholder="<?php esc_attr_e( 'Filter by IP (e.g. 127.0.0.1)...', 'advanced-email-log' ); ?>" />
			</div>

			<div class="ael-filter-actions-group">
				<button type="button" class="button button-primary ael-analytics-apply-btn" id="ael-analytics-apply-btn">
					<span class="dashicons dashicons-filter"></span>
					<?php esc_html_e( 'Apply Filter', 'advanced-email-log' ); ?>
				</button>
				<button type="button" class="button ael-analytics-reset-btn" id="ael-analytics-reset-btn" title="<?php esc_attr_e( 'Reset to Last 7 Days', 'advanced-email-log' ); ?>">
					<span class="dashicons dashicons-image-rotate"></span>
					<?php esc_html_e( 'Reset', 'advanced-email-log' ); ?>
				</button>
			</div>

			<div class="ael-analytics-loading-spinner" id="ael-analytics-spinner" style="display:none;">
				<span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span>
				<span><?php esc_html_e( 'Loading analytics data...', 'advanced-email-log' ); ?></span>
			</div>
		</div>
	</div>

	<!-- KPI Summary Metrics Grid (5 cards with counts & percentages) -->
	<div class="ael-kpi-grid">
		<!-- Total Emails -->
		<div class="ael-kpi-card ael-kpi-total">
			<div class="ael-kpi-header">
				<span class="dashicons dashicons-email-alt"></span>
				<h3><?php esc_html_e( 'Total Emails', 'advanced-email-log' ); ?></h3>
			</div>
			<div class="ael-kpi-body">
				<div class="ael-kpi-number" id="ael-kpi-total"><?php echo esc_html( number_format_i18n( $ael_summary['total'] ) ); ?></div>
				<div class="ael-kpi-meta">
					<span class="ael-badge ael-badge-info"><?php esc_html_e( '100% Tracked', 'advanced-email-log' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Accepted / Delivered -->
		<div class="ael-kpi-card ael-kpi-accepted">
			<div class="ael-kpi-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<h3><?php esc_html_e( 'Accepted / Sent', 'advanced-email-log' ); ?></h3>
			</div>
			<div class="ael-kpi-body">
				<div class="ael-kpi-number" id="ael-kpi-accepted"><?php echo esc_html( number_format_i18n( $ael_summary['accepted_count'] ) ); ?></div>
				<div class="ael-kpi-meta">
					<span class="ael-badge ael-badge-success" id="ael-kpi-accepted-pct"><?php echo esc_html( $ael_summary['accepted_pct'] ); ?>%</span>
					<span class="ael-kpi-subtext"><?php esc_html_e( 'of total', 'advanced-email-log' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Failed -->
		<div class="ael-kpi-card ael-kpi-failed">
			<div class="ael-kpi-header">
				<span class="dashicons dashicons-dismiss"></span>
				<h3><?php esc_html_e( 'Failed Transmissions', 'advanced-email-log' ); ?></h3>
			</div>
			<div class="ael-kpi-body">
				<div class="ael-kpi-number" id="ael-kpi-failed"><?php echo esc_html( number_format_i18n( $ael_summary['failed_count'] ) ); ?></div>
				<div class="ael-kpi-meta">
					<span class="ael-badge ael-badge-danger" id="ael-kpi-failed-pct"><?php echo esc_html( $ael_summary['failed_pct'] ); ?>%</span>
					<span class="ael-kpi-subtext"><?php esc_html_e( 'error rate', 'advanced-email-log' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Pending -->
		<div class="ael-kpi-card ael-kpi-pending">
			<div class="ael-kpi-header">
				<span class="dashicons dashicons-clock"></span>
				<h3><?php esc_html_e( 'Pending Processing', 'advanced-email-log' ); ?></h3>
			</div>
			<div class="ael-kpi-body">
				<div class="ael-kpi-number" id="ael-kpi-pending"><?php echo esc_html( number_format_i18n( $ael_summary['pending_count'] ) ); ?></div>
				<div class="ael-kpi-meta">
					<span class="ael-badge ael-badge-warning" id="ael-kpi-pending-pct"><?php echo esc_html( $ael_summary['pending_pct'] ); ?>%</span>
					<span class="ael-kpi-subtext"><?php esc_html_e( 'in queue', 'advanced-email-log' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Unknown -->
		<div class="ael-kpi-card ael-kpi-unknown">
			<div class="ael-kpi-header">
				<span class="dashicons dashicons-warning"></span>
				<h3><?php esc_html_e( 'Unknown / Interrupted', 'advanced-email-log' ); ?></h3>
			</div>
			<div class="ael-kpi-body">
				<div class="ael-kpi-number" id="ael-kpi-unknown"><?php echo esc_html( number_format_i18n( $ael_summary['unknown_count'] ) ); ?></div>
				<div class="ael-kpi-meta">
					<span class="ael-badge ael-badge-neutral" id="ael-kpi-unknown-pct"><?php echo esc_html( $ael_summary['unknown_pct'] ); ?>%</span>
					<span class="ael-kpi-subtext"><?php esc_html_e( 'unconfirmed', 'advanced-email-log' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Interactive Visual Charts Section -->
	<div class="ael-charts-row">
		<!-- Time Series Bar Chart -->
		<div class="ael-card ael-chart-card ael-timeline-card">
			<div class="ael-card-header">
				<h2>
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Email Activity Over Time', 'advanced-email-log' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Daily volume of accepted deliveries vs failed email transmissions.', 'advanced-email-log' ); ?>
				</p>
			</div>
			<div class="ael-card-body ael-chart-canvas-container">
				<canvas id="ael-timeline-chart" height="90"></canvas>
			</div>
		</div>

		<!-- Status Breakdown Donut Chart -->
		<div class="ael-card ael-chart-card ael-donut-card">
			<div class="ael-card-header">
				<h2>
					<span class="dashicons dashicons-chart-pie"></span>
					<?php esc_html_e( 'Delivery Status Share', 'advanced-email-log' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Proportional breakdown of all email dispatch statuses.', 'advanced-email-log' ); ?>
				</p>
			</div>
			<div class="ael-card-body ael-chart-canvas-container ael-donut-canvas-wrap">
				<canvas id="ael-donut-chart" height="180"></canvas>
			</div>
		</div>
	</div>

	<!-- Deep-Dive Diagnostic Tables Grid -->
	<div class="ael-diagnostic-grid">
		<!-- Top Triggering IPs (Bot & Spam Detection) -->
		<div class="ael-card ael-diag-card">
			<div class="ael-card-header">
				<h2>
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Top Triggering IPs & Spam/Bot Detection', 'advanced-email-log' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Monitor IP addresses sending emails. High failure rates highlight potential spam bots or hijacked forms.', 'advanced-email-log' ); ?>
				</p>
			</div>
			<div class="ael-card-body">
				<div class="ael-table-responsive-mini">
					<table class="widefat striped ael-analytics-table" id="ael-ip-stats-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'IP Address', 'advanced-email-log' ); ?></th>
								<th><?php esc_html_e( 'Total Sent', 'advanced-email-log' ); ?></th>
								<th><?php esc_html_e( 'Failed', 'advanced-email-log' ); ?></th>
								<th><?php esc_html_e( 'Failure Rate', 'advanced-email-log' ); ?></th>
								<th><?php esc_html_e( 'Risk Level', 'advanced-email-log' ); ?></th>
								<th><?php esc_html_e( 'Action', 'advanced-email-log' ); ?></th>
							</tr>
						</thead>
						<tbody id="ael-ip-stats-tbody">
							<?php if ( empty( $ael_top_ips ) ) : ?>
								<tr>
									<td colspan="6" style="text-align:center; color:#646970; padding:18px;">
										<?php esc_html_e( 'No IP activity recorded in this time range.', 'advanced-email-log' ); ?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $ael_top_ips as $ael_ip_item ) : ?>
									<tr>
										<td>
											<code><?php echo esc_html( $ael_ip_item['ip_address'] ); ?></code>
										</td>
										<td><strong><?php echo esc_html( number_format_i18n( $ael_ip_item['total'] ) ); ?></strong></td>
										<td>
											<span style="color: <?php echo $ael_ip_item['failed_count'] > 0 ? '#d63638' : '#2c3338'; ?>; font-weight:600;">
												<?php echo esc_html( number_format_i18n( $ael_ip_item['failed_count'] ) ); ?>
											</span>
										</td>
										<td>
											<span class="ael-badge <?php echo $ael_ip_item['failure_rate'] > 40.0 ? 'ael-badge-danger' : 'ael-badge-neutral'; ?>">
												<?php echo esc_html( $ael_ip_item['failure_rate'] ); ?>%
											</span>
										</td>
										<td>
											<?php if ( $ael_ip_item['is_suspicious'] ) : ?>
												<span class="ael-risk-pill ael-risk-danger" title="<?php esc_attr_e( 'High failure rate detected. Possible spam bot or brute-force form submission.', 'advanced-email-log' ); ?>">
													<span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Suspicious Bot', 'advanced-email-log' ); ?>
												</span>
											<?php else : ?>
												<span class="ael-risk-pill ael-risk-safe">
													<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Normal', 'advanced-email-log' ); ?>
												</span>
											<?php endif; ?>
										</td>
										<td>
											<button type="button" class="button button-small ael-filter-by-ip-btn" data-ip="<?php echo esc_attr( $ael_ip_item['ip_address'] ); ?>">
												<span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter', 'advanced-email-log' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Top Failure Diagnostics -->
		<div class="ael-card ael-diag-card">
			<div class="ael-card-header">
				<h2>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Top Failure Diagnostics & Reasons', 'advanced-email-log' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Most common transport errors preventing mail delivery.', 'advanced-email-log' ); ?>
				</p>
			</div>
			<div class="ael-card-body">
				<div id="ael-error-stats-container">
					<?php if ( empty( $ael_errors ) ) : ?>
						<div class="ael-empty-state-notice">
							<span class="dashicons dashicons-yes-alt" style="color:#00a32a; font-size:32px; width:32px; height:32px; margin-bottom:8px;"></span>
							<p style="font-size:14px; color:#2c3338; margin:0; font-weight:500;">
								<?php esc_html_e( 'No delivery errors recorded in this time range!', 'advanced-email-log' ); ?>
							</p>
							<p style="color:#646970; font-size:12px; margin-top:4px;">
								<?php esc_html_e( 'Your WordPress email pipeline is operating smoothly without failures.', 'advanced-email-log' ); ?>
							</p>
						</div>
					<?php else : ?>
						<ul class="ael-error-list" id="ael-error-list-ul">
							<?php foreach ( $ael_errors as $ael_err ) : ?>
								<li class="ael-error-item">
									<div class="ael-error-item-header">
										<span class="ael-error-message-text" title="<?php echo esc_attr( $ael_err['error_message'] ); ?>">
											<?php echo esc_html( wp_trim_words( $ael_err['error_message'], 15, '...' ) ); ?>
										</span>
										<span class="ael-badge ael-badge-danger">
											<?php echo esc_html( number_format_i18n( $ael_err['count'] ) ); ?> <?php esc_html_e( 'events', 'advanced-email-log' ); ?>
										</span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Raw Initial Data Passed to JavaScript for Initial Chart Render -->
<script type="text/javascript">
var aelAnalyticsInitialData = {
	summary: <?php echo wp_json_encode( $ael_summary ); ?>,
	timeline: <?php echo wp_json_encode( $ael_timeline ); ?>,
	top_ips: <?php echo wp_json_encode( $ael_top_ips ); ?>,
	errors: <?php echo wp_json_encode( $ael_errors ); ?>
};
</script>
