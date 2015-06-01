<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

/**
 * A stream wrapper allowing to gradually render a response while reading it with stream functions like fread().
 * @package netis\utils\crud
 */
class JsonRendererStream extends RendererStream
{
    public function renderHeader()
    {
        return "[\n" . json_encode($this->getHeader());
    }

    public function renderRow($data)
    {
        return ',' . json_encode($data->toArray());
    }

    public function renderFooter()
    {
        return "\n]";
    }
}