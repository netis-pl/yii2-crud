<?php
/**
 * @author    Patryk Radziszewski <pradziszewski@netis.pl>
 * @link      http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use Yii;
use yii\web\ForbiddenHttpException;

trait SaveFileTrait
{
    /**
     * @param string $fileClass    Namespace of file class
     * @param array  $files        UploadedFile instances
     * @param mixed  $primaryValue Basic model primary
     * @param string $foreignKey
     *
     * @return bool
     * @throws \yii\web\ForbiddenHttpException
     */
    public function saveFiles($fileClass, $files, $primaryValue, $foreignKey)
    {
        foreach ($files as $documentFile) {
            /** @var \yii\db\ActiveRecord $document */
            $document = new $fileClass();
            $content  = file_get_contents($documentFile->tempName);
            $info     = strpos($documentFile->type, 'image/') !== 0 ? [null, null] : getimagesizefromstring($content);
            $document->setAttributes([
                'filename'  => $documentFile->name,
                'size'      => $documentFile->size,
                'content'   => base64_encode($content),
                'mimetype'  => $documentFile->type,
                'hash'      => sha1($content),
                'width'     => $info[0],
                'height'    => $info[1],
                $foreignKey => $primaryValue,
            ], false);
            if (!$document->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \yii\db\ActiveRecord|string $fileClass Namespace of file class
     * @param array  $files     UploadedFile instances
     *
     * @return bool
     * @throws \yii\web\ForbiddenHttpException
     */
    public function deleteFiles($fileClass, $files)
    {
        foreach ($files as $id) {
            /** @var \yii\db\ActiveRecord $document */
            $document = $fileClass::findOne(Action::importKey($fileClass::primaryKey(), $id));
            if (!Yii::$app->user->can("$fileClass.delete", ['model' => $document])) {
                throw new ForbiddenHttpException(Yii::t('app', 'Access denied.'));
            }
            if (!$document->delete()) {
                return false;
            }
        }

        return true;
    }
}
