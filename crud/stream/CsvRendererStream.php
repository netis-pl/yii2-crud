<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud\stream;

/**
 * A stream wrapper allowing to gradually render a response while reading it with stream functions like fread().
 * @package netis\crud\crud
 */
class CsvRendererStream extends RendererStream
{
    private $handle;

    /**
     * @inheritdoc
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->handle = fopen('php://memory', 'r+');
        return parent::stream_open($path, $mode, $options, $opened_path);
    }

    public function render($data)
    {
        rewind($this->handle);
        $length = fputcsv($this->handle, $data);
        rewind($this->handle);
        // mb_convert_encoding($csv, 'iso-8859-2', 'utf-8')
        return stream_get_contents($this->handle, $length);
    }

    public function renderHeader()
    {
        return $this->render($this->getHeader());
    }

    public function renderRow($data, $index, $count)
    {
        return $this->render($data->toArray());
    }

    public function renderFooter()
    {
        return '';
    }
}