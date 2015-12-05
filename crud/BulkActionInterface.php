<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

/**
 * BulkActionInterface defines an interface of actions performing bulk operations.
 *
 * Such actions perform following steps:
 * * take a FilterForm and row selection data as input which creates record selection criteria
 * * display a form to configure the operation
 * * run the job
 * * display a summary after finishing the batches, including list of errors to allow retrying
 *
 * @author jwas
 */
interface BulkActionInterface
{
    /**
     * Method defines steps for bulk action. It should return array in format:
     * ```
     * [`step` => Callable]
     * ```
     * where step will be used as url param.
     *
     * @return array
     */
    public function steps();

    /**
     * Renders a configuration form.
     */
    public function prepare();

    /**
     * Performs bulk operations, displays progress if they can be split into batches
     * or are performed by a background worker or redirects to the post summary.
     * May also ask for confirmation as an extra failsafe.
     */
    public function execute();
}
