<?php
/**
 * Register CLI commands
 */

namespace TyB\CLI;

use TyB\CLI\MigratePostType;

/**
 * CLI Handler
 */
class Handler {

	/**
	 * Migrate Post Types.
	 *
	 * @subcommand migrate-post-type
	 *
	 * ## OPTIONS
	 *
	 * --from=<string>
	 * : The post type to migrate from
	 *
	 * --to=<string>
	 * : The post type to migrate to
	 *
	 * --taxonomy=<string>
	 * --term=<string>
	 * : Assign posts to specific term in a taxonomy
	 *
	 * --per-page=<int>
	 * : How many items to process per run. Default 150.
	 *
	 * --offset=<int>
	 * : How many posts to offset from the begining. Default 0.
	 *
	 * --dry-run=<boolean>
	 * : Run as a dry-run or actually import things?
	 *
	 * @param array $args       Args from WP CLI
	 * @param array $assoc_args Associative args from CLI
	 */
	public function migrate_post_type( $args, $assoc_args ) {
		$command = new MigratePostType( $args, $assoc_args );
		$command->run();
	}

	/**
	 * Set a global timer variable to time the execution
	 *
	 * @return boolean
	 */
	public static function start_timer() {
		global $time_start;
		$time_start = microtime( true );
		return true;
	}

	/**
	 * Stop the BX Timer and output the elapsed time
	 *
	 * @param  boolean $echo Whether to echo the time or not
	 * @return float         The time elapsed
	 */
	public static function stop_timer( $echo = false ) {
		global $time_start, $time_end;

		$time_end   = microtime( true );
		$time_total = $time_end - $time_start;

		if ( function_exists( 'number_format_i18n' ) ) {
			$display = number_format_i18n( $time_total, 3 );
		} else {
			$display = number_format( $time_total, 3 );
		}

		if ( $echo ) {
			echo esc_html( $display );
		}

		return $display;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'tyb', \TyB\CLI::class );
}
