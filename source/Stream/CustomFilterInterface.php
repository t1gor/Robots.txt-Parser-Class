<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

interface CustomFilterInterface {

	/**
	 * Called when applying the filter.
	 *
	 * @param resource $in
	 *   in is a resource pointing to a bucket brigade which contains one or more bucket
	 *   objects containing data to be filtered.
	 * @param resource $out
	 *   out is a resource pointing to a second bucket brigade into which your modified
	 *   buckets should be placed.
	 * @param int $consumed
	 *   consumed, which must always be declared by reference, should be incremented by
	 *   the length of the data which your filter reads in and alters. In most cases
	 *   this means you will increment consumed by $bucket->datalen for each $bucket.
	 * @param bool $closing
	 *   If the stream is in the process of closing (and therefore this is the last pass
	 *   through the filterchain), the closing parameter will be set to TRUE.
	 *
	 * @return int
	 *   The filter() method must return one of three values upon completion.
	 *   - PSFS_PASS_ON: Filter processed successfully with data available in the out
	 *                   bucket brigade.
	 *   - PSFS_FEED_ME: Filter processed successfully, however no data was available to
	 *                   return. More data is required from the stream or prior filter.
	 *   - PSFS_ERR_FATAL (default): The filter experienced an unrecoverable error and
	 *                               cannot continue.
	 */
	public function filter($in, $out, &$consumed, $closing);

	/**
	 * Called when creating the filter.
	 *
	 * @return bool
	 *   Your implementation of this method should return FALSE on failure, or TRUE on success.
	 */
	public function onCreate();

	/**
	 * Called when closing the filter.
	 */
	public function onClose();
}
