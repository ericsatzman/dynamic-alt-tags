<?php
/**
 * Queue repository.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Queue_Repo {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'ai_alt_queue';
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table;
	}

	/**
	 * Enqueue one attachment if needed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id Parent post ID.
	 * @return bool
	 */
	public function enqueue( $attachment_id, $post_id = 0 ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post_id       = absint( $post_id );

		if ( ! $attachment_id ) {
			return false;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			)
		);

		if ( $existing_id ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$result = $wpdb->insert(
			$this->table,
			array(
				'attachment_id' => $attachment_id,
				'post_id'       => $post_id,
				'status'        => 'queued',
				'provider'      => 'cloudflare',
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Enqueue attachment or reset existing queue row to queued.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id Parent post ID.
	 * @return bool
	 */
	public function enqueue_or_requeue( $attachment_id, $post_id = 0 ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post_id       = absint( $post_id );
		if ( ! $attachment_id ) {
			return false;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			)
		);

		$now = current_time( 'mysql' );
		if ( $existing_id ) {
			$result = $wpdb->update(
				$this->table,
				array(
					'post_id'        => $post_id,
					'status'         => 'queued',
					'raw_caption'    => null,
					'suggested_alt'  => '',
					'final_alt'      => '',
					'confidence'     => 0,
					'error_code'     => null,
					'error_message'  => null,
					'locked_at'      => null,
					'updated_at'     => $now,
				),
				array( 'id' => absint( $existing_id ) ),
				array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		return $this->enqueue( $attachment_id, $post_id );
	}

	/**
	 * Backfill queue.
	 *
	 * @param int $limit Max to enqueue.
	 * @return int
	 */
	public function enqueue_missing_alts( $limit = 200 ) {
		global $wpdb;

		$limit = max( 1, min( 1000, absint( $limit ) ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
				 WHERE p.post_type = 'attachment'
				 AND p.post_mime_type LIKE 'image/%'
				 AND (pm.meta_value IS NULL OR pm.meta_value = '')
				 ORDER BY p.ID DESC
				 LIMIT %d",
				$limit
			)
		);

		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->enqueue( absint( $id ), 0 ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Claim jobs atomically enough for WP cron.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function claim_jobs( $limit ) {
		global $wpdb;

		$limit       = max( 1, min( 50, absint( $limit ) ) );
		$now         = current_time( 'mysql' );
		$lock_expiry = gmdate( 'Y-m-d H:i:s', time() - ( 15 * MINUTE_IN_SECONDS ) );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
				 FROM {$this->table}
				 WHERE status IN ('queued', 'failed')
				 AND (locked_at IS NULL OR locked_at < %s)
				 ORDER BY updated_at ASC
				 LIMIT %d",
				$lock_expiry,
				$limit
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$update_sql   = $wpdb->prepare(
			"UPDATE {$this->table} SET status = 'processing', locked_at = %s, updated_at = %s WHERE id IN ({$placeholders})",
			array_merge( array( $now, $now ), $ids )
		);
		$wpdb->query( $update_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id IN ({$placeholders})",
				$ids
			),
			ARRAY_A
		);
	}

	/**
	 * Update generated row.
	 *
	 * @param int    $id Row ID.
	 * @param string $raw_caption Raw caption.
	 * @param string $suggested_alt Suggested alt.
	 * @param float  $confidence Confidence.
	 * @return void
	 */
	public function mark_generated( $id, $raw_caption, $suggested_alt, $confidence ) {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'        => 'generated',
				'raw_caption'   => (string) $raw_caption,
				'suggested_alt' => sanitize_text_field( $suggested_alt ),
				'confidence'    => max( 0.0, min( 1.0, (float) $confidence ) ),
				'error_code'    => null,
				'error_message' => null,
				'updated_at'    => current_time( 'mysql' ),
				'locked_at'     => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark failure.
	 *
	 * @param int    $id Row ID.
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	public function mark_failed( $id, $code, $message ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = 'failed',
				 attempts = attempts + 1,
				 error_code = %s,
				 error_message = %s,
				 updated_at = %s,
				 locked_at = NULL
				 WHERE id = %d",
				sanitize_key( (string) $code ),
				sanitize_text_field( (string) $message ),
				current_time( 'mysql' ),
				absint( $id )
			)
		);
	}

	/**
	 * Mark final status.
	 *
	 * @param int    $id Row ID.
	 * @param string $status Status.
	 * @param string $final_alt Final alt value.
	 * @return bool
	 */
	public function mark_final( $id, $status, $final_alt = '' ) {
		global $wpdb;

		$allowed = array( 'approved', 'rejected', 'skipped' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'final_alt'  => sanitize_text_field( $final_alt ),
				'updated_at' => current_time( 'mysql' ),
				'locked_at'  => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get row.
	 *
	 * @param int $id Row ID.
	 * @return array<string,mixed>|null
	 */
	public function get_row( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", absint( $id ) ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get row by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null
	 */
	public function get_row_by_attachment( $attachment_id ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete queue rows by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Number of deleted rows.
	 */
	public function delete_by_attachment_id( $attachment_id ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return 0;
		}

		return (int) $wpdb->delete(
			$this->table,
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);
	}

	/**
	 * Paginate queue rows.
	 *
	 * @param int    $page Current page.
	 * @param int    $per_page Per page.
	 * @param string $status Status filter.
	 * @param string $view View mode: active|history.
	 * @return array<string,mixed>
	 */
	public function get_paginated( $page = 1, $per_page = 20, $status = '', $view = 'active' ) {
		global $wpdb;

		$page              = max( 1, absint( $page ) );
		$per_page          = max( 1, min( 100, absint( $per_page ) ) );
		$offset            = ( $page - 1 ) * $per_page;
		$params            = array();
		$view              = sanitize_key( $view );
		$active_statuses   = array( 'queued', 'processing', 'generated', 'failed' );
		$history_statuses  = array( 'approved', 'rejected', 'skipped' );
		$allowed_statuses  = 'history' === $view ? $history_statuses : $active_statuses;
		$status            = sanitize_key( $status );
		$has_status_filter = false;

		$in_sql = "'" . implode( "', '", array_map( 'esc_sql', $allowed_statuses ) ) . "'";
		$where  = "q.status IN ({$in_sql})";

		if ( '' !== $status && in_array( $status, $allowed_statuses, true ) ) {
			$where    .= ' AND q.status = %s';
			$params[] = $status;
			$has_status_filter = true;
		}

		$total_sql = "SELECT COUNT(*) FROM {$this->table} q WHERE {$where}";
		$rows_sql  = "SELECT q.* FROM {$this->table} q WHERE {$where} ORDER BY q.updated_at DESC LIMIT %d OFFSET %d";

		if ( $has_status_filter ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					$total_sql,
					$params
				)
			);
		} else {
			$total = (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$params[] = $per_page;
		$params[] = $offset;
		if ( $has_status_filter ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$rows_sql,
					$params
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$rows_sql,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'rows'     => is_array( $rows ) ? $rows : array(),
		);
	}

	/**
	 * Get latest failed queue row.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_failed_row() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT id, attachment_id, error_code, error_message, updated_at FROM {$this->table} WHERE status = 'failed' ORDER BY updated_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get latest row from active queue statuses.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_active_row() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT id, attachment_id, status, updated_at FROM {$this->table} WHERE status IN ('queued', 'processing', 'generated', 'failed') ORDER BY updated_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get counts for active queue statuses.
	 *
	 * @return array<string,int>
	 */
	public function get_active_status_counts() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS c FROM {$this->table} WHERE status IN ('queued', 'processing', 'generated', 'failed') GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$counts = array(
			'queued'     => 0,
			'processing' => 0,
			'generated'  => 0,
			'failed'     => 0,
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				}
			}
		}

		return $counts;
	}

	/**
	 * Count image attachments that currently have no alt text.
	 *
	 * @return int
	 */
	public function get_total_no_alt_images() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Paginate image attachments with empty alt text.
	 *
	 * @param int $page Current page.
	 * @param int $per_page Per page.
	 * @return array<string,mixed>
	 */
	public function get_no_alt_paginated( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 100, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS attachment_id, q.id AS queue_row_id, q.status AS queue_status
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
				 LEFT JOIN {$this->table} q ON (q.attachment_id = p.ID AND q.provider = %s)
				 WHERE p.post_type = 'attachment'
				 AND p.post_mime_type LIKE 'image/%%'
				 AND (pm.meta_value IS NULL OR pm.meta_value = '')
				 ORDER BY p.ID DESC
				 LIMIT %d OFFSET %d",
				'cloudflare',
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'rows'     => is_array( $rows ) ? $rows : array(),
		);
	}
}
