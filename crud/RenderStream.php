<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use yii\base\Exception;
use yii\web\Response;

/**
 * A stream wrapper allowing to gradually render a response while reading it with stream functions like fread().
 * @package netis\utils\crud
 */
class RenderStream
{
    /**
     * @var resource The current context, or NULL if no context was passed to the caller function.
     */
    public $context;
    /**
     * @var array view params
     */
    public static $params;
    /**
     * @var string format
     */
    public static $format;
    /**
     * @var string
     */
    private $actionId;

    /**
     * @return array supported formats as mimetype => format, @see ContentNegotiator::$formats
     */
    public static function formats()
    {
        return [
            'application/json' => Response::FORMAT_JSON,
            'application/xml' => Response::FORMAT_XML,
        ];
    }

    /**
     * Opens file or URL.
     * @param string $path Specifies the URL that was passed to the original function.
     * @param string $mode The mode used to open the file, as detailed for fopen().
     * @param int $options Holds additional flags set by the streams API.
     *                     It can hold one or more of the following values OR'd together.
     * @param string $opened_path If the path is opened successfully, and STREAM_USE_PATH is set in options,
     *                            opened_path should be set to the full path of the file/resource
     *                            that was actually opened.
     * @return bool Returns TRUE on success or FALSE on failure.
     * @throws Exception
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        if ($mode !== 'r') {
            throw new Exception('DataProviderStream supports only the "r" mode.');
        }
        if ($options !== 0) {
            throw new Exception('DataProviderStream does not support any options.');
        }
        $opened_path = $path;
        $url = parse_url($path);
        $this->actionId = $url["host"];

        return true;
    }

    /**
     * Read from stream.
     * @param int $count How many bytes of data from the current position should be returned.
     * @return string If there are less than count bytes available, return as many as are available.
     * If no more data is available, return either FALSE or an empty string.
     */
    public function stream_read($count)
    {
        return false;
    }

    /**
     * Retrieve the current position of a stream
     * @return int Should return the current position of the stream.
     */
    public function stream_tell()
    {
        return null;
    }

    /**
     * Tests for end-of-file on a file pointer
     * @return bool Should return TRUE if the read/write position is at the end of the stream
     * and if no more data is available to be read, or FALSE otherwise.
     */
    public function stream_eof()
    {
        return true;
    }

    /**
     * Seeks to specific location in a stream
     * @param int $offset The stream offset to seek to.
     * @param int $whence Possible values:
     * SEEK_SET - Set position equal to offset bytes.
     * SEEK_CUR - Set position to current location plus offset.
     * SEEK_END - Set position to end-of-file plus offset.
     * @return bool Return TRUE if the position was updated, FALSE otherwise.
     */
    public function stream_seek($offset, $whence)
    {
        return false;
    }
}
