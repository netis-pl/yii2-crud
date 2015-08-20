<?php
/**
 * @author    Patryk Radziszewski <pradziszewski@netis.pl>
 * @link      http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;

trait SaveFileTrait
{
    /**
     * @param object $model Basic model object
     * @param Request $request
     * @param string $filesAttribute Attribute contains UploadedFile instance
     *
     * @return mixed
     */
    protected function load($model, $request, $filesAttribute)
    {
        if (($result = parent::load($model, $request))) {
            $model->$filesAttribute = \yii\web\UploadedFile::getInstances($model, $filesAttribute);
        }

        return $result;
    }

    /**
     * @param object $model Basic model object
     * @param string $filesAttribute Attribute contains UploadedFile instance
     * @param string $modelDocument
     * @param array $documentRelation ActiveQuery->link property
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    protected function save($model, $filesAttribute, $modelDocument, $documentRelation)
    {
        $trx = $model::getDb()->beginTransaction();
        $model->beginChangeset();
        if (!$model->save(false) || !$model->saveRelations(Yii::$app->getRequest()->getBodyParams())) {
            $model->endChangeset();
            $trx->rollback();

            return false;
        }
        if (empty($model->$filesAttribute)) {
            $model->endChangeset();
            $trx->commit();

            return true;
        }
        foreach ($model->$filesAttribute as $documentFile) {
            $document = new $modelDocument;
            $content  = file_get_contents($documentFile->tempName);
            $info     = strpos($documentFile->type, 'image/') !== 0 ? [null, null] : getimagesizefromstring($content);
            $document->setAttributes([
                'filename'              => $documentFile->name,
                'size'                  => $documentFile->size,
                'content'               => base64_encode($content),
                'mimetype'              => $documentFile->type,
                'hash'                  => sha1($content),
                'width'                 => $info[0],
                'height'                => $info[1],
                $documentRelation['id'] => $model->getPrimaryKey(),
            ], false);
            if (!$document->save()) {
                $model->addError($filesAttribute, Yii::t('netis/shipments/app', 'Failed to save document.'));
                $model->endChangeset();
                $trx->rollback();

                return false;
            }
        }
        $model->endChangeset();
        $trx->commit();

        return true;
    }
}