<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud\stream;

/**
 * A stream wrapper allowing to gradually render a response while reading it with stream functions like fread().
 * @package netis\utils\crud
 */
class XlsRendererStream extends RendererStream
{
    /**
     * @var \PHPExcel
     */
    private $objPHPExcel;
    /**
     * @var \PHPExcel_Worksheet
     */
    private $sheet;
    /**
     * @var integer
     */
    private $offset;
    /**
     * @var int how many rows are rendered in a single chunk
     */
    public $chunkRowsNumber = -1;

    /**
     * @inheritdoc
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        \PHPExcel_Settings::setCacheStorageMethod(\PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3);
        $this->objPHPExcel = new \PHPExcel();
        $this->sheet       = $this->objPHPExcel->getActiveSheet();
        $this->offset      = 1;
        return parent::stream_open($path, $mode, $options, $opened_path);
    }

    public function render($data)
    {
        $column = 'A';
        foreach ($data as $value) {
            $cellCoordinates = $column++ . $this->offset;
            $this->sheet->setCellValue($cellCoordinates, $value);
        }
        $this->offset++;

        return '';
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
        $filename = tempnam(\Yii::getAlias('@runtime'), 'xls');
        $objWriter = new \PHPExcel_Writer_Excel5($this->objPHPExcel);
        $objWriter->save($filename);

        $content = file_get_contents($filename);
        unlink($filename);
        return $content;
    }
}
