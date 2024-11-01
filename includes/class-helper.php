<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpc_Helper' ) ) {
	class Wpcpc_Helper {
		public function __construct() {
			register_activation_hook( WPCPC_FILE, [ $this, 'register_schedule' ] );
			register_deactivation_hook( WPCPC_FILE, [ $this, 'deregister_schedule' ] );
			add_action( 'wpcpc_cronjob', [ $this, 'cronjob' ] );
		}

		public function register_schedule() {
			if ( ! as_next_scheduled_action( 'wpcpc_cronjob' ) ) {
				as_schedule_recurring_action( time(), 60, 'wpcpc_cronjob' );
			}
		}

		public function deregister_schedule() {
			as_unschedule_all_actions( 'wpcpc_cronjob' );
		}

		public function cronjob() {
			$args = [
				'post_type'      => 'product',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => 25,
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'wpcpc_cronjob',
						'compare' => 'NOT EXISTS'
					],
					[
						'key'     => 'wpcpc_cronjob',
						'value'   => current_time( 'timestamp', true ) - 300,
						'compare' => '<'
					]
				]
			];

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					global $product;
					$product_id  = $product->get_id();
					$collections = self::get_collections();

					if ( is_array( $collections ) && count( $collections ) ) {
						foreach ( $collections as $collection ) {
							$match      = false;
							$conditions = get_term_meta( $collection, 'wpcpc_conditions', true ) ? (array) get_term_meta( $collection, 'wpcpc_conditions', true ) : [];
							$include    = get_term_meta( $collection, 'wpcpc_include', true ) ? (array) get_term_meta( $collection, 'wpcpc_include', true ) : [];
							$exclude    = get_term_meta( $collection, 'wpcpc_exclude', true ) ? (array) get_term_meta( $collection, 'wpcpc_exclude', true ) : [];

							// exclude
							if ( empty( $exclude ) || ! in_array( $product_id, $exclude ) ) {
								// include
								if ( ! empty( $include ) && in_array( $product_id, $include ) ) {
									$match = true;
								} else {
									// conditions
									if ( ! empty( $conditions ) ) {
										$_match = true;

										foreach ( $conditions as $condition ) {
											$__match   = false;
											$condition = array_merge( [
												'apply'   => '',
												'compare' => 'is',
												'value'   => []
											], $condition );

											if ( ! empty( $condition['apply'] ) && ! empty( $condition['compare'] ) && ! empty( $condition['value'] ) ) {
												$terms = array_map( 'strval', (array) $condition['value'] );

												if ( ( $condition['compare'] === 'is' ) && has_term( $terms, $condition['apply'], $product_id ) ) {
													$__match = true;
												}

												if ( ( $condition['compare'] === 'is_not' ) && ! has_term( $terms, $condition['apply'], $product_id ) ) {
													$__match = true;
												}
											}

											$_match &= $__match;
										}

										if ( $_match ) {
											$match = true;
										}
									}
								}
							}

							if ( $match ) {
								// add to collection if matching
								$terms   = wp_get_post_terms( $product_id, 'wpc-collection', [ 'fields' => 'ids' ] );
								$terms[] = (int) $collection;
								wp_set_post_terms( $product_id, $terms, 'wpc-collection' );
							} else {
								// remove from collection
								wp_remove_object_terms( $product_id, [ (int) $collection ], 'wpc-collection' );
							}
						}
					}

					update_post_meta( $product_id, 'wpcpc_cronjob', current_time( 'timestamp', true ) );
				}

				wp_reset_postdata();
			}
		}

		public function get_collections() {
			return get_terms( [
				'taxonomy'   => 'wpc-collection',
				'fields'     => 'ids',
				'hide_empty' => false
			] );
		}
	}

	new Wpcpc_Helper();
}
