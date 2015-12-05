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
class JsonRendererStream extends RendererStream
{
    public function renderHeader()
    {
        /** @var \yii\rest\Serializer $serializer */
        $serializer = $this->serializer;
        if ($serializer->collectionEnvelope === null) {
            return "[";
        }
        // \n\"labels\": " . json_encode($this->getHeader()).",
        return "{\n\"{$serializer->collectionEnvelope}\": [";
    }

    public function renderRow($data, $index, $count)
    {
        return ($index !== 0 ? ",\n" : "\n") . json_encode($this->serializeModel($data));
    }

    public function renderFooter()
    {
        /** @var \yii\rest\Serializer $serializer */
        $serializer = $this->serializer;

        if ($serializer->collectionEnvelope === null) {
            return "\n]";
        }
        $result = "\n]";
        /** @var \yii\data\ActiveDataProvider $dataProvider */
        $dataProvider = $this->grid->dataProvider;
        if (($pagination = $dataProvider->getPagination()) !== false) {
            $serialized = $this->serializePagination($pagination);
            $result .= ",\"{$serializer->linksEnvelope}\": " . json_encode($serialized[$serializer->linksEnvelope]);
            $result .= ",\"{$serializer->metaEnvelope}\": " . json_encode($serialized[$serializer->metaEnvelope]);
        }
        return $result . "\n}";
    }
}