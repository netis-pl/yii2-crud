<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\DataReader;
use yii\db\QueryInterface;
use yii\grid\DataColumn;
use yii\grid\GridView;

/**
 * A stream wrapper allowing to gradually render a response while reading it with stream functions like fread().
 * @package netis\utils\crud
 */
class RendererStream
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
    public $format;
    /**
     * @var string output encoding, if null defaults to UTF-8
     */
    public $encoding;
    /**
     * @var string
     */
    private $actionId;
    /**
     * @var GridView
     */
    private $grid;
    /**
     * @var DataReader
     */
    private $dataReader;
    /**
     * @var int
     */
    private $rowNumber = 0;
    /**
     * @var string
     */
    private $buffer = '';

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
        $query = [];
        parse_str($url['query'], $query);
        $this->actionId = $url["host"];
        foreach (['encoding', 'format'] as $option) {
            if (isset($query[$option])) {
                $this->$option = $query[$option];
            }
        }

        $this->rowNumber = 0;
        $this->buffer = '';

        $this->grid = \Yii::createObject([
            'class' => 'yii\grid\GridView',
            'dataProvider' => self::$params['dataProvider'],
            'columns' => self::$params['columns'],
        ]);
        $this->dataReader = $this->getDataReader();
        /** @var \yii\grid\DataColumn $column */
        foreach ($this->grid->columns as $column) {
            if ($column instanceof DataColumn) {
                $column->enableSorting = false;
            }
        }

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
        /** @var ActiveDataProvider $dataProvider */
        $dataProvider = $this->grid->dataProvider;
        /** @var \yii\db\ActiveQuery $query */
        $query = $dataProvider->query;

        while (strlen($this->buffer) < $count && $this->dataReader !== null) {
            if ($this->rowNumber === 0) {
                $this->buffer .= $this->renderHeader();
            }
            for ($i = 0; $i < 100; $i++, $this->rowNumber++) {
                $row = $this->dataReader->read();
                if ($row === false) {
                    $this->dataReader = null;
                    $this->buffer .= $this->renderFooter();
                    break 2;
                }
                $models = $query->populate([$row]);
                //$dataProvider->setmodels($models);

                $this->buffer .= $this->renderRow(reset($models));
            }
        }
        if ($this->buffer === '' && $this->dataReader === null) {
            return false;
        }
        $result = substr($this->buffer, 0, $count);
        $this->buffer = $count >= strlen($this->buffer) ? '' : substr($this->buffer, $count);
        return $result;
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
        return $this->dataReader === null;
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

    protected function getDataReader()
    {
        /** @var ActiveDataProvider $dataProvider */
        $dataProvider = $this->grid->dataProvider;
        if (!$dataProvider instanceof ActiveDataProvider) {
            throw new InvalidConfigException('The "dataProvider" property must be an instance of a \yii\data\ActiveDataProvider or its subclasses.');
        }
        if (!$dataProvider->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        /** @var \yii\db\ActiveQuery $query */
        $query = clone $dataProvider->query;
        if (($pagination = $dataProvider->getPagination()) !== false) {
            $pagination->totalCount = $dataProvider->getTotalCount();
            $query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }
        if (($sort = $dataProvider->getSort()) !== false) {
            $query->addOrderBy($sort->getOrders());
        }

        return $query->createCommand($dataProvider->db)->query();
    }

    protected function getHeader()
    {
        $headers = [];
        foreach ($this->grid->columns as $column) {
            $r = new \ReflectionMethod($column, 'renderHeaderCellContent');
            $r->setAccessible(true);
            $header = $r->invoke($column);
            if ($this->encoding !== null) {
                $header = iconv('UTF-8', $this->encoding, $header);
            }
            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * @param integer $row the row number (zero-based).
     * @param mixed $data  an item from the DataProvider.models array
     * @return array processed values ready for output
     */
    public function getRow($row, $data)
    {
        $values = [];

        /** @var Column $column */
        foreach ($this->grid->columns as $column) {
            $r = new \ReflectionMethod($column, 'renderDataCellContent');
            $r->setAccessible(true);
            $value = $r->invoke($column, $data, $data->getPrimaryKey(), $row);
            //$value = $column->renderDataCellContent($data, $data->primaryKey, $row);
            /*if ($this->stripTags) {
                $value = strip_tags($value);
            }
            if ($this->decodeHtmlEntities) {
                $value = html_entity_decode($value);
            }*/
            if ($this->encoding !== null) {
                $value = iconv('UTF-8', $this->encoding, $value);
            }
            $values[] = $value;
        }

        return $values;
    }

    public function renderHeader()
    {
        return "array(\n" . var_export($this->getHeader());
    }

    public function renderRow($data)
    {
        return var_export($data->toArray());
    }

    public function renderFooter()
    {
        return "\n)";
    }
}
